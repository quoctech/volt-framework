<?php

declare(strict_types=1);

namespace Volt\Core\AwesomeBar\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Volt\Core\AwesomeBar\Models\AwesomeBarModel;

class AwesomeBarController extends Controller
{
    private AwesomeBarModel $awesomeBarModel;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->awesomeBarModel = new AwesomeBarModel();
    }

    public function search(): ResponseInterface
    {
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $this->awesomeBarModel->seedCorePages();

        $actor = service('voltAuth')->currentUser();

        if ($actor === null) {
            return $this->response->setJSON(['results' => []]);
        }

        $resolver = service('voltPermissionResolver');

        $results = $this->awesomeBarModel->search($q);

        $filtered = [];

        foreach ($results as $item) {
            if ($item['item_type'] === 'entity') {
                if (! $resolver->can($item['item_name'], 'read')) {
                    continue;
                }
            }

            if ($item['item_type'] === 'page') {
                $adminPages = ['entity_builder', 'create_module', 'user_list', 'role_list', 'system_status'];
                if (in_array($item['item_name'], $adminPages, true) && ! $actor->isAdmin()) {
                    continue;
                }

                if ($item['item_name'] === 'error_logs' && ! $actor->isAdmin() && ! $resolver->can('error_logs', 'read', null, null, $actor)) {
                    continue;
                }
            }

            $filtered[] = $item;
        }

        return $this->response->setJSON(['results' => $filtered]);
    }
}
