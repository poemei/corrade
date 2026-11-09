CREATE TABLE IF NOT EXISTS corrade_schema (
    id TINYINT UNSIGNED NOT NULL,
    schema_version VARCHAR(64) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO corrade_schema (id, schema_version)
VALUES (1, '0.1.0')
ON DUPLICATE KEY UPDATE schema_version = VALUES(schema_version);

CREATE TABLE IF NOT EXISTS corrade_agents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    agent_name VARCHAR(64) NOT NULL,
    configuration_path VARCHAR(512) NOT NULL,
    service_name VARCHAR(128) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY corrade_agents_name_unique (agent_name),
    UNIQUE KEY corrade_agents_service_unique (service_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
