<?php

declare(strict_types=1);

namespace Volt\Core\Models;

use CodeIgniter\Model;

final class FileModel extends Model
{
    protected $table = 'sys_file';
    protected $primaryKey = 'name';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $protectFields = false;
    protected $allowedFields = [
        'name', 'file_name', 'file_path', 'file_size', 'file_type',
        'attached_to_entity', 'attached_to_name', 'attached_to_field',
        'is_private', 'owner', 'creation', 'modified',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'creation';
    protected $updatedField = 'modified';

    public function findByEntity(string $entity, string $name, ?string $field = null): array
    {
        $builder = $this->db->table($this->table)
            ->where('attached_to_entity', $entity)
            ->where('attached_to_name', $name)
            ->orderBy('creation', 'ASC');

        if ($field !== null) {
            $builder->where('attached_to_field', $field);
        }

        return $builder->get()->getResultArray();
    }

    public function deleteByEntity(string $entity, string $name, ?string $field = null): void
    {
        $builder = $this->db->table($this->table)
            ->where('attached_to_entity', $entity)
            ->where('attached_to_name', $name);

        if ($field !== null) {
            $builder->where('attached_to_field', $field);
        }

        $files = $builder->get()->getResultArray();

        foreach ($files as $file) {
            $this->deleteFileWithRecord($file['name']);
        }
    }

    public function deleteFileWithRecord(string $name): bool
    {
        $file = $this->find($name);
        if (!$file) {
            return false;
        }

        $filePath = WRITEPATH . 'uploads/' . $file['file_path'];
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        return $this->delete($name);
    }
}
