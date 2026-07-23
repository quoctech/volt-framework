<?php

declare(strict_types=1);

namespace Volt\Core\Metadata\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Database\BaseConnection;
use Psr\Log\LoggerInterface;
use Throwable;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Engine\VoltMetadataCompiler;
use Volt\Core\Models\VoltModel;

final class VoltResourceController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 20, 50, 100, 200, 500, 1000, 2500];

    private const DEFAULT_PER_PAGE = 50;

    private const DEFAULT_SESSION_UID = 'primary';

    private readonly VoltMetadataCompiler $compiler;

    private readonly BaseConnection $db;

    /** @var array<string, array> */
    private array $metaCache = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $fieldsCache = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $sessionsCache = [];

    /** @var array<string, array<string, array<string, string>>> */
    private array $linkTargetsCache = [];

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        helper(['url']);
        $this->compiler = service('voltMetadataCompiler') ?? new VoltMetadataCompiler();
        $this->db = VoltDatabase::connection();
    }

    // ========================================================================
    //  FORM / HTML VIEWS
    // ========================================================================

    public function indexView(string $entityName): string|ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        if (! $model->canRead()) {
            return $this->forbiddenHtml();
        }

        $entitySnake = $this->snake($entityName);
        $module = $this->getEntityModule($entityName);
        $moduleStudly = $this->studly($module);
        $moduleSnake = $this->snake($moduleStudly);

        $meta = $this->getCompiledMeta($entityName);

        return view("App\\Modules\\{$moduleStudly}\\Views\\{$entitySnake}_list", [
            'title'        => $this->titleize($entityName) . ' List',
            'dataUrl'      => site_url("{$moduleSnake}/api/{$entitySnake}"),
            'createUrl'    => site_url("{$moduleSnake}/{$entitySnake}/create"),
            'editUrlBase'  => site_url("{$moduleSnake}/{$entitySnake}/edit"),
            'builderUrl'   => site_url("desk/entity-builder?entity={$entitySnake}"),
            'linkTargets'  => $this->getLinkTargets($entityName),
            'isSubmittable' => (bool) ($meta['entity']['custom_attributes']['is_submittable'] ?? $meta['workflow']['is_submittable'] ?? false),
        ]);
    }

    public function createView(string $entityName): string|ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        if (! $model->canWrite('create')) {
            return $this->forbiddenHtml();
        }

        $entitySnake = $this->snake($entityName);
        $module = $this->getEntityModule($entityName);
        $moduleStudly = $this->studly($module);
        $moduleSnake = $this->snake($moduleStudly);

        $meta = $this->getCompiledMeta($entityName);

        return view("App\\Modules\\{$moduleStudly}\\Views\\{$entitySnake}_form", [
            'title'        => 'New ' . $this->titleize($entityName),
            'listUrl'      => site_url("{$moduleSnake}/{$entitySnake}"),
            'saveUrl'      => site_url("{$moduleSnake}/api/{$entitySnake}/save"),
            'loadUrlBase'  => site_url("{$moduleSnake}/api/{$entitySnake}/load"),
            'fields'       => $this->getFormFields($entityName),
            'sessions'     => $this->getFormSessions($entityName),
            'linkTargets'  => $this->getLinkTargets($entityName),
            'recordName'   => '',
            'isSubmittable' => (bool) ($meta['entity']['custom_attributes']['is_submittable'] ?? $meta['workflow']['is_submittable'] ?? false),
            'submitUrl'    => site_url("{$moduleSnake}/api/{$entitySnake}/submit"),
            'approveUrl'   => site_url("{$moduleSnake}/api/{$entitySnake}/approve"),
            'cancelUrl'    => site_url("{$moduleSnake}/api/{$entitySnake}/cancel"),
            'amendUrl'     => site_url("{$moduleSnake}/api/{$entitySnake}/amend"),
        ]);
    }

    public function editView(string $entityName, string $id): string|ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        if (! $model->canWrite('write')) {
            return $this->forbiddenHtml();
        }

        $entitySnake = $this->snake($entityName);
        $module = $this->getEntityModule($entityName);
        $moduleStudly = $this->studly($module);
        $moduleSnake = $this->snake($moduleStudly);

        $meta = $this->getCompiledMeta($entityName);

        return view("App\\Modules\\{$moduleStudly}\\Views\\{$entitySnake}_form", [
            'title'        => 'Edit ' . $this->titleize($entityName),
            'listUrl'      => site_url("{$moduleSnake}/{$entitySnake}"),
            'saveUrl'      => site_url("{$moduleSnake}/api/{$entitySnake}/save"),
            'loadUrlBase'  => site_url("{$moduleSnake}/api/{$entitySnake}/load"),
            'fields'       => $this->getFormFields($entityName),
            'sessions'     => $this->getFormSessions($entityName),
            'linkTargets'  => $this->getLinkTargets($entityName),
            'recordName'   => $id,
            'isSubmittable' => (bool) ($meta['entity']['custom_attributes']['is_submittable'] ?? $meta['workflow']['is_submittable'] ?? false),
            'submitUrl'    => site_url("{$moduleSnake}/api/{$entitySnake}/submit"),
            'approveUrl'   => site_url("{$moduleSnake}/api/{$entitySnake}/approve"),
            'cancelUrl'    => site_url("{$moduleSnake}/api/{$entitySnake}/cancel"),
            'amendUrl'     => site_url("{$moduleSnake}/api/{$entitySnake}/amend"),
        ]);
    }

    // ========================================================================
    //  JSON DATA API
    // ========================================================================

    public function data(string $entityName): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        if (! $model->canRead()) {
            return $this->respondError('Forbidden', 403);
        }

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $query = mb_trim((string) ($this->request->getGet('q') ?? ''));
        $fields = $this->getFormFields($entityName);
        $builder = $model->builder();

        if ($query !== '') {
            $builder->groupStart();
            $builder->like('name', $query);
            foreach ($fields as $field) {
                $fieldname = (string) ($field['fieldname'] ?? '');
                if ($fieldname === '' || $fieldname === 'name') {
                    continue;
                }
                $builder->orLike($fieldname, $query);
            }
            $builder->groupEnd();
        }

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults(false);
        $rows = $builder
            ->orderBy('modified', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        $rows = $this->hydrateLinkDisplayValues($rows, $entityName);

        return $this->response->setJSON([
            'status' => 'ok',
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
                'options' => self::PER_PAGE_OPTIONS,
            ],
        ]);
    }

    public function show(string $entityName, string $id): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        $row = $model->find($id);
        if (! is_array($row)) {
            return $this->respondError('Record not found.', 404);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => $row,
        ]);
    }

    public function linkOptions(string $entityName): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        if (! $model->canRead()) {
            return $this->respondError('Forbidden', 403);
        }

        $query = mb_trim((string) ($this->request->getGet('q') ?? ''));
        $builder = $model->builder()
            ->select('name')
            ->limit(20);

        if ($query !== '') {
            $builder->like('name', "%{$query}%");
        }

        $rows = $builder->get()->getResultArray();

        return $this->response->setJSON([
            'status' => 'ok',
            'items' => $rows,
        ]);
    }

    /**
     * Unified save for form POST (handles both create and update).
     */
    public function store(string $entityName): ResponseInterface
    {
        $model = $this->resolveModel($entityName);

        $payload = $this->extractPayload();
        if (! is_array($payload)) {
            return $this->respondError('Invalid payload.', 422);
        }

        $fields = $this->getFormFields($entityName);
        $row = $this->normalizePayload($fields, $payload);
        $name = mb_trim((string) ($row['name'] ?? ''));

        try {
            $exists = $name !== '' && is_array($model->find($name));

            if ($exists && ! $model->canWrite('write')) {
                return $this->respondError('Forbidden', 403);
            }

            if (! $exists && ! $model->canWrite('create')) {
                return $this->respondError('Forbidden', 403);
            }

            if (! $exists && $name === '') {
                $name = $this->generateDocumentName($entityName);
                $row['name'] = $name;
            }

            if ($exists) {
                $row = $this->applyReadOnlyFields(fields: $fields, model: $model, row: $row, existingName: $name);
            }

            $this->assertRequiredFields(fields: $fields, row: $row, model: $model, existingName: $exists ? $name : null);

            if ($exists) {
                $model->update($name, $row);
            } else {
                $model->insert($row);
            }

            return $this->response->setJSON([
                'status' => 'ok',
                'message' => $exists ? 'Record updated.' : 'Record created.',
                'data' => ['name' => $name],
            ]);
        } catch (Throwable $throwable) {
            if ($exists === false && str_contains($throwable->getMessage(), 'duplicate key')) {
                for ($retry = 0; $retry < 100; $retry++) {
                    $row['name'] = $this->generateDocumentName($entityName);
                    try {
                        $model->insert($row);
                        return $this->response->setJSON([
                            'status' => 'ok',
                            'message' => 'Record created.',
                            'data' => ['name' => $row['name']],
                        ]);
                    } catch (Throwable $retryThrowable) {
                        if (! str_contains($retryThrowable->getMessage(), 'duplicate key')) {
                            return $this->respondError($retryThrowable->getMessage(), 422);
                        }
                    }
                }

                return $this->respondError('Could not generate unique name after retries.', 422);
            }

            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    public function destroy(string $entityName, string $id): ResponseInterface
    {
        $model = $this->resolveModel($entityName);

        try {
            $model->delete($id);

            return $this->response->setJSON([
                'status' => 'ok',
                'message' => 'Record deleted.',
            ]);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    // ========================================================================
    //  RESTful JSON API
    // ========================================================================

    public function restIndex(string $entityName): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        if (! $model->canRead()) {
            return $this->respondError('Forbidden', 403);
        }

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = min(100, max(1, (int) ($this->request->getGet('per_page') ?? 50)));
        $query = mb_trim((string) ($this->request->getGet('q') ?? ''));

        $builder = $model->builder();

        if ($query !== '') {
            $pk = $model->primaryKey;
            $builder->groupStart();
            $builder->like($pk, $query);
            foreach ($model->allowedFields as $field) {
                if ($field === $pk) {
                    continue;
                }
                $builder->orLike($field, $query);
            }
            $builder->groupEnd();
        }

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->countAllResults(false);
        $rows = $builder
            ->orderBy('modified', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'data' => $rows,
            'meta' => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function restShow(string $entityName, string $id): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        $row = $model->find($id);
        if (! is_array($row)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        return $this->response->setJSON([
            'data' => $row,
        ]);
    }

    public function restStore(string $entityName): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        if (! $model->canWrite('create')) {
            return $this->respondError('Forbidden', 403);
        }

        $payload = $this->extractPayload();
        if (! is_array($payload)) {
            return $this->respondError('Invalid JSON payload.', 400);
        }

        $allowedFields = $model->allowedFields;
        if ($allowedFields !== []) {
            $payload = $this->filterAllowedFields($payload, $allowedFields);
        }

        try {
            $id = $model->insert($payload);
            if ($id === false) {
                $errors = $model->errors();
                if (! empty($errors)) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'status' => 'error',
                        'message' => 'Validation failed.',
                        'errors' => $errors,
                    ]);
                }

                return $this->respondError('Unable to create record.', 422);
            }

            $record = $model->find($id);

            return $this->response->setStatusCode(201)->setJSON([
                'data' => $record,
            ]);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    public function restUpdate(string $entityName, string $id): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        $existing = $model->find($id);
        if (! is_array($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        if (! $model->canWrite('write')) {
            return $this->respondError('Forbidden', 403);
        }

        $payload = $this->extractPayload();
        if (! is_array($payload)) {
            return $this->respondError('Invalid JSON payload.', 400);
        }

        $allowedFields = $model->allowedFields;
        if ($allowedFields !== []) {
            $payload = $this->filterAllowedFields($payload, $allowedFields);
        }
        unset($payload[$model->primaryKey]);

        try {
            if (! $model->update($id, $payload)) {
                $errors = $model->errors();
                if (! empty($errors)) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'status' => 'error',
                        'message' => 'Validation failed.',
                        'errors' => $errors,
                    ]);
                }

                return $this->respondError('Unable to update record.', 422);
            }

            $record = $model->find($id);

            return $this->response->setJSON([
                'data' => $record,
            ]);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    public function restDestroy(string $entityName, string $id): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        $existing = $model->find($id);
        if (! is_array($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        try {
            $model->delete($id);

            return $this->response->setStatusCode(204);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    public function restSubmit(string $entityName, string $id): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        $existing = $model->find($id);
        if (! is_array($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        try {
            $result = $model->submit($id);

            return $this->response->setJSON([
                'status' => 'ok',
                'data' => $result,
            ]);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    public function restApprove(string $entityName, string $id): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        $existing = $model->find($id);
        if (! is_array($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        try {
            $result = $model->approve($id);

            return $this->response->setJSON([
                'status' => 'ok',
                'data' => $result,
            ]);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    public function restCancel(string $entityName, string $id): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        $existing = $model->find($id);
        if (! is_array($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        try {
            $result = $model->cancel($id);

            return $this->response->setJSON([
                'status' => 'ok',
                'data' => $result,
            ]);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    public function restAmend(string $entityName, string $id): ResponseInterface
    {
        $model = $this->resolveModel($entityName);
        $existing = $model->find($id);
        if (! is_array($existing)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        try {
            $record = $model->amend($id);

            return $this->response->setJSON([
                'status' => 'ok',
                'data' => $record,
            ]);
        } catch (Throwable $throwable) {
            return $this->respondError($throwable->getMessage(), 422);
        }
    }

    // ========================================================================
    //  ENTITY METADATA HELPERS
    // ========================================================================

    private function getCompiledMeta(string $entityName): array
    {
        $cacheKey = mb_strtolower(mb_trim($entityName));
        if (isset($this->metaCache[$cacheKey])) {
            return $this->metaCache[$cacheKey];
        }

        $meta = $this->compiler->compileEntity($entityName);
        $this->metaCache[$cacheKey] = $meta;

        return $meta;
    }

    private function getEntityModule(string $entityName): string
    {
        $meta = $this->getCompiledMeta($entityName);

        return (string) ($meta['entity']['module'] ?? '');
    }

    private function getAutoname(string $entityName): string
    {
        $meta = $this->getCompiledMeta($entityName);

        return (string) ($meta['entity']['autoname'] ?? 'HASH');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFormFields(string $entityName): array
    {
        $cacheKey = mb_strtolower(mb_trim($entityName));
        if (isset($this->fieldsCache[$cacheKey])) {
            return $this->fieldsCache[$cacheKey];
        }

        $meta = $this->getCompiledMeta($entityName);
        $this->fieldsCache[$cacheKey] = $this->extractFormFields($meta);

        return $this->fieldsCache[$cacheKey];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFormSessions(string $entityName): array
    {
        $cacheKey = mb_strtolower(mb_trim($entityName));
        if (isset($this->sessionsCache[$cacheKey])) {
            return $this->sessionsCache[$cacheKey];
        }

        $meta = $this->getCompiledMeta($entityName);
        $this->sessionsCache[$cacheKey] = $this->extractFormSessions($meta);

        return $this->sessionsCache[$cacheKey];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getLinkTargets(string $entityName): array
    {
        $cacheKey = mb_strtolower(mb_trim($entityName));
        if (isset($this->linkTargetsCache[$cacheKey])) {
            return $this->linkTargetsCache[$cacheKey];
        }

        $fields = $this->getFormFields($entityName);
        $this->linkTargetsCache[$cacheKey] = $this->resolveLinkTargets($entityName, $fields);

        return $this->linkTargetsCache[$cacheKey];
    }

    private function resolveModel(string $entityName): VoltModel
    {
        $module = $this->getEntityModule($entityName);
        $moduleStudly = $this->studly($module);
        $entityStudly = $this->studly($entityName);
        $modelClass = "App\\Modules\\{$moduleStudly}\\Models\\{$entityStudly}Model";

        if (! class_exists($modelClass)) {
            throw new \RuntimeException("Model not found: {$modelClass}");
        }

        return new $modelClass();
    }

    // ========================================================================
    //  FORM FIELD / SESSION EXTRACTION
    // ========================================================================

    /**
     * @param array<string, mixed> $compiled
     * @return array<int, array<string, mixed>>
     */
    private function extractFormFields(array $compiled): array
    {
        $fields = [];

        $source = is_array($compiled['fields'] ?? null) ? $compiled['fields'] : [];
        $childColumnsByEntity = $this->batchResolveChildColumns($source);

        foreach ($source as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === 'name' || ($field['hidden'] ?? false)) {
                continue;
            }

            $custom = is_array($field['f_custom_jsonb'] ?? null) ? $field['f_custom_jsonb'] : [];
            $row = [
                'fieldname' => $fieldname,
                'label' => (string) ($field['label'] ?? ''),
                'fieldtype' => (string) ($field['fieldtype'] ?? 'Input'),
                'options' => (string) ($field['options'] ?? ''),
                'default_value' => $field['default_value'] ?? $custom['default_value'] ?? '',
                'placeholder' => (string) ($field['placeholder'] ?? $custom['placeholder'] ?? ''),
                'fetch_from' => (string) ($field['fetch_from'] ?? $custom['fetch_from'] ?? ''),
                'is_required' => (bool) ($field['reqd'] ?? $field['is_required'] ?? false),
                'read_only' => (bool) ($field['read_only'] ?? false),
                'idx' => (int) ($field['idx'] ?? 0),
                'session_uid' => (string) ($field['session_uid'] ?? $custom['session_uid'] ?? self::DEFAULT_SESSION_UID),
                'column' => min(4, max(1, (int) ($field['column'] ?? $custom['column'] ?? 1))),
                'custom_meta' => $custom,
            ];

            if (in_array($row['fieldtype'], ['Table', 'Child Table (JSONB)'], true)) {
                $childName = $this->parseChildEntityName($row['options']);
                $row['child_columns'] = $childColumnsByEntity[$childName] ?? [];
                $row['column'] = 1;
            }

            $fields[] = $row;
        }

        return array_values(array_filter($fields, static fn (array $f): bool => (string) ($f['fieldname'] ?? '') !== ''));
    }

    /**
     * @param array<string, mixed> $compiled
     * @return array<int, array<string, mixed>>
     */
    private function extractFormSessions(array $compiled): array
    {
        $custom = is_array($compiled['entity']['custom_attributes'] ?? null) ? $compiled['entity']['custom_attributes'] : [];
        $layout = is_array($custom['layout'] ?? null) ? $custom['layout'] : [];
        $source = is_array($layout['sessions'] ?? null) ? $layout['sessions'] : [];

        $sessions = [];
        foreach ($source as $session) {
            if (! is_array($session)) {
                continue;
            }
            $sessions[] = [
                'uid' => (string) ($session['uid'] ?? 'primary'),
                'title' => (string) ($session['title'] ?? 'Session'),
                'description' => (string) ($session['description'] ?? ''),
                'column_count' => min(4, max(1, (int) ($session['column_count'] ?? 1))),
            ];
        }

        if ($sessions === []) {
            $sessions[] = [
                'uid' => 'primary',
                'title' => 'Primary',
                'description' => '',
                'column_count' => 1,
            ];
        }

        return $sessions;
    }

    /**
     * @param array<string, array> $fieldMap
     * @return array<string, array<int, array{fieldname:string,label:string,fieldtype:string}>>
     */
    private function batchResolveChildColumns(array $fieldMap): array
    {
        $childNames = [];
        foreach ($fieldMap as $field) {
            if (! is_array($field)) {
                continue;
            }
            if (! in_array(($field['fieldtype'] ?? ''), ['Table', 'Child Table (JSONB)'], true)) {
                continue;
            }
            $childName = $this->parseChildEntityName((string) ($field['options'] ?? ''));
            if ($childName !== '') {
                $childNames[$childName] = true;
            }
        }

        if ($childNames === []) {
            return [];
        }

        $allRows = $this->db->table('sys_entity_field')
            ->select('parent, fieldname, label, fieldtype, hidden')
            ->whereIn('parent', array_keys($childNames))
            ->orderBy('parent', 'ASC')
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($allRows as $row) {
            $parent = (string) ($row['parent'] ?? '');
            if ($parent === '') {
                continue;
            }
            $result[$parent] ??= [];

            if ((int) ($row['hidden'] ?? 0) === 1) {
                continue;
            }
            $fn = (string) ($row['fieldname'] ?? '');
            if ($fn === '' || $fn === 'name') {
                continue;
            }

            $result[$parent][] = [
                'fieldname' => $fn,
                'label' => (string) ($row['label'] ?? ''),
                'fieldtype' => (string) ($row['fieldtype'] ?? 'Input'),
            ];
        }

        foreach (array_keys($childNames) as $name) {
            $result[$name] ??= [];
        }

        return $result;
    }

    private function parseChildEntityName(string $options): string
    {
        $parts = explode(':', $options);
        $name = mb_trim($parts[0]);
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';
        $name = strtolower($name);

        return $name !== '' ? $name : '';
    }

    // ========================================================================
    //  PAYLOAD / DATA NORMALISATION
    // ========================================================================

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

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $fields, array $payload): array
    {
        $row = [];
        if (isset($payload['name'])) {
            $row['name'] = $payload['name'];
        }

        foreach ($fields as $field) {
            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '') {
                continue;
            }

            $fieldtype = (string) ($field['fieldtype'] ?? 'Input');
            $value = $payload[$fieldname] ?? null;

            if ($fieldtype === 'Check') {
                $row[$fieldname] = in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
                continue;
            }

            if (in_array($fieldtype, ['Int', 'Float'], true)) {
                $row[$fieldname] = $value === '' || $value === null ? null : $value;
                continue;
            }

            if (in_array($fieldtype, ['Date', 'Datetime', 'Time'], true)) {
                $row[$fieldname] = $value === '' || $value === null ? null : $value;
                continue;
            }

            if (in_array($fieldtype, ['Table', 'Child Table (JSONB)'], true)) {
                $row[$fieldname] = is_array($value) ? $value : [];
                continue;
            }

            $row[$fieldname] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function applyReadOnlyFields(array $fields, VoltModel $model, array $row, string $existingName): array
    {
        $existing = $model->find($existingName);
        if (! is_array($existing)) {
            return $row;
        }

        foreach ($fields as $field) {
            if ((bool) ($field['read_only'] ?? false) !== true) {
                continue;
            }

            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '' || ! array_key_exists($fieldname, $existing)) {
                continue;
            }

            $row[$fieldname] = $existing[$fieldname];
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $row
     */
    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $row
     */
    private function assertRequiredFields(array $fields, array $row, VoltModel $model, ?string $existingName = null): void
    {
        $existing = null;
        if ($existingName !== null && $existingName !== '') {
            /** @var array<string, mixed>|null $record */
            $record = $model->find($existingName);
            $existing = is_array($record) ? $record : null;
        }

        foreach ($fields as $field) {
            if ((bool) ($field['is_required'] ?? false) !== true) {
                continue;
            }

            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '') {
                continue;
            }

            $value = $row[$fieldname] ?? ($existing[$fieldname] ?? null);
            if (! $this->hasFieldValue($field, $value)) {
                $label = (string) ($field['label'] ?? $fieldname);
                throw new \InvalidArgumentException($label . ' is required.');
            }
        }
    }

    private function hasFieldValue(array $field, mixed $value): bool
    {
        if ((string) ($field['fieldtype'] ?? '') === 'Check') {
            return $value !== null;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return mb_trim((string) ($value ?? '')) !== '';
    }

    private function generateDocumentName(string $entityName): string
    {
        $pattern = mb_trim($this->getAutoname($entityName));
        if ($pattern === '' || $pattern === 'HASH') {
            return bin2hex(random_bytes(16));
        }

        $resolved = strtr($pattern, [
            '.YYYY.' => gmdate('Y'),
            '.YY.' => gmdate('y'),
            '.MM.' => gmdate('m'),
            '.DD.' => gmdate('d'),
        ]);
        $resolved = preg_replace('/([\-\/])\.(#+)/', '$1$2', $resolved) ?? $resolved;

        if (! preg_match('/#+/', $resolved, $matches)) {
            return $resolved;
        }

        $token = $matches[0];
        $sequence = $this->nextSequenceValue(strtolower($this->snake($entityName) . ':' . $resolved));
        $serial = str_pad((string) $sequence, strlen($token), '0', STR_PAD_LEFT);

        return preg_replace('/#+/', $serial, $resolved, 1) ?? $resolved;
    }

    private function nextSequenceValue(string $key): int
    {
        $this->db->transStart();

        $row = $this->db->table('sys_sequence')
            ->where('key', $key)
            ->get()
            ->getRowArray();

        $current = is_array($row) ? (int) ($row['current_value'] ?? 0) : 0;
        $next = $current + 1;

        if (is_array($row)) {
            $this->db->table('sys_sequence')
                ->where('key', $key)
                ->update(['current_value' => $next]);
        } else {
            $this->db->table('sys_sequence')->insert([
                'key' => $key,
                'current_value' => $next,
            ]);
        }

        $this->db->transComplete();

        return $next;
    }

    // ========================================================================
    //  LINK TARGETS & DISPLAY VALUE HYDRATION
    // ========================================================================

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateLinkDisplayValues(array $rows, string $entityName): array
    {
        $linkTargets = $this->getLinkTargets($entityName);
        if ($rows === [] || $linkTargets === []) {
            return $rows;
        }

        $groups = [];
        $fieldValues = [];

        foreach ($linkTargets as $fieldname => $target) {
            $displayField = mb_trim((string) ($target['display_field'] ?? 'name'));
            $targetEntity = mb_trim((string) ($target['entity'] ?? ''));
            if ($fieldname === '' || $displayField === '' || $targetEntity === '') {
                continue;
            }

            $groupKey = $targetEntity;
            $groups[$groupKey]['display_fields'][$displayField] = true;
            $groups[$groupKey]['fields'][$fieldname] = $displayField;

            $names = [];
            foreach ($rows as $row) {
                $value = mb_trim((string) ($row[$fieldname] ?? ''));
                if ($value !== '') {
                    $names[] = $value;
                }
            }
            $fieldValues[$fieldname] = array_values(array_unique($names));
        }

        $displayByName = [];

        foreach ($groups as $targetEntity => $group) {
            $allNames = [];
            foreach ($group['fields'] as $fieldname => $displayField) {
                foreach ($fieldValues[$fieldname] as $name) {
                    $allNames[] = $name;
                }
            }
            $allNames = array_values(array_unique($allNames));
            if ($allNames === []) {
                continue;
            }

            $select = 'name';
            foreach (array_keys($group['display_fields']) as $df) {
                $select .= ', ' . $df;
            }

            $linkedRows = $this->db->table(TableNameResolver::entity($targetEntity))
                ->select($select)
                ->whereIn('name', $allNames)
                ->get()
                ->getResultArray();

            foreach ($group['fields'] as $fieldname => $displayField) {
                $displayByName[$fieldname] ??= [];
                foreach ($linkedRows as $linkedRow) {
                    $displayByName[$fieldname][(string) ($linkedRow['name'] ?? '')] = (string) ($linkedRow[$displayField] ?? '');
                }
            }
        }

        foreach ($rows as &$row) {
            foreach ($linkTargets as $fieldname => $target) {
                $value = mb_trim((string) ($row[$fieldname] ?? ''));
                $row[$fieldname . '__display'] = $displayByName[$fieldname][$value] ?? '';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, array<string, string>>
     */
    private function resolveLinkTargets(string $entityName, array $fields): array
    {
        $targetsByField = [];
        $entityNames = [];

        foreach ($fields as $field) {
            if ((string) ($field['fieldtype'] ?? '') !== 'Link') {
                continue;
            }

            $fieldname = mb_trim((string) ($field['fieldname'] ?? ''));
            $targetEntity = mb_trim((string) ($field['options'] ?? ''));
            if ($fieldname === '' || $targetEntity === '') {
                continue;
            }

            $targetsByField[$fieldname] = $targetEntity;
            $entityNames[] = $targetEntity;
        }

        if ($targetsByField === []) {
            return [];
        }

        $rows = $this->db->table('sys_entity')
            ->select('name, module')
            ->whereIn('name', array_values(array_unique($entityNames)))
            ->get()
            ->getResultArray();

        $targetFields = $this->db->table('sys_entity_field')
            ->select('parent, fieldname, fieldtype, hidden, idx')
            ->whereIn('parent', array_values(array_unique($entityNames)))
            ->orderBy('parent', 'ASC')
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $modulesByEntity = [];
        foreach ($rows as $row) {
            $modulesByEntity[(string) ($row['name'] ?? '')] = (string) ($row['module'] ?? '');
        }

        $fieldsByEntity = [];
        foreach ($targetFields as $row) {
            $entityNameRow = (string) ($row['parent'] ?? '');
            if ($entityNameRow === '') {
                continue;
            }
            $fieldsByEntity[$entityNameRow] ??= [];
            $fieldsByEntity[$entityNameRow][] = [
                'fieldname' => (string) ($row['fieldname'] ?? ''),
                'fieldtype' => (string) ($row['fieldtype'] ?? ''),
                'hidden' => (bool) ($row['hidden'] ?? false),
            ];
        }

        $linkTargets = [];
        foreach ($targetsByField as $fieldname => $targetEntity) {
            $moduleName = $modulesByEntity[$targetEntity] ?? '';
            if ($moduleName === '') {
                continue;
            }

            $entitySlug = $this->snake($targetEntity);
            $displayField = $this->resolveLinkDisplayField($targetEntity, $fieldsByEntity[$targetEntity] ?? []);

            $linkTargets[$fieldname] = [
                'entity' => $targetEntity,
                'module' => $moduleName,
                'display_field' => $displayField,
                'list_url' => site_url($moduleName . '/' . $entitySlug),
                'edit_url_base' => site_url($moduleName . '/' . $entitySlug . '/edit'),
                'data_url' => site_url($moduleName . '/api/' . $entitySlug),
                'load_url_base' => site_url($moduleName . '/api/' . $entitySlug . '/load'),
            ];
        }

        return $linkTargets;
    }

    /**
     * @param array<int, array{fieldname:string,fieldtype:string,hidden:bool}> $fields
     */
    private function resolveLinkDisplayField(string $entityName, array $fields): string
    {
        $entitySnake = $this->snake($entityName);
        $preferred = [
            $entitySnake . '_name',
            'title',
            'label',
            'full_name',
            'display_name',
            'description',
        ];

        $found = array_find($preferred, function (string $fieldname) use ($fields): bool {
            return array_any($fields, function (array $field) use ($fieldname): bool {
                return (bool) ($field['hidden'] ?? false) !== true && (string) ($field['fieldname'] ?? '') === $fieldname;
            });
        });
        if ($found !== null) {
            return $found;
        }

        foreach ($fields as $field) {
            if ((bool) ($field['hidden'] ?? false) === true) {
                continue;
            }
            $fieldtype = (string) ($field['fieldtype'] ?? '');
            if (in_array($fieldtype, ['Data', 'Input', 'Link'], true)) {
                return (string) ($field['fieldname'] ?? 'name');
            }
        }

        return 'name';
    }

    // ========================================================================
    //  RESPONSE HELPERS
    // ========================================================================

    private function respondError(string $message, int $code = 400): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON([
            'status' => 'error',
            'message' => $message,
        ]);
    }

    private function forbiddenHtml(): ResponseInterface
    {
        $this->response->setStatusCode(403);

        return $this->response->setBody('<h1>403 Forbidden</h1><p>Access denied.</p>');
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $allowedFields
     * @return array<string, mixed>
     */
    private function filterAllowedFields(array $payload, array $allowedFields): array
    {
        return array_intersect_key($payload, array_flip($allowedFields));
    }

    // ========================================================================
    //  STRING HELPERS
    // ========================================================================

    private function studly(string $text): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $text)));
    }

    private function snake(string $text): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $text));
    }

    private function titleize(string $text): string
    {
        return ucwords(str_replace('_', ' ', $text));
    }
}
