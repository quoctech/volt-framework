<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Engine\MigrationCoordinator;
use Volt\Core\Engine\SchemaSync;

class VoltSync extends BaseCommand
{
    // QUẢN LÝ HẰNG SỐ - TUYỆT ĐỐI KHÔNG HARD-CODE
    const T_ENTITY = 'sys_entity';

    /**
     * Nhóm lệnh hiển thị khi gõ php spark
     */
    protected $group = 'Volt Core';

    /**
     * Tên lệnh thô để kích nổ từ Terminal
     */
    protected $name = 'volt:sync';

    /**
     * Mô tả tính năng lệnh
     */
    protected $description = 'Tính toán độ lệch Delta và tự động rèn bảng vật lý cho Entity từ Metadata';

    /**
     * Hướng dẫn cú pháp sử dụng
     */
    protected $usage = 'volt:sync [EntityName] hoặc volt:sync --all';

    protected $arguments = [
        'EntityName' => 'Tên của thực thể logic cần đồng bộ (Ví dụ: SalesInvoice)',
    ];

    protected $options = [
        '--all'               => 'Đồng bộ quét sạch toàn bộ các thực thể đang khai báo trong hệ thống',
        '--dry-run'           => 'Chỉ tính toán plan, không apply thay đổi nào',
        '--prune'             => 'Cho phép xóa cột dư thừa không còn khai báo trong metadata (phá vỡ)',
        '--allow-type-change' => 'Cho phép đổi kiểu dữ liệu cột khi metadata khác schema vật lý (phá vỡ)',
        '--allow-rename'      => 'Cho phép đổi tên cột theo bản đồ --renames',
        '--renames'           => 'Bản đồ đổi tên cột dạng "old:new,old2:new2" (yêu cầu --allow-rename)',
        '--defaults'          => 'Bản đồ backfill giá trị mặc định cho cột mới bắt buộc dạng "field:value,field2:value2"',
        '--data-check'        => 'Chỉ kiểm tra dữ liệu thực tế (đếm dòng, duplicate name, child mồ côi), không sửa schema',
        '--preview'           => 'Tính plan (dry-run) và in ra, không tạo migration request',
        '--request'           => 'Tạo migration request từ metadata hiện tại (breaking ops chờ duyệt)',
        '--approve <id>'      => 'Duyệt migration request (cho phép apply breaking ops)',
        '--apply <id>'        => 'Áp dụng migration request đã duyệt',
        '--rollback <id>'     => 'Rollback migration request đã applied (bằng inverse ops)',
        '--list-migrations'   => 'Liệt kê migration requests (lọc theo [entity] hoặc --status)',
        '--status <s>'        => 'Lọc theo trạng thái migration (dùng với --list-migrations)',
    ];

    /**
     * Bộ não xử lý đồng bộ cấu trúc
     */
    private ?SchemaSync $engine = null;

    private ?MigrationCoordinator $coordinator = null;

    /**
     * Kết nối database lõi
     */
    private ?BaseConnection $db = null;

    public function __construct()
    {
    }

    /**
     * Điểm kích nổ chính của lệnh CLI
     */
    public function run(array $params): void
    {
        // Kịch bản migration request management (approval flow)
        $migrationId = (int) (CLI::getOption('apply') ?? CLI::getOption('approve') ?? CLI::getOption('rollback') ?? 0);
        if ($migrationId > 0) {
            $this->runMigrationAction((string) CLI::getOption('approve'), (string) CLI::getOption('apply'), (string) CLI::getOption('rollback'), $migrationId);
            return;
        }

        if (CLI::getOption('list-migrations')) {
            $this->listMigrations($params[0] ?? '');
            return;
        }

        // Kịch bản preview / request
        $runEntityName = $params[0] ?? CLI::getSegment(2);
        if (CLI::getOption('preview')) {
            if (empty($runEntityName)) {
                CLI::error('❌ Cần chỉ định EntityName để preview.');
                return;
            }
            $this->runPreview((string) $runEntityName);
            return;
        }

        if (CLI::getOption('request')) {
            if (empty($runEntityName)) {
                CLI::error('❌ Cần chỉ định EntityName để tạo migration request.');
                return;
            }
            $this->runRequest((string) $runEntityName);
            return;
        }

        $opts = $this->buildOptions();

        // Kịch bản 0: Kiểm tra dữ liệu thực tế (--data-check)
        if (CLI::getOption('data-check')) {
            if (CLI::getOption('all')) {
                $entities = $this->db()->table(self::T_ENTITY)->select('name')->get()->getResultArray();

                if (empty($entities)) {
                    CLI::error('❌ Không tìm thấy bất kỳ Metadata Entity nào trong bảng ' . self::T_ENTITY . '!');
                    return;
                }

                foreach ($entities as $entity) {
                    $this->runDataCheck((string) $entity['name']);
                }
            } else {
                $entityName = $params[0] ?? CLI::getSegment(2);

                if (empty($entityName)) {
                    CLI::error('❌ Lỗi cú pháp! Vui lòng chỉ định rõ tên Entity. Ví dụ: php spark volt:sync Product --data-check');
                    return;
                }

                $this->runDataCheck((string) $entityName);
            }

            return;
        }

        // Kịch bản 1: Đồng bộ tất cả thực thể (--all)
        if (CLI::getOption('all')) {
            CLI::write('🔄 Đang quét danh mục để đồng bộ toàn diện hệ thống...', 'yellow');
            
            // Tận dụng hằng số thay vì dùng chuỗi thô 'sys_entity'
            $entities = $this->db()->table(self::T_ENTITY)->select('name')->get()->getResultArray();
            
            if (empty($entities)) {
                CLI::error('❌ Không tìm thấy bất kỳ Metadata Entity nào trong bảng ' . self::T_ENTITY . '!');
                return;
            }

            foreach ($entities as $entity) {
                $this->executeSync($entity['name'], $opts);
            }
            
            CLI::write('🎉 Đã hoàn thành đồng bộ toàn diện hệ thống Volt Framework!', 'green');
            return;
        }

        // Kịch bản 2: Đồng bộ đích danh 1 Entity truyền vào
        $entityName = $params[0] ?? CLI::getSegment(2);
        
        if (empty($entityName)) {
            CLI::error('❌ Lỗi cú pháp! Vui lòng chỉ định rõ tên Entity. Ví dụ: php spark volt:sync Product');
            return;
        }

        $this->executeSync((string)$entityName, $opts);
    }

    /**
     * Đọc và chuẩn hóa các option CLI thành opts cho SchemaSync.
     *
     * @return array<string, mixed>
     */
    private function buildOptions(): array
    {
        $opts = [
            'dry_run'            => (bool) CLI::getOption('dry-run'),
            'prune'              => (bool) CLI::getOption('prune'),
            'allow_drop'         => (bool) CLI::getOption('prune'),
            'allow_type_change'  => (bool) CLI::getOption('allow-type-change'),
            'allow_rename'       => (bool) CLI::getOption('allow-rename'),
            'renames'            => $this->parseRenames((string) CLI::getOption('renames')),
            'defaults'           => $this->parseDefaults((string) CLI::getOption('defaults')),
        ];

        if (CLI::getOption('dry-run')) {
            CLI::write('📋 Chế độ DRY-RUN: chỉ tính toán plan, không apply thay đổi.', 'yellow');
        }

        return $opts;
    }

    /**
     * Chuyển chuỗi "old:new,old2:new2" thành mảng assoc.
     *
     * @return array<string, string>
     */
    private function parseRenames(string $raw): array
    {
        if (mb_trim($raw) === '') {
            return [];
        }

        $result = [];
        foreach (explode(',', $raw) as $pair) {
            $parts = explode(':', mb_trim($pair), 2);
            $old = mb_trim((string) ($parts[0] ?? ''));
            $new = mb_trim((string) ($parts[1] ?? ''));
            if ($old !== '' && $new !== '') {
                $result[$old] = $new;
            }
        }

        return $result;
    }

    /**
     * Chuyển chuỗi "field:value,field2:value2" thành mảng assoc backfill defaults.
     *
     * @return array<string, string>
     */
    private function parseDefaults(string $raw): array
    {
        if (mb_trim($raw) === '') {
            return [];
        }

        $result = [];
        foreach (explode(',', $raw) as $pair) {
            $parts = explode(':', mb_trim($pair), 2);
            $field = mb_trim((string) ($parts[0] ?? ''));
            $value = mb_trim((string) ($parts[1] ?? ''));
            if ($field !== '' && $value !== '') {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    /**
     * Chạy báo cáo kiểm tra dữ liệu cho một entity.
     */
    private function runDataCheck(string $entityName): void
    {
        CLI::write("🔍 Đang kiểm tra dữ liệu thực tế: {$entityName}...", 'cyan');
        $result = $this->engine()->checkData($entityName);

        if (($result['status'] ?? '') !== 'success') {
            CLI::write("   ❌ Thất bại: " . ($result['message'] ?? 'Lỗi không xác định'), 'red');
            return;
        }

        CLI::write("   📄 Tổng số dòng: " . (int) $result['rows'], 'white');

        $duplicates = $result['duplicates'] ?? [];
        if ($duplicates === []) {
            CLI::write("   ✅ Không có tên trùng lặp.", 'green');
        } else {
            CLI::write("   ⚠️  Phát hiện " . count($duplicates) . " tên trùng lặp:", 'yellow');
            foreach ($duplicates as $d) {
                CLI::write("      - " . $d['name'] . " (x" . (int) $d['count'] . ")", 'yellow');
            }
        }

        $orphans = $result['orphan_children'] ?? [];
        if ($orphans === []) {
            CLI::write("   ✅ Không có child row mồ côi.", 'green');
        } else {
            CLI::write("   ⚠️  Child row mồ côi:", 'yellow');
            foreach ($orphans as $o) {
                CLI::write("      - " . $o['entity'] . " (" . $o['table'] . "): " . (int) $o['count'] . " dòng không có parent hợp lệ", 'yellow');
            }
        }
    }

    /**
     * Hàm điều hướng lệnh thực thi thô sạch sẽ, bảo đảm Type Hinting chặt chẽ
     *
     * @param array<string, mixed> $opts
     */
    private function executeSync(string $entityName, array $opts = []): void
    {
        CLI::write("⚡ Đang kiểm tra thực thể: {$entityName}...", 'cyan');
        $result = $this->engine()->syncEntity($entityName, $opts);

        if ($result['status'] === 'success') {
            foreach ($result['logs'] as $log) {
                CLI::write("   " . $log, 'green');
            }
        } else {
            CLI::write("   ❌ Thất bại: " . $result['message'], 'red');
        }
    }

    private function engine(): SchemaSync
    {
        return $this->engine ??= new SchemaSync();
    }

    private function coordinator(): MigrationCoordinator
    {
        return $this->coordinator ??= new MigrationCoordinator();
    }

    private function runPreview(string $entityName): void
    {
        $result = $this->coordinator()->preview($entityName, $this->buildOptions());

        if (($result['status'] ?? '') !== 'success') {
            CLI::error('❌ ' . ($result['message'] ?? 'Preview thất bại.'));
            return;
        }

        $this->printPlan($result['plan'] ?? [], $result['logs'] ?? []);
        CLI::write('ℹ️  Preview chỉ là tính toán (dry-run) — không tạo migration request.', 'yellow');
    }

    private function runRequest(string $entityName): void
    {
        $result = $this->coordinator()->request($entityName, 'cli', [
            'allow_type_change' => true,
            'prune'             => true,
            'allow_drop'        => true,
        ]);

        if (($result['status'] ?? '') !== 'success') {
            CLI::error('❌ ' . ($result['message'] ?? 'Tạo migration request thất bại.'));
            return;
        }

        $this->printPlan($result['plan'] ?? [], $result['logs'] ?? []);

        $safe = $result['safe_migration'] ?? null;
        $pending = $result['migration'] ?? null;

        if ($safe !== null && ($safe['status'] ?? '') === 'applied') {
            CLI::write("✅ Safe ops đã áp dụng (migration #{$safe['id']}).", 'green');
        }

        if ($pending !== null && ($pending['status'] ?? '') === 'pending_approval') {
            CLI::write("⏳ Breaking ops tạo migration request #{$pending['id']} chờ duyệt.", 'yellow');
            CLI::write("   Duyệt: php spark volt:sync --approve {$pending['id']}", 'yellow');
            CLI::write("   Áp dụng sau khi duyệt: php spark volt:sync --apply {$pending['id']}", 'yellow');
        }
    }

    private function runMigrationAction(string $approve, string $apply, string $rollback, int $id): void
    {
        $coordinator = $this->coordinator();

        if ($approve !== '') {
            $result = $coordinator->approve($id, 'cli');
            $this->printMigrationResult($result, 'duyệt');
            return;
        }

        if ($apply !== '') {
            $result = $coordinator->apply($id, 'cli');
            $this->printMigrationResult($result, 'áp dụng');
            return;
        }

        if ($rollback !== '') {
            $result = $coordinator->rollback($id, 'cli');
            $this->printMigrationResult($result, 'rollback');
        }
    }

    /** @param array<string, mixed> $result */
    private function printMigrationResult(array $result, string $verb): void
    {
        if (($result['status'] ?? '') === 'success') {
            $request = $result['request'] ?? [];
            CLI::write("✅ Đã {$verb} migration #{$request['id']}: {$request['status']}", 'green');
            if (($result['message'] ?? '') !== '') {
                CLI::write('   ' . $result['message'], 'green');
            }
            return;
        }

        CLI::error('❌ ' . ($result['message'] ?? "Không thể {$verb} migration."));
    }

    private function listMigrations(string $entityName): void
    {
        $filters = [];
        if ($entityName !== '') {
            $filters['entity'] = $entityName;
        }
        $status = (string) CLI::getOption('status');
        if ($status !== '') {
            $filters['status'] = $status;
        }

        $rows = $this->coordinator()->list($filters);

        if ($rows === []) {
            CLI::write('Không có migration request nào.', 'yellow');
            return;
        }

        CLI::table(array_map(static fn (array $r): array => [
            'id'     => (string) $r['id'],
            'entity' => $r['entity'],
            'status' => $r['status'],
            'ops'    => (string) count($r['ops'] ?? []),
            'by'     => (string) ($r['requested_by'] ?? ''),
        ], $rows), ['id', 'entity', 'status', 'ops', 'requested_by']);
    }

    /**
     * @param list<array<string, mixed>> $plan
     * @param list<string> $logs
     */
    private function printPlan(array $plan, array $logs): void
    {
        foreach ($logs as $log) {
            CLI::write('   ' . $log, 'white');
        }

        if ($plan === []) {
            CLI::write('ℹ️  Không có thay đổi schema.', 'yellow');
            return;
        }

        $rows = array_map(static fn (array $op): array => [
            'table'     => (string) ($op['table'] ?? ''),
            'operation' => (string) ($op['operation'] ?? ''),
            'column'    => (string) ($op['column'] ?? ''),
            'severity'  => (string) ($op['severity'] ?? 'safe'),
            'downtime'  => (string) ($op['downtime'] ?? ''),
        ], $plan);
        CLI::table($rows, ['table', 'operation', 'column', 'severity', 'downtime']);
    }

    private function db(): BaseConnection
    {
        return $this->db ??= VoltDatabase::connection();
    }
}
