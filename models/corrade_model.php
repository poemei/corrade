<?php
declare(strict_types=1);

// path: /user/modules/corrade/models/corrade_model.php

final class corrade_model extends model
{
    public function databaseState(): string
    {
        if (!$this->tableExists('corrade_schema') || !$this->tableExists('corrade_agents')) {
            return 'missing';
        }
        $row = $this->fetch('SELECT schema_version FROM corrade_schema WHERE id = 1 LIMIT 1');
        return is_array($row) && ($row['schema_version'] ?? null) === '0.1.0'
            ? 'current' : 'invalid';
    }

    public function installSchema(): void
    {
        if ($this->databaseState() !== 'missing') {
            throw new RuntimeException('Database installation is not available.');
        }
        $this->executeSqlFile(__DIR__ . '/../sql/schema.sql');
    }

    public function getAgents(): array
    {
        return $this->fetchAll(
            'SELECT id, agent_name, configuration_path, service_name, is_enabled, created_at, updated_at '
            . 'FROM corrade_agents ORDER BY agent_name'
        ) ?: [];
    }

    public function createAgent(array $input): int
    {
        $name = trim((string) ($input['agent_name'] ?? ''));
        $path = trim((string) ($input['configuration_path'] ?? ''));
        $service = trim((string) ($input['service_name'] ?? ''));

        if (preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $name) !== 1) {
            throw new InvalidArgumentException('Agent name must be 1–64 letters, numbers, underscores or hyphens.');
        }
        if ($path === '' || strlen($path) > 512 || $path[0] !== '/' || str_contains($path, '..')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new InvalidArgumentException('A valid absolute configuration path is required.');
        }
        if (preg_match('/^corrade@[a-zA-Z0-9_-]{1,64}\.service$/D', $service) !== 1
            || $service !== 'corrade@' . $name . '.service') {
            throw new InvalidArgumentException('Service must be corrade@<agent-name>.service.');
        }
        return (int) $this->insert('corrade_agents', [
            'agent_name' => $name,
            'configuration_path' => $path,
            'service_name' => $service,
            'is_enabled' => 0,
        ]);
    }

    public function deleteAgent(int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Invalid agent identifier.');
        }
        $this->query('DELETE FROM corrade_agents WHERE id = :id', ['id' => $id]);
    }
}
