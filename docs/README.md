# Corrade Manager — 0.1.0

The module belongs in `user/modules/corrade/`.

Each agent has a unique name, configuration path, service name, and enabled preference. Enabled does not mean running.

Core retains authentication, routing, CSRF, module installation and removal. The web application must not receive unrestricted systemd or shell privileges. Corrade multi-instance isolation must be verified before runtime actions are added.

Schema installation must be explicit, never automatic on page load. The manifest `sha256` placeholder must be replaced only with a hash of a real release artifact.
