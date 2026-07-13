<?php

declare(strict_types=1);

namespace App\Modules\Hrms\Entities\EmployeeCheckin;

final class EmployeeCheckin
{
    /**
     * Hook chạy trước insert.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function beforeInsert(array $data): array
    {
        return $data;
    }

    /**
     * Hook chạy trước cả insert và update.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function beforeSave(array $data): array
    {
        return $data;
    }

    /**
     * Hook validate nghiệp vụ.
     *
     * @param array<string, mixed> $data
     */
    public function validate(array $data): void
    {
    }

    /**
     * Hook sau insert.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function afterInsert(array $data, array $context = []): void
    {
    }

    /**
     * Hook sau save cho cả insert và update.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function afterSave(array $data, array $context = []): void
    {
    }

    /**
     * Hook sau update.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function onUpdate(array $data, array $context = []): void
    {
    }
}