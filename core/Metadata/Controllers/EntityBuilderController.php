<?php

declare(strict_types=1);

namespace Volt\Core\Metadata\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;
use Volt\Core\Metadata\EntityBuilderService;

class EntityBuilderController extends Controller
{
    private EntityBuilderService $builderService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        helper(['form', 'url']);
        $this->builderService = new EntityBuilderService();
    }

    public function index(): string
    {
        $userContext = $this->deskUserContext();

        return view('Volt\\Core\\Metadata\\Views\\entity_builder', [
            'modules'          => $this->builderService->listModules(),
            'entityOptions'    => $this->builderService->listEntityOptions(),
            'entityFieldCatalog' => $this->builderService->listEntityFieldCatalog(),
            'initialEntityName' => (string) ($this->request->getGet('entity') ?? ''),
            'isAdmin' => $userContext['isAdmin'],
            'currentUserName' => $userContext['currentUserName'],
        ]);
    }

    public function desk(): string
    {
        $userContext = $this->deskUserContext();

        return view('Volt\\Core\\Metadata\\Views\\desk', [
            'moduleCount' => count($this->builderService->listModules()),
            'entityCount' => count($this->builderService->listEntityNames()),
            'isAdmin' => $userContext['isAdmin'],
            'currentUserName' => $userContext['currentUserName'],
        ]);
    }

    public function entityList(): string
    {
        $moduleFilter = trim((string) ($this->request->getGet('module') ?? ''));
        $userContext = $this->deskUserContext();

        return view('Volt\\Core\\Metadata\\Views\\entity_list', [
            'modules' => $this->builderService->listModules(),
            'moduleFilter' => $moduleFilter,
            'entities' => $this->builderService->listEntities($moduleFilter !== '' ? $moduleFilter : null),
            'isAdmin' => $userContext['isAdmin'],
            'currentUserName' => $userContext['currentUserName'],
        ]);
    }

    public function modulePage(): string
    {
        $userContext = $this->deskUserContext();

        return view('Volt\\Core\\Metadata\\Views\\create_module', [
            'modules' => $this->builderService->listModules(),
            'isAdmin' => $userContext['isAdmin'],
            'currentUserName' => $userContext['currentUserName'],
        ]);
    }

    /**
     * @return array{isAdmin:bool,currentUserName:string}
     */
    private function deskUserContext(): array
    {
        $user = service('voltAuth')->currentUser();

        return [
            'isAdmin' => $user !== null && $user->isAdmin(),
            'currentUserName' => $user !== null ? (string) $user->name : '',
        ];
    }

    public function load(string $entityName): ResponseInterface
    {
        try {
            $payload = $this->builderService->loadEntity($entityName);

            return $this->response->setJSON([
                'status' => 'ok',
                'data'   => $payload,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $throwable) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    public function save(): ResponseInterface
    {
        $payload = $this->extractPayload();

        if (! is_array($payload)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Invalid payload.',
            ]);
        }

        try {
            $result = $this->builderService->saveEntity($payload);

            return $this->response->setJSON([
                'status'  => 'ok',
                'message' => 'Entity saved successfully.',
                'data'    => $result,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $throwable) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    public function saveModule(): ResponseInterface
    {
        $payload = $this->extractPayload();

        if (! is_array($payload)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Invalid payload.',
            ]);
        }

        try {
            $result = $this->builderService->createModule($payload);

            return $this->response->setJSON([
                'status'  => 'ok',
                'message' => 'Module created successfully.',
                'data'    => $result,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $throwable) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPayload(): ?array
    {
        if ($this->request->is('json')) {
            $payload = $this->request->getJSON(true);
            return is_array($payload) ? $payload : null;
        }

        $payload = $this->request->getPost();

        return is_array($payload) ? $payload : null;
    }
}
