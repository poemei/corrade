<?php
declare(strict_types=1);

// path: /user/modules/corrade/models/corrade_model.php

/**
 * Corrade Manager model.
 *
 * Owns only the Corrade module's agent registry and schema lifecycle.
 * Does not execute Corrade or manage operating-system services.
 */
final class corrade_model extends model
{
    private const TABLES = [
        'corrade_schema',
        'corrade_agents',
    ];

    private const STATE_TABLE = 'corrade_schema';
    private const AGENT_TABLE = 'corrade_agents';

    public function databaseState(): string
    {
        foreach (self::TABLES as $table) {
            if (!$this->tableExists($table)) {
                return 'missing';
            }
        }

        $row = $this->fetch(
            'SELECT `schema_version` FROM `' . self::STATE_TABLE . '` '
            . 'WHERE `id` = 1 LIMIT 1'
        );

        $current = is_array($row)
            ? trim((string) ($row['schema_version'] ?? ''))
            : '';
        $target = $this->targetVersion();

        if ($current === '' || $target === '') {
            return 'invalid';
        }

        return $current === $target ? 'current' : 'invalid';
    }

    public function installSchema(): void
    {
        if ($this->databaseState() !== 'missing') {
            throw new RuntimeException('Corrade schema is already installed or invalid.');
        }

        $this->executeSqlFile(__DIR__ . '/../sql/schema.sql');
    }

    public function getAgents(): array
    {
        return $this->fetchAll(
            'SELECT `id`, `agent_name`, `configuration_path`, '
            . '`service_name`, `is_enabled`, `created_at`, `updated_at` '
            . 'FROM `' . self::AGENT_TABLE . '` ORDER BY `agent_name`'
        ) ?: [];
    }

    public function createAgent(array $input): int
    {
        $name = trim((string) ($input['agent_name'] ?? ''));
        $path = trim((string) ($input['configuration_path'] ?? ''));
        $service = trim((string) ($input['service_name'] ?? ''));

        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $name) !== 1) {
            throw new InvalidArgumentException(
                'Agent name must contain 1–64 letters, numbers, underscores or hyphens.'
            );
        }

        if ($path === '' || strlen($path) > 512 || $path[0] !== '/'
            || str_contains($path, '..')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException(
                'A valid absolute configuration path is required.'
            );
        }

        if ($service !== 'corrade@' . $name . '.service' && $service !== 'corrade.service') {
            throw new InvalidArgumentException(
                'Service must be corrade@<agent-name>.service or corrade.service.'
            );
        }

        return (int) $this->insert(self::AGENT_TABLE, [
            'agent_name' => $name,
            'configuration_path' => $path,
            'service_name' => $service,
            'is_enabled' => 0,
        ]);
    }

    /**
     * Adopt a known, existing Corrade installation into the registry only.
     * Never reads credentials, rewrites XML or controls the service.
     */
    public function adoptExistingAgent(): int
    {
        return $this->createAgent([
            'agent_name' => 'sparklesagent',
            'configuration_path' => '/home/stnchain/corrade/CorradeConfiguration.xml',
            'service_name' => 'corrade.service',
        ]);
    }

    public function deleteAgent(int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Invalid agent identifier.');
        }

        $this->query(
            'DELETE FROM `' . self::AGENT_TABLE . '` WHERE `id` = :id',
            ['id' => $id]
        );
    }

    private function targetVersion(): string
    {
        $raw = file_get_contents(__DIR__ . '/../module.json');
        $metadata = is_string($raw) ? json_decode($raw, true) : null;
        $version = is_array($metadata)
            ? trim((string) ($metadata['schema_version'] ?? ''))
            : '';

        return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $version) === 1
            ? $version
            : '';
    }

    /**
     * Execute a module-owned SQL definition, using the canonical
     * Example Module statement separation convention.
     */
    private function executeSqlFile(string $file): void
    {
        if (!is_file($file) || is_link($file)) {
            throw new RuntimeException('Corrade SQL file could not be read.');
        }

        $sql = file_get_contents($file);

        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Corrade SQL file could not be read.');
        }

        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

        if (!is_array($statements)) {
            throw new RuntimeException('Corrade SQL file could not be parsed.');
        }

        foreach ($statements as $statement) {
            $statement = trim($statement);

            if ($statement !== '') {
                $this->query($statement);
            }
        }
    }

    /**
     * The ChAoS MVC Example Module provides this as a private
     * model helper, not as an inherited Core model method.
     */
    private function tableExists(string $table): bool
    {
        if (!in_array($table, self::TABLES, true)) {
            return false;
        }

        return (bool) $this->fetch(
            'SELECT 1 FROM information_schema.tables '
            . 'WHERE table_schema = :schema '
            . 'AND table_name = :table_name LIMIT 1',
            [
                'schema' => DB_NAME,
                'table_name' => $table,
            ]
        );
    }
}
