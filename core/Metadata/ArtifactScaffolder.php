<?php

declare(strict_types=1);

namespace Volt\Core\Metadata;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;

final class ArtifactScaffolder
{
    private const DIR_CONFIG = 'Config';
    private const DIR_CONTROLLERS = 'Controllers';
    private const DIR_ENTITIES = 'Entities';
    private const DIR_MODELS = 'Models';
    private const DIR_VIEWS = 'Views';
    private const FILE_MODULE_JSON = 'module.json';
    private const FILE_ROUTES = 'Routes.php';
    private const FILE_SUFFIX_LIST_SCRIPT = '_list.js';
    private const FILE_SUFFIX_FORM_SCRIPT = '_form.js';
    private const DEFAULT_PER_PAGE_OPTIONS = [50, 100, 200, 500, 1000, 2500];
    private const DEFAULT_SESSION_UID = 'primary';
    private const DEFAULT_SESSION_TITLE = 'Primary';

    /**
     * @return array{name:string,label:string,namespace:string,module_path:string}
     */
    public function scaffoldModule(string $moduleName, string $label): array
    {
        $moduleSnake  = $this->snake($moduleName);
        $moduleStudly = $this->studly($moduleName);
        $modulePath   = $this->modulePath($moduleStudly);
        $namespace    = $this->moduleNamespace($moduleStudly);

        $this->ensureDir($modulePath);
        $this->ensureDir($this->moduleSubPath($moduleStudly, self::DIR_CONFIG));
        $this->ensureDir($this->moduleSubPath($moduleStudly, self::DIR_CONTROLLERS));
        $this->ensureDir($this->moduleSubPath($moduleStudly, self::DIR_ENTITIES));
        $this->ensureDir($this->moduleSubPath($moduleStudly, self::DIR_MODELS));
        $this->ensureDir($this->moduleSubPath($moduleStudly, self::DIR_VIEWS));

        $this->writeFile(
            $this->moduleFilePath($moduleStudly, self::DIR_CONFIG, self::FILE_ROUTES),
            $this->buildModuleRoutesFile($moduleStudly, $moduleSnake, [])
        );

        $this->writeIfMissing(
            $this->moduleFilePath($moduleStudly, '', self::FILE_MODULE_JSON),
            json_encode([
                'name' => $moduleSnake,
                'label' => $label,
                'namespace' => $namespace,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        return [
            'name' => $moduleSnake,
            'label' => $label,
            'namespace' => $namespace,
            'module_path' => 'app/Modules/' . $moduleStudly,
        ];
    }

    /**
     * @param array<string, mixed> $compiled
     *
     * @return array{list_url:string,data_url:string,create_url:string}
     */
    public function scaffoldEntity(string $moduleName, string $entityName, array $compiled): array
    {
        $moduleSnake  = $this->snake($moduleName);
        $moduleStudly = $this->studly($moduleName);
        $entityStudly = $this->studly($entityName);
        $entitySnake  = $this->snake($entityName);
        $entityDir    = $this->entityArtifactDir($moduleStudly, $entityStudly);
        $listUrl      = '/' . $moduleSnake . '/' . $entitySnake;
        $dataUrl      = '/' . $moduleSnake . '/api/' . $entitySnake;
        $createUrl    = '/' . $moduleSnake . '/' . $entitySnake . '/create';
        $editUrl      = '/' . $moduleSnake . '/' . $entitySnake . '/edit';
        $loadUrl      = '/' . $moduleSnake . '/api/' . $entitySnake . '/load';
        $saveUrl      = '/' . $moduleSnake . '/api/' . $entitySnake . '/save';
        // Entity là đơn vị metadata chuẩn của Volt nên artifact được đặt thống nhất dưới thư mục Entities.
        $this->ensureDir($entityDir);
        $this->ensureDir($this->moduleSubPath($moduleStudly, self::DIR_CONTROLLERS));
        $this->ensureDir($this->moduleSubPath($moduleStudly, self::DIR_MODELS));
        $this->ensureDir($this->moduleSubPath($moduleStudly, self::DIR_VIEWS));

        $jsonPayload = json_encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            throw new RuntimeException('Unable to encode entity JSON artifact.');
        }

        $this->writeFile($this->entityArtifactFilePath($moduleStudly, $entityStudly, $entitySnake . '.json'), $jsonPayload . "\n");
        $this->writeFile($this->entityArtifactFilePath($moduleStudly, $entityStudly, $entityStudly . '.php'), $this->buildEntityHookClass($moduleStudly, $entityStudly));
        $this->writeFile($this->entityArtifactFilePath($moduleStudly, $entityStudly, $entitySnake . self::FILE_SUFFIX_LIST_SCRIPT), $this->buildEntityListScript($entitySnake));
        $this->writeFile($this->entityArtifactFilePath($moduleStudly, $entityStudly, $entitySnake . self::FILE_SUFFIX_FORM_SCRIPT), $this->buildEntityFormScript($entitySnake));
        $this->writeFile($this->moduleFilePath($moduleStudly, self::DIR_MODELS, $entityStudly . 'Model.php'), $this->buildEntityModel($moduleStudly, $entityStudly, $entitySnake));
        $this->writeIfMissing(
            $this->moduleFilePath($moduleStudly, self::DIR_CONTROLLERS, 'BaseApiController.php'),
            $this->buildBaseApiController($moduleStudly)
        );
        $this->writeFile($this->moduleFilePath($moduleStudly, self::DIR_CONTROLLERS, $entityStudly . 'Controller.php'), $this->buildEntityController($moduleStudly, $entityStudly, $entitySnake, $compiled));
        $this->writeFile($this->moduleFilePath($moduleStudly, self::DIR_CONTROLLERS, $entityStudly . 'ApiController.php'), $this->buildEntityApiController($moduleStudly, $entityStudly, $entitySnake));
        $this->writeFile($this->moduleFilePath($moduleStudly, self::DIR_VIEWS, $entitySnake . '_list.php'), $this->buildEntityListView($moduleStudly, $entityStudly, $entitySnake, $compiled, $listUrl, $dataUrl, $createUrl, $editUrl, $moduleSnake));
        $this->writeFile($this->moduleFilePath($moduleStudly, self::DIR_VIEWS, $entitySnake . '_form.php'), $this->buildEntityFormView($moduleStudly, $entityStudly, $entitySnake, $compiled, $listUrl, $saveUrl, $loadUrl));
        $this->writeFile(
            $this->moduleFilePath($moduleStudly, self::DIR_CONFIG, self::FILE_ROUTES),
            $this->buildModuleRoutesFile($moduleStudly, $moduleSnake, $this->discoverModuleEntities($this->moduleSubPath($moduleStudly, self::DIR_ENTITIES)))
        );

        return [
            'list_url' => $listUrl,
            'data_url' => $dataUrl,
            'create_url' => $createUrl,
            'rest_api_base' => '/' . $moduleSnake . '/rest/' . $entitySnake,
        ];
    }

    public function removeEntity(string $moduleName, string $entityName): void
    {
        $moduleStudly = $this->studly($moduleName);
        $entityStudly = $this->studly($entityName);
        $entitySnake = $this->snake($entityName);

        $this->removePath($this->entityArtifactDir($moduleStudly, $entityStudly));
        $this->removePath($this->moduleFilePath($moduleStudly, self::DIR_MODELS, $entityStudly . 'Model.php'));
        $this->removePath($this->moduleFilePath($moduleStudly, self::DIR_CONTROLLERS, $entityStudly . 'Controller.php'));
        $this->removePath($this->moduleFilePath($moduleStudly, self::DIR_CONTROLLERS, $entityStudly . 'ApiController.php'));
        $this->removePath($this->moduleFilePath($moduleStudly, self::DIR_VIEWS, $entitySnake . '_list.php'));
        $this->removePath($this->moduleFilePath($moduleStudly, self::DIR_VIEWS, $entitySnake . '_form.php'));

        $this->writeFile(
            $this->moduleFilePath($moduleStudly, self::DIR_CONFIG, self::FILE_ROUTES),
            $this->buildModuleRoutesFile($moduleStudly, $this->snake($moduleName), $this->discoverModuleEntities($this->moduleSubPath($moduleStudly, self::DIR_ENTITIES)))
        );
    }

    private function buildBaseApiController(string $moduleStudly): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$moduleStudly}\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseApiController extends Controller
{
    public function initController(\CodeIgniter\HTTP\RequestInterface \$request, \CodeIgniter\HTTP\ResponseInterface \$response, LoggerInterface \$logger)
    {
        parent::initController(\$request, \$response, \$logger);
    }

    protected function respondSuccess(mixed \$data, int \$code = 200): ResponseInterface
    {
        return \$this->response->setStatusCode(\$code)->setJSON([
            'data' => \$data,
        ]);
    }

    protected function respondError(string \$message, int \$code = 400): ResponseInterface
    {
        return \$this->response->setStatusCode(\$code)->setJSON([
            'status' => 'error',
            'message' => \$message,
        ]);
    }

    protected function respondNotFound(string \$message = 'Record not found.'): ResponseInterface
    {
        return \$this->respondError(\$message, 404);
    }

    protected function respondValidationError(array \$errors): ResponseInterface
    {
        return \$this->response->setStatusCode(422)->setJSON([
            'status' => 'error',
            'message' => 'Validation failed.',
            'errors' => \$errors,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function extractPayload(): ?array
    {
        if (\$this->request->is('json')) {
            try {
                \$payload = \$this->request->getJSON(true);
                return is_array(\$payload) ? \$payload : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> \$payload
     * @param list<string> \$allowedFields
     * @return array<string, mixed>
     */
    protected function filterAllowedFields(array \$payload, array \$allowedFields): array
    {
        return array_intersect_key(\$payload, array_flip(\$allowedFields));
    }
}
PHP;
    }

    private function buildEntityApiController(string $moduleStudly, string $entityStudly, string $entitySnake): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$moduleStudly}\Controllers;

use App\Modules\\{$moduleStudly}\Models\\{$entityStudly}Model;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class {$entityStudly}ApiController extends BaseApiController
{
    private {$entityStudly}Model \$model;

    public function initController(\CodeIgniter\HTTP\RequestInterface \$request, \CodeIgniter\HTTP\ResponseInterface \$response, LoggerInterface \$logger)
    {
        parent::initController(\$request, \$response, \$logger);
        \$this->model = new {$entityStudly}Model();
    }

    public function index(): ResponseInterface
    {
        if (! \$this->model->canRead()) {
            return \$this->respondError('Forbidden', 403);
        }

        \$page = max(1, (int) (\$this->request->getGet('page') ?? 1));
        \$perPage = min(100, max(1, (int) (\$this->request->getGet('per_page') ?? 50)));
        \$query = trim((string) (\$this->request->getGet('q') ?? ''));

        \$builder = \$this->model->builder();

        if (\$query !== '') {
            \$pk = \$this->model->primaryKey;
            \$builder->groupStart();
            \$builder->like(\$pk, \$query);
            foreach (\$this->model->allowedFields as \$field) {
                if (\$field === \$pk) {
                    continue;
                }
                \$builder->orLike(\$field, \$query);
            }
            \$builder->groupEnd();
        }

        \$countBuilder = clone \$builder;
        \$total = (int) \$countBuilder->countAllResults(false);
        \$rows = \$builder
            ->orderBy('modified', 'DESC')
            ->limit(\$perPage, (\$page - 1) * \$perPage)
            ->get()
            ->getResultArray();

        return \$this->response->setJSON([
            'data' => \$rows,
            'meta' => [
                'page'       => \$page,
                'per_page'   => \$perPage,
                'total'      => \$total,
                'total_pages' => max(1, (int) ceil(\$total / \$perPage)),
            ],
        ]);
    }

    public function show(string \$id): ResponseInterface
    {
        \$row = \$this->model->find(\$id);
        if (! is_array(\$row)) {
            return \$this->respondNotFound();
        }

        return \$this->respondSuccess(\$row);
    }

    public function store(): ResponseInterface
    {
        if (! \$this->model->canWrite('create')) {
            return \$this->respondError('Forbidden', 403);
        }

        \$payload = \$this->extractPayload();
        if (! is_array(\$payload)) {
            return \$this->respondError('Invalid JSON payload.', 400);
        }

        \$allowedFields = \$this->model->allowedFields;
        if (\$allowedFields !== []) {
            \$payload = \$this->filterAllowedFields(\$payload, \$allowedFields);
        }

        try {
            \$id = \$this->model->insert(\$payload);
            if (\$id === false) {
                \$errors = \$this->model->errors();
                if (! empty(\$errors)) {
                    return \$this->respondValidationError(\$errors);
                }

                return \$this->respondError('Unable to create record.', 422);
            }

            \$record = \$this->model->find(\$id);

            return \$this->respondSuccess(\$record, 201);
        } catch (Throwable \$throwable) {
            return \$this->respondError(\$throwable->getMessage(), 422);
        }
    }

    public function update(string \$id): ResponseInterface
    {
        \$existing = \$this->model->find(\$id);
        if (! is_array(\$existing)) {
            return \$this->respondNotFound();
        }

        if (! \$this->model->canWrite('write')) {
            return \$this->respondError('Forbidden', 403);
        }

        \$payload = \$this->extractPayload();
        if (! is_array(\$payload)) {
            return \$this->respondError('Invalid JSON payload.', 400);
        }

        \$allowedFields = \$this->model->allowedFields;
        if (\$allowedFields !== []) {
            \$payload = \$this->filterAllowedFields(\$payload, \$allowedFields);
        }
        unset(\$payload[\$this->model->primaryKey]);

        try {
            if (! \$this->model->update(\$id, \$payload)) {
                \$errors = \$this->model->errors();
                if (! empty(\$errors)) {
                    return \$this->respondValidationError(\$errors);
                }

                return \$this->respondError('Unable to update record.', 422);
            }

            \$record = \$this->model->find(\$id);

            return \$this->respondSuccess(\$record);
        } catch (Throwable \$throwable) {
            return \$this->respondError(\$throwable->getMessage(), 422);
        }
    }

    public function destroy(string \$id): ResponseInterface
    {
        \$existing = \$this->model->find(\$id);
        if (! is_array(\$existing)) {
            return \$this->respondNotFound();
        }

        try {
            \$this->model->delete(\$id);

            return \$this->response->setStatusCode(200)->setJSON([
                'status' => 'ok',
            ]);
        } catch (Throwable \$throwable) {
            return \$this->respondError(\$throwable->getMessage(), 422);
        }
    }
}
PHP;
    }

    private function buildEntityHookClass(string $moduleStudly, string $entityStudly): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$moduleStudly}\Entities\\{$entityStudly};

final class {$entityStudly}
{
    /**
     * Hook chạy trước insert.
     *
     * @param array<string, mixed> \$data
     * @return array<string, mixed>
     */
    public function beforeInsert(array \$data): array
    {
        return \$data;
    }

    /**
     * Hook chạy trước cả insert và update.
     *
     * @param array<string, mixed> \$data
     * @return array<string, mixed>
     */
    public function beforeSave(array \$data): array
    {
        return \$data;
    }

    /**
     * Hook validate nghiệp vụ.
     *
     * @param array<string, mixed> \$data
     */
    public function validate(array \$data): void
    {
    }

    /**
     * Hook sau insert.
     *
     * @param array<string, mixed> \$data
     * @param array<string, mixed> \$context
     */
    public function afterInsert(array \$data, array \$context = []): void
    {
    }

    /**
     * Hook sau save cho cả insert và update.
     *
     * @param array<string, mixed> \$data
     * @param array<string, mixed> \$context
     */
    public function afterSave(array \$data, array \$context = []): void
    {
    }

    /**
     * Hook sau update.
     *
     * @param array<string, mixed> \$data
     * @param array<string, mixed> \$context
     */
    public function onUpdate(array \$data, array \$context = []): void
    {
    }
}
PHP;
    }

    private function buildEntityModel(string $moduleStudly, string $entityStudly, string $entitySnake): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$moduleStudly}\Models;

use App\Modules\\{$moduleStudly}\Entities\\{$entityStudly}\\{$entityStudly};
use Volt\Core\Models\VoltModel;

final class {$entityStudly}Model extends VoltModel
{
    protected \$table = '{$this->escapePhpSingleQuoted(TableNameResolver::entity($entitySnake))}';
    protected \$primaryKey = 'name';
    protected \$returnType = 'array';
    protected \$useAutoIncrement = false;
    protected \$protectFields = false;
    protected \$allowedFields = [];
    protected \$beforeInsert = ['callBeforeInsert', 'voltBeforeInsert'];
    protected \$afterInsert = ['voltAfterInsert', 'callAfterInsert'];
    protected \$beforeUpdate = ['callBeforeUpdate', 'voltBeforeUpdate'];
    protected \$afterUpdate = ['voltAfterUpdate', 'callAfterUpdate'];

    private ?{$entityStudly} \$docType = null;

    public function __construct()
    {
        parent::__construct();
        \$this->setEntityName('{$entityStudly}');
    }

    protected function callBeforeInsert(array \$event): array
    {
        \$payload = \$this->extractPayload(\$event);
        \$payload = \$this->docType()->beforeInsert(\$payload);
        \$payload = \$this->docType()->beforeSave(\$payload);
        \$this->docType()->validate(\$payload);
        \$event['data'] = \$payload;

        return \$event;
    }

    protected function callBeforeUpdate(array \$event): array
    {
        \$payload = \$this->extractPayload(\$event);
        \$payload = \$this->docType()->beforeSave(\$payload);
        \$this->docType()->validate(\$payload);
        \$event['data'] = \$payload;

        return \$event;
    }

    protected function callAfterInsert(array \$event): array
    {
        \$payload = \$this->extractPayload(\$event);
        \$this->docType()->afterInsert(\$payload, \$event);
        \$this->docType()->afterSave(\$payload, \$event);

        return \$event;
    }

    protected function callAfterUpdate(array \$event): array
    {
        \$payload = \$this->extractPayload(\$event);
        \$this->docType()->onUpdate(\$payload, \$event);
        \$this->docType()->afterSave(\$payload, \$event);

        return \$event;
    }

    private function docType(): {$entityStudly}
    {
        return \$this->docType ??= new {$entityStudly}();
    }

    /**
     * @param array<string, mixed> \$event
     * @return array<string, mixed>
     */
    private function extractPayload(array \$event): array
    {
        return isset(\$event['data']) && is_array(\$event['data']) ? \$event['data'] : [];
    }
}
PHP;
    }

    /**
     * @param array<string, mixed> $compiled
     */
    private function buildEntityController(string $moduleStudly, string $entityStudly, string $entitySnake, array $compiled): string
    {
        $viewListPath = 'App\\Modules\\' . $moduleStudly . '\\Views\\' . $entitySnake . '_list';
        $viewFormPath = 'App\\Modules\\' . $moduleStudly . '\\Views\\' . $entitySnake . '_form';
        $fields = $this->extractFormFields($compiled);
        $sessions = $this->extractFormSessions($compiled);
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sessionsJson = json_encode($sessions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($fieldsJson === false) {
            throw new RuntimeException('Unable to encode form fields.');
        }
        if ($sessionsJson === false) {
            throw new RuntimeException('Unable to encode form sessions.');
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$moduleStudly}\Controllers;

use App\Modules\\{$moduleStudly}\Models\\{$entityStudly}Model;
use CodeIgniter\Controller;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;

final class {$entityStudly}Controller extends Controller
{
    private const PER_PAGE_OPTIONS = [{$this->implodeIntList(self::DEFAULT_PER_PAGE_OPTIONS)}];
    private const AUTONAME_PATTERN = '{$this->escapePhpSingleQuoted((string) (($compiled['entity']['autoname'] ?? 'HASH')))}';

    /** @var array<int, array<string, mixed>> */
    private array \$fields = [];
    /** @var array<int, array<string, mixed>> */
    private array \$sessions = [];
    /** @var array<string, array<string, string>> */
    private array \$linkTargets = [];
    private {$entityStudly}Model \$model;
    private BaseConnection \$db;

    public function initController(\CodeIgniter\HTTP\RequestInterface \$request, \CodeIgniter\HTTP\ResponseInterface \$response, LoggerInterface \$logger)
    {
        parent::initController(\$request, \$response, \$logger);
        helper(['url']);
        \$this->model = new {$entityStudly}Model();
        \$this->db = VoltDatabase::connection();
        \$this->fields = json_decode('{$this->escapePhpSingleQuoted($fieldsJson)}', true) ?: [];
        \$this->sessions = json_decode('{$this->escapePhpSingleQuoted($sessionsJson)}', true) ?: [];
        \$this->linkTargets = \$this->resolveLinkTargets();
    }

    public function index(): string|\CodeIgniter\HTTP\ResponseInterface
    {
        if (! \$this->model->canRead()) {
            \$this->response->setStatusCode(403);
            return \$this->response->setBody('<h1>403 Forbidden</h1><p>Bạn không có quyền truy cập trang này.</p>');
        }

        return view('{$viewListPath}', [
            'title' => '{$entityStudly} List',
            'dataUrl' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}'),
            'createUrl' => site_url('{$this->snake($moduleStudly)}/{$entitySnake}/create'),
            'editUrlBase' => site_url('{$this->snake($moduleStudly)}/{$entitySnake}/edit'),
            'builderUrl' => site_url('desk/entity-builder?entity={$entitySnake}'),
            'linkTargets' => \$this->linkTargets,
        ]);
    }

    public function create(): string|\CodeIgniter\HTTP\ResponseInterface
    {
        if (! \$this->model->canWrite('create')) {
            \$this->response->setStatusCode(403);
            return \$this->response->setBody('<h1>403 Forbidden</h1><p>Bạn không có quyền truy cập trang này.</p>');
        }

        return view('{$viewFormPath}', [
            'title' => 'New {$entityStudly}',
            'listUrl' => site_url('{$this->snake($moduleStudly)}/{$entitySnake}'),
            'saveUrl' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}/save'),
            'loadUrlBase' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}/load'),
            'fields' => \$this->fields,
            'sessions' => \$this->sessions,
            'linkTargets' => \$this->linkTargets,
            'recordName' => '',
        ]);
    }

    public function edit(string \$name): string|\CodeIgniter\HTTP\ResponseInterface
    {
        if (! \$this->model->canWrite('write')) {
            \$this->response->setStatusCode(403);
            return \$this->response->setBody('<h1>403 Forbidden</h1><p>Bạn không có quyền truy cập trang này.</p>');
        }

        return view('{$viewFormPath}', [
            'title' => 'Edit {$entityStudly}',
            'listUrl' => site_url('{$this->snake($moduleStudly)}/{$entitySnake}'),
            'saveUrl' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}/save'),
            'loadUrlBase' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}/load'),
            'fields' => \$this->fields,
            'sessions' => \$this->sessions,
            'linkTargets' => \$this->linkTargets,
            'recordName' => \$name,
        ]);
    }

    public function data(): ResponseInterface
    {
        if (! \$this->model->canRead()) {
            return \$this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
        }

        \$page = max(1, (int) (\$this->request->getGet('page') ?? 1));
        \$perPage = (int) (\$this->request->getGet('per_page') ?? 50);
        if (! in_array(\$perPage, self::PER_PAGE_OPTIONS, true)) {
            \$perPage = 50;
        }

        \$query = trim((string) (\$this->request->getGet('q') ?? ''));
        \$builder = \$this->model->builder();

        if (\$query !== '') {
            \$builder->groupStart();
            \$builder->like('name', \$query);
            foreach (\$this->fields as \$field) {
                \$fieldname = (string) (\$field['fieldname'] ?? '');
                if (\$fieldname === '' || \$fieldname === 'name') {
                    continue;
                }

                \$builder->orLike(\$fieldname, \$query);
            }
            \$builder->groupEnd();
        }

        \$countBuilder = clone \$builder;
        \$total = (int) \$countBuilder->countAllResults(false);
        \$rows = \$builder
            ->orderBy('modified', 'DESC')
            ->limit(\$perPage, (\$page - 1) * \$perPage)
            ->get()
            ->getResultArray();
        \$rows = \$this->hydrateLinkDisplayValues(\$rows);

        return \$this->response->setJSON([
            'status' => 'ok',
            'rows' => \$rows,
            'pagination' => [
                'page' => \$page,
                'per_page' => \$perPage,
                'total' => \$total,
                'total_pages' => max(1, (int) ceil(\$total / \$perPage)),
                'options' => self::PER_PAGE_OPTIONS,
            ],
        ]);
    }

    public function load(string \$name): ResponseInterface
    {
        \$row = \$this->model->find(\$name);
        if (! is_array(\$row)) {
            return \$this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Record not found.',
            ]);
        }

        return \$this->response->setJSON([
            'status' => 'ok',
            'data' => \$row,
        ]);
    }

    public function save(): ResponseInterface
    {
        \$payload = \$this->extractPayload();

        if (! is_array(\$payload)) {
            return \$this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid payload.',
            ]);
        }

        \$row = \$this->normalizePayload(\$payload);
        \$name = trim((string) (\$row['name'] ?? ''));

        try {
            \$exists = \$name !== '' && is_array(\$this->model->find(\$name));

            if (\$exists && ! \$this->model->canWrite('write')) {
                return \$this->response->setStatusCode(403)->setJSON([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền chỉnh sửa.',
                ]);
            }

            if (! \$exists && ! \$this->model->canWrite('create')) {
                return \$this->response->setStatusCode(403)->setJSON([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền tạo mới.',
                ]);
            }

            if (! \$exists && \$name === '') {
                \$name = \$this->generateDocumentName();
                \$row['name'] = \$name;
            }

            \$row = \$this->applyReadOnlyFields(\$row, \$exists ? \$name : null);
            \$this->assertRequiredFields(\$row, \$exists ? \$name : null);

            if (\$exists) {
                \$this->model->update(\$name, \$row);
            } else {
                \$this->model->insert(\$row);
            }

            return \$this->response->setJSON([
                'status' => 'ok',
                'message' => \$exists ? 'Record updated.' : 'Record created.',
                'data' => [
                    'name' => \$name,
                ],
            ]);
        } catch (Throwable \$throwable) {
            if (\$exists === false && str_contains(\$throwable->getMessage(), 'duplicate key')) {
                for (\$retry = 0; \$retry < 100; \$retry++) {
                    \$row['name'] = \$this->generateDocumentName();
                    try {
                        \$this->model->insert(\$row);
                        return \$this->response->setJSON([
                            'status' => 'ok',
                            'message' => 'Record created.',
                            'data' => [
                                'name' => \$row['name'],
                            ],
                        ]);
                    } catch (Throwable \$retryThrowable) {
                        if (! str_contains(\$retryThrowable->getMessage(), 'duplicate key')) {
                            return \$this->response->setStatusCode(422)->setJSON([
                                'status' => 'error',
                                'message' => \$retryThrowable->getMessage(),
                            ]);
                        }
                    }
                }
            }

            return \$this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Could not generate unique name after retries.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPayload(): ?array
    {
        if (\$this->request->is('json')) {
            \$payload = \$this->request->getJSON(true);
            return is_array(\$payload) ? \$payload : null;
        }

        \$payload = \$this->request->getPost();

        return is_array(\$payload) ? \$payload : null;
    }

    private function generateDocumentName(): string
    {
        \$pattern = trim(self::AUTONAME_PATTERN);
        if (\$pattern === '' || \$pattern === 'HASH') {
            return bin2hex(random_bytes(16));
        }

        \$resolved = strtr(\$pattern, [
            '.YYYY.' => gmdate('Y'),
            '.YY.' => gmdate('y'),
            '.MM.' => gmdate('m'),
            '.DD.' => gmdate('d'),
        ]);
        \$resolved = preg_replace('/([\\-\\/])\\.(#+)/', '$1$2', \$resolved) ?? \$resolved;

        if (! preg_match('/#+/', \$resolved, \$matches)) {
            return \$resolved;
        }

        \$token = \$matches[0];
        \$sequence = \$this->nextSequenceValue(strtolower('{$entitySnake}:' . \$resolved));
        \$serial = str_pad((string) \$sequence, strlen(\$token), '0', STR_PAD_LEFT);

        return preg_replace('/#+/', \$serial, \$resolved, 1) ?? \$resolved;
    }

    private function nextSequenceValue(string \$key): int
    {
        \$this->db->transStart();

        \$row = \$this->db->table('sys_sequence')
            ->where('key', \$key)
            ->get()
            ->getRowArray();

        \$current = is_array(\$row) ? (int) (\$row['current_value'] ?? 0) : 0;
        \$next = \$current + 1;

        if (is_array(\$row)) {
            \$this->db->table('sys_sequence')
                ->where('key', \$key)
                ->update(['current_value' => \$next]);
        } else {
            \$this->db->table('sys_sequence')->insert([
                'key' => \$key,
                'current_value' => \$next,
            ]);
        }

        \$this->db->transComplete();

        return \$next;
    }

    public function delete(string \$name): ResponseInterface
    {
        try {
            \$this->model->delete(\$name);

            return \$this->response->setJSON([
                'status' => 'ok',
                'message' => 'Record deleted.',
            ]);
        } catch (Throwable \$throwable) {
            return \$this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => \$throwable->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> \$payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array \$payload): array
    {
        \$row = [];
        if (isset(\$payload['name'])) {
            \$row['name'] = \$payload['name'];
        }

        foreach (\$this->fields as \$field) {
            \$fieldname = (string) (\$field['fieldname'] ?? '');
            if (\$fieldname === '') {
                continue;
            }

            \$fieldtype = (string) (\$field['fieldtype'] ?? 'Input');
            \$value = \$payload[\$fieldname] ?? null;

            if (\$fieldtype === 'Check') {
                \$row[\$fieldname] = in_array(strtolower((string) \$value), ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
                continue;
            }

            if (in_array(\$fieldtype, ['Int', 'Float'], true)) {
                \$row[\$fieldname] = \$value === '' || \$value === null ? null : \$value;
                continue;
            }

            if (in_array(\$fieldtype, ['Table', 'Child Table (JSONB)'], true)) {
                \$row[\$fieldname] = is_array(\$value) ? \$value : [];
                continue;
            }

            \$row[\$fieldname] = is_scalar(\$value) || \$value === null ? \$value : json_encode(\$value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return \$row;
    }

    /**
     * @param array<string, mixed> \$row
     * @return array<string, mixed>
     */
    private function applyReadOnlyFields(array \$row, ?string \$existingName = null): array
    {
        if (\$existingName === null || \$existingName === '') {
            return \$row;
        }

        \$existing = \$this->model->find(\$existingName);
        if (! is_array(\$existing)) {
            return \$row;
        }

        foreach (\$this->fields as \$field) {
            if ((bool) (\$field['read_only'] ?? false) !== true) {
                continue;
            }

            \$fieldname = (string) (\$field['fieldname'] ?? '');
            if (\$fieldname === '' || ! array_key_exists(\$fieldname, \$existing)) {
                continue;
            }

            \$row[\$fieldname] = \$existing[\$fieldname];
        }

        return \$row;
    }

    /**
     * @param array<string, mixed> \$row
     */
    private function assertRequiredFields(array \$row, ?string \$existingName = null): void
    {
        \$existing = null;
        if (\$existingName !== null && \$existingName !== '') {
            \$existingRecord = \$this->model->find(\$existingName);
            \$existing = is_array(\$existingRecord) ? \$existingRecord : null;
        }

        foreach (\$this->fields as \$field) {
            if ((bool) (\$field['is_required'] ?? false) !== true) {
                continue;
            }

            \$fieldname = (string) (\$field['fieldname'] ?? '');
            if (\$fieldname === '') {
                continue;
            }

            \$value = \$row[\$fieldname] ?? (\$existing[\$fieldname] ?? null);
            if (! \$this->hasFieldValue(\$field, \$value)) {
                \$label = (string) (\$field['label'] ?? \$fieldname);
                throw new \InvalidArgumentException(\$label . ' is required.');
            }
        }
    }

    private function hasFieldValue(array \$field, mixed \$value): bool
    {
        if ((string) (\$field['fieldtype'] ?? '') === 'Check') {
            return \$value !== null;
        }

        if (is_array(\$value)) {
            return \$value !== [];
        }

        return trim((string) (\$value ?? '')) !== '';
    }

    /**
     * @param array<int, array<string, mixed>> \$rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateLinkDisplayValues(array \$rows): array
    {
        if (\$rows === [] || \$this->linkTargets === []) {
            return \$rows;
        }

        // Group Link fields by target entity to batch queries
        \$groups = [];
        \$fieldValues = [];

        foreach (\$this->linkTargets as \$fieldname => \$target) {
            \$displayField = trim((string) (\$target['display_field'] ?? 'name'));
            \$targetEntity = trim((string) (\$target['entity'] ?? ''));
            if (\$fieldname === '' || \$displayField === '' || \$targetEntity === '') {
                continue;
            }

            \$groupKey = \$targetEntity;
            \$groups[\$groupKey]['display_fields'][\$displayField] = true;
            \$groups[\$groupKey]['fields'][\$fieldname] = \$displayField;

            \$names = [];
            foreach (\$rows as \$row) {
                \$value = trim((string) (\$row[\$fieldname] ?? ''));
                if (\$value !== '') {
                    \$names[] = \$value;
                }
            }
            \$fieldValues[\$fieldname] = array_values(array_unique(\$names));
        }

        \$displayByName = [];

        foreach (\$groups as \$targetEntity => \$group) {
            \$allNames = [];
            foreach (\$group['fields'] as \$fieldname => \$displayField) {
                foreach (\$fieldValues[\$fieldname] as \$name) {
                    \$allNames[] = \$name;
                }
            }
            \$allNames = array_values(array_unique(\$allNames));
            if (\$allNames === []) {
                continue;
            }

            \$select = 'name';
            foreach (array_keys(\$group['display_fields']) as \$df) {
                \$select .= ', ' . \$df;
            }

            \$linkedRows = \$this->db->table(TableNameResolver::entity(\$targetEntity))
                ->select(\$select)
                ->whereIn('name', \$allNames)
                ->get()
                ->getResultArray();

            foreach (\$group['fields'] as \$fieldname => \$displayField) {
                \$displayByName[\$fieldname] ??= [];
                foreach (\$linkedRows as \$linkedRow) {
                    \$displayByName[\$fieldname][(string) (\$linkedRow['name'] ?? '')] = (string) (\$linkedRow[\$displayField] ?? '');
                }
            }
        }

        foreach (\$rows as &\$row) {
            foreach (\$this->linkTargets as \$fieldname => \$target) {
                \$value = trim((string) (\$row[\$fieldname] ?? ''));
                \$row[\$fieldname . '__display'] = \$displayByName[\$fieldname][\$value] ?? '';
            }
        }
        unset(\$row);

        return \$rows;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function resolveLinkTargets(): array
    {
        \$targetsByField = [];
        \$entityNames = [];

        foreach (\$this->fields as \$field) {
            if ((string) (\$field['fieldtype'] ?? '') !== 'Link') {
                continue;
            }

            \$fieldname = trim((string) (\$field['fieldname'] ?? ''));
            \$targetEntity = trim((string) (\$field['options'] ?? ''));
            if (\$fieldname === '' || \$targetEntity === '') {
                continue;
            }

            \$targetsByField[\$fieldname] = \$targetEntity;
            \$entityNames[] = \$targetEntity;
        }

        if (\$targetsByField === []) {
            return [];
        }

        \$rows = \$this->db->table('sys_entity')
            ->select('name, module')
            ->whereIn('name', array_values(array_unique(\$entityNames)))
            ->get()
            ->getResultArray();

        \$targetFields = \$this->db->table('sys_entity_field')
            ->select('parent, fieldname, fieldtype, hidden, idx')
            ->whereIn('parent', array_values(array_unique(\$entityNames)))
            ->orderBy('parent', 'ASC')
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        \$modulesByEntity = [];
        foreach (\$rows as \$row) {
            \$modulesByEntity[(string) (\$row['name'] ?? '')] = (string) (\$row['module'] ?? '');
        }

        \$fieldsByEntity = [];
        foreach (\$targetFields as \$row) {
            \$entityName = (string) (\$row['parent'] ?? '');
            if (\$entityName === '') {
                continue;
            }

            \$fieldsByEntity[\$entityName] ??= [];
            \$fieldsByEntity[\$entityName][] = [
                'fieldname' => (string) (\$row['fieldname'] ?? ''),
                'fieldtype' => (string) (\$row['fieldtype'] ?? ''),
                'hidden' => (bool) (\$row['hidden'] ?? false),
            ];
        }

        \$linkTargets = [];
        foreach (\$targetsByField as \$fieldname => \$targetEntity) {
            \$moduleName = \$modulesByEntity[\$targetEntity] ?? '';
            if (\$moduleName === '') {
                continue;
            }

            \$entitySlug = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', trim((string) \$targetEntity)) ?? trim((string) \$targetEntity));
            \$displayField = \$this->resolveLinkDisplayField(\$targetEntity, \$fieldsByEntity[\$targetEntity] ?? []);
            \$linkTargets[\$fieldname] = [
                'entity' => \$targetEntity,
                'module' => \$moduleName,
                'display_field' => \$displayField,
                'list_url' => site_url(\$moduleName . '/' . \$entitySlug),
                'edit_url_base' => site_url(\$moduleName . '/' . \$entitySlug . '/edit'),
                'data_url' => site_url(\$moduleName . '/api/' . \$entitySlug . '/link-options'),
                'load_url_base' => site_url(\$moduleName . '/api/' . \$entitySlug . '/load'),
            ];
        }

        return \$linkTargets;
    }

    /**
     * @param array<int, array{fieldname:string,fieldtype:string,hidden:bool}> \$fields
     */
    private function resolveLinkDisplayField(string \$entityName, array \$fields): string
    {
        \$entitySnake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', trim(\$entityName)) ?? trim(\$entityName));
        \$preferred = [
            \$entitySnake . '_name',
            'title',
            'label',
            'full_name',
            'display_name',
            'description',
        ];

        foreach (\$preferred as \$fieldname) {
            foreach (\$fields as \$field) {
                if ((bool) (\$field['hidden'] ?? false) === true) {
                    continue;
                }

                if ((string) (\$field['fieldname'] ?? '') === \$fieldname) {
                    return \$fieldname;
                }
            }
        }

        foreach (\$fields as \$field) {
            if ((bool) (\$field['hidden'] ?? false) === true) {
                continue;
            }

            \$fieldtype = (string) (\$field['fieldtype'] ?? '');
            if (in_array(\$fieldtype, ['Data', 'Input', 'Link'], true)) {
                return (string) (\$field['fieldname'] ?? 'name');
            }
        }

        return 'name';
    }
}
PHP;
    }

    /**
     * @param array<string, mixed> $compiled
     */
    private function buildEntityListView(string $moduleStudly, string $entityStudly, string $entitySnake, array $compiled, string $listUrl, string $dataUrl, string $createUrl, string $editUrl, string $moduleSnake): string
    {
        $columns = $this->extractListColumns($compiled);
        $columnsJson = json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($columnsJson === false) {
            throw new RuntimeException('Unable to encode list columns.');
        }

        $scriptPath = "APPPATH . 'Modules/{$moduleStudly}/Entities/{$entityStudly}/{$entitySnake}_list.js'";

        return <<<PHP
<?php

/** @var string \$title */
/** @var string \$dataUrl */
/** @var string \$createUrl */
/** @var string \$editUrlBase */
/** @var string \$builderUrl */
/** @var array<string, array<string, string>> \$linkTargets */
\$columns = json_decode('{$this->escapePhpSingleQuoted($columnsJson)}', true) ?: [];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(\$title) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
</head>
<body class="min-h-screen bg-zinc-100 text-base text-zinc-900">
    <div x-data="{$entitySnake}ListApp({
            title: <?= esc(json_encode(\$title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            dataUrl: <?= esc(json_encode(\$dataUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            createUrl: <?= esc(json_encode(\$createUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            editUrlBase: <?= esc(json_encode(\$editUrlBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            deleteUrlBase: <?= esc(json_encode(site_url('{$moduleSnake}/api/{$entitySnake}/delete'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            columns: <?= esc(json_encode(\$columns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            linkTargets: <?= esc(json_encode(\$linkTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>
        })" x-init="init()" class="mx-auto max-w-7xl p-6">
        <header class="mb-4 flex items-center justify-between border border-zinc-300 bg-white px-4 py-3">
            <div>
                <h1 class="font-semibold"><?= esc(\$title) ?></h1>
                <p class="text-zinc-500">Generated list route: <?= esc('{$listUrl}') ?></p>
            </div>
            <div class="flex gap-2">
                <a href="<?= esc(\$builderUrl) ?>" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Open Builder</a>
                <a href="<?= esc(\$createUrl) ?>" class="inline-flex items-center border border-slate-900 bg-slate-900 px-3 py-2 font-semibold text-white hover:bg-slate-800">Create {$entityStudly}</a>
            </div>
        </header>

        <section class="border border-zinc-300 bg-white">
            <div class="flex flex-wrap items-center gap-3 border-b border-zinc-300 px-4 py-3">
                <input x-model="query" @keydown.enter.prevent="load(1)" type="text" placeholder="Filter rows" class="min-w-64 flex-1 border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500">
                <select x-model="perPage" @change="load(1)" class="border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500">
                    <template x-for="option in perPageOptions" :key="option">
                        <option :value="option" x-text="option"></option>
                    </template>
                </select>
                <button @click="load(1)" type="button" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Reload</button>
            </div>

            <div class="overflow-auto">
                <table class="min-w-full border-collapse">
                    <thead class="bg-zinc-50">
                        <tr>
                            <template x-for="column in columns" :key="column.fieldname">
                                <th class="border-b border-zinc-300 px-4 py-3 text-left font-medium" x-text="column.label"></th>
                            </template>
                            <th class="border-b border-zinc-300 px-4 py-3 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="loading">
                            <tr>
                                <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-zinc-500">Loading...</td>
                            </tr>
                        </template>
                        <template x-if="!loading && rows.length === 0">
                            <tr>
                                <td :colspan="columns.length + 1" class="px-4 py-8 text-center text-zinc-500">No rows found.</td>
                            </tr>
                        </template>
                        <template x-for="row in rows" :key="row.name ?? JSON.stringify(row)">
                            <tr class="border-b border-zinc-200">
                                <template x-for="column in columns" :key="column.fieldname">
                                    <td class="px-4 py-3">
                                        <template x-if="isLinkColumn(column) && canOpenLinkedRecord(column, row)">
                                            <button @click="openLinkedRecord(column, row)" type="button" class="text-left text-sky-700 underline" x-text="linkDisplayValue(column, row)"></button>
                                        </template>
                                        <template x-if="!isLinkColumn(column) || !canOpenLinkedRecord(column, row)">
                                            <span x-text="isLinkColumn(column) ? linkDisplayValue(column, row) : cellValue(row, column.fieldname)"></span>
                                        </template>
                                    </td>
                                </template>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2">
                                        <button @click="openEdit(row.name)" type="button" class="border border-zinc-300 px-2 py-1 hover:bg-zinc-50">Edit</button>
                                        <button @click="deleteRow(row.name)" type="button" class="border border-zinc-300 px-2 py-1 hover:bg-zinc-50">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-zinc-300 px-4 py-3">
                <p class="text-zinc-500" x-text="paginationText()"></p>
                <div class="flex gap-2">
                    <button @click="load(page - 1)" :disabled="page <= 1" type="button" class="border border-zinc-300 px-3 py-2 disabled:opacity-40">Prev</button>
                    <button @click="load(page + 1)" :disabled="page >= totalPages" type="button" class="border border-zinc-300 px-3 py-2 disabled:opacity-40">Next</button>
                </div>
            </div>
        </section>
    </div>

    <script><?php readfile({$scriptPath}); ?></script>
</body>
</html>
PHP;
    }

    /**
     * @param array<string, mixed> $compiled
     */
    private function buildEntityFormView(string $moduleStudly, string $entityStudly, string $entitySnake, array $compiled, string $listUrl, string $saveUrl, string $loadUrl): string
    {
        $fields = $this->extractFormFields($compiled);
        $sessions = $this->extractFormSessions($compiled);
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sessionsJson = json_encode($sessions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($fieldsJson === false) {
            throw new RuntimeException('Unable to encode form fields.');
        }
        if ($sessionsJson === false) {
            throw new RuntimeException('Unable to encode form sessions.');
        }

        $scriptPath = "APPPATH . 'Modules/{$moduleStudly}/Entities/{$entityStudly}/{$entitySnake}_form.js'";

        return <<<PHP
<?php

/** @var string \$title */
/** @var string \$listUrl */
/** @var string \$saveUrl */
/** @var string \$loadUrlBase */
/** @var string \$recordName */
/** @var array<int, array<string, mixed>> \$fields */
/** @var array<int, array<string, mixed>> \$sessions */
/** @var array<string, array<string, string>> \$linkTargets */
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(\$title) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/vendor/tailwindcss/tailwind.min.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpinejs/alpine.min.js') ?>"></script>
</head>
<body class="min-h-screen bg-zinc-100 text-base text-zinc-900">
    <div x-data="{$entitySnake}FormApp({
            title: <?= esc(json_encode(\$title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            listUrl: <?= esc(json_encode(\$listUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            saveUrl: <?= esc(json_encode(\$saveUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            loadUrlBase: <?= esc(json_encode(\$loadUrlBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            recordName: <?= esc(json_encode(\$recordName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            fields: <?= esc(json_encode(\$fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            sessions: <?= esc(json_encode(\$sessions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            linkTargets: <?= esc(json_encode(\$linkTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>
        })" x-init="init()" class="mx-auto max-w-4xl p-6">
        <header class="mb-4 flex items-center justify-between border border-zinc-300 bg-white px-4 py-3">
            <div>
                <h1 class="font-semibold"><?= esc(\$title) ?></h1>
                <p class="text-zinc-500"><?= esc('{$listUrl}') ?></p>
            </div>
            <div class="flex gap-2">
                <a href="<?= esc(\$listUrl) ?>" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Back to List</a>
                <button @click="save()" type="button" class="inline-flex items-center border border-slate-900 bg-slate-900 px-3 py-2 font-semibold text-white hover:bg-slate-800">Save Item</button>
            </div>
        </header>

        <section class="border border-zinc-300 bg-white p-4">
            <div class="space-y-6">
                <template x-for="session in sessions" :key="session.uid">
                    <section class="border border-zinc-200 bg-zinc-50/40">
                        <div class="border-b border-zinc-200 px-4 py-3">
                            <h2 class="font-medium" x-text="session.title || 'Session'"></h2>
                            <p x-show="session.description" class="mt-1 text-sm text-zinc-500" x-text="session.description"></p>
                        </div>
                        <div class="p-4">
                            <div class="grid gap-4" :style="sessionGridStyle(session)">
                                <template x-for="columnNumber in sessionColumnNumbers(session)" :key="session.uid + '_' + columnNumber">
                                    <div class="space-y-4">
                                        <template x-for="field in sessionFieldsByColumn(session.uid, columnNumber)" :key="field.fieldname">
                                            <label class="block">
                                                <span class="mb-1 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500">
                                                    <span x-text="field.label"></span>
                                                    <span x-show="field.is_required" x-cloak class="text-red-600">*</span>
                                                    <span x-show="field.read_only" x-cloak class="border border-sky-300 bg-sky-50 px-1.5 py-0.5 text-[10px] tracking-normal text-sky-800">Read only</span>
                                                </span>
                                                <template x-if="field.fieldtype === 'Check'">
                                                    <input x-model="form[field.fieldname]" type="checkbox" class="h-4 w-4 border-zinc-400" :disabled="field.read_only">
                                                </template>
                                                <template x-if="field.fieldtype === 'Select'">
                                                    <select x-model="form[field.fieldname]" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :disabled="field.read_only" :required="field.is_required">
                                                        <option value="">Select</option>
                                                        <template x-for="option in parseOptions(field.options)" :key="option">
                                                            <option :value="option" x-text="option"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <template x-if="field.fieldtype === 'Link'">
                                                    <div class="relative" @click.outside="closeLinkLookup(field.fieldname)">
                                                        <input
                                                            x-model="form[field.fieldname]"
                                                            @focus="openLinkLookup(field)"
                                                            @click="openLinkLookup(field)"
                                                            @input="handleLinkInput(field)"
                                                            @change="handleLinkChange(field)"
                                                            type="text"
                                                            class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500"
                                                            :placeholder="field.placeholder || ''"
                                                            :readonly="field.read_only"
                                                            :required="field.is_required"
                                                            autocomplete="off"
                                                        >
                                                        <div x-show="linkLookupOpen(field.fieldname)" x-cloak class="absolute left-0 top-12 z-20 w-[22rem] max-w-[calc(100vw-3rem)] border border-zinc-300 bg-white shadow-sm">
                                                            <div x-show="linkLookupState(field.fieldname).loading" x-cloak class="border-b border-zinc-200 px-3 py-2 text-sm text-zinc-500">
                                                                Searching...
                                                            </div>
                                                            <div class="max-h-80 overflow-auto">
                                                                <template x-for="item in linkLookupState(field.fieldname).items" :key="item.name">
                                                                    <button @click.prevent="selectLinkLookupItem(field, item)" type="button" class="block w-full border-b border-zinc-100 px-3 py-2 text-left hover:bg-zinc-50">
                                                                        <div class="font-medium text-zinc-900" x-text="linkLookupCodeText(item)"></div>
                                                                        <div x-show="linkLookupPrimaryText(field, item) !== ''" x-cloak class="text-sm text-zinc-500" x-text="linkLookupPrimaryText(field, item)"></div>
                                                                    </button>
                                                                </template>
                                                                <div x-show="!linkLookupState(field.fieldname).loading && linkLookupState(field.fieldname).items.length === 0" x-cloak class="px-3 py-2 text-sm text-zinc-500">
                                                                    No linked record found.
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="field.fieldtype === 'Table' || field.fieldtype === 'Child Table (JSONB)'">
                                                    <div class="w-full" :class="field.read_only ? 'opacity-60 pointer-events-none' : ''">
                                                        <table class="w-full border-collapse border border-zinc-300 text-sm">
                                                            <thead>
                                                                <tr class="bg-zinc-100">
                                                                    <template x-for="col in (field.child_columns || [])" :key="col.fieldname">
                                                                        <th class="border border-zinc-300 px-2 py-1.5 text-left font-medium" x-text="col.label || col.fieldname"></th>
                                                                    </template>
                                                                    <th x-show="!field.read_only" class="border border-zinc-300 px-2 py-1.5 w-10"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <template x-for="(row, rowIdx) in (form[field.fieldname] || [])" :key="rowIdx">
                                                                    <tr>
                                                                        <template x-for="col in (field.child_columns || [])" :key="col.fieldname">
                                                                            <td class="border border-zinc-300 px-2 py-1">
                                                                                <template x-if="col.fieldtype === 'Check'">
                                                                                    <input type="checkbox" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="h-4 w-4 border-zinc-400">
                                                                                </template>
                                                                                <template x-if="col.fieldtype === 'Select'">
                                                                                    <select x-model="form[field.fieldname][rowIdx][col.fieldname]" class="w-full border border-zinc-300 px-1.5 py-1 text-sm">
                                                                                        <option value="">Select</option>
                                                                                        <template x-for="opt in parseOptions(col.options || '')" :key="opt">
                                                                                            <option :value="opt" x-text="opt"></option>
                                                                                        </template>
                                                                                    </select>
                                                                                </template>
                                                                                <template x-if="col.fieldtype === 'Int'">
                                                                                    <input type="number" step="1" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="w-full border border-zinc-300 px-1.5 py-1 text-sm">
                                                                                </template>
                                                                                <template x-if="col.fieldtype === 'Float'">
                                                                                    <input type="number" step="any" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="w-full border border-zinc-300 px-1.5 py-1 text-sm">
                                                                                </template>
                                                                                <template x-if="!['Check', 'Select', 'Int', 'Float'].includes(col.fieldtype)">
                                                                                    <input type="text" x-model="form[field.fieldname][rowIdx][col.fieldname]" class="w-full border border-zinc-300 px-1.5 py-1 text-sm">
                                                                                </template>
                                                                            </td>
                                                                        </template>
                                                                        <td x-show="!field.read_only" class="border border-zinc-300 px-2 py-1 text-center">
                                                                            <button @click="removeChildRow(field.fieldname, rowIdx)" type="button" class="text-red-600 hover:text-red-800 text-xs font-bold" title="Remove row">&times;</button>
                                                                        </td>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                        <button x-show="!field.read_only" @click="addChildRow(field.fieldname)" type="button" class="mt-1 border border-zinc-300 px-2 py-1 text-xs hover:bg-zinc-50">+ Add Row</button>
                                                    </div>
                                                </template>
                                                <template x-if="field.fieldtype === 'Text' || field.fieldtype === 'Code'">
                                                    <textarea x-model="form[field.fieldname]" rows="6" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :placeholder="field.placeholder || ''" :readonly="field.read_only" :required="field.is_required"></textarea>
                                                </template>
                                                <template x-if="!['Check', 'Select', 'Link', 'Text', 'Code', 'Table', 'Child Table (JSONB)'].includes(field.fieldtype)">
                                                    <input x-model="form[field.fieldname]" :type="inputType(field.fieldtype)" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :placeholder="field.placeholder || ''" :readonly="field.read_only" :required="field.is_required">
                                                </template>
                                            </label>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </section>
    </div>

    <script><?php readfile({$scriptPath}); ?></script>
</body>
</html>
PHP;
    }

    private function buildEntityListScript(string $entitySnake): string
    {
        return <<<JS
function {$entitySnake}ListApp(boot) {
    return {
        title: boot.title || '',
        dataUrl: boot.dataUrl || '',
        createUrl: boot.createUrl || '',
        editUrlBase: boot.editUrlBase || '',
        deleteUrlBase: boot.deleteUrlBase || '',
        columns: boot.columns || [],
        linkTargets: boot.linkTargets || {},
        query: '',
        loading: false,
        rows: [],
        page: 1,
        perPage: 50,
        total: 0,
        totalPages: 1,
        perPageOptions: [50, 100, 200, 500, 1000, 2500],
        requestUrl(url) {
            const resolved = new URL(String(url || ''), window.location.origin);
            if (resolved.origin === window.location.origin) {
                return resolved.toString();
            }

            return window.location.origin + resolved.pathname + resolved.search + resolved.hash;
        },
        async init() {
            await this.load(1);
        },
        cellValue(row, fieldname) {
            const value = row && Object.prototype.hasOwnProperty.call(row, fieldname) ? row[fieldname] : '';
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            if (typeof value === 'object') {
                return JSON.stringify(value);
            }

            return value;
        },
        linkDisplayValue(column, row) {
            if (!column || !row) {
                return '-';
            }

            const code = String(row[column.fieldname] || '').trim();
            const display = String(row[column.fieldname + '__display'] || '').trim();
            if (code === '') {
                return '-';
            }

            if (display === '' || display === code) {
                return code;
            }

            return code + ' - ' + display;
        },
        isLinkColumn(column) {
            return String(column?.fieldtype || '') === 'Link';
        },
        linkTarget(column) {
            return this.linkTargets?.[column?.fieldname] || null;
        },
        canOpenLinkedRecord(column, row) {
            const target = this.linkTarget(column);
            const value = row && column ? row[column.fieldname] : '';
            return !!target && String(value || '').trim() !== '';
        },
        openLinkedRecord(column, row) {
            const target = this.linkTarget(column);
            const value = row && column ? row[column.fieldname] : '';
            if (!target || String(value || '').trim() === '') {
                return;
            }

            window.location.href = target.edit_url_base + '/' + encodeURIComponent(String(value).trim());
        },
        paginationText() {
            if (this.total === 0) {
                return '0 rows';
            }

            const start = ((this.page - 1) * this.perPage) + 1;
            const end = Math.min(this.total, this.page * this.perPage);
            return String(start) + '-' + String(end) + ' / ' + String(this.total);
        },
        openEdit(name) {
            if (!name) {
                return;
            }

            window.location.href = this.editUrlBase + '/' + encodeURIComponent(name);
        },
        async deleteRow(name) {
            if (!name || !window.confirm('Delete ' + name + '?')) {
                return;
            }

            const response = await fetch(this.requestUrl(this.deleteUrlBase + '/' + encodeURIComponent(name)), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: '',
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Unable to delete record.');
            }

            await this.load(this.page);
        },
        async load(page = 1) {
            this.loading = true;
            this.page = Math.max(1, page);
            try {
                const params = new URLSearchParams({
                    page: String(this.page),
                    per_page: String(this.perPage),
                    q: this.query || '',
                });
                const response = await fetch(this.requestUrl(this.dataUrl + '?' + params.toString()), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'ok') {
                    throw new Error(result.message || 'Unable to load list.');
                }

                this.rows = Array.isArray(result.rows) ? result.rows : [];
                this.page = Number(result.pagination?.page || this.page);
                this.perPage = Number(result.pagination?.per_page || this.perPage);
                this.total = Number(result.pagination?.total || 0);
                this.totalPages = Number(result.pagination?.total_pages || 1);
                this.perPageOptions = Array.isArray(result.pagination?.options) ? result.pagination.options : this.perPageOptions;
            } catch (error) {
                console.error(error);
                this.rows = [];
                this.total = 0;
                this.totalPages = 1;
            } finally {
                this.loading = false;
            }
        },
    };
}
JS;
    }

    private function buildEntityFormScript(string $entitySnake): string
    {
        return <<<JS
function {$entitySnake}FormApp(boot) {
    return {
        title: boot.title || '',
        listUrl: boot.listUrl || '',
        saveUrl: boot.saveUrl || '',
        loadUrlBase: boot.loadUrlBase || '',
        recordName: boot.recordName || '',
        fields: boot.fields || [],
        sessions: boot.sessions || [],
        linkTargets: boot.linkTargets || {},
        form: {},
        linkLookups: {},
        requestUrl(url) {
            const resolved = new URL(String(url || ''), window.location.origin);
            if (resolved.origin === window.location.origin) {
                return resolved.toString();
            }

            return window.location.origin + resolved.pathname + resolved.search + resolved.hash;
        },
        init() {
            if (!Array.isArray(this.sessions) || this.sessions.length === 0) {
                this.sessions = [{ uid: '{$this->escapePhpSingleQuoted(self::DEFAULT_SESSION_UID)}', title: '{$this->escapePhpSingleQuoted(self::DEFAULT_SESSION_TITLE)}', description: '', column_count: 1 }];
            }

            this.fields.forEach((field) => {
                if (field.fieldtype === 'Check') {
                    this.form[field.fieldname] = String(field.default_value || '') === '1';
                    return;
                }

                if (['Table', 'Child Table (JSONB)'].includes(field.fieldtype)) {
                    this.form[field.fieldname] = [];
                    return;
                }

                this.form[field.fieldname] = field.default_value ?? '';
            });

            if (this.recordName) {
                this.load();
            }
        },
        addChildRow(fieldname) {
            if (!Array.isArray(this.form[fieldname])) {
                this.form[fieldname] = [];
            }

            this.form[fieldname].push({});
        },
        removeChildRow(fieldname, index) {
            if (!Array.isArray(this.form[fieldname])) {
                return;
            }

            this.form[fieldname].splice(index, 1);
        },
        parseOptions(options) {
            return String(options || '')
                .split(/\\n|,/)
                .map((item) => item.trim())
                .filter(Boolean);
        },
        sessionColumnNumbers(session) {
            const count = Math.min(4, Math.max(1, Number(session?.column_count || 1)));
            return Array.from({ length: count }, (_, index) => index + 1);
        },
        sessionGridStyle(session) {
            const count = Math.min(4, Math.max(1, Number(session?.column_count || 1)));
            return 'grid-template-columns: repeat(' + String(count) + ', minmax(0, 1fr));';
        },
        sessionFieldsByColumn(sessionUid, columnNumber) {
            return this.fields.filter((field) => {
                const fieldSession = field.session_uid || this.sessions[0]?.uid || '{$this->escapePhpSingleQuoted(self::DEFAULT_SESSION_UID)}';
                const fieldColumn = Math.min(4, Math.max(1, Number(field.column || 1)));
                return fieldSession === sessionUid && fieldColumn === columnNumber;
            }).sort((a, b) => a.idx - b.idx);
        },
        inputType(fieldType) {
            if (fieldType === 'Int' || fieldType === 'Float') {
                return 'number';
            }

            if (fieldType === 'Date') {
                return 'date';
            }

            return 'text';
        },
        linkTarget(field) {
            return this.linkTargets?.[field?.fieldname] || null;
        },
        linkLookupState(fieldname) {
            if (!this.linkLookups[fieldname]) {
                this.linkLookups[fieldname] = {
                    open: false,
                    loading: false,
                    query: '',
                    items: [],
                };
            }

            return this.linkLookups[fieldname];
        },
        linkLookupOpen(fieldname) {
            return !!this.linkLookupState(fieldname).open;
        },
        closeLinkLookup(fieldname) {
            this.linkLookupState(fieldname).open = false;
        },
        openLinkLookup(field) {
            if (!field || field.read_only) {
                return;
            }

            const state = this.linkLookupState(field.fieldname);
            state.query = String(this.form[field.fieldname] || '').trim();
            state.open = true;
            this.searchLinkLookup(field);
        },
        handleLinkInput(field) {
            if (!field || field.read_only) {
                return;
            }

            const state = this.linkLookupState(field.fieldname);
            state.query = String(this.form[field.fieldname] || '').trim();
            state.open = true;
            this.searchLinkLookup(field);
        },
        async searchLinkLookup(field) {
            const target = this.linkTarget(field);
            if (!field || !target || !target.data_url) {
                return;
            }

            const state = this.linkLookupState(field.fieldname);
            state.loading = true;

            try {
                const params = new URLSearchParams({
                    page: '1',
                    per_page: '50',
                    q: state.query || '',
                });
                const response = await fetch(this.requestUrl(target.data_url + '?' + params.toString()), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'ok') {
                    throw new Error(result.message || 'Unable to load linked records.');
                }

                state.items = Array.isArray(result.rows) ? result.rows.slice(0, 50) : [];
            } catch (error) {
                console.error(error);
                state.items = [];
            } finally {
                state.loading = false;
            }
        },
        selectLinkLookupItem(field, item) {
            if (!field || !item || !item.name) {
                return;
            }

            this.form[field.fieldname] = item.name;
            this.linkLookupState(field.fieldname).query = item.name;
            this.closeLinkLookup(field.fieldname);
            this.handleLinkChange(field);
        },
        linkLookupCodeText(item) {
            return String(item?.name || '');
        },
        linkLookupPrimaryText(field, item) {
            const target = this.linkTarget(field);
            const displayField = String(target?.display_field || 'name');
            const displayValue = item && Object.prototype.hasOwnProperty.call(item, displayField)
                ? item[displayField]
                : '';

            if (displayValue !== null && displayValue !== undefined && String(displayValue).trim() !== '') {
                return String(displayValue);
            }

            return String(item?.name || '');
        },
        async handleLinkChange(field) {
            if (!field || field.fieldtype !== 'Link') {
                return;
            }

            const target = this.linkTarget(field);
            const linkValue = String(this.form[field.fieldname] || '').trim();
            if (!target || linkValue === '') {
                this.applyFetchedValues(field, {});
                return;
            }

            try {
                const response = await fetch(this.requestUrl(target.load_url_base + '/' + encodeURIComponent(linkValue)), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'ok') {
                    throw new Error(result.message || 'Unable to load linked record.');
                }

                this.applyFetchedValues(field, result.data || {});
            } catch (error) {
                console.error(error);
            }
        },
        applyFetchedValues(linkField, linkedRow) {
            const prefix = String(linkField?.fieldname || '') + '.';
            if (prefix === '.') {
                return;
            }

            this.fields.forEach((field) => {
                const fetchFrom = String(field.fetch_from || '').trim();
                if (!fetchFrom.startsWith(prefix)) {
                    return;
                }

                const sourceFieldname = fetchFrom.slice(prefix.length);
                const fetchedValue = linkedRow && Object.prototype.hasOwnProperty.call(linkedRow, sourceFieldname)
                    ? linkedRow[sourceFieldname]
                    : '';
                this.form[field.fieldname] = field.fieldtype === 'Check'
                    ? String(fetchedValue) === '1' || fetchedValue === 1 || fetchedValue === true
                    : fetchedValue;
            });
        },
        async load() {
            const response = await fetch(this.requestUrl(this.loadUrlBase + '/' + encodeURIComponent(this.recordName)), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Unable to load record.');
            }

            this.fields.forEach((field) => {
                const hasData = result.data && Object.prototype.hasOwnProperty.call(result.data, field.fieldname);
                const value = hasData ? result.data[field.fieldname] : null;

                if (field.fieldtype === 'Check') {
                    this.form[field.fieldname] = hasData
                        ? String(value) === '1' || value === 1 || value === true
                        : false;
                } else if (['Table', 'Child Table (JSONB)'].includes(field.fieldtype)) {
                    this.form[field.fieldname] = hasData && Array.isArray(value) ? value : [];
                } else {
                    this.form[field.fieldname] = hasData ? value : (field.default_value ?? '');
                }
            });
        },
        async save() {
            const payload = {};
            this.fields.forEach((field) => {
                const value = this.form[field.fieldname];
                payload[field.fieldname] = value;
            });
            if (this.recordName) {
                payload.name = this.recordName;
            }

            const response = await fetch(this.requestUrl(this.saveUrl), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json; charset=UTF-8',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Unable to save record.');
            }
            window.location.href = this.listUrl;
        },
    };
}
JS;
    }

    /**
     * @param array<int, array{name:string,snake:string,studly:string}> $entities
     */
    private function buildModuleRoutesFile(string $moduleStudly, string $moduleSnake, array $entities): string
    {
        $routeLines = [];
        foreach ($entities as $entity) {
            $routeLines[] = "\$routes->get('{$entity['snake']}', '{$entity['studly']}Controller::index');";
            $routeLines[] = "\$routes->get('{$entity['snake']}/create', '{$entity['studly']}Controller::create');";
            $routeLines[] = "\$routes->get('{$entity['snake']}/edit/(:segment)', '{$entity['studly']}Controller::edit/$1');";
            $routeLines[] = "\$routes->get('api/{$entity['snake']}', '{$entity['studly']}Controller::data');";
            $routeLines[] = "\$routes->get('api/{$entity['snake']}/link-options', '{$entity['studly']}Controller::data');";
            $routeLines[] = "\$routes->get('api/{$entity['snake']}/load/(:segment)', '{$entity['studly']}Controller::load/$1');";
            $routeLines[] = "\$routes->post('api/{$entity['snake']}/save', '{$entity['studly']}Controller::save');";
            $routeLines[] = "\$routes->post('api/{$entity['snake']}/delete/(:segment)', '{$entity['studly']}Controller::delete/$1');";

            // RESTful API routes
            $routeLines[] = "\$routes->get('rest/{$entity['snake']}', '{$entity['studly']}ApiController::index');";
            $routeLines[] = "\$routes->get('rest/{$entity['snake']}/(:segment)', '{$entity['studly']}ApiController::show/$1');";
            $routeLines[] = "\$routes->post('rest/{$entity['snake']}', '{$entity['studly']}ApiController::store');";
            $routeLines[] = "\$routes->put('rest/{$entity['snake']}/(:segment)', '{$entity['studly']}ApiController::update/$1');";
            $routeLines[] = "\$routes->delete('rest/{$entity['snake']}/(:segment)', '{$entity['studly']}ApiController::destroy/$1');";
        }

        $body = implode("\n    ", $routeLines);
        if ($body !== '') {
            $body = "    {$body}\n";
        }

        return <<<PHP
<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection \$routes */
\$routes->group('{$moduleSnake}', ['namespace' => 'App\\Modules\\{$moduleStudly}\\Controllers', 'filter' => 'auth'], static function (RouteCollection \$routes): void {
{$body}});
PHP;
    }

    /**
     * @return array<int, array{name:string,snake:string,studly:string}>
     */
    private function discoverModuleEntities(string $entityArtifactsPath): array
    {
        if (! is_dir($entityArtifactsPath)) {
            return [];
        }

        $entries = scandir($entityArtifactsPath);
        if ($entries === false) {
            return [];
        }

        $entities = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (! is_dir($entityArtifactsPath . '/' . $entry)) {
                continue;
            }

            $entities[] = [
                'name' => $entry,
                'snake' => $this->snake($entry),
                'studly' => $this->studly($entry),
            ];
        }

        usort($entities, static fn (array $left, array $right): int => strcmp($left['snake'], $right['snake']));

        return $entities;
    }

    /**
     * @param array<string, mixed> $compiled
     * @return array<int, array{fieldname:string,label:string}>
     */
    private function extractListColumns(array $compiled): array
    {
        $columns = [
            ['fieldname' => 'name', 'label' => 'Name', 'fieldtype' => 'Data'],
        ];

        $fields = is_array($compiled['fields'] ?? null) ? $compiled['fields'] : [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (($field['hidden'] ?? false) || in_array(($field['fieldtype'] ?? ''), ['Table', 'Child Table (JSONB)'], true)) {
                continue;
            }

            $custom = is_array($field['f_custom_jsonb'] ?? null) ? $field['f_custom_jsonb'] : [];
            if ((($field['in_list_view'] ?? $custom['in_list_view'] ?? false) !== true)) {
                continue;
            }

            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '') {
                continue;
            }

            $columns[] = [
                'fieldname' => $fieldname,
                'label' => (string) ($field['label'] ?? $this->studly($fieldname)),
                'fieldtype' => (string) ($field['fieldtype'] ?? 'Data'),
            ];
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $compiled
     * @return array<int, array<string, mixed>>
     */
    private function extractFormFields(array $compiled): array
    {
        $fields = [];

        $source = is_array($compiled['fields'] ?? null) ? $compiled['fields'] : [];

        // Batch-fetch child columns for all Table fields (avoids N+1)
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
                'is_required' => (bool) ($field['is_required'] ?? false),
                'read_only' => (bool) ($field['read_only'] ?? false),
                'idx' => (int) ($field['idx'] ?? 0),
                'session_uid' => (string) ($field['session_uid'] ?? $custom['session_uid'] ?? self::DEFAULT_SESSION_UID),
                'column' => min(4, max(1, (int) ($field['column'] ?? $custom['column'] ?? 1))),
                'custom_meta' => $custom,
            ];

            // Với Table / Child Table (JSONB) field, nhúng child_columns để UI render grid
            if (in_array($row['fieldtype'], ['Table', 'Child Table (JSONB)'], true)) {
                $childName = $this->parseChildEntityName($row['options']);
                $row['child_columns'] = $childColumnsByEntity[$childName] ?? [];
                $row['column'] = 1;
            }

            $fields[] = $row;
        }

        return array_values(array_filter($fields, static fn (array $field): bool => (string) ($field['fieldname'] ?? '') !== ''));
    }

    /**
     * Batch-fetch child columns for all Table fields in a single query.
     *
     * @param array<int, array<string, mixed>> $source
     * @return array<string, array<int, array{fieldname:string,label:string,fieldtype:string}>>
     */
    private function batchResolveChildColumns(array $source): array
    {
        $childNames = [];
        foreach ($source as $field) {
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

        $db = VoltDatabase::connection();
        $allRows = $db->table('sys_entity_field')
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

    /**
     * @return array<int, array{fieldname:string,label:string,fieldtype:string}>
     */
    private function resolveChildColumns(string $options): array
    {
        $childName = $this->parseChildEntityName($options);
        if ($childName === '') {
            return [];
        }

        try {
            $rows = VoltDatabase::connection()
                ->table('sys_entity_field')
                ->select('fieldname, label, fieldtype, hidden')
                ->where('parent', $childName)
                ->orderBy('idx', 'ASC')
                ->get()
                ->getResultArray();

            return array_values(array_filter(
                array_map(function (array $row): ?array {
                    if ((int) ($row['hidden'] ?? 0) === 1) {
                        return null;
                    }

                    $fn = (string) ($row['fieldname'] ?? '');
                    if ($fn === '' || $fn === 'name') {
                        return null;
                    }

                    return [
                        'fieldname' => $fn,
                        'label' => (string) ($row['label'] ?? ''),
                        'fieldtype' => (string) ($row['fieldtype'] ?? 'Input'),
                    ];
                }, $rows),
                static fn (?array $col): bool => $col !== null
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    private function parseChildEntityName(string $options): string
    {
        $parts = explode(':', $options);
        $name = trim($parts[0]);

        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';

        return $name !== '' ? $name : '';
    }

    /**
     * @param array<string, mixed> $compiled
     * @return array<int, array{uid:string,title:string,description:string,column_count:int}>
     */
    private function extractFormSessions(array $compiled): array
    {
        $entity = is_array($compiled['entity'] ?? null) ? $compiled['entity'] : [];
        $custom = is_array($entity['custom_attributes'] ?? null)
            ? $entity['custom_attributes']
            : (is_array($entity['s_custom_jsonb'] ?? null) ? $entity['s_custom_jsonb'] : []);
        $layout = is_array($custom['layout'] ?? null) ? $custom['layout'] : [];
        $sessions = is_array($layout['sessions'] ?? null) ? $layout['sessions'] : [];

        $result = [];
        foreach ($sessions as $session) {
            if (! is_array($session)) {
                continue;
            }

            $uid = trim((string) ($session['uid'] ?? ''));
            if ($uid === '') {
                continue;
            }

            $result[] = [
                'uid' => $uid,
                'title' => (string) ($session['title'] ?? 'Session'),
                'description' => (string) ($session['description'] ?? ''),
                'column_count' => min(4, max(1, (int) ($session['column_count'] ?? 1))),
            ];
        }

        if ($result === []) {
            $result[] = [
                'uid' => self::DEFAULT_SESSION_UID,
                'title' => self::DEFAULT_SESSION_TITLE,
                'description' => '',
                'column_count' => 1,
            ];
        }

        return $result;
    }

    private function ensureDir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }

    private function writeIfMissing(string $path, string $content): void
    {
        if (is_file($path)) {
            return;
        }

        $this->writeFile($path, $content);
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('Unable to write file: ' . $path);
        }
    }

    private function removePath(string $path): void
    {
        if (is_file($path)) {
            if (! @unlink($path) && file_exists($path)) {
                throw new RuntimeException('Unable to delete file: ' . $path);
            }

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $target = $item->getPathname();

            if ($item->isDir()) {
                if (! @rmdir($target) && is_dir($target)) {
                    throw new RuntimeException('Unable to delete directory: ' . $target);
                }

                continue;
            }

            if (! @unlink($target) && file_exists($target)) {
                throw new RuntimeException('Unable to delete file: ' . $target);
            }
        }

        if (! @rmdir($path) && is_dir($path)) {
            throw new RuntimeException('Unable to delete directory: ' . $path);
        }
    }

    private function moduleNamespace(string $moduleStudly): string
    {
        return 'App\\Modules\\' . $moduleStudly;
    }

    private function modulePath(string $moduleStudly): string
    {
        return APPPATH . 'Modules/' . $moduleStudly;
    }

    private function moduleSubPath(string $moduleStudly, string $subdir): string
    {
        return rtrim($this->modulePath($moduleStudly) . ($subdir !== '' ? '/' . $subdir : ''), '/');
    }

    private function moduleFilePath(string $moduleStudly, string $subdir, string $filename): string
    {
        return $this->moduleSubPath($moduleStudly, $subdir) . '/' . $filename;
    }

    private function entityArtifactDir(string $moduleStudly, string $entityStudly): string
    {
        return $this->moduleSubPath($moduleStudly, self::DIR_ENTITIES) . '/' . $entityStudly;
    }

    private function entityArtifactFilePath(string $moduleStudly, string $entityStudly, string $filename): string
    {
        return $this->entityArtifactDir($moduleStudly, $entityStudly) . '/' . $filename;
    }

    /**
     * @param array<int, int> $values
     */
    private function implodeIntList(array $values): string
    {
        return implode(', ', array_map(static fn (int $value): string => (string) $value, $values));
    }

    private function snake(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        $value = preg_replace('/_+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    private function studly(string $value): string
    {
        $value = $this->snake($value);

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
    }

    private function escapePhpSingleQuoted(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }
}
