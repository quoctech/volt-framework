<?php

declare(strict_types=1);

namespace Volt\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Volt\Core\AwesomeBar\Models\AwesomeBarModel;
use Volt\Core\Database\VoltDatabase;

class SyncAwesomeBar extends BaseCommand
{
    protected $group       = 'Volt';
    protected $name        = 'volt:sync-awesome-bar';
    protected $description = 'Sync existing entities into the awesome bar.';

    public function run(array $params)
    {
        $db = VoltDatabase::connection();
        $model = new AwesomeBarModel();

        $rows = $db->table('sys_entity')
            ->select('name, module')
            ->select("COALESCE(custom_attributes, '{}'::jsonb) AS custom_attributes")
            ->get()
            ->getResultArray();

        $count = 0;

        foreach ($rows as $row) {
            $custom = json_decode((string) ($row['custom_attributes'] ?? '{}'), true);
            $label = (string) ($custom['label'] ?? ucwords(str_replace('_', ' ', (string) ($row['name'] ?? ''))));

            $model->registerEntity(
                (string) ($row['name'] ?? ''),
                $label,
                (string) ($row['module'] ?? ''),
                'system',
            );
            $count++;
        }

        CLI::write("Synced {$count} entities into sys_awesome_bar.");
    }
}
