<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Volt\Core\Database\VoltDatabase;
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
        '--data-check'        => 'Chỉ kiểm tra dữ liệu thực tế (đếm dòng, duplicate name, child mồ côi), không sửa schema',
    ];

    /**
     * Bộ não xử lý đồng bộ cấu trúc
     */
    private ?SchemaSync $engine = null;

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

    private function db(): BaseConnection
    {
        return $this->db ??= VoltDatabase::connection();
    }
}
