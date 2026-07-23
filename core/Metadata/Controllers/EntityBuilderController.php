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
    private readonly EntityBuilderService $builderService;

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

        $data = [
            'modules' => $this->builderService->listModules(),
            'moduleFilter' => $moduleFilter,
            'entities' => $this->builderService->listEntities($moduleFilter !== '' ? $moduleFilter : null),
            'isAdmin' => $userContext['isAdmin'],
            'currentUserName' => $userContext['currentUserName'],
        ];

        $content = view('Volt\\Core\\Metadata\\Views\\entity_list', $data);

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'      => 'Entity List · Volt Desk',
            'currentUserName' => $userContext['currentUserName'],
            'isAdmin'        => $userContext['isAdmin'],
            'deskActive'     => 'entities',
            'content'        => $content,
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
            service('voltErrorLog')->logException($exception, ['entity' => $entityName], 'entity_builder', 'entity_builder_load_invalid_argument');
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, ['entity' => $entityName], 'entity_builder', 'entity_builder_load_failed');
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
            service('voltErrorLog')->logException($exception, ['payload' => $payload], 'entity_builder', 'entity_builder_save_invalid_argument');
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, ['payload' => $payload], 'entity_builder', 'entity_builder_save_failed');
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    public function delete(string $entityName): ResponseInterface
    {
        try {
            $payload = $this->extractPayload();
            $password = is_array($payload) ? trim((string) ($payload['password'] ?? '')) : '';

            if ($password === '') {
                throw new InvalidArgumentException('Password is required.');
            }

            $confirmation = service('voltAuth')->confirmCurrentPassword($password);
            if (($confirmation['ok'] ?? false) !== true) {
                throw new InvalidArgumentException((string) ($confirmation['message'] ?? 'Password confirmation failed.'));
            }

            $result = $this->builderService->deleteEntity($entityName);

            return $this->response->setJSON([
                'status' => 'ok',
                'message' => 'Entity deleted successfully.',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $exception) {
            service('voltErrorLog')->logException($exception, ['entity' => $entityName], 'entity_builder', 'entity_builder_delete_invalid_argument');
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, ['entity' => $entityName], 'entity_builder', 'entity_builder_delete_failed');
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
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
            service('voltErrorLog')->logException($exception, ['payload' => $payload], 'entity_builder', 'entity_builder_module_save_invalid_argument');
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $throwable) {
            service('voltErrorLog')->logException($throwable, ['payload' => $payload], 'entity_builder', 'entity_builder_module_save_failed');
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
