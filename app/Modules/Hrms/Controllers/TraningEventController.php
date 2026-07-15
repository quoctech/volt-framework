<?php

declare(strict_types=1);

namespace App\Modules\Hrms\Controllers;

use App\Modules\Hrms\Models\TraningEventModel;
use CodeIgniter\Controller;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;

final class TraningEventController extends Controller
{
    private const PER_PAGE_OPTIONS = [50, 100, 200, 500, 1000, 2500];
    private const AUTONAME_PATTERN = 'TE-.YYYY.-#####';

    /** @var array<int, array<string, mixed>> */
    private array $fields = [];
    /** @var array<int, array<string, mixed>> */
    private array $sessions = [];
    /** @var array<string, array<string, string>> */
    private array $linkTargets = [];
    private TraningEventModel $model;
    private BaseConnection $db;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        helper(['url']);
        $this->model = new TraningEventModel();
        $this->db = VoltDatabase::connection();
        $this->fields = json_decode('[{"fieldname":"title","label":"Title","fieldtype":"Data","options":"","default_value":"","placeholder":"","fetch_from":"","is_required":false,"read_only":false,"idx":1,"session_uid":"fa7b17ac-951f-467c-9dab-5fcc426c32a2","column":1,"custom_meta":[]},{"fieldname":"content","label":"Content","fieldtype":"Data","options":"","default_value":"","placeholder":"","fetch_from":"","is_required":false,"read_only":false,"idx":2,"session_uid":"fa7b17ac-951f-467c-9dab-5fcc426c32a2","column":1,"custom_meta":[]}]', true) ?: [];
        $this->sessions = json_decode('[{"uid":"fa7b17ac-951f-467c-9dab-5fcc426c32a2","title":"Primary","description":"Main fields","column_count":1}]', true) ?: [];
        $this->linkTargets = $this->resolveLinkTargets();
    }

    public function index(): string
    {
        return view('App\Modules\Hrms\Views\traning_event_list', [
            'title' => 'TraningEvent List',
            'dataUrl' => site_url('hrms/api/traning_event'),
            'createUrl' => site_url('hrms/traning_event/create'),
            'editUrlBase' => site_url('hrms/traning_event/edit'),
            'builderUrl' => site_url('desk/entity-builder?entity=traning_event'),
            'linkTargets' => $this->linkTargets,
        ]);
    }

    public function create(): string
    {
        return view('App\Modules\Hrms\Views\traning_event_form', [
            'title' => 'New TraningEvent',
            'listUrl' => site_url('hrms/traning_event'),
            'saveUrl' => site_url('hrms/api/traning_event/save'),
            'loadUrlBase' => site_url('hrms/api/traning_event/load'),
            'fields' => $this->fields,
            'sessions' => $this->sessions,
            'linkTargets' => $this->linkTargets,
            'recordName' => '',
        ]);
    }

    public function edit(string $name): string
    {
        return view('App\Modules\Hrms\Views\traning_event_form', [
            'title' => 'Edit TraningEvent',
            'listUrl' => site_url('hrms/traning_event'),
            'saveUrl' => site_url('hrms/api/traning_event/save'),
            'loadUrlBase' => site_url('hrms/api/traning_event/load'),
            'fields' => $this->fields,
            'sessions' => $this->sessions,
            'linkTargets' => $this->linkTargets,
            'recordName' => $name,
        ]);
    }

    public function data(): ResponseInterface
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 50);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 50;
        }

        $query = trim((string) ($this->request->getGet('q') ?? ''));
        $builder = $this->model->builder();

        if ($query !== '') {
            $builder->groupStart();
            $builder->like('name', $query);
            foreach ($this->fields as $field) {
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
        $rows = $this->hydrateLinkDisplayValues($rows);

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

    public function load(string $name): ResponseInterface
    {
        $row = $this->model->find($name);
        if (! is_array($row)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => $row,
        ]);
    }

    public function save(): ResponseInterface
    {
        $payload = $this->extractPayload();

        if (! is_array($payload)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid payload.',
            ]);
        }

        $row = $this->normalizePayload($payload);
        $name = trim((string) ($row['name'] ?? ''));

        try {
            $exists = $name !== '' && is_array($this->model->find($name));
            if (! $exists && $name === '') {
                $name = $this->generateDocumentName();
                $row['name'] = $name;
            }

            $row = $this->applyReadOnlyFields($row, $exists ? $name : null);
            $this->assertRequiredFields($row, $exists ? $name : null);

            if ($exists) {
                $this->model->update($name, $row);
            } else {
                $this->model->insert($row);
            }

            return $this->response->setJSON([
                'status' => 'ok',
                'message' => $exists ? 'Record updated.' : 'Record created.',
                'data' => [
                    'name' => $name,
                ],
            ]);
        } catch (Throwable $throwable) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
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

    private function generateDocumentName(): string
    {
        $pattern = trim(self::AUTONAME_PATTERN);
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
        $sequence = $this->nextSequenceValue(strtolower('traning_event:' . $resolved));
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

    public function delete(string $name): ResponseInterface
    {
        try {
            $this->model->delete($name);

            return $this->response->setJSON([
                'status' => 'ok',
                'message' => 'Record deleted.',
            ]);
        } catch (Throwable $throwable) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $row = [];
        if (isset($payload['name'])) {
            $row['name'] = $payload['name'];
        }

        foreach ($this->fields as $field) {
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

            if ($fieldtype === 'Table') {
                $row[$fieldname] = is_array($value) ? $value : [];
                continue;
            }

            $row[$fieldname] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function applyReadOnlyFields(array $row, ?string $existingName = null): array
    {
        if ($existingName === null || $existingName === '') {
            return $row;
        }

        $existing = $this->model->find($existingName);
        if (! is_array($existing)) {
            return $row;
        }

        foreach ($this->fields as $field) {
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
     * @param array<string, mixed> $row
     */
    private function assertRequiredFields(array $row, ?string $existingName = null): void
    {
        $existing = null;
        if ($existingName !== null && $existingName !== '') {
            $existingRecord = $this->model->find($existingName);
            $existing = is_array($existingRecord) ? $existingRecord : null;
        }

        foreach ($this->fields as $field) {
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

        return trim((string) ($value ?? '')) !== '';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateLinkDisplayValues(array $rows): array
    {
        if ($rows === [] || $this->linkTargets === []) {
            return $rows;
        }

        // Group Link fields by target entity to batch queries
        $groups = [];
        $fieldValues = [];

        foreach ($this->linkTargets as $fieldname => $target) {
            $displayField = trim((string) ($target['display_field'] ?? 'name'));
            $targetEntity = trim((string) ($target['entity'] ?? ''));
            if ($fieldname === '' || $displayField === '' || $targetEntity === '') {
                continue;
            }

            $groupKey = $targetEntity;
            $groups[$groupKey]['display_fields'][$displayField] = true;
            $groups[$groupKey]['fields'][$fieldname] = $displayField;

            $names = [];
            foreach ($rows as $row) {
                $value = trim((string) ($row[$fieldname] ?? ''));
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
            foreach ($this->linkTargets as $fieldname => $target) {
                $value = trim((string) ($row[$fieldname] ?? ''));
                $row[$fieldname . '__display'] = $displayByName[$fieldname][$value] ?? '';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function resolveLinkTargets(): array
    {
        $targetsByField = [];
        $entityNames = [];

        foreach ($this->fields as $field) {
            if ((string) ($field['fieldtype'] ?? '') !== 'Link') {
                continue;
            }

            $fieldname = trim((string) ($field['fieldname'] ?? ''));
            $targetEntity = trim((string) ($field['options'] ?? ''));
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
            $entityName = (string) ($row['parent'] ?? '');
            if ($entityName === '') {
                continue;
            }

            $fieldsByEntity[$entityName] ??= [];
            $fieldsByEntity[$entityName][] = [
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

            $entitySlug = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', trim((string) $targetEntity)) ?? trim((string) $targetEntity));
            $displayField = $this->resolveLinkDisplayField($targetEntity, $fieldsByEntity[$targetEntity] ?? []);
            $linkTargets[$fieldname] = [
                'entity' => $targetEntity,
                'module' => $moduleName,
                'display_field' => $displayField,
                'list_url' => site_url($moduleName . '/' . $entitySlug),
                'edit_url_base' => site_url($moduleName . '/' . $entitySlug . '/edit'),
                'data_url' => site_url($moduleName . '/api/' . $entitySlug . '/link-options'),
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
        $entitySnake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', trim($entityName)) ?? trim($entityName));
        $preferred = [
            $entitySnake . '_name',
            'title',
            'label',
            'full_name',
            'display_name',
            'description',
        ];

        foreach ($preferred as $fieldname) {
            foreach ($fields as $field) {
                if ((bool) ($field['hidden'] ?? false) === true) {
                    continue;
                }

                if ((string) ($field['fieldname'] ?? '') === $fieldname) {
                    return $fieldname;
                }
            }
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
}