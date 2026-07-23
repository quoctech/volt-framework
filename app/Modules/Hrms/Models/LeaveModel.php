<?php

declare(strict_types=1);

namespace App\Modules\Hrms\Models;

use App\Modules\Hrms\Entities\Leave\Leave;
use Volt\Core\Models\VoltModel;

final class LeaveModel extends VoltModel
{
    protected $table = 'tab_leave';
    protected $primaryKey = 'name';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $protectFields = false;
    protected $allowedFields = [];
    protected $beforeInsert = ['callBeforeInsert', 'voltBeforeInsert'];
    protected $afterInsert = ['voltAfterInsert', 'callAfterInsert'];
    protected $beforeUpdate = ['callBeforeUpdate', 'voltBeforeUpdate'];
    protected $afterUpdate = ['voltAfterUpdate', 'callAfterUpdate'];

    private ?Leave $docType = null;

    public function __construct()
    {
        parent::__construct();
        $this->setEntityName('Leave');
    }

    protected function callBeforeInsert(array $event): array
    {
        $payload = $this->extractPayload($event);
        $payload = $this->docType()->beforeInsert($payload);
        $payload = $this->docType()->beforeSave($payload);
        $this->docType()->validate($payload);
        $event['data'] = $payload;

        return $event;
    }

    protected function callBeforeUpdate(array $event): array
    {
        $payload = $this->extractPayload($event);
        $payload = $this->docType()->beforeSave($payload);
        $this->docType()->validate($payload);
        $event['data'] = $payload;

        return $event;
    }

    protected function callAfterInsert(array $event): array
    {
        $payload = $this->extractPayload($event);
        $this->docType()->afterInsert($payload, $event);
        $this->docType()->afterSave($payload, $event);

        return $event;
    }

    protected function callAfterUpdate(array $event): array
    {
        $payload = $this->extractPayload($event);
        $this->docType()->onUpdate($payload, $event);
        $this->docType()->afterSave($payload, $event);

        return $event;
    }

    private function docType(): Leave
    {
        return $this->docType ??= new Leave();
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function extractPayload(array $event): array
    {
        return isset($event['data']) && is_array($event['data']) ? $event['data'] : [];
    }
}