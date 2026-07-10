<?php

declare(strict_types=1);

namespace Volt\Core\Metadata;

use RuntimeException;

final class ArtifactScaffolder
{
    /**
     * @return array{name:string,label:string,namespace:string,module_path:string}
     */
    public function scaffoldModule(string $moduleName, string $label): array
    {
        $moduleSnake  = $this->snake($moduleName);
        $moduleStudly = $this->studly($moduleName);
        $modulePath   = APPPATH . 'Modules/' . $moduleStudly;
        $namespace    = 'App\\Modules\\' . $moduleStudly;

        $this->ensureDir($modulePath);
        $this->ensureDir($modulePath . '/Config');
        $this->ensureDir($modulePath . '/Controllers');
        $this->ensureDir($modulePath . '/DocTypes');
        $this->ensureDir($modulePath . '/Entities');
        $this->ensureDir($modulePath . '/Models');
        $this->ensureDir($modulePath . '/Views');

        $this->writeFile(
            $modulePath . '/Config/Routes.php',
            $this->buildModuleRoutesFile($moduleStudly, $moduleSnake, [])
        );

        $this->writeIfMissing(
            $modulePath . '/module.json',
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
        $docTypeDir   = APPPATH . 'Modules/' . $moduleStudly . '/DocTypes/' . $entityStudly;
        $moduleDir    = APPPATH . 'Modules/' . $moduleStudly;
        $listUrl      = '/' . $moduleSnake . '/' . $entitySnake;
        $dataUrl      = '/' . $moduleSnake . '/api/' . $entitySnake;
        $createUrl    = '/' . $moduleSnake . '/' . $entitySnake . '/create';
        $editUrl      = '/' . $moduleSnake . '/' . $entitySnake . '/edit';
        $loadUrl      = '/' . $moduleSnake . '/api/' . $entitySnake . '/load';
        $saveUrl      = '/' . $moduleSnake . '/api/' . $entitySnake . '/save';
        $this->ensureDir($docTypeDir);
        $this->ensureDir($moduleDir . '/Controllers');
        $this->ensureDir($moduleDir . '/Models');
        $this->ensureDir($moduleDir . '/Views');

        $jsonPayload = json_encode($compiled, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            throw new RuntimeException('Unable to encode entity JSON artifact.');
        }

        $this->writeFile($docTypeDir . '/' . $entitySnake . '.json', $jsonPayload . "\n");
        $this->writeFile($docTypeDir . '/' . $entityStudly . '.php', $this->buildDocTypeHookClass($moduleStudly, $entityStudly));
        $this->writeFile($docTypeDir . '/' . $entitySnake . '_list.js', $this->buildEntityListScript($entitySnake));
        $this->writeFile($docTypeDir . '/' . $entitySnake . '_form.js', $this->buildEntityFormScript($entitySnake));
        $this->writeFile($moduleDir . '/Models/' . $entityStudly . 'Model.php', $this->buildEntityModel($moduleStudly, $entityStudly, $entitySnake));
        $this->writeFile($moduleDir . '/Controllers/' . $entityStudly . 'Controller.php', $this->buildEntityController($moduleStudly, $entityStudly, $entitySnake, $compiled));
        $this->writeFile($moduleDir . '/Views/' . $entitySnake . '_list.php', $this->buildEntityListView($moduleStudly, $entityStudly, $entitySnake, $compiled, $listUrl, $dataUrl, $createUrl, $editUrl, $moduleSnake));
        $this->writeFile($moduleDir . '/Views/' . $entitySnake . '_form.php', $this->buildEntityFormView($moduleStudly, $entityStudly, $entitySnake, $compiled, $listUrl, $saveUrl, $loadUrl));
        $this->writeFile($moduleDir . '/Config/Routes.php', $this->buildModuleRoutesFile($moduleStudly, $moduleSnake, $this->discoverModuleEntities($moduleDir . '/DocTypes')));

        return [
            'list_url' => $listUrl,
            'data_url' => $dataUrl,
            'create_url' => $createUrl,
        ];
    }

    private function buildDocTypeHookClass(string $moduleStudly, string $entityStudly): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$moduleStudly}\DocTypes\\{$entityStudly};

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

use App\Modules\\{$moduleStudly}\DocTypes\\{$entityStudly}\\{$entityStudly};
use Volt\Core\Models\VoltModel;

final class {$entityStudly}Model extends VoltModel
{
    protected \$table = '{$entitySnake}';
    protected \$primaryKey = 'name';
    protected \$returnType = 'array';
    protected \$useAutoIncrement = false;
    protected \$protectFields = false;
    protected \$allowedFields = [];
    protected \$beforeInsert = ['voltBeforeInsert', 'callBeforeInsert'];
    protected \$afterInsert = ['voltAfterInsert', 'callAfterInsert'];
    protected \$beforeUpdate = ['voltBeforeUpdate', 'callBeforeUpdate'];
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
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($fieldsJson === false) {
            throw new RuntimeException('Unable to encode form fields.');
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Modules\\{$moduleStudly}\Controllers;

use App\Modules\\{$moduleStudly}\Models\\{$entityStudly}Model;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class {$entityStudly}Controller extends Controller
{
    private const PER_PAGE_OPTIONS = [50, 100, 200, 500, 1000, 2500];

    /** @var array<int, array<string, mixed>> */
    private array \$fields = [];
    private {$entityStudly}Model \$model;

    public function initController(\CodeIgniter\HTTP\RequestInterface \$request, \CodeIgniter\HTTP\ResponseInterface \$response, LoggerInterface \$logger)
    {
        parent::initController(\$request, \$response, \$logger);
        helper(['url']);
        \$this->model = new {$entityStudly}Model();
        \$this->fields = json_decode('{$this->escapePhpSingleQuoted($fieldsJson)}', true) ?: [];
    }

    public function index(): string
    {
        return view('{$viewListPath}', [
            'title' => '{$entityStudly} List',
            'dataUrl' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}'),
            'createUrl' => site_url('{$this->snake($moduleStudly)}/{$entitySnake}/create'),
            'editUrlBase' => site_url('{$this->snake($moduleStudly)}/{$entitySnake}/edit'),
            'builderUrl' => site_url('desk/entity-builder?entity={$entitySnake}'),
            'csrfTokenName' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }

    public function create(): string
    {
        return view('{$viewFormPath}', [
            'title' => 'New {$entityStudly}',
            'listUrl' => site_url('{$this->snake($moduleStudly)}/{$entitySnake}'),
            'saveUrl' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}/save'),
            'loadUrlBase' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}/load'),
            'fields' => \$this->fields,
            'recordName' => '',
            'csrfTokenName' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }

    public function edit(string \$name): string
    {
        return view('{$viewFormPath}', [
            'title' => 'Edit {$entityStudly}',
            'listUrl' => site_url('{$this->snake($moduleStudly)}/{$entitySnake}'),
            'saveUrl' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}/save'),
            'loadUrlBase' => site_url('{$this->snake($moduleStudly)}/api/{$entitySnake}/load'),
            'fields' => \$this->fields,
            'recordName' => \$name,
            'csrfTokenName' => csrf_token(),
            'csrfHash' => csrf_hash(),
        ]);
    }

    public function data(): ResponseInterface
    {
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
        \$payload = \$this->request->getJSON(true);
        if (! is_array(\$payload)) {
            \$payload = \$this->request->getPost();
        }

        if (! is_array(\$payload)) {
            return \$this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Invalid payload.',
            ]);
        }

        \$row = \$this->normalizePayload(\$payload);
        \$name = trim((string) (\$row['name'] ?? ''));
        if (\$name === '') {
            return \$this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Name is required.',
            ]);
        }

        try {
            \$exists = is_array(\$this->model->find(\$name));
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
            return \$this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => \$throwable->getMessage(),
            ]);
        }
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

            \$row[\$fieldname] = is_scalar(\$value) || \$value === null ? \$value : json_encode(\$value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return \$row;
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

        $scriptPath = "APPPATH . 'Modules/{$moduleStudly}/DocTypes/{$entityStudly}/{$entitySnake}_list.js'";

        return <<<PHP
<?php

/** @var string \$title */
/** @var string \$dataUrl */
/** @var string \$createUrl */
/** @var string \$editUrlBase */
/** @var string \$builderUrl */
/** @var string \$csrfTokenName */
/** @var string \$csrfHash */
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
            csrfTokenName: <?= esc(json_encode(\$csrfTokenName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            csrfHash: <?= esc(json_encode(\$csrfHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>
        })" x-init="init()" class="mx-auto max-w-7xl p-6">
        <header class="mb-4 flex items-center justify-between border border-zinc-300 bg-white px-4 py-3">
            <div>
                <h1 class="font-semibold"><?= esc(\$title) ?></h1>
                <p class="text-zinc-500">Generated list route: <?= esc('{$listUrl}') ?></p>
            </div>
            <div class="flex gap-2">
                <a href="<?= esc(\$builderUrl) ?>" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Open Builder</a>
                <a href="<?= esc(\$createUrl) ?>" class="inline-flex items-center border border-zinc-900 bg-zinc-950 px-3 py-2 font-semibold text-white hover:bg-zinc-800">Create {$entityStudly}</a>
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
                                    <td class="px-4 py-3" x-text="cellValue(row, column.fieldname)"></td>
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
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($fieldsJson === false) {
            throw new RuntimeException('Unable to encode form fields.');
        }

        $scriptPath = "APPPATH . 'Modules/{$moduleStudly}/DocTypes/{$entityStudly}/{$entitySnake}_form.js'";

        return <<<PHP
<?php

/** @var string \$title */
/** @var string \$listUrl */
/** @var string \$saveUrl */
/** @var string \$loadUrlBase */
/** @var string \$recordName */
/** @var array<int, array<string, mixed>> \$fields */
/** @var string \$csrfTokenName */
/** @var string \$csrfHash */
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
            csrfTokenName: <?= esc(json_encode(\$csrfTokenName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>,
            csrfHash: <?= esc(json_encode(\$csrfHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'attr') ?>
        })" x-init="init()" class="mx-auto max-w-4xl p-6">
        <header class="mb-4 flex items-center justify-between border border-zinc-300 bg-white px-4 py-3">
            <div>
                <h1 class="font-semibold"><?= esc(\$title) ?></h1>
                <p class="text-zinc-500"><?= esc('{$listUrl}') ?></p>
            </div>
            <div class="flex gap-2">
                <a href="<?= esc(\$listUrl) ?>" class="border border-zinc-300 px-3 py-2 hover:bg-zinc-50">Back to List</a>
                <button @click="save()" type="button" class="inline-flex items-center border border-zinc-900 bg-zinc-950 px-3 py-2 font-semibold text-white hover:bg-zinc-800">Save Item</button>
            </div>
        </header>

        <section class="border border-zinc-300 bg-white p-4">
            <div class="grid gap-4 lg:grid-cols-2">
                <template x-for="field in fields" :key="field.fieldname">
                    <label class="block" :class="field.fieldtype === 'Text' || field.fieldtype === 'Code' ? 'lg:col-span-2' : ''">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-500" x-text="field.label"></span>
                        <template x-if="field.fieldtype === 'Check'">
                            <input x-model="form[field.fieldname]" type="checkbox" class="h-4 w-4 border-zinc-400">
                        </template>
                        <template x-if="field.fieldtype === 'Select'">
                            <select x-model="form[field.fieldname]" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500">
                                <option value="">Select</option>
                                <template x-for="option in parseOptions(field.options)" :key="option">
                                    <option :value="option" x-text="option"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="field.fieldtype === 'Text' || field.fieldtype === 'Code'">
                            <textarea x-model="form[field.fieldname]" rows="6" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :placeholder="field.placeholder || ''"></textarea>
                        </template>
                        <template x-if="!['Check', 'Select', 'Text', 'Code'].includes(field.fieldtype)">
                            <input x-model="form[field.fieldname]" :type="inputType(field.fieldtype)" class="w-full border border-zinc-300 px-3 py-2 outline-none focus:border-zinc-500" :placeholder="field.placeholder || ''">
                        </template>
                    </label>
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
        csrfTokenName: boot.csrfTokenName || '',
        csrfHash: boot.csrfHash || '',
        columns: boot.columns || [],
        query: '',
        loading: false,
        rows: [],
        page: 1,
        perPage: 50,
        total: 0,
        totalPages: 1,
        perPageOptions: [50, 100, 200, 500, 1000, 2500],
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

            const response = await fetch(this.deleteUrlBase + '/' + encodeURIComponent(name), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': this.csrfHash,
                },
                body: new URLSearchParams({
                    [this.csrfTokenName]: this.csrfHash,
                }).toString(),
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
                const response = await fetch(this.dataUrl + '?' + params.toString(), {
                    headers: { Accept: 'application/json' },
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
        csrfTokenName: boot.csrfTokenName || '',
        csrfHash: boot.csrfHash || '',
        form: {},
        init() {
            this.fields.forEach((field) => {
                if (field.fieldtype === 'Check') {
                    this.form[field.fieldname] = String(field.default_value || '') === '1';
                    return;
                }

                this.form[field.fieldname] = field.default_value ?? '';
            });

            if (this.recordName) {
                this.load();
            }
        },
        parseOptions(options) {
            return String(options || '')
                .split(/\\n|,/)
                .map((item) => item.trim())
                .filter(Boolean);
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
        async load() {
            const response = await fetch(this.loadUrlBase + '/' + encodeURIComponent(this.recordName), {
                headers: { Accept: 'application/json' },
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') {
                throw new Error(result.message || 'Unable to load record.');
            }

            this.fields.forEach((field) => {
                const value = result.data && Object.prototype.hasOwnProperty.call(result.data, field.fieldname)
                    ? result.data[field.fieldname]
                    : (field.default_value ?? '');
                this.form[field.fieldname] = field.fieldtype === 'Check'
                    ? String(value) === '1' || value === 1 || value === true
                    : value;
            });
        },
        async save() {
            const payload = {};
            this.fields.forEach((field) => {
                const value = this.form[field.fieldname];
                payload[field.fieldname] = field.fieldtype === 'Check' ? (value ? '1' : '0') : value;
            });

            const response = await fetch(this.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfHash,
                },
                body: new URLSearchParams({
                    ...payload,
                    [this.csrfTokenName]: this.csrfHash,
                }).toString(),
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
            $routeLines[] = "\$routes->get('api/{$entity['snake']}/load/(:segment)', '{$entity['studly']}Controller::load/$1');";
            $routeLines[] = "\$routes->post('api/{$entity['snake']}/save', '{$entity['studly']}Controller::save');";
            $routeLines[] = "\$routes->post('api/{$entity['snake']}/delete/(:segment)', '{$entity['studly']}Controller::delete/$1');";
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
    private function discoverModuleEntities(string $docTypesPath): array
    {
        if (! is_dir($docTypesPath)) {
            return [];
        }

        $entries = scandir($docTypesPath);
        if ($entries === false) {
            return [];
        }

        $entities = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (! is_dir($docTypesPath . '/' . $entry)) {
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
            ['fieldname' => 'name', 'label' => 'Name'],
        ];

        $fields = is_array($compiled['fields'] ?? null) ? $compiled['fields'] : [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (($field['hidden'] ?? false) || ($field['fieldtype'] ?? '') === 'Table') {
                continue;
            }

            $custom = is_array($field['f_custom_jsonb'] ?? null) ? $field['f_custom_jsonb'] : [];
            if (($custom['in_list_view'] ?? false) !== true) {
                continue;
            }

            $fieldname = (string) ($field['fieldname'] ?? '');
            if ($fieldname === '') {
                continue;
            }

            $columns[] = [
                'fieldname' => $fieldname,
                'label' => (string) ($field['label'] ?? $this->studly($fieldname)),
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
        $fields = [[
            'fieldname' => 'name',
            'label' => 'Name',
            'fieldtype' => 'Input',
            'options' => '',
            'default_value' => '',
            'placeholder' => '',
            'is_required' => true,
        ]];

        $source = is_array($compiled['fields'] ?? null) ? $compiled['fields'] : [];
        foreach ($source as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (($field['hidden'] ?? false) || ($field['fieldtype'] ?? '') === 'Table') {
                continue;
            }

            $custom = is_array($field['f_custom_jsonb'] ?? null) ? $field['f_custom_jsonb'] : [];
            $fields[] = [
                'fieldname' => (string) ($field['fieldname'] ?? ''),
                'label' => (string) ($field['label'] ?? ''),
                'fieldtype' => (string) ($field['fieldtype'] ?? 'Input'),
                'options' => (string) ($field['options'] ?? ''),
                'default_value' => $custom['default_value'] ?? '',
                'placeholder' => (string) ($custom['placeholder'] ?? ''),
                'is_required' => (bool) ($field['is_required'] ?? false),
            ];
        }

        return array_values(array_filter($fields, static fn (array $field): bool => (string) ($field['fieldname'] ?? '') !== ''));
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
