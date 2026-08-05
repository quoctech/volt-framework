<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Audit\RequestContext;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Tenant\Models\TenantModel;

class TenantCreate extends BaseCommand
{
    protected $group = 'Volt Core';
    protected $name = 'volt:tenant-create';
    protected $description = 'Create a new tenant and its database.';

    public function run(array $params): void
    {
        $name = $params[0] ?? CLI::prompt('Tenant name (lowercase, underscore)', null, 'required|regex[/^[a-z0-9_]+$/]');
        $label = $params[1] ?? CLI::prompt('Tenant label', $name);
        $dbName = $params[2] ?? CLI::prompt('Database name', "volt_tenant_{$name}");

        $model = new TenantModel();

        if ($model->find($name) !== null) {
            CLI::error("Tenant '{$name}' already exists.");
            return;
        }

        $model->save([
            'name'      => $name,
            'label'     => $label,
            'db_name'   => $dbName,
            'db_host'   => $params[3] ?? 'localhost',
            'db_port'   => (int) ($params[4] ?? 5432),
            'db_username' => $params[5] ?? 'volt_admin',
            'db_password' => $params[6] ?? '',
            'is_active' => 1,
        ]);

        (new AuditTrailWriter(VoltDatabase::hubConnection()))->write(
            AuditTrailWriter::CAT_TENANT,
            'tenant:create',
            'sys_tenant',
            $name,
            [],
            ['label' => $label, 'db_name' => $dbName],
            'cli',
            ['tenant' => $name, 'request_id' => RequestContext::fresh()],
        );

        CLI::write("Tenant '{$name}' created.", 'green');

        $db = VoltDatabase::tenantConnection($name);
        CLI::write("Connected to tenant database '{$dbName}'.", 'green');
    }
}
