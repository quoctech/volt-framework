<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
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
        '--all' => 'Đồng bộ quét sạch toàn bộ các thực thể đang khai báo trong hệ thống',
    ];

    /**
     * Bộ não xử lý đồng bộ cấu trúc
     */
    private SchemaSync $engine;

    /**
     * Kết nối database lõi
     */
    private BaseConnection $db;

    /**
     * Khởi tạo và nạp động các dịch vụ dùng chung
     */
    public function __construct()
    {
        // Giữ lại cấu trúc cha của CI4 Command
        parent::__construct();
        
        $this->engine = new SchemaSync();
        $this->db     = Database::connect();
    }

    /**
     * Điểm kích nổ chính của lệnh CLI
     */
    public function run(array $params): void
    {
        // Khởi tạo dịch vụ trực tiếp tại đây để triệt tiêu lỗi parent::__construct
        $this->engine = new SchemaSync();
        $this->db     = Database::connect();
        
        // Kịch bản 1: Đồng bộ tất cả thực thể (--all)
        if (CLI::getOption('all')) {
            CLI::write('🔄 Đang quét danh mục để đồng bộ toàn diện hệ thống...', 'yellow');
            
            // Tận dụng hằng số thay vì dùng chuỗi thô 'sys_entity'
            $entities = $this->db->table(self::T_ENTITY)->select('name')->get()->getResultArray();
            
            if (empty($entities)) {
                CLI::error('❌ Không tìm thấy bất kỳ Metadata Entity nào trong bảng ' . self::T_ENTITY . '!');
                return;
            }

            foreach ($entities as $entity) {
                $this->executeSync($entity['name']);
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

        $this->executeSync((string)$entityName);
    }

    /**
     * Hàm điều hướng lệnh thực thi thô sạch sẽ, bảo đảm Type Hinting chặt chẽ
     */
    private function executeSync(string $entityName): void
    {
        CLI::write("⚡ Đang kiểm tra thực thể: {$entityName}...", 'cyan');
        $result = $this->engine->syncEntity($entityName);

        if ($result['status'] === 'success') {
            foreach ($result['logs'] as $log) {
                CLI::write("   " . $log, 'green');
            }
        } else {
            CLI::write("   ❌ Thất bại: " . $result['message'], 'red');
        }
    }
}