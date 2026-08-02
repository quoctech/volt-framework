<?php

declare(strict_types=1);

namespace Volt\Core\Workspace\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Config\Lang\LangService;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Metadata\EntityBuilderService;
use Volt\Core\Workspace\Models\WorkspaceBlockModel;
use Volt\Core\Workspace\Models\WorkspaceModel;

class WorkspaceController extends Controller
{
    private WorkspaceModel $workspaceModel;

    private WorkspaceBlockModel $blockModel;

    private EntityBuilderService $builderService;

    /** @var list<array{name:string,label:string,module:string,istable:bool}>|null */
    private ?array $entityOptionsCache = null;

    /** @var array<string, array<int, array{fieldname:string,label:string,fieldtype:string}>>|null */
    private ?array $fieldCatalogCache = null;

    /** @var array<string, bool> */
    private array $readableCache = [];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->workspaceModel  = new WorkspaceModel();
        $this->blockModel      = new WorkspaceBlockModel();
        $this->builderService  = new EntityBuilderService();
    }

    // ========================================================================
    //  PAGE
    // ========================================================================

    public function index(): string
    {
        $actor = service('voltAuth')->currentUser();
        if ($actor === null) {
            return redirect()->to(site_url('login'));
        }

        $workspace = $this->workspaceModel->getOrCreateForUser($actor);
        $blocks    = $this->blockModel->listForWorkspace((int) $workspace['id']);

        $data = [
            'pageTitle'       => $this->t('workspace.title', 'Workspace') . ' · Volt Desk',
            'workspace'       => $workspace,
            'blocks'          => $this->resolveBlocks($blocks),
            'availableEntities' => $this->entityOptions(),
            'pages'           => $this->quickPickPages($actor),
            'deskActive'      => 'desk',
            'currentUserName' => (string) ($actor->name ?? ''),
            'isAdmin'         => $actor->isAdmin(),
        ];

        $content = view('Volt\\Core\\Workspace\\Views\\workspace', $data);

        return view('Volt\\Core\\Metadata\\Views\\layouts\\desk', [
            'pageTitle'       => $data['pageTitle'],
            'currentUserName' => $data['currentUserName'],
            'isAdmin'         => $data['isAdmin'],
            'deskActive'      => $data['deskActive'],
            'content'         => $content,
        ]);
    }

    // ========================================================================
    //  API
    // ========================================================================

    public function load(): ResponseInterface
    {
        [$workspaceId, $workspace] = $this->requireWorkspace();

        $blocks = $this->resolveBlocks($this->blockModel->listForWorkspace($workspaceId));

        return $this->response->setJSON([
            'status'      => 'ok',
            'workspace'   => $workspace,
            'blocks'      => $blocks,
            'entities'    => $this->builderService->listEntityOptions(),
        ]);
    }

    public function saveBlock(): ResponseInterface
    {
        [$workspaceId] = $this->requireWorkspace();

        $json = $this->request->getJSON(true);
        $payload = is_array($json) ? $json : [];

        try {
            $block = $this->blockModel->upsert(
                $workspaceId,
                (int) ($payload['id'] ?? 0),
                $payload
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'block'  => $this->resolveBlockData($block),
        ]);
    }

    public function deleteBlock(): ResponseInterface
    {
        [$workspaceId] = $this->requireWorkspace();

        $json = $this->request->getJSON(true);
        $blockId = (int) (is_array($json) ? ($json['id'] ?? 0) : 0);

        if ($blockId <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'workspace.invalid_block_id',
            ]);
        }

        $this->blockModel->delete($workspaceId, $blockId);

        return $this->response->setJSON(['status' => 'ok']);
    }

    public function reorderBlocks(): ResponseInterface
    {
        [$workspaceId] = $this->requireWorkspace();

        $json = $this->request->getJSON(true);
        $ids = is_array($json) ? ($json['ids'] ?? []) : [];
        $ids = is_array($ids) ? $ids : [];

        $this->blockModel->reorder($workspaceId, $ids);

        return $this->response->setJSON(['status' => 'ok']);
    }

    public function save(): ResponseInterface
    {
        [$workspaceId] = $this->requireWorkspace();

        $json = $this->request->getJSON(true);
        $payload = is_array($json) ? $json : [];

        if (array_key_exists('columns', $payload)) {
            $this->workspaceModel->updateColumns($workspaceId, (int) $payload['columns']);
        }

        if (array_key_exists('title', $payload)) {
            $this->workspaceModel->updateTitle($workspaceId, (string) $payload['title']);
        }

        return $this->response->setJSON(['status' => 'ok']);
    }

    /**
     * Core pages available for shortcut quick-pick.
     *
     * @return list<array{label:string,url:string}>
     */
    private function quickPickPages(UserEntity $actor): array
    {
        $t = fn (string $key, string $fallback): string => $this->t($key, $fallback);

        $pages = [
            ['label' => $t('workspace.quickpick_desk', 'Desk'), 'url' => '/desk'],
            ['label' => $t('workspace.quickpick_entities', 'Entities'), 'url' => '/desk/entities'],
        ];

        if ($actor->isAdmin()) {
            $pages[] = ['label' => $t('workspace.quickpick_entity_builder', 'Entity Builder'), 'url' => '/desk/entity-builder'];
            $pages[] = ['label' => $t('workspace.quickpick_create_module', 'Create Module'), 'url' => '/desk/create-module'];
            $pages[] = ['label' => $t('workspace.quickpick_pages', 'Pages'), 'url' => '/desk/pages'];
            $pages[] = ['label' => $t('workspace.quickpick_users', 'Users'), 'url' => '/desk/users'];
            $pages[] = ['label' => $t('workspace.quickpick_roles', 'Roles'), 'url' => '/desk/roles'];
            $pages[] = ['label' => $t('workspace.quickpick_tenants', 'Tenants'), 'url' => '/desk/tenants'];
            $pages[] = ['label' => $t('workspace.quickpick_reports', 'Reports'), 'url' => '/desk/reports'];
            $pages[] = ['label' => $t('workspace.quickpick_system_status', 'System Status'), 'url' => '/desk/system-status'];
            $pages[] = ['label' => $t('workspace.quickpick_system_settings', 'System Settings'), 'url' => '/desk/system-settings'];
        }

        return $pages;
    }

    // ========================================================================
    //  HELPERS
    // ========================================================================

    /**
     * @return array{0:int, 1:array<string, mixed>}
     */
    private function requireWorkspace(): array
    {
        $actor = service('voltAuth')->currentUser();

        $workspace = $actor !== null
            ? $this->workspaceModel->getOrCreateForUser($actor)
            : ['id' => 0];

        return [(int) ($workspace['id'] ?? 0), $workspace];
    }

    /**
     * Resolve entity_list / count blocks with live data.
     *
     * Queries are batched: one query per distinct (entity, limit) for lists and
     * one per distinct entity for counts, instead of one query per block.
     *
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    private function resolveBlocks(array $blocks): array
    {
        $listGroups = [];
        $countGroups = [];

        foreach ($blocks as $i => $block) {
            $blockType = (string) ($block['block_type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $entityName = (string) ($data['entity'] ?? '');
            if ($entityName === '') {
                continue;
            }

            if ($blockType === 'entity_list') {
                $limit = min(5, max(1, (int) ($data['max_rows'] ?? 5)));
                $listGroups[$entityName . '|' . $limit][] = $i;
            } elseif ($blockType === 'count') {
                $countGroups[$entityName][] = $i;
            }
        }

        foreach ($countGroups as $entityName => $indices) {
            $count = $this->resolveCountValue($entityName);
            foreach ($indices as $i) {
                $blocks[$i]['count'] = $count;
                $blocks[$i]['record_url'] = $this->resolveEntityUrlFor($entityName);
            }
        }

        foreach ($listGroups as $key => $indices) {
            [$entityName, $limit] = explode('|', $key, 2);
            $records = $this->resolveListRecords($entityName, (int) $limit);
            foreach ($indices as $i) {
                $blocks[$i]['records'] = $records;
                $blocks[$i]['record_url'] = $this->resolveEntityUrlFor($entityName);
            }
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function resolveBlockData(array $block): array
    {
        $blockType = (string) ($block['block_type'] ?? '');
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        if ($blockType === 'entity_list') {
            $block['records'] = $this->resolveEntityRows($data, true);
            $block['record_url'] = $this->resolveEntityUrl($data);
        }

        if ($blockType === 'count') {
            $block['count'] = $this->resolveEntityRows($data, false);
            $block['record_url'] = $this->resolveEntityUrl($data);
        }

        return $block;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolveEntityRows(array $data, bool $asList): array
    {
        $entityName = (string) ($data['entity'] ?? '');
        if ($entityName === '') {
            return $asList ? $this->emptyListResult() : $this->emptyCountResult();
        }

        return $asList
            ? $this->resolveListRecords($entityName, min(5, max(1, (int) ($data['max_rows'] ?? 5))))
            : $this->resolveCountValue($entityName);
    }

    /**
     * @return array{ok:bool, value:int|null}
     */
    private function resolveCountValue(string $entityName): array
    {
        if (! $this->entityReadable($entityName)) {
            return $this->emptyCountResult();
        }

        $table = TableNameResolver::entity($entityName);

        try {
            $builder = VoltDatabase::connection()->table($table);
            $this->applyDeletedFilter($builder, $table);
            $row = $builder->selectCount('*', 'total')->get()->getRowArray();

            return ['ok' => true, 'value' => (int) ($row['total'] ?? 0)];
        } catch (\Throwable $throwable) {
            $this->logResolveError($throwable, $entityName);

            return $this->emptyCountResult();
        }
    }

    /**
     * @return array{ok:bool, columns:list<array{name:string,label:string}>, rows:list<array<string, mixed>>}
     */
    private function resolveListRecords(string $entityName, int $limit): array
    {
        if (! $this->entityReadable($entityName)) {
            return $this->emptyListResult();
        }

        $table = TableNameResolver::entity($entityName);

        try {
            $fields = $this->pickListFields($entityName);
            $fieldNames = array_column($fields, 'name');

            $builder = VoltDatabase::connection()->table($table);
            $this->applyDeletedFilter($builder, $table);

            $rows = $builder
                ->select(implode(', ', array_map(static fn (string $f): string => '"' . $f . '"', $fieldNames)))
                ->orderBy('modified', 'DESC')
                ->limit($limit)
                ->get()
                ->getResultArray();

            return [
                'ok'      => true,
                'columns' => array_map(static fn (array $f): array => ['name' => $f['name'], 'label' => $f['label']], $fields),
                'rows'    => is_array($rows) ? $rows : [],
            ];
        } catch (\Throwable $throwable) {
            $this->logResolveError($throwable, $entityName);

            return $this->emptyListResult();
        }
    }

    /**
     * @return array{ok:bool, value:null}
     */
    private function emptyCountResult(): array
    {
        return ['ok' => false, 'value' => null];
    }

    /**
     * @return array{ok:bool, columns:list<mixed>, rows:list<mixed>}
     */
    private function emptyListResult(): array
    {
        return ['ok' => false, 'columns' => [], 'rows' => []];
    }

    private function logResolveError(\Throwable $throwable, string $entityName): void
    {
        service('voltErrorLog')->logException($throwable, ['entity' => $entityName], 'workspace', 'workspace_entity_resolve_failed');
    }

    private function entityReadable(string $entityName): bool
    {
        if (! array_key_exists($entityName, $this->readableCache)) {
            try {
                $this->readableCache[$entityName] = service('voltPermissionResolver')->can($entityName, 'read');
            } catch (\Throwable) {
                $this->readableCache[$entityName] = false;
            }
        }

        return $this->readableCache[$entityName];
    }

    /**
     * @return list<array{name:string,label:string}>
     */
    private function pickListFields(string $entityName): array
    {
        $catalog = $this->fieldCatalog();
        $source = $catalog[$entityName] ?? [];

        $fields = [['name' => 'name', 'label' => $this->t('workspace.name_label', 'Name')]];
        $skip = ['name', 'docstatus', 'owner', 'creation', 'modified', 'workflow_state', 'amended_from'];
        $simpleTypes = ['Data', 'Text', 'Small Text', 'Text Editor', 'Int', 'Float', 'Currency', 'Date', 'Datetime', 'Select', 'Link', 'Dynamic Link'];

        foreach ($source as $field) {
            if (count($fields) >= 3) {
                break;
            }

            $fieldname = (string) ($field['fieldname'] ?? '');
            $fieldtype = (string) ($field['fieldtype'] ?? '');
            $label = (string) ($field['label'] ?? '');

            if ($fieldname === '' || in_array($fieldname, $skip, true)) {
                continue;
            }
            if (! in_array($fieldtype, $simpleTypes, true)) {
                continue;
            }

            $fields[] = ['name' => $fieldname, 'label' => $label !== '' ? $label : ucfirst($fieldname)];
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveEntityUrl(array $data): string
    {
        return $this->resolveEntityUrlFor((string) ($data['entity'] ?? ''));
    }

    private function resolveEntityUrlFor(string $entityName): string
    {
        if ($entityName === '') {
            return '';
        }

        foreach ($this->entityOptions() as $option) {
            if (($option['name'] ?? '') === $entityName) {
                $module = (string) ($option['module'] ?? '');
                if ($module !== '') {
                    return site_url($module . '/' . $entityName);
                }
            }
        }

        return site_url('desk/entities');
    }

    /**
     * Translate a key with an English fallback when the key is missing.
     */
    private function t(string $key, string $fallback): string
    {
        $value = LangService::get($key, [], LangService::getLang());

        return $value === $key ? $fallback : $value;
    }

    /**
     * Entity options memoized per request.
     *
     * @return list<array{name:string,label:string,module:string,istable:bool}>
     */
    private function entityOptions(): array
    {
        if ($this->entityOptionsCache === null) {
            $this->entityOptionsCache = $this->builderService->listEntityOptions();
        }

        return $this->entityOptionsCache;
    }

    /**
     * Field catalog memoized per request.
     *
     * @return array<string, array<int, array{fieldname:string,label:string,fieldtype:string}>>
     */
    private function fieldCatalog(): array
    {
        if ($this->fieldCatalogCache === null) {
            $this->fieldCatalogCache = $this->builderService->listEntityFieldCatalog();
        }

        return $this->fieldCatalogCache;
    }

    /**
     * Loại bản ghi đã xóa mềm (nếu bảng có cột deleted_at).
     *
     * @param \CodeIgniter\Database\BaseBuilder $builder
     */
    private function applyDeletedFilter($builder, string $table): void
    {
        if (! VoltDatabase::connection()->fieldExists('deleted_at', $table)) {
            return;
        }

        $builder->where($table . '.deleted_at', null);
    }
}
