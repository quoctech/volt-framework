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
        return view('Volt\\Core\\Metadata\\Views\\entity_builder', [
            'modules'           => $this->builderService->listModules(),
            'entities'          => $this->builderService->listEntityNames(),
            'initialEntityName' => (string) ($this->request->getGet('entity') ?? ''),
            'csrfTokenName'     => csrf_token(),
            'csrfHash'          => csrf_hash(),
        ]);
    }

    public function desk(): string
    {
        $moduleFilter = trim((string) ($this->request->getGet('module') ?? ''));

        return view('Volt\\Core\\Metadata\\Views\\desk', [
            'moduleCount' => count($this->builderService->listModules()),
            'entityCount' => count($this->builderService->listEntityNames()),
            'modules' => $this->builderService->listModules(),
            'moduleFilter' => $moduleFilter,
            'entities' => $this->builderService->listEntities($moduleFilter !== '' ? $moduleFilter : null),
        ]);
    }

    public function modulePage(): string
    {
        return view('Volt\\Core\\Metadata\\Views\\create_module', [
            'modules'       => $this->builderService->listModules(),
            'csrfTokenName' => csrf_token(),
            'csrfHash'      => csrf_hash(),
        ]);
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
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

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
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

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
}
