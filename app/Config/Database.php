<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations and Seeds directories.
     */
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to use if no other is specified.
     */
    public string $defaultGroup = 'default';

    /**
     * The default database connection.
     *
     * @var array<string, mixed>
     */
    public array $default = [];
    
    // public array $default = [
    //     'DSN'          => '',
    //     'hostname'     => 'localhost',
    //     'username'     => '',
    //     'password'     => '',
    //     'database'     => '',
    //     'DBDriver'     => 'MySQLi',
    //     'DBPrefix'     => '',
    //     'pConnect'     => false,
    //     'DBDebug'      => true,
    //     'charset'      => 'utf8mb4',
    //     'DBCollat'     => 'utf8mb4_general_ci',
    //     'swapPre'      => '',
    //     'encrypt'      => false,
    //     'compress'     => false,
    //     'strictOn'     => false,
    //     'failover'     => [],
    //     'port'         => 3306,
    //     'numberNative' => false,
    //     'foundRows'    => false,
    //     'dateFormat'   => [
    //         'date'     => 'Y-m-d',
    //         'datetime' => 'Y-m-d H:i:s',
    //         'time'     => 'H:i:s',
    //     ],
    // ];

    //    /**
    //     * Sample database connection for SQLite3.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'database'    => 'database.db',
    //        'DBDriver'    => 'SQLite3',
    //        'DBPrefix'    => '',
    //        'DBDebug'     => true,
    //        'swapPre'     => '',
    //        'failover'    => [],
    //        'foreignKeys' => true,
    //        'busyTimeout' => 1000,
    //        'synchronous' => null,
    //        'dateFormat'  => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for Postgre.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => '',
    //        'hostname'   => 'localhost',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'database'   => 'ci4',
    //        'schema'     => 'public',
    //        'DBDriver'   => 'Postgre',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'utf8',
    //        'swapPre'    => '',
    //        'failover'   => [],
    //        'port'       => 5432,
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for SQLSRV.
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => '',
    //        'hostname'   => 'localhost',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'database'   => 'ci4',
    //        'schema'     => 'dbo',
    //        'DBDriver'   => 'SQLSRV',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'utf8',
    //        'swapPre'    => '',
    //        'encrypt'    => false,
    //        'failover'   => [],
    //        'port'       => 1433,
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    //    /**
    //     * Sample database connection for OCI8.
    //     *
    //     * You may need the following environment variables:
    //     *   NLS_LANG                = 'AMERICAN_AMERICA.UTF8'
    //     *   NLS_DATE_FORMAT         = 'YYYY-MM-DD HH24:MI:SS'
    //     *   NLS_TIMESTAMP_FORMAT    = 'YYYY-MM-DD HH24:MI:SS'
    //     *   NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS'
    //     *
    //     * @var array<string, mixed>
    //     */
    //    public array $default = [
    //        'DSN'        => 'localhost:1521/FREEPDB1',
    //        'username'   => 'root',
    //        'password'   => 'root',
    //        'DBDriver'   => 'OCI8',
    //        'DBPrefix'   => '',
    //        'pConnect'   => false,
    //        'DBDebug'    => true,
    //        'charset'    => 'AL32UTF8',
    //        'swapPre'    => '',
    //        'failover'   => [],
    //        'dateFormat' => [
    //            'date'     => 'Y-m-d',
    //            'datetime' => 'Y-m-d H:i:s',
    //            'time'     => 'H:i:s',
    //        ],
    //    ];

    /**
     * This database connection is used when running PHPUnit database tests.
     *
     * @var array<string, mixed>
     */
    public array $tests = [];

    public function __construct()
    {
        parent::__construct();

        $this->default = $this->buildDefaultConnection();
        $this->tests   = $this->buildTestConnection();

        // Ensure that we always set the database group to 'tests' if
        // we are currently running an automated test suite, so that
        // we don't overwrite live data on accident.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }
    }

    /**
     * Build the primary database connection from environment values.
     *
     * @return array<string, mixed>
     */
    private function buildDefaultConnection(): array
    {
        return [
            'DSN'        => (string) env('database.default.DSN', ''),
            'hostname'   => (string) env('database.default.hostname', 'localhost'),
            'username'   => (string) env('database.default.username', ''),
            'password'   => (string) env('database.default.password', ''),
            'database'   => (string) env('database.default.database', ''),
            'schema'     => (string) env('database.default.schema', 'public'),
            'DBDriver'   => (string) env('database.default.DBDriver', 'Postgre'),
            'DBPrefix'   => (string) env('database.default.DBPrefix', ''),
            'pConnect'   => filter_var(env('database.default.pConnect', false), FILTER_VALIDATE_BOOL),
            'DBDebug'    => filter_var(env('database.default.DBDebug', true), FILTER_VALIDATE_BOOL),
            'charset'    => (string) env('database.default.charset', 'utf8'),
            'swapPre'    => (string) env('database.default.swapPre', ''),
            'failover'   => [],
            'port'       => (int) env('database.default.port', 5432),
            'dateFormat' => [
                'date'     => 'Y-m-d',
                'datetime' => 'Y-m-d H:i:s',
                'time'     => 'H:i:s',
            ],
        ];
    }

    /**
     * Build the PHPUnit database connection from environment values.
     *
     * @return array<string, mixed>
     */
    private function buildTestConnection(): array
    {
        return [
            'DSN'         => (string) env('database.tests.DSN', ''),
            'hostname'    => (string) env('database.tests.hostname', '127.0.0.1'),
            'username'    => (string) env('database.tests.username', ''),
            'password'    => (string) env('database.tests.password', ''),
            'database'    => (string) env('database.tests.database', ':memory:'),
            'DBDriver'    => (string) env('database.tests.DBDriver', 'SQLite3'),
            'DBPrefix'    => (string) env('database.tests.DBPrefix', 'db_'),
            'pConnect'    => filter_var(env('database.tests.pConnect', false), FILTER_VALIDATE_BOOL),
            'DBDebug'     => filter_var(env('database.tests.DBDebug', true), FILTER_VALIDATE_BOOL),
            'charset'     => (string) env('database.tests.charset', 'utf8'),
            'DBCollat'    => (string) env('database.tests.DBCollat', ''),
            'swapPre'     => (string) env('database.tests.swapPre', ''),
            'encrypt'     => filter_var(env('database.tests.encrypt', false), FILTER_VALIDATE_BOOL),
            'compress'    => filter_var(env('database.tests.compress', false), FILTER_VALIDATE_BOOL),
            'strictOn'    => filter_var(env('database.tests.strictOn', true), FILTER_VALIDATE_BOOL),
            'failover'    => [],
            'port'        => (int) env('database.tests.port', 3306),
            'foreignKeys' => filter_var(env('database.tests.foreignKeys', true), FILTER_VALIDATE_BOOL),
            'busyTimeout'  => (int) env('database.tests.busyTimeout', 1000),
            'synchronous'  => null,
            'dateFormat'   => [
                'date'     => 'Y-m-d',
                'datetime' => 'Y-m-d H:i:s',
                'time'     => 'H:i:s',
            ],
        ];
    }
}
