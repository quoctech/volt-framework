<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Entities;

use CodeIgniter\Entity\Entity;

class AuthEntity extends Entity
{
    protected $casts = [
        'authenticated' => 'boolean',
        'setup_required' => 'boolean',
    ];
}
