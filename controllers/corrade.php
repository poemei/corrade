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
                if ($action === 'create') {
                    $model->createAgent($_POST);
                    $this->redirectAdmin('created');
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
        $this->view('admin/index', [
            'database_state' => $state,
            'agents' => $state === 'current' ? $model->getAgents() : [],
            'error' => $error,
            'status' => trim((string) ($_GET['status'] ?? '')),
        ]);
    }

    private function redirectAdmin(string $status): void
    {
        header('Location: /admin/corrade?status=' . rawurlencode($status), true, 303);
        exit;
    }
}
