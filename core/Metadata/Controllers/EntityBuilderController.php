<?php

declare(strict_types=1);

namespace Volt\Core\Metadata\Controllers;

use CodeIgniter\Controller;
use InvalidArgumentException;
use Volt\Core\Metadata\EntityBuilderService;

class EntityBuilderController extends Controller
{
    private EntityBuilderService $builderService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        helper(['form', 'url']);
        $this->builderService = new EntityBuilderService();
    }

    public function index()
    {
        return view('entities/builder', [
            'entities' => $this->builderService->listEntities(),
            'error' => session()->getFlashdata('entity_error'),
            'success' => session()->getFlashdata('entity_success'),
        ]);
    }

    public function store()
    {
        $payload = $this->request->getPost();
        $fieldsJson = (string) ($payload['fields_json'] ?? '[]');

        $entity = [
            'name' => (string) ($payload['name'] ?? ''),
            'module' => (string) ($payload['module'] ?? ''),
            'issingle' => (int) (($payload['issingle'] ?? 0) === '1' || ($payload['issingle'] ?? 0) === 1),
            'istable' => (int) (($payload['istable'] ?? 0) === '1' || ($payload['istable'] ?? 0) === 1),
            'autoname' => (string) ($payload['autoname'] ?? 'HASH'),
            'states' => $this->decodeJson((string) ($payload['states_json'] ?? '{}')),
            'custom_attributes' => $this->decodeJson((string) ($payload['custom_attributes_json'] ?? '{}')),
        ];

        $fields = $this->decodeJson($fieldsJson);

        try {
            $this->builderService->createEntity($entity, is_array($fields) ? $fields : []);
        } catch (InvalidArgumentException $exception) {
            return view('entities/builder', [
                'entities' => $this->builderService->listEntities(),
                'error' => $exception->getMessage(),
                'success' => null,
            ]);
        } catch (\Throwable $throwable) {
            return view('entities/builder', [
                'entities' => $this->builderService->listEntities(),
                'error' => $throwable->getMessage(),
                'success' => null,
            ]);
        }

        return redirect()->to(site_url('entities/new'))->with('entity_success', 'Entity đã được tạo.');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
