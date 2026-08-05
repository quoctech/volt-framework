<?php

declare(strict_types=1);

namespace Volt\Core\Tenant\Models;

use CodeIgniter\Model;
use Volt\Core\Database\VoltDatabase;

class TenantModel extends Model
{
    protected $table            = 'sys_tenant';
    protected $primaryKey       = 'name';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $deletedField     = 'deleted_at';

    protected $allowedFields = [
        'name', 'label', 'domain', 'db_host', 'db_port', 'db_name',
        'db_username', 'db_password', 'is_active',
        'deleted_at', 'deleted_by', 'purge_at',
    ];

    public function __construct()
    {
        parent::__construct(VoltDatabase::hubConnection());
    }

    public function getActive(): array
    {
        $this->where('is_active', 1);
        $this->orderBy('label', 'ASC');
        return $this->findAll();
    }

    public function findByDomain(string $host): ?array
    {
        $this->where('domain', $host);
        $this->where('is_active', 1);
        return $this->first() ?: null;
    }

    public function getTrashed(): array
    {
        $this->onlyDeleted();
        $this->orderBy('deleted_at', 'DESC');
        return $this->findAll();
    }

    public function getDuePurge(): array
    {
        $this->onlyDeleted();
        $this->where('purge_at IS NOT NULL');
        $this->where('purge_at <= NOW()');
        return $this->findAll();
    }
}
