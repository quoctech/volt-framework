<?php

declare(strict_types=1);

namespace Volt\Core\Report\Models;

use CodeIgniter\Model;
use Volt\Core\Database\VoltDatabase;

class ReportModel extends Model
{
    protected $table          = 'sys_report';
    protected $primaryKey     = 'name';
    protected $useAutoIncrement = false;
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    protected $allowedFields = [
        'name', 'module', 'label', 'description', 'report_type',
        'is_active', 'query', 'columns', 'roles', 'charts', 'owner',
    ];

    public function __construct()
    {
        parent::__construct(VoltDatabase::connection());
    }

    public function getAll(): array
    {
        $this->orderBy('label', 'ASC');
        return $this->findAll();
    }

    public function getByName(string $name): ?array
    {
        $row = $this->find($name);
        return is_array($row) ? $row : null;
    }

    public function upsert(array $data): void
    {
        $this->save($data);
    }
}
