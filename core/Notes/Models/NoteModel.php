<?php

declare(strict_types=1);

namespace Volt\Core\Notes\Models;

use Volt\Core\Models\VoltModel;
use Volt\Core\Notes\Entities\NoteEntity;

class NoteModel extends VoltModel
{
    protected $table         = 'sys_note';
    protected $primaryKey    = 'id';
    protected $returnType    = NoteEntity::class;
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $useTimestamps = false;
    protected $allowedFields = [
        'title',
        'body',
        'status',
        'owner',
        'created_at',
        'updated_at',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->setEntityName('Note');
    }
}
