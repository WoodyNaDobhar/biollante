<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    */

    'path' => [

        'migration'         => database_path('migrations/'),

        'model'             => app_path('Models/'),

        'policy'            => app_path('Policies/'),

        'datatables'        => app_path('DataTables/'),

        'livewire_tables'   => app_path('Http/Livewire/'),

        'repository'        => app_path('Repositories/'),

        'routes'            => base_path('routes/web.php'),

        'api_routes'        => base_path('routes/api.php'),

        'app_service_provider' => app_path('Providers/AppServiceProvider.php'),

        'request'           => app_path('Http/Requests/'),

        'api_request'       => app_path('Http/Requests/API/'),

        'controller'        => app_path('Http/Controllers/'),

        'api_controller'    => app_path('Http/Controllers/API/'),

        'api_resource'      => app_path('Http/Resources/'),

        'schema_files'      => resource_path('model_schemas/'),

        'seeder'            => database_path('seeders/Test/'),

        'database_seeder'   => database_path('seeders/TestDatabaseSeeder.php'),

        'factory'           => database_path('factories/'),

        'view_provider'     => app_path('Providers/ViewServiceProvider.php'),

        'tests'             => base_path('tests/'),

        'repository_test'   => base_path('tests/Repositories/'),

        'api_test'          => base_path('tests/APIs/'),

        'permission_test'   => base_path('tests/Permissions/'),

        'unit_test'         => base_path('tests/Unit/'),

        'views'             => resource_path('views/'),

        'menu_file'         => resource_path('views/layouts/menu.blade.php'),

        'interfaces'        => resource_path('@client/interfaces/'),

        'constants'         => resource_path('@client/constants/'),

        'tips'        => resource_path('@client/tips/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    */

    'namespace' => [

        'model'             => 'Biollante\Models',

        'datatables'        => 'Biollante\DataTables',

        'livewire_tables'   => 'Biollante\Http\Livewire',

        'policy'            => 'Biollante\Policies',

        'repository'        => 'Biollante\Repositories',

        'controller'        => 'Biollante\Http\Controllers',

        'api_controller'    => 'Biollante\Http\Controllers\API',

        'api_resource'      => 'Biollante\Http\Resources',

        'request'           => 'Biollante\Http\Requests',

        'api_request'       => 'Biollante\Http\Requests\API',

        'seeder'            => 'Database\Seeders\Test',

        'factory'           => 'Database\Factories',

        'tests'             => 'Tests',

        'repository_test'   => 'Tests\Repositories',

        'api_test'          => 'Tests\APIs',

        'permission_test'   => 'Tests\Permissions',

        'unit_test'         => 'Tests\Unit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    */

    'templates' => 'adminlte-templates',

    /*
    |--------------------------------------------------------------------------
    | Model extend class
    |--------------------------------------------------------------------------
    |
    */

    'model_extend_class' => 'Illuminate\Database\Eloquent\Model',

    /*
    |--------------------------------------------------------------------------
    | API routes prefix & version
    |--------------------------------------------------------------------------
    |
    */

    'api_prefix'  => 'api',

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    |
    */

    'options' => [

        'soft_delete' => true,

        'save_schema_file' => true,

        'localized' => false,

        'policies' => true,

        'repository_pattern' => true,

        'resources' => true,

        'factory' => true,

        'seeder' => true,

        'swagger' => true,

        'tests' => true,

        'excluded_fields' => ['id'], // Array of columns that aren't required while creating scaffold

        'excluded_tables' => ['audits','sessions','password_histories'], // Array of tables that aren't scaffolded or related, typically because it's part of a package doing that work
    
        'hidden_fields' => ['remember_token','api_token','two_factor_recovery_codes', 'two_factor_secret', 'password'], // Array of columns that are hidden in the resource

        'excluded_seeds' => ['pronouns'], // Array of model names that do not require test seeding
    ],

    /*
    |--------------------------------------------------------------------------
    | Prefixes
    |--------------------------------------------------------------------------
    |
    */

    'prefixes' => [

        'route' => '',  // e.g. admin or admin.shipping or admin.shipping.logistics

        'namespace' => '',  // e.g. Admin or Admin\Shipping or Admin\Shipping\Logistics

        'view' => '',  // e.g. admin or admin/shipping or admin/shipping/logistics
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Types
    |
    | Possible Options: blade, datatables, livewire
    |--------------------------------------------------------------------------
    |
    */

    'tables' => 'blade',

    /*
    |--------------------------------------------------------------------------
    | Timestamp Fields
    |--------------------------------------------------------------------------
    |
    */

    'timestamps' => [

        'enabled'       => true,

        'created_at'    => 'created_at',

        'updated_at'    => 'updated_at',

        'deleted_at'    => 'deleted_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Specify custom doctrine mappings as per your need
    |--------------------------------------------------------------------------
    |
    */

    'from_table' => [

        'doctrine_mappings' => [],
    ],

];
