<?php

declare(strict_types=1);

namespace Volt\Core\Auth\Models;

use CodeIgniter\Model;
use Volt\Core\Auth\Entities\UserEntity;

class UserModel extends Model
{
    protected $table            = 'sys_user';
    protected $primaryKey       = 'name';
    protected $returnType       = UserEntity::class;
    protected $useSoftDeletes   = false;
    protected $useAutoIncrement = false;
    protected $protectFields    = true;
    protected $useTimestamps    = false;
    protected $allowedFields    = [
        'name',
        'password',
        'roles',
        'user_metadata',
        'is_active',
        'api_token_hash',
        'api_token_expires_at',
        'last_login_at',
        'failed_login_attempts',
        'locked_until',
        'created_at',
        'updated_at',
    ];

    /** @var array<int, string>|null */
    private ?array $tableFields = null;

    public function findByName(string $name): ?UserEntity
    {
        $user = $this->where('name', $name)->first();

        return $user instanceof UserEntity ? $user : null;
    }

    public function findAdminUsers(): array
    {
        $select = 'name, roles, user_metadata';

        if ($this->hasColumn('is_active')) {
            $select .= ', is_active';
        }

        $users = $this->select($select)->findAll();

        return array_values(array_filter($users, static fn ($user): bool => $user instanceof UserEntity && $user->isAdmin() && $user->isActive()));
    }

    public function insert($data = null, bool $returnID = true)
    {
        return parent::insert($this->normalizeStoragePayload($data), $returnID);
    }

    public function update($id = null, $data = null): bool
    {
        return parent::update($id, $this->normalizeStoragePayload($data));
    }

    public function save($data): bool
    {
        return parent::save($this->normalizeStoragePayload($data));
    }

    private function normalizeStoragePayload(mixed $data): mixed
    {
        if ($data instanceof UserEntity) {
            $data = $data->toRawArray();
        }

        if (! is_array($data)) {
            return $data;
        }

        $data = $this->filterExistingColumns($data);

        if (array_key_exists('roles', $data) && is_array($data['roles'])) {
            $data['roles'] = json_encode(array_values($data['roles']), JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('user_metadata', $data) && is_array($data['user_metadata'])) {
            $data['user_metadata'] = json_encode($data['user_metadata'], JSON_UNESCAPED_UNICODE);
        }

        return $data;
    }

    private function filterExistingColumns(array $data): array
    {
        $fields = array_flip($this->tableFields());

        return array_intersect_key($data, $fields);
    }

    public function hasColumn(string $column): bool
    {
        return in_array($column, $this->tableFields(), true);
    }

    /**
     * @return array<int, string>
     */
    private function tableFields(): array
    {
        if ($this->tableFields === null) {
            $this->tableFields = $this->db->getFieldNames($this->table) ?: [];
        }

        return $this->tableFields;
    }

    public function decodeJsonField(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            $unserialized = @unserialize($value, ['allowed_classes' => false]);

            if (is_array($unserialized)) {
                return $unserialized;
            }
        }

        return is_array($value) ? $value : [];
    }
}
