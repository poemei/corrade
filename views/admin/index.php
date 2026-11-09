<?php
// path: /user/modules/corrade/views/admin/index.php
require APPROOT . '/views/inc/head.php';
$state = (string) ($data['database_state'] ?? 'invalid');
$agents = is_array($data['agents'] ?? null) ? $data['agents'] : [];
$error = $data['error'] ?? null;
$status = (string) ($data['status'] ?? '');
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="container my-3">
    <p><a href="/admin">Admin</a> &gt;&gt; Corrade</p>
    <h1>Corrade Manager</h1>
    <p>Version 0.1.0 · Database state: <strong><?= $e($state) ?></strong></p>
    <p>Agent registry only. No Corrade processes or systemd services are modified here.</p>
    <?php if ($error): ?><p role="alert"><?= $e($error) ?></p><?php endif; ?>
    <?php if (in_array($status, ['installed', 'created', 'deleted'], true)): ?>
        <p role="status">Operation completed: <?= $e($status) ?></p>
    <?php endif; ?>
    <?php if ($state === 'missing'): ?>
        <form method="post" action="/admin/corrade">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="action" value="install_sql">
            <button type="submit">Install SQL</button>
        </form>
    <?php elseif ($state === 'current'): ?>
        <h2>Registered agents (<?= count($agents) ?>)</h2>
        <table>
            <thead><tr><th>Agent</th><th>Configuration</th><th>Service</th><th>Enabled</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($agents as $agent): ?>
                <tr>
                    <td><?= $e($agent['agent_name']) ?></td>
                    <td><?= $e($agent['configuration_path']) ?></td>
                    <td><?= $e($agent['service_name']) ?></td>
                    <td><?= !empty($agent['is_enabled']) ? 'Yes' : 'No' ?></td>
                    <td>
                        <form method="post" action="/admin/corrade">
                            <?= $this->csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $agent['id'] ?>">
                            <button type="submit">Remove registration</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h2>Register agent</h2>
        <form method="post" action="/admin/corrade">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <label>Agent name <input name="agent_name" required maxlength="64" pattern="[A-Za-z0-9_-]+"></label>
            <label>Configuration path <input name="configuration_path" required maxlength="512" placeholder="/home/stnchain/corrade/agents/agent01/CorradeConfiguration.xml"></label>
            <label>Service name <input name="service_name" required maxlength="80" placeholder="corrade@agent01.service"></label>
            <button type="submit">Register</button>
        </form>
    <?php else: ?>
        <p role="alert">Database state is invalid. No mutation is available.</p>
    <?php endif; ?>
</div>
<?php require APPROOT . '/views/inc/footer.php'; ?>
