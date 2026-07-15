<?php

declare(strict_types=1);

namespace Volt\Core\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Volt\Core\AwesomeBar\Models\AwesomeBarModel;

class VoltAwesomeBarSeeder extends Seeder
{
    public function run()
    {
        $model = new AwesomeBarModel();
        $model->seedCorePages();

        echo "Seeded core pages into sys_awesome_bar.\n";
    }
}
