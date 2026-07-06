<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Entities;

use CodeIgniter\Entity\Entity;

class UserEntity extends Entity
{
    protected $casts = [
        'is_active'            => 'boolean',
        'failed_login_attempts' => 'integer',
    ];

    public function hasRole(string $role): bool
    {
        $roles = $this->normalizeListValue($this->roles ?? []);

        return in_array($role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isActive(): bool
    {
        if (! property_exists($this, 'is_active') || $this->is_active === null) {
            return true;
        }

        return (bool) $this->is_active;
    }

    private function normalizeListValue(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded), static fn (string $item): bool => $item !== ''));
            }

            $unserialized = @unserialize($value, ['allowed_classes' => false]);

            if (is_array($unserialized)) {
                return array_values(array_filter(array_map('strval', $unserialized), static fn (string $item): bool => $item !== ''));
            }
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $value), static fn (string $item): bool => $item !== ''));
    }
}
