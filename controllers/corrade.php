<?php
declare(strict_types=1);

// path: /user/modules/corrade/controllers/corrade.php

final class corrade extends controller
{
    public function index(array $params = []): void
    {
        $this->view('index', ['module' => 'Corrade Manager']);
    }

    public function admin(array $params = []): void
    {
        $this->require_admin(7);
        $model = $this->model('corrade_model');
        $state = $model->databaseState();
        $error = null;
        require_once __DIR__ . '/../lib/CorradeXmlEditor.php';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            $action = trim((string) ($_POST['action'] ?? ''));
            try {
                if ($action === 'install_sql') {
                    if ($state !== 'missing') {
                        throw new RuntimeException('Database installation is not available in this state.');
                    }
                    $model->installSchema();
                    $this->redirectAdmin('installed');
                    return;
                }
                if ($state !== 'current') {
                    throw new RuntimeException('The module database is not current.');
                }
                if ($action === 'adopt_sparklesagent') {
                    $model->adoptExistingAgent();
                    $this->redirectAdmin('adopted');
                    return;
                }
                if ($action === 'create') {
                    $model->createAgent($_POST);
                    $this->redirectAdmin('created');
                    return;
                }
                if ($action === 'save_xml') {
                    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                    $agent = $id ? $model->getAgent($id) : null;
                    if (!$agent) {
                        throw new InvalidArgumentException('Unknown Corrade bot.');
                    }
                    CorradeXmlEditor::save($model->configurationPath($agent), $_POST);
                    $this->redirectAdmin('xml_saved');
                    return;
                }
                if ($action === 'update') {
                    $model->updateAgent($_POST);
                    $this->redirectAdmin('updated');
                    return;
                }
                if ($action === 'delete_data') {
                    $confirmation = (string) ($_POST['confirmation'] ?? '');
                    if ($confirmation !== 'DELETE DATA') {
                        throw new InvalidArgumentException('Type DELETE DATA to clear agent registrations.');
                    }
                    $model->deleteData();
                    $this->redirectAdmin('data_deleted');
                    return;
                }
                if ($action === 'delete') {
                    $model->deleteAgent((int) ($_POST['id'] ?? 0));
                    $this->redirectAdmin('deleted');
                    return;
                }
                throw new InvalidArgumentException('Unknown administration action.');
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = 'Corrade administration failed.';
            }
        }

        $state = $model->databaseState();
        $editAgent = null;
        $xmlConfig = null;
        $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
        if ($state === 'current' && $editId) {
            $editAgent = $model->getAgent($editId);
            if ($editAgent) {
                try {
                    $xmlConfig = CorradeXmlEditor::read($model->configurationPath($editAgent));
                } catch (Throwable $exception) {
                    $error = 'Corrade XML could not be read: ' . $exception->getMessage();
                }
            }
        }
        $this->view('admin/index', [
            'database_state' => $state,
            'xml_config' => $xmlConfig,
            'edit_agent' => $editAgent,
            'agents' => $state === 'current' ? $model->getAgents() : [],
            'error' => $error,
            'status' => trim((string) ($_GET['status'] ?? '')),
            'module' => [
                'name' => 'Corrade Manager',
                'version' => '0.1.0',
                'schema_version' => '0.1.0',
            ],
        ]);
    }

    private function redirectAdmin(string $status): void
    {
        header('Location: /admin/corrade?status=' . rawurlencode($status), true, 303);
        exit;
    }
}
