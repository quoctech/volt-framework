<?php

// 1. Kích nổ môi trường Bootstrapping chuẩn CodeIgniter 4 v4.7+ CLI
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Nạp trực tiếp file định nghĩa đường dẫn hệ thống
require_once __DIR__ . '/app/Config/Paths.php';
$paths = new Config\Paths();

// Trỏ thẳng vào file Boot gốc của hệ thống CodeIgniter
require_once rtrim($paths->systemDirectory, '/\\') . '/Boot.php';
CodeIgniter\Boot::bootSpark($paths);

// 2. Nhập thư viện lõi SchemaSync của anh em mình vào
use Volt\Core\Engine\SchemaSync;    

$db = \Config\Database::connect();
$engine = new SchemaSync();

echo "🚀 BẮT ĐẦU KHỞI CHẠY KHẢO SÁT VOLT ENGINE...\n";
echo "------------------------------------------------\n";

// 3. KHỞI TẠO METADATA GIẢ LẬP: Định nghĩa Doctype 'Product'
$entityName = 'Product';

echo "1. Đang nạp Metadata cấu hình cho Entity [{$entityName}] vào sys_...\n";

// Dọn rác cũ nếu có để tránh trùng khóa ngoại khi chạy lại test
$db->table('sys_entity_field')->where('parent', $entityName)->delete();
$db->table('sys_entity')->where('name', $entityName)->delete();

// Chèn dòng định nghĩa vào bảng sys_entity
$db->table('sys_entity')->insert([
    'name'     => $entityName,
    'module'   => 'Stock',
    'issingle' => 0,
    'istable'  => 0,
    'autoname' => 'PROD-.#####.'
]);

// Chèn các trường dữ liệu logic vào sys_entity_field
$fields = [
    ['fieldname' => 'product_name', 'label' => 'Tên Sản Phẩm', 'fieldtype' => 'Data',  'length' => 150, 'reqd' => 1, 'idx' => 1],
    ['fieldname' => 'sku',          'label' => 'Mã SKU',       'fieldtype' => 'Data',  'length' => 50,  'reqd' => 1, 'idx' => 2],
    ['fieldname' => 'price',        'label' => 'Giá Bán',      'fieldtype' => 'Float', 'length' => null,'reqd' => 0, 'idx' => 3],
    ['fieldname' => 'stock_qty',    'label' => 'Số Lượng Kho', 'fieldtype' => 'Int',   'length' => null,'reqd' => 0, 'idx' => 4],
];

foreach ($fields as $f) {
    $f['parent'] = $entityName;
    $db->table('sys_entity_field')->insert($f);
}
echo "✓ Nạp Metadata thành công!\n\n";

// 4. KÍCH HOẠT QUÁI THÚ SCHEMASYNC TÍNH TOÁN DELTA VÀ ĐÚC BẢNG
echo "2. Triển khai lệnh rèn bảng vật lý tự động...\n";
$result = $engine->syncEntity($entityName);

if ($result['status'] === 'success') {
    foreach ($result['logs'] as $log) {
        echo "   {$log}\n";
    }
    echo "\n🎉 HOÀN THÀNH MỸ MÃN! BẢNG VẬT LÝ ĐÃ ĐƯỢC ĐÚC XONG.\n";
} else {
    echo "❌ LỖI VẬN HÀNH ENGINE: " . $result['message'] . "\n";
}

// =================================================================
// 🔥 THỬ THÁCH CHẶT CHẼ HƠN: GIẢ LẬP ADMIN BẤM THÊM TRƯỜNG ĐỘNG (WAR PATCHING)
// =================================================================
echo "\n------------------------------------------------\n";
echo "🔄 GIẢ LẬP: Doanh nghiệp phát sinh nhu cầu, thêm trường mới...\n";

// Bổ sung thêm 2 trường mới tinh vào Metadata của Doctype Product
$newFields = [
    ['fieldname' => 'barcode',     'label' => 'Mã Vạch Sản Phẩm', 'fieldtype' => 'Data',  'length' => 100, 'reqd' => 0, 'idx' => 5],
    ['fieldname' => 'is_disabled',  'label' => 'Ngừng Kinh Doanh', 'fieldtype' => 'Check', 'length' => null,'reqd' => 0, 'idx' => 6],
];

foreach ($newFields as $nf) {
    $nf['parent'] = $entityName;
    $db->table('sys_entity_field')->insert($nf);
}
echo "✓ Đã nạp thêm 2 trường mới vào sys_entity_field.\n";

echo "3. Gọi SchemaSync tính toán độ lệch Delta để vá trực tiếp...\n";
$deltaResult = $engine->syncEntity($entityName);

if ($deltaResult['status'] === 'success') {
    foreach ($deltaResult['logs'] as $log) {
        echo "   {$log}\n";
    }
    echo "\n🏆 KẾT THÚC ĐÊM NAY: LÕI SCHEMASYNC ĐÃ ĐẠT ĐẲNG CẤP HOÀN HẢO!\n";
} else {
    echo "❌ LỖI VÀO TẦNG VÁ DELTA: " . $deltaResult['message'] . "\n";
}