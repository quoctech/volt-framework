<?php

declare(strict_types=1);

namespace Volt\Core\Engine;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;
use Volt\Core\Database\VoltDatabase;

final class WorkflowEngine
{
    private const IMPLICIT_WORKFLOW = 'implicit';

    private const STATE_DRAFT     = 'Draft';
    private const STATE_SUBMITTED = 'Submitted';
    private const STATE_CANCELLED = 'Cancelled';

    private const DOCSTATUS_DRAFT     = 0;
    private const DOCSTATUS_SUBMITTED = 1;
    private const DOCSTATUS_CANCELLED = 2;

    private readonly BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? VoltDatabase::connection();
    }

    public function getWorkflow(string $entityName): ?array
    {
        $entityName = strtolower($entityName);

        $row = $this->db->table('sys_workflow')
            ->where('entity', $entityName)
            ->where('is_active', 1)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        $workflowName = (string) ($row['name'] ?? '');

        $states = $this->db->table('sys_workflow_state')
            ->where('workflow', $workflowName)
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $transitions = $this->db->table('sys_workflow_transition')
            ->where('workflow', $workflowName)
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'name'          => $workflowName,
            'entity'        => (string) ($row['entity'] ?? ''),
            'label'         => (string) ($row['label'] ?? ''),
            'states'        => $states,
            'transitions'   => $transitions,
            'states_order'  => $this->decodeJsonArray($row['states_order'] ?? '[]'),
        ];
    }

    public function getImplicitWorkflow(string $entityName): array
    {
        return [
            'name'         => self::IMPLICIT_WORKFLOW,
            'entity'       => $entityName,
            'label'        => 'Implicit Workflow',
            'states'       => [
                ['name' => self::STATE_DRAFT,     'label' => 'Draft',     'docstatus' => self::DOCSTATUS_DRAFT,     'allow_edit' => 1, 'is_final' => 0],
                ['name' => self::STATE_SUBMITTED, 'label' => 'Submitted', 'docstatus' => self::DOCSTATUS_SUBMITTED, 'allow_edit' => 0, 'is_final' => 0],
                ['name' => self::STATE_CANCELLED, 'label' => 'Cancelled', 'docstatus' => self::DOCSTATUS_CANCELLED, 'allow_edit' => 0, 'is_final' => 1],
            ],
            'transitions'  => [
                ['from_state' => self::STATE_DRAFT,     'action' => 'submit', 'to_state' => self::STATE_SUBMITTED],
                ['from_state' => self::STATE_SUBMITTED, 'action' => 'cancel', 'to_state' => self::STATE_CANCELLED],
                ['from_state' => self::STATE_CANCELLED, 'action' => 'amend',  'to_state' => self::STATE_DRAFT],
            ],
            'states_order' => [self::STATE_DRAFT, self::STATE_SUBMITTED, self::STATE_CANCELLED],
        ];
    }

    public function getTransitions(string $workflowName, string $currentState): array
    {
        $rows = $this->db->table('sys_workflow_transition')
            ->where('workflow', $workflowName)
            ->where('from_state', $currentState)
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        return is_array($rows) ? $rows : [];
    }

    public function applyTransition(
        string $entityName,
        string $documentName,
        string $action,
        ?string $comment = null,
    ): array {
        $workflow = $this->getWorkflow($entityName) ?? $this->getImplicitWorkflow($entityName);
        $tableName = $this->resolveTableName($entityName);

        $currentDoc = $this->db->table($tableName)
            ->select('name, docstatus, workflow_state')
            ->where('name', $documentName)
            ->get()
            ->getRowArray();

        if (! is_array($currentDoc)) {
            throw new InvalidArgumentException("Document {$documentName} not found.");
        }

        $currentState = (string) ($currentDoc['workflow_state'] ?? self::STATE_DRAFT);

        $transitions = $this->getValidTransitions($workflow, $currentState, $action);

        if ($transitions === []) {
            throw new RuntimeException(
                "Transition '{$action}' not allowed from state '{$currentState}' for {$entityName}."
            );
        }

        $transition = $transitions[0];
        $targetState = (string) ($transition['to_state'] ?? '');
        $targetDocstatus = $this->resolveDocstatus($workflow, $targetState);

        $comment = match (true) {
            $comment === null || mb_trim($comment) === '' => null,
            $this->requiresComment($action) && mb_trim($comment) === '' => throw new InvalidArgumentException("Action '{$action}' requires a comment."),
            default => mb_trim($comment),
        };

        $actorName = (string) (service('voltAuth')->currentUser()?->name ?? 'system');

        $this->db->transStart();

        $this->db->table($tableName)
            ->where('name', $documentName)
            ->update([
                'docstatus'      => $targetDocstatus,
                'workflow_state' => $targetState,
                'modified'       => date('Y-m-d H:i:s'),
            ]);

        if ($comment !== null) {
            $this->db->table('sys_audit_trail')->insert([
                'entity'   => $entityName,
                'document' => $documentName,
                'action'   => 'workflow:' . $action,
                'before'   => json_encode(['workflow_state' => $currentState]),
                'after'    => json_encode(['workflow_state' => $targetState, 'comment' => $comment]),
                'owner'    => $actorName,
                'creation' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->transComplete();

        return [
            'previous_state' => $currentState,
            'new_state'      => $targetState,
            'docstatus'      => $targetDocstatus,
            'action'         => $action,
        ];
    }

    public function canTransition(string $workflowName, string $fromState, string $action): bool
    {
        return $this->db->table('sys_workflow_transition')
            ->where('workflow', $workflowName)
            ->where('from_state', $fromState)
            ->where('action', $action)
            ->countAllResults() > 0;
    }

    public function getStates(string $entityName): array
    {
        return ($this->getWorkflow($entityName) ?? $this->getImplicitWorkflow($entityName))['states'];
    }

    public function isSubmittable(string $entityName): bool
    {
        $entityName = strtolower($entityName);

        $entity = $this->db->table('sys_entity')
            ->select('name')
            ->select("COALESCE(custom_attributes->>'is_submittable', 'false')::boolean AS is_submittable")
            ->where('name', $entityName)
            ->get()
            ->getRowArray();

        if (! is_array($entity)) {
            return false;
        }

        return (bool) ($entity['is_submittable'] ?? false) || $this->getWorkflow($entityName) !== null;
    }

    private function getValidTransitions(array $workflow, string $currentState, string $action): array
    {
        return array_values(array_filter(
            $workflow['transitions'] ?? [],
            fn(array $t): bool => (string) ($t['from_state'] ?? '') === $currentState
                && (string) ($t['action'] ?? '') === $action,
        ));
    }

    private function resolveDocstatus(array $workflow, string $stateName): int
    {
        $state = array_find(
            $workflow['states'] ?? [],
            fn(array $s): bool => (string) ($s['name'] ?? '') === $stateName,
        );

        return (int) ($state['docstatus'] ?? 0);
    }

    private function requiresComment(string $actionName): bool
    {
        $row = $this->db->table('sys_workflow_action')
            ->select('requires_comment')
            ->where('name', $actionName)
            ->get()
            ->getRowArray();

        return is_array($row) ? (bool) ($row['requires_comment'] ?? false) : in_array($actionName, ['reject', 'send_back'], true);
    }

    private function resolveTableName(string $entityName): string
    {
        $prefix = env('database.default.DB_PREFIX', '');
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entityName));

        return $prefix . 'tab_' . $snake;
    }

    private function decodeJsonArray(mixed $value): array
    {
        return match (true) {
            is_array($value) => $value,
            is_string($value) && $value !== '' && json_validate($value) && is_array($decoded = json_decode($value, true)) => $decoded,
            default => [],
        };
    }
}
