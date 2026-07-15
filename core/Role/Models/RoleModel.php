<?php

declare(strict_types=1);

namespace Volt\Core\Role\Models;

use CodeIgniter\Model;
use Volt\Core\Role\Entities\RoleEntity;

class RoleModel extends Model
{
    protected $table           = 'sys_role';
    protected $primaryKey      = 'name';
    protected $returnType      = RoleEntity::class;
    protected $useSoftDeletes  = false;
    protected $useAutoIncrement = false;
    protected $protectFields   = true;
    protected $useTimestamps   = true;
    protected $allowedFields  = [
        'name',
        'label',
        'description',
        'is_system',
        'owner',
    ];

    public function findByName(string $name): ?RoleEntity
    {
        $role = $this->where('name', $name)->first();

        return $role instanceof RoleEntity ? $role : null;
    }
}
