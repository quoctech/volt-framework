<?php

declare(strict_types=1);

namespace Volt\Core\Metadata\Services;

use InvalidArgumentException;
use Volt\Core\AwesomeBar\Models\AwesomeBarModel;
use Volt\Core\Metadata\Models\PageModel;

class PageService
{
    private const PAGES_DIR = 'Pages';
    private const PAGE_ROUTES_FILE = APPPATH . 'Config/PageRoutes.php';

    /** @var list<string> */
    private const RESERVED_ROUTES = [
        'health', 'ping', 'login', 'logout', 'setup', 'desk', 'api',
    ];

    private readonly PageModel $pageModel;

    public function __construct(?PageModel $pageModel = null)
    {
        $this->pageModel = $pageModel ?? new PageModel();
    }

    public function getAll(): array
    {
        return $this->pageModel->getAll();
    }

    public function getByName(string $name): ?array
    {
        return $this->pageModel->getByName($name);
    }

    public function getByRoute(string $route): ?array
    {
        return $this->pageModel->getByRoute($route);
    }

    public function getModules(): array
    {
        $modulesDir = APPPATH . 'Modules';
        $modules = [];

        if (! is_dir($modulesDir)) {
            return $modules;
        }

        $items = scandir($modulesDir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $modulesDir . '/' . $item;

            if (! is_dir($path)) {
                continue;
            }

            $modules[] = [
                'name'  => $item,
                'label' => $item,
            ];
        }

        usort($modules, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $modules;
    }

    public function getRoles(): array
    {
        $db = \Volt\Core\Database\VoltDatabase::connection();

        return $db->table('sys_role')
            ->select('name, label')
            ->orderBy('label', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function save(array $data): array
    {
        $name = $this->normalizeName((string) ($data['name'] ?? ''));
        $label = mb_trim((string) ($data['label'] ?? ''));
        $module = mb_trim((string) ($data['module'] ?? ''));
        $route = mb_trim((string) ($data['route'] ?? ''));
        $originalName = $data['original_name'] ?? null;

        if ($name === '') {
            throw new InvalidArgumentException('Page name is required.');
        }
        if ($label === '') {
            $label = $this->titleize($name);
        }
        if ($module === '') {
            throw new InvalidArgumentException('Module is required.');
        }
        if ($route === '') {
            $route = $name;
        }

        $route = $this->sanitizeRoute($route);
        $this->validateRoute($route, $originalName);

        if ($originalName !== null) {
            $oldPage = $this->pageModel->getByName($originalName);

            if ($oldPage !== null && $oldPage['module'] !== $module) {
                $this->removePageFiles($oldPage);
            }
        }

        $payload = [
            'name'         => $name,
            'module'       => $module,
            'label'        => $label,
            'icon'         => mb_trim((string) ($data['icon'] ?? '')),
            'route'        => $route,
            'html_content' => $data['html_content'] ?? '',
            'css_content'  => $data['css_content'] ?? '',
            'js_content'   => $data['js_content'] ?? '',
            'roles'        => json_encode($data['roles'] ?? [], JSON_UNESCAPED_UNICODE),
            'is_active'    => (int) ($data['is_active'] ?? 1),
        ];

        $this->pageModel->upsert($payload);
        $this->scaffoldPageFiles($payload);
        $this->regeneratePageRoutes();

        $actor = service('voltAuth')->currentUser();
        (new AwesomeBarModel())->registerEntity(
            'page_' . $name,
            $label,
            $module,
            $actor?->name ?? 'system'
        );

        return $this->pageModel->getByName($name) ?? $payload;
    }

    public function delete(string $name): void
    {
        $page = $this->pageModel->getByName($name);

        if ($page === null) {
            return;
        }

        $this->removePageFiles($page);
        $this->pageModel->delete($name);
        $this->regeneratePageRoutes();

        (new AwesomeBarModel())->removeEntity('page_' . $name);
    }

    /**
     * @param array<string, mixed> $page
     */
    public function scaffoldPageFiles(array $page): void
    {
        $moduleStudly = $this->studly($page['module']);
        $pagesDir = $this->pagesDir($moduleStudly);

        $this->ensureDir($pagesDir);

        $pageSlug = $page['name'];

        $this->writeFile(
            $pagesDir . '/' . $pageSlug . '.html',
            $page['html_content'] ?? ''
        );
        $this->writeFile(
            $pagesDir . '/' . $pageSlug . '.css',
            $page['css_content'] ?? ''
        );
        $this->writeFile(
            $pagesDir . '/' . $pageSlug . '.js',
            $page['js_content'] ?? ''
        );
    }

    /**
     * @param array<string, mixed> $page
     */
    public function removePageFiles(array $page): void
    {
        $moduleStudly = $this->studly($page['module']);
        $pagesDir = $this->pagesDir($moduleStudly);
        $pageSlug = $page['name'];

        $files = [
            $pagesDir . '/' . $pageSlug . '.html',
            $pagesDir . '/' . $pageSlug . '.css',
            $pagesDir . '/' . $pageSlug . '.js',
        ];

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public function regeneratePageRoutes(): void
    {
        $pages = $this->pageModel->getAll();
        $lines = [];

        foreach ($pages as $page) {
            if (! (int) ($page['is_active'] ?? 0)) {
                continue;
            }

            $route = $page['route'];
            $lines[] = "\$routes->get('{$route}', '\\Volt\\Core\\Metadata\\Controllers\\PageController::serve/{$route}');";
        }

        $body = $lines !== [] ? "    " . implode("\n    ", $lines) . "\n" : '';

        $content = <<<PHP
<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection \$routes */
{$body}
PHP;

        $this->writeFile(self::PAGE_ROUTES_FILE, $content);
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(mb_trim($name));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';
        $name = mb_trim($name, '_');

        return $name;
    }

    private function sanitizeRoute(string $route): string
    {
        $route = mb_strtolower(mb_trim($route));
        $route = preg_replace('/[^a-z0-9_\/-]+/', '-', $route) ?? '';
        $route = preg_replace('/-+/', '-', $route) ?? '';
        $route = mb_trim($route, '-/');

        return $route;
    }

    /**
     * @param string      $route
     * @param string|null $excludeName
     */
    private function validateRoute(string $route, ?string $excludeName = null): void
    {
        $firstSegment = explode('/', $route)[0];

        if (in_array($firstSegment, self::RESERVED_ROUTES, true)) {
            throw new InvalidArgumentException("Route '{$route}' conflicts with a system route.");
        }

        if ($this->pageModel->routeExists($route, $excludeName)) {
            throw new InvalidArgumentException("Route '{$route}' is already taken.");
        }
    }

    private function pagesDir(string $moduleStudly): string
    {
        return APPPATH . 'Modules/' . $moduleStudly . '/' . self::PAGES_DIR;
    }

    private function ensureDir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new \RuntimeException('Unable to create directory: ' . $path);
        }
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Unable to write file: ' . $path);
        }
    }

    private function studly(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;
        $value = mb_strtolower(mb_trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        $value = preg_replace('/_+/', '_', $value) ?? '';
        $value = mb_trim($value, '_');

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
    }

    private function titleize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
