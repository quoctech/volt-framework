<?php

declare(strict_types=1);

namespace Volt\Core\Role\Entities;

use CodeIgniter\Entity\Entity;

class RoleEntity extends Entity
{
    protected $casts = [
        'is_system' => 'boolean',
    ];
}
