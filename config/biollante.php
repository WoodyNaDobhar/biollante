<?php

return [

	/*
	|--------------------------------------------------------------------------
	| Biollante Configuration
	|--------------------------------------------------------------------------
	|
	| All configuration for the Biollante scaffolding package lives here.
	|
	| Publish this file to your application with:
	|
	|   php artisan vendor:publish --tag=biollante
	|
	*/

	/*
	|--------------------------------------------------------------------------
	| Paths
	|--------------------------------------------------------------------------
	|
	| Output directories for generated files. Override any path to match
	| your project's directory structure.
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

		'tips'              => resource_path('@client/tips/'),

		'rules'             => resource_path('@client/rules/'),
	],

	/*
	|--------------------------------------------------------------------------
	| Namespaces
	|--------------------------------------------------------------------------
	|
	| PHP namespaces for generated classes. Override to match your project's
	| namespace structure.
	|
	*/

	'namespace' => [

		'model'             => 'App\Models',

		'datatables'        => 'App\DataTables',

		'livewire_tables'   => 'App\Http\Livewire',

		'policy'            => 'App\Policies',

		'repository'        => 'App\Repositories',

		'controller'        => 'App\Http\Controllers',

		'api_controller'    => 'App\Http\Controllers\API',

		'api_resource'      => 'App\Http\Resources',

		'request'           => 'App\Http\Requests',

		'api_request'       => 'App\Http\Requests\API',

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
	| Model Extend Class
	|--------------------------------------------------------------------------
	|
	| The base class that generated models extend.
	|
	*/

	'model_extend_class' => 'Illuminate\Database\Eloquent\Model',

	/*
	|--------------------------------------------------------------------------
	| API Prefix
	|--------------------------------------------------------------------------
	|
	| The route prefix for generated API routes.
	|
	*/

	'api_prefix' => 'api',

	/*
	|--------------------------------------------------------------------------
	| Options
	|--------------------------------------------------------------------------
	|
	| Feature toggles and exclusion lists that control what gets generated.
	|
	*/

	'options' => [

		'soft_delete'         => true,

		'save_schema_file'    => true,

		'localized'           => false,

		'policies'            => true,

		'repository_pattern'  => true,

		'resources'           => true,

		'factory'             => true,

		'seeder'              => true,

		'swagger'             => true,

		'tests'               => true,

		'excluded_fields'     => ['id'],

		'excluded_tables'     => [],

		'hidden_fields'       => [
			'remember_token',
			'api_token',
			'two_factor_recovery_codes',
			'two_factor_secret',
			'password',
		],

		'excluded_seeds'      => [],

		/*
		|----------------------------------------------------------------------
		| Model Template Options
		|----------------------------------------------------------------------
		|
		| Control which traits, interfaces, and base classes are used in
		| generated models. Set any to false/null to disable.
		|
		*/

		'auditable'           => true,

		'userstamps'          => true,
	],

	/*
	|--------------------------------------------------------------------------
	| Prefixes
	|--------------------------------------------------------------------------
	|
	| Route, namespace, and view prefixes for organizing generated code
	| into sub-directories (e.g. an admin panel).
	|
	*/

	'prefixes' => [

		'route'     => '',  // e.g. 'admin' or 'admin.shipping'

		'namespace' => '',  // e.g. 'Admin' or 'Admin\Shipping'

		'view'      => '',  // e.g. 'admin' or 'admin/shipping'
	],

	/*
	|--------------------------------------------------------------------------
	| Table Types
	|--------------------------------------------------------------------------
	|
	| The table rendering approach for generated views.
	|
	| Possible values: 'blade', 'datatables', 'livewire'
	|
	*/

	'tables' => 'blade',

	/*
	|--------------------------------------------------------------------------
	| Timestamp Fields
	|--------------------------------------------------------------------------
	|
	| Column names for Laravel's timestamp conventions.
	|
	*/

	'timestamps' => [

		'enabled'    => true,

		'created_at' => 'created_at',

		'updated_at' => 'updated_at',

		'deleted_at' => 'deleted_at',
	],

	/*
	|--------------------------------------------------------------------------
	| Organizer Roles
	|--------------------------------------------------------------------------
	|
	| Entity types that have scoped organizer authority in your application.
	| Each entry is a base name — Biollante appends " Organizer" when
	| resolving role names (e.g. 'Practice' becomes 'Practice Organizer').
	|
	| These drive permission path resolution in RoleFieldResolver and
	| role listings in generated Swagger documentation.
	|
	| Set to an empty array if your app has no scoped organizer roles.
	|
	*/

	'organizer_roles' => [],

	/*
	|--------------------------------------------------------------------------
	| Scope Resolver
	|--------------------------------------------------------------------------
	|
	| The class that determines how your application proves a user has
	| scoped authority over a particular entity at runtime.
	|
	| Must implement Biollante\Contracts\ScopeResolver.
	|
	| Set to null if your app does not use scoped access. Biollante will
	| generate policies with only Full and Own permission checks.
	|
	*/

	'scope_resolver' => null,

	/*
	|--------------------------------------------------------------------------
	| Parent Hierarchy
	|--------------------------------------------------------------------------
	|
	| Maps child entity types to their parent entity type and the foreign
	| key field that connects them. This is used in generated policies to
	| check whether an organizer's authority over a parent entity should
	| cascade to its children.
	|
	| Format: 'ChildModel' => ['parent_type' => 'ParentModel', 'parent_field' => 'parent_model_id']
	|
	| Example for ELF: 'Chapter' => ['parent_type' => 'Collective', 'parent_field' => 'collective_id']
	|
	| Set to an empty array if your app has no hierarchical entity
	| relationships that affect permission scoping.
	|
	*/

	'parent_hierarchy' => [],

];