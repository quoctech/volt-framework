<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\RawSql;
use Volt\Core\Database\VoltDatabase;

class VoltRegisterEntities extends BaseCommand
{
    protected $group = 'Volt Core';
    protected $name = 'volt:register-entities';
    protected $description = 'Read entity JSON files from modules and register into sys_entity tables';

    public function run(array $params): void
    {
        CLI::write('Scanning module definition files...', 'yellow');

        $db = VoltDatabase::connection();
        $moduleFiles = glob(ROOTPATH . 'app/Modules/*/module.json');
        $modulesRegistered = 0;
        $modulesSkipped = 0;

        if ($moduleFiles !== false) {
            foreach ($moduleFiles as $moduleFile) {
                $moduleStudly = basename(dirname($moduleFile));
                $modulePath = 'app/Modules/' . $moduleStudly;
                $content = file_get_contents($moduleFile);
                $data = $content !== false ? json_decode($content, true) : null;

                $moduleName = is_array($data) ? (string) ($data['name'] ?? '') : '';
                if ($moduleName === '') {
                    $moduleName = strtolower($moduleStudly);
                }
                $label = is_array($data) ? (string) ($data['label'] ?? '') : '';
                $label = $label !== '' ? $label : ucfirst($moduleName);
                $namespace = is_array($data) ? (string) ($data['namespace'] ?? '') : '';

                $exists = $db->table('sys_module')->where('name', $moduleName)->countAllResults() > 0;
                if ($exists) {
                    $db->table('sys_module')->where('name', $moduleName)->update([
                        'label'       => $label,
                        'namespace'   => $namespace,
                        'module_path' => $modulePath,
                        'is_active'   => 1,
                        'updated_at'  => new RawSql('CURRENT_TIMESTAMP'),
                    ]);
                    $modulesSkipped++;
                    continue;
                }

                $db->table('sys_module')->insert([
                    'name'        => $moduleName,
                    'label'       => $label,
                    'namespace'   => $namespace,
                    'module_path' => $modulePath,
                    'is_active'   => 1,
                    'created_at'  => new RawSql('CURRENT_TIMESTAMP'),
                    'updated_at'  => new RawSql('CURRENT_TIMESTAMP'),
                ]);
                CLI::write("  MODULE: {$moduleName}", 'green');
                $modulesRegistered++;
            }
        }

        CLI::write('Scanning entity definition files...', 'yellow');

        $files = glob(ROOTPATH . 'app/Modules/*/Entities/*/*.json');
        if ($files === false || $files === []) {
            CLI::error('No entity JSON files found.');
            return;
        }

        $db = VoltDatabase::connection();
        $success = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                CLI::error("  Cannot read: {$filePath}");
                $failed++;
                continue;
            }

            $data = json_decode($content, true);
            if (! is_array($data)) {
                CLI::error("  Invalid JSON: {$filePath}");
                $failed++;
                continue;
            }

            $source = $data['source'] ?? null;
            if (! is_array($source)) {
                CLI::error("  No source section in: {$filePath}");
                $failed++;
                continue;
            }

            $entityRow = $source['entity'] ?? null;
            if (! is_array($entityRow)) {
                CLI::error("  No source.entity in: {$filePath}");
                $failed++;
                continue;
            }

            $entityName = $entityRow['name'] ?? '';
            $moduleName = $entityRow['module'] ?? '';
            if ($entityName === '' || $moduleName === '') {
                CLI::error("  Missing name or module in: {$filePath}");
                $failed++;
                continue;
            }

            // Check if entity already exists
            $existing = $db->table('sys_entity')->where('name', $entityName)->get()->getRowArray();
            if (is_array($existing)) {
                CLI::write("  SKIP: {$entityName} (already registered)", 'yellow');
                $skipped++;
                continue;
            }

            $db->transStart();
            try {
                // 1. Insert sys_entity
                $db->table('sys_entity')->insert([
                    'name'              => $entityName,
                    'module'            => $moduleName,
                    'issingle'          => (int) ($entityRow['issingle'] ?? 0),
                    'istable'           => (int) ($entityRow['istable'] ?? 0),
                    'autoname'          => (string) ($entityRow['autoname'] ?? 'HASH'),
                    'states'            => (string) ($entityRow['states'] ?? '[]'),
                    'custom_attributes' => (string) ($entityRow['custom_attributes'] ?? '{}'),
                ]);

                // 2. Insert sys_entity_field
                $fields = $source['fields'] ?? [];
                if (is_array($fields)) {
                    foreach ($fields as $field) {
                        if (! is_array($field) || ! isset($field['fieldname'])) {
                            continue;
                        }
                        $db->table('sys_entity_field')->insert([
                            'parent'    => $entityName,
                            'fieldname' => $field['fieldname'],
                            'label'     => (string) ($field['label'] ?? $field['fieldname']),
                            'fieldtype' => (string) ($field['fieldtype'] ?? 'Data'),
                            'length'    => isset($field['length']) ? ($field['length'] !== null ? (int) $field['length'] : null) : 255,
                            'options'   => (string) ($field['options'] ?? ''),
                            'reqd'      => (int) ($field['reqd'] ?? 0),
                            'read_only' => (int) ($field['read_only'] ?? 0),
                            'hidden'    => (int) ($field['hidden'] ?? 0),
                            'idx'       => (int) ($field['idx'] ?? 0),
                        ]);
                    }
                }

                // 3. Insert sys_entity_custom
                $customRows = $source['customRows'] ?? [];
                if (is_array($customRows)) {
                    foreach ($customRows as $customRow) {
                        if (! is_array($customRow)) {
                            continue;
                        }
                        $applyToRole = $customRow['apply_to_role'] ?? null;
                        $db->table('sys_entity_custom')->insert([
                            'entity_name'   => $entityName,
                            'apply_to_role' => $applyToRole !== null && $applyToRole !== '' ? (string) $applyToRole : null,
                            'custom_meta'   => json_encode($customRow['custom_meta'] ?? []),
                        ]);
                    }
                }

                $db->transComplete();
                CLI::write("  OK: {$entityName} ({$moduleName})", 'green');
                $success++;

                // 4. Register workflow OUTSIDE the entity transaction
                $workflow = $data['workflow'] ?? [];
                if (is_array($workflow) && ! empty($workflow['active']) && ! empty($workflow['name']) && ! empty($workflow['states'])) {
                    try {
                        $this->registerWorkflow($db, $entityName, $workflow);
                    } catch (\Throwable $e) {
                        CLI::write("    Workflow registration warning: {$e->getMessage()}", 'yellow');
                    }
                }
            } catch (\Throwable $e) {
                $db->transRollback();
                CLI::error("  FAIL: {$entityName} - {$e->getMessage()}");
                $failed++;
            }
        }

        CLI::write("Done. Modules: {$modulesRegistered} registered, {$modulesSkipped} already present. Entities: {$success} registered, {$skipped} skipped, {$failed} failed", 'yellow');
    }

    private function registerWorkflow($db, string $entityName, array $workflow): void
    {
        $workflowName = $workflow['name'];

        $existing = $db->table('sys_workflow')->where('name', $workflowName)->get()->getRowArray();
        if (is_array($existing)) {
            CLI::write("    Workflow {$workflowName} already exists, skipping", 'yellow');
            return;
        }

        $states = $workflow['states'] ?? [];
        $stateNames = array_values(array_filter(array_map(static fn($s) => $s['name'] ?? '', $states)));

        $db->transStart();

        $db->table('sys_workflow')->insert([
            'name'      => $workflowName,
            'entity'    => $entityName,
            'label'     => $workflow['label'] ?? $workflowName,
            'is_active' => 1,
            'states_order' => json_encode($stateNames),
        ]);

        foreach ($states as $state) {
            if (! is_array($state) || ! isset($state['name'])) {
                continue;
            }
            $db->table('sys_workflow_state')->insert([
                'workflow'          => $workflowName,
                'name'              => $state['name'],
                'label'             => $state['label'] ?? $state['name'],
                'docstatus'         => (int) ($state['docstatus'] ?? 0),
                'allow_edit'        => (int) ($state['allow_edit'] ?? 0),
                'is_final'          => (int) ($state['is_final'] ?? 0),
                'color'             => (string) ($state['color'] ?? '#6b7280'),
                'idx'               => (int) ($state['idx'] ?? 0),
                'custom_attributes' => (string) ($state['custom_attributes'] ?? '{}'),
            ]);
        }

        $transitions = [];

        if (count($stateNames) >= 2) {
            $transitions[] = [
                'name'       => "{$workflowName}.{$stateNames[0]}.submit",
                'from_state' => $stateNames[0],
                'action'     => 'submit',
                'to_state'   => $stateNames[1],
                'idx'        => 0,
            ];
        }

        if (count($stateNames) >= 3) {
            $transitions[] = [
                'name'       => "{$workflowName}.{$stateNames[1]}.approve",
                'from_state' => $stateNames[1],
                'action'     => 'approve',
                'to_state'   => $stateNames[2],
                'idx'        => 1,
            ];

            $transitions[] = [
                'name'       => "{$workflowName}.{$stateNames[1]}.cancel",
                'from_state' => $stateNames[1],
                'action'     => 'cancel',
                'to_state'   => 'Cancelled',
                'idx'        => 2,
            ];
        }

        $cancelledIdx = array_search('Cancelled', $stateNames, true);
        if ($cancelledIdx !== false) {
            $transitions[] = [
                'name'       => "{$workflowName}.Cancelled.amend",
                'from_state' => 'Cancelled',
                'action'     => 'amend',
                'to_state'   => 'Draft',
                'idx'        => 3,
            ];
        }

        foreach ($transitions as $t) {
            $exists = $db->table('sys_workflow_transition')
                ->where('workflow', $workflowName)
                ->where('from_state', $t['from_state'])
                ->where('action', $t['action'])
                ->get()
                ->getRowArray();

            if (! is_array($exists)) {
                $db->table('sys_workflow_transition')->insert([
                    'name'       => $t['name'],
                    'workflow'   => $workflowName,
                    'from_state' => $t['from_state'],
                    'action'     => $t['action'],
                    'to_state'   => $t['to_state'],
                    'idx'        => $t['idx'],
                ]);
            }
        }

        $db->transComplete();
        CLI::write("    Workflow {$workflowName} registered", 'green');
    }
}
