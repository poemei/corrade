<?php
declare(strict_types=1);

/**
 * Corrade XML configuration editor. Never returns stored secrets.
 * The installed CorradeConfiguration.xsd must be supplied by the operator
 * alongside the selected configuration file.
 */
final class CorradeXmlEditor
{
    private const NS = 'urn:corrade-configuration-schema';

    public static function read(string $path): array
    {
        [$doc, $root] = self::load($path);
        $groups = [];
        foreach (self::children(self::child($root, 'Groups'), 'Group') as $group) {
            $groups[] = [
                'name' => self::value($group, 'Name'),
                'has_password' => self::value($group, 'Password') !== '',
                'permissions' => array_map(static fn($node) => trim($node->textContent), self::children(self::child($group, 'Permissions'), 'Permission')),
                'notifications' => array_map(static fn($node) => trim($node->textContent), self::children(self::child($group, 'Notifications'), 'Notification')),
            ];
        }
        $locations = self::children(self::child($root, 'StartLocations'), 'StartLocation');
        return [
            'first_name' => self::value($root, 'FirstName'),
            'last_name' => self::value($root, 'LastName'),
            'has_password' => self::value($root, 'Password') !== '',
            'auto_connect' => self::value($root, 'AutoConnect') === '1',
            'start_region' => isset($locations[0]) ? self::value($locations[0], 'Region') : '',
            'groups' => $groups,
        ];
    }

    public static function save(string $path, array $input): void
    {
        [$doc, $root] = self::load($path);
        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        if (!preg_match('/^[^<>\\x00-\\x1F]{1,64}$/uD', $first)
            || !preg_match('/^[^<>\\x00-\\x1F]{1,64}$/uD', $last)) {
            throw new InvalidArgumentException('Valid first and last names are required.');
        }
        self::setValue($root, 'FirstName', $first);
        self::setValue($root, 'LastName', $last);

        $password = (string) ($input['login_password'] ?? '');
        if ($password !== '') {
            // Corrade login hash: literal $1$ followed by 32 lowercase MD5 hex characters.
            self::setValue($root, 'Password', '$1$' . md5($password));
        }
        self::setValue($root, 'AutoConnect', !empty($input['auto_connect']) ? '1' : '0');

        $region = trim((string) ($input['start_region'] ?? ''));
        if ($region === '' || strlen($region) > 256 || preg_match('/[\\x00-\\x1F]/', $region)) {
            throw new InvalidArgumentException('A valid start region is required.');
        }
        $start = self::child($root, 'StartLocations');
        $locations = self::children($start, 'StartLocation');
        if (!isset($locations[0])) {
            throw new RuntimeException('StartLocation is missing from Corrade XML.');
        }
        self::setValue($locations[0], 'Region', $region);

        $names = $input['group_name'] ?? [];
        $passwords = $input['group_password'] ?? [];
        $permissions = $input['group_permissions'] ?? [];
        $notifications = $input['group_notifications'] ?? [];
        if (!is_array($names) || !is_array($passwords) || !is_array($permissions) || !is_array($notifications) || count($names) > 50) {
            throw new InvalidArgumentException('Invalid group configuration.');
        }
        $groupsNode = self::child($root, 'Groups');
        $existing = self::children($groupsNode, 'Group');
        $old = [];
        foreach ($existing as $group) {
            $old[self::value($group, 'Name')] = $group;
        }
        $seen = [];
        $newNodes = [];
        foreach ($names as $index => $rawName) {
            $name = trim((string) $rawName);
            if ($name === '') {
                continue;
            }
            if (strlen($name) > 128 || isset($seen[$name]) || preg_match('/[\\x00-\\x1F]/', $name)) {
                throw new InvalidArgumentException('Group names must be unique and valid.');
            }
            $seen[$name] = true;
            $group = $old[$name] ?? null;
            if (!$group) {
                $group = $doc->createElementNS(self::NS, 'Group');
                foreach (['Name', 'Password', 'Notifications', 'Permissions', 'CacheMembers', 'Cookies'] as $tag) {
                    $group->appendChild($doc->createElementNS(self::NS, $tag));
                }
                self::setValue($group, 'CacheMembers', '1');
            }
            self::setValue($group, 'Name', $name);
            $replacement = (string) ($passwords[$index] ?? '');
            if ($replacement !== '') {
                if (strlen($replacement) > 512 || preg_match('/[\\x00-\\x1F]/', $replacement)) {
                    throw new InvalidArgumentException('Invalid group password.');
                }
                self::setValue($group, 'Password', $replacement);
            } elseif (!isset($old[$name])) {
                throw new InvalidArgumentException('A password is required for new groups.');
            }
            foreach (['Permissions' => ['Permission', $permissions], 'Notifications' => ['Notification', $notifications]] as $containerName => [$tag, $values]) {
                $container = self::child($group, $containerName);
                foreach (self::children($container, $tag) as $node) {
                    $container->removeChild($node);
                }
                $raw = $values[$index] ?? '';
                if (!is_string($raw)) {
                    throw new InvalidArgumentException('Invalid group options.');
                }
                $items = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\\r\\n]+/', $raw) ?: []))));
                if (count($items) > 100) {
                    throw new InvalidArgumentException('Too many group options.');
                }
                foreach ($items as $item) {
                    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $item)) {
                        throw new InvalidArgumentException('Invalid permission or notification value.');
                    }
                    $node = $doc->createElementNS(self::NS, $tag);
                    $node->appendChild($doc->createTextNode($item));
                    $container->appendChild($node);
                }
            }
            $newNodes[] = $group;
        }
        foreach ($existing as $node) {
            $groupsNode->removeChild($node);
        }
        foreach ($newNodes as $node) {
            $groupsNode->appendChild($node);
        }

        $schema = dirname($path) . '/CorradeConfiguration.xsd';
        if (!is_file($schema) || is_link($schema)) {
            throw new RuntimeException('CorradeConfiguration.xsd is required alongside the XML file.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$doc->schemaValidate($schema)) {
                throw new RuntimeException('The modified XML failed Corrade XSD validation.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xml = $doc->saveXML();
        if (!is_string($xml)) {
            throw new RuntimeException('Failed to serialize Corrade configuration.');
        }
        $stat = stat($path);
        if (!$stat || !is_writable($path) || !is_writable(dirname($path))) {
            throw new RuntimeException('Corrade configuration is not writable by the PHP process.');
        }
        $backup = $path . '.backup-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
        if (!copy($path, $backup)) {
            throw new RuntimeException('Unable to back up Corrade configuration.');
        }
        chmod($backup, $stat['mode'] & 0777);
        $temp = tempnam(dirname($path), '.corrade-');
        if ($temp === false) {
            throw new RuntimeException('Unable to create temporary configuration.');
        }
        try {
            if (file_put_contents($temp, $xml, LOCK_EX) !== strlen($xml)) {
                throw new RuntimeException('Unable to write complete configuration.');
            }
            chmod($temp, $stat['mode'] & 0777);
            if (!rename($temp, $path)) {
                throw new RuntimeException('Unable to atomically replace configuration.');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private static function load(string $path): array
    {
        if ($path === '' || !is_file($path) || is_link($path)) {
            throw new RuntimeException('Corrade configuration is missing or is a symbolic link.');
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$doc->load($path, LIBXML_NONET)) {
                throw new RuntimeException('Unable to parse Corrade configuration.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $doc->documentElement;
        if (!$root || $root->localName !== 'CorradeConfiguration' || $root->namespaceURI !== self::NS) {
            throw new RuntimeException('Unexpected Corrade XML namespace or root.');
        }
        return [$doc, $root];
    }

    private static function child(?DOMElement $parent, string $name): DOMElement
    {
        if (!$parent) {
            throw new RuntimeException('Required Corrade XML element missing: ' . $name);
        }
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $name && $node->namespaceURI === self::NS) {
                return $node;
            }
        }
        throw new RuntimeException('Required Corrade XML element missing: ' . $name);
    }

    private static function children(?DOMElement $parent, string $name): array
    {
        if (!$parent) {
            return [];
        }
        $result = [];
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $name && $node->namespaceURI === self::NS) {
                $result[] = $node;
            }
        }
        return $result;
    }

    private static function value(DOMElement $parent, string $name): string
    {
        return trim(self::child($parent, $name)->textContent);
    }

    private static function setValue(DOMElement $parent, string $name, string $value): void
    {
        $node = self::child($parent, $name);
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($parent->ownerDocument->createTextNode($value));
    }
}
