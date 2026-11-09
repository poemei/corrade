<?php
declare(strict_types=1);

// path: /user/modules/corrade/views/admin/index.php
// render the header
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
$state = (string) ($data['database_state'] ?? 'invalid');
$agents = is_array($data['agents'] ?? null) ? $data['agents'] : [];
$module = is_array($data['module'] ?? null) ? $data['module'] : [];
$error = $data['error'] ?? null;
$status = (string) ($data['status'] ?? '');
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$messages = [
    'installed' => 'Corrade database installed.',
    'created' => 'Bot registered.',
    'adopted' => 'Existing sparklesagent registered.',
    'updated' => 'Bot registry settings updated.',
    'deleted' => 'Bot registration removed.',
    'data_deleted' => 'All bot registrations deleted. Database tables were preserved.',
    'xml_saved' => 'Corrade XML saved and backed up. Restart separately when appropriate.',
];
$tab = (string) ($_GET['tab'] ?? 'status');
if (!in_array($tab, ['status', 'bots', 'register'], true)) {
    $tab = 'status';
}
if (in_array($status, ['created', 'adopted', 'updated', 'deleted'], true)) {
    $tab = 'bots';
}
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$editing = null;
foreach ($agents as $agent) {
    if ($editId && (int) ($agent['id'] ?? 0) === $editId) {
        $editing = $agent;
        $tab = 'bots';
        break;
    }
}
$sparklesRegistered = false;
foreach ($agents as $agent) {
    if (($agent['agent_name'] ?? '') === 'sparklesagent') {
        $sparklesRegistered = true;
        break;
    }
}
?>
<div class="container my-4">
    <nav aria-label="Breadcrumb" class="mb-3">
        <small><a href="/admin">Admin</a> &gt;&gt; <strong>Corrade</strong></small>
    </nav>

    <header class="mb-4">
        <h1>Corrade Manager</h1>
        <p class="text-muted">Manage Corrade bot registrations on this Ubuntu server.</p>
    </header>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= $escape($error) ?></div>
    <?php endif; ?>
    <?php if (isset($messages[$status])): ?>
        <div class="alert alert-success" role="status"><?= $escape($messages[$status]) ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" role="tablist" aria-label="Corrade administration">
        <?php foreach (['status' => 'Module Status', 'bots' => 'Bots (' . count($agents) . ')', 'register' => 'Register Bot'] as $key => $label): ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
                   href="/admin/corrade?tab=<?= $escape($key) ?>"
                   <?= $tab === $key ? 'aria-current="page"' : '' ?>><?= $escape($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($tab === 'status'): ?>
        <section class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Module Status</h2>
                <dl class="row">
                    <dt class="col-sm-3">Module</dt><dd class="col-sm-9"><?= $escape($module['name'] ?? 'Corrade Manager') ?></dd>
                    <dt class="col-sm-3">Version</dt><dd class="col-sm-9"><?= $escape($module['version'] ?? '') ?></dd>
                    <dt class="col-sm-3">Schema Version</dt><dd class="col-sm-9"><?= $escape($module['schema_version'] ?? '') ?></dd>
                    <dt class="col-sm-3">Database State</dt><dd class="col-sm-9"><strong><?= $escape($state) ?></strong></dd>
                    <dt class="col-sm-3">Registered Bots</dt><dd class="col-sm-9"><?= count($agents) ?></dd>
                </dl>
                <?php if ($state === 'missing'): ?>
                    <form method="post" action="/admin/corrade">
                        <?= $this->csrf_field() ?>
                        <input type="hidden" name="action" value="install_sql">
                        <button class="btn btn-primary" type="submit">Install Corrade Database</button>
                    </form>
                <?php elseif ($state !== 'current'): ?>
                    <div class="alert alert-warning">Database state is invalid. Registry changes are disabled.</div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($state === 'current'): ?>
            <section class="card mb-4">
                <div class="card-body">
                    <h2 class="h5">Delete Registry Data</h2>
                    <p>Delete every registered bot record. The database tables and module remain installed. Corrade processes and configuration files are untouched.</p>
                    <form method="post" action="/admin/corrade" class="row g-3 align-items-end">
                        <?= $this->csrf_field() ?>
                        <input type="hidden" name="action" value="delete_data">
                        <div class="col-md-5">
                            <label class="form-label" for="corrade-delete-confirm">Type DELETE DATA to confirm</label>
                            <input class="form-control" id="corrade-delete-confirm" name="confirmation" required autocomplete="off">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-outline-danger">Delete Registry Data</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <section class="card border-danger mb-4">
            <div class="card-body">
                <h2 class="h5">Nuke Module</h2>
                <p>Core-owned uninstall removes this module and its declared database tables. This action does not stop Corrade or delete its external configuration files.</p>
                <form method="post" action="/admin/uninstall" onsubmit="return confirm('Nuke Corrade Manager and its database tables?');">
                    <?= $this->csrf_field() ?>
                    <input type="hidden" name="module" value="corrade">
                    <button type="submit" class="btn btn-danger">Nuke Corrade Manager</button>
                </form>
            </div>
        </section>

    <?php elseif ($tab === 'bots'): ?>
        <section class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Registered Bots</h2>
                <?php if ($state !== 'current'): ?>
                    <div class="alert alert-warning">Install or repair the module database first.</div>
                <?php elseif (!$agents): ?>
                    <p class="text-muted">No bots registered. Use Register Bot to add one.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead><tr><th>Bot</th><th>Configuration</th><th>Service</th><th>Enabled</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($agents as $agent): ?>
                                    <tr>
                                        <td><?= $escape($agent['agent_name']) ?></td>
                                        <td><code><?= $escape($agent['configuration_path']) ?></code></td>
                                        <td><code><?= $escape($agent['service_name']) ?></code></td>
                                        <td><?= !empty($agent['is_enabled']) ? 'Yes' : 'No' ?></td>
                                        <td><a class="btn btn-sm btn-primary" href="/admin/corrade?tab=bots&amp;edit=<?= (int) $agent['id'] ?>">Edit</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($editing !== null && $state === 'current'): ?>
            <section class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Edit Bot: <?= $escape($editing['agent_name']) ?></h2>
                    <form method="post" action="/admin/corrade">
                        <?= $this->csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label" for="edit-name">Bot Name</label>
                            <input class="form-control" id="edit-name" name="agent_name" required maxlength="64" pattern="[A-Za-z0-9_-]+" value="<?= $escape($editing['agent_name']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="edit-config">Configuration Path</label>
                            <input class="form-control" id="edit-config" name="configuration_path" required maxlength="512" value="<?= $escape($editing['configuration_path']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="edit-service">Service Name</label>
                            <input class="form-control" id="edit-service" name="service_name" required maxlength="128" value="<?= $escape($editing['service_name']) ?>">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="edit-enabled" name="is_enabled" value="1" <?= !empty($editing['is_enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="edit-enabled">Enabled in registry</label>
                        </div>
                        <p class="form-text">Registry settings only. Changes do not rewrite Corrade XML or start, stop or restart services.</p>
                        <button type="submit" class="btn btn-primary">Save Bot</button>
                        <a class="btn btn-secondary" href="/admin/corrade?tab=bots">Cancel</a>
                    </form>
                    <?php $xml = $data['xml_config'] ?? null; ?>
                    <?php if (is_array($xml)): ?>
                        <hr>
                        <h3 class="h5">Corrade XML Configuration</h3>
                        <p class="text-muted">These settings are read from the selected bot's XML file. Existing credentials are never displayed. A blank replacement password preserves the current value.</p>
                        <form method="post" action="/admin/corrade">
                            <?= $this->csrf_field() ?>
                            <input type="hidden" name="action" value="save_xml">
                            <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="xml-first">Second Life First Name</label>
                                    <input class="form-control" id="xml-first" name="first_name" maxlength="31" required value="<?= $escape($xml['first_name']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="xml-last">Second Life Last Name</label>
                                    <input class="form-control" id="xml-last" name="last_name" maxlength="31" required value="<?= $escape($xml['last_name']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="xml-password">Replace Login Password</label>
                                    <input class="form-control" id="xml-password" name="login_password" type="password" autocomplete="new-password">
                                    <div class="form-text">Existing hash <?= !empty($xml['has_password']) ? 'present' : 'missing' ?>; leave blank to preserve it.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="xml-region">Start Region</label>
                                    <input class="form-control" id="xml-region" name="start_region" required value="<?= $escape($xml['start_region']) ?>">
                                </div>
                            </div>
                            <div class="form-check mb-4">
                                <input class="form-check-input" id="xml-autoconnect" type="checkbox" name="auto_connect" value="1" <?= !empty($xml['auto_connect']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="xml-autoconnect">Auto Connect</label>
                            </div>
                            <h4 class="h6">Groups</h4>
                            <p class="text-muted">One row per group. Comma-separated permissions and notifications. Empty group name omits that row. Removing a group from this form removes it from the saved XML.</p>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead><tr><th>Group</th><th>Replace Group Password</th><th>Permissions</th><th>Notifications</th></tr></thead>
                                    <tbody>
                                    <?php foreach (array_merge($xml['groups'], [['name' => '', 'has_password' => false, 'permissions' => [], 'notifications' => []]]) as $i => $group): ?>
                                        <tr>
                                            <td><input class="form-control" aria-label="Group name" name="group_name[<?= $i ?>]" maxlength="128" value="<?= $escape($group['name']) ?>"></td>
                                            <td>
                                                <input class="form-control" aria-label="Replacement group password" type="password" autocomplete="new-password" name="group_password[<?= $i ?>]">
                                                <small class="text-muted"><?= !empty($group['has_password']) ? 'Existing value preserved if blank' : 'Required for new group' ?></small>
                                            </td>
                                            <td><textarea class="form-control" aria-label="Group permissions" rows="2" name="group_permissions[<?= $i ?>]"><?= $escape(implode(', ', $group['permissions'])) ?></textarea></td>
                                            <td><textarea class="form-control" aria-label="Group notifications" rows="2" name="group_notifications[<?= $i ?>]"><?= $escape(implode(', ', $group['notifications'])) ?></textarea></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="form-text">The final empty row adds one group. Save validates against CorradeConfiguration.xsd, backs up the existing XML and replaces it atomically. No service restart occurs.</p>
                            <button type="submit" class="btn btn-primary">Save Corrade XML</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3">XML configuration editor unavailable. Check the configuration path, permissions, and XML file.</div>
                    <?php endif; ?>
                    <hr>
                    <form method="post" action="/admin/corrade" onsubmit="return confirm('Remove this bot registration? Corrade will not be stopped.');">
                        <?= $this->csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger">Remove Bot Registration</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

    <?php elseif ($tab === 'register'): ?>
        <section class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Register Bot</h2>
                <?php if ($state !== 'current'): ?>
                    <div class="alert alert-warning">Install or repair the database first.</div>
                <?php else: ?>
                    <?php if (!$sparklesRegistered): ?>
                        <div class="alert alert-info">
                            <strong>Existing installation:</strong> sparklesagent
                            <p class="mb-2">Configuration: <code>/home/stnchain/corrade/CorradeConfiguration.xml</code><br>Service: <code>corrade.service</code></p>
                            <form method="post" action="/admin/corrade">
                                <?= $this->csrf_field() ?>
                                <input type="hidden" name="action" value="adopt_sparklesagent">
                                <button type="submit" class="btn btn-outline-primary">Register Existing sparklesagent</button>
                            </form>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="/admin/corrade">
                        <?= $this->csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label" for="new-name">Bot Name</label>
                            <input class="form-control" id="new-name" name="agent_name" required maxlength="64" pattern="[A-Za-z0-9_-]+">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="new-config">Configuration Path</label>
                            <input class="form-control" id="new-config" name="configuration_path" required maxlength="512" placeholder="/home/stnchain/corrade/agents/agent01/CorradeConfiguration.xml">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="new-service">Service Name</label>
                            <input class="form-control" id="new-service" name="service_name" required maxlength="128" placeholder="corrade@agent01.service">
                        </div>
                        <button type="submit" class="btn btn-primary">Register Bot</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php
// Render the footer
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
