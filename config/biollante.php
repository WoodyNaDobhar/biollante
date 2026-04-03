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

		'model'             => app_path('Models/'),

		'policy'            => app_path('Policies/'),

		'repository'        => app_path('Repositories/'),

		'api_routes'        => base_path('routes/api.php'),

		'app_service_provider' => app_path('Providers/AppServiceProvider.php'),

		'api_request'       => app_path('Http/Requests/API/'),

		'api_controller'    => app_path('Http/Controllers/API/'),

		'api_resource'      => app_path('Http/Resources/'),

		'schema_files'      => resource_path('model_schemas/'),

		'seeder'            => database_path('seeders/Test/'),

		'factory'           => database_path('factories/'),

		'api_test'          => base_path('tests/APIs/'),

		'permission_test'   => base_path('tests/Permissions/'),

		'repository_test'   => base_path('tests/Repositories/'),

		'unit_test'         => base_path('tests/Unit/'),

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

		'policy'            => 'App\Policies',

		'repository'        => 'App\Repositories',

		'api_controller'    => 'App\Http\Controllers\API',

		'api_resource'      => 'App\Http\Resources',

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

	'model_extend_class' => 'Biollante\Models\BaseModel',

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
	| Namespace prefix for organizing generated code into sub-directories
	| (e.g. an admin panel). This appends to all generated namespace and
	| path values.
	|
	*/

	'prefixes' => [

		'namespace' => '',  // e.g. 'Admin' or 'Admin\Shipping'
	],

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

		/*
	|--------------------------------------------------------------------------
	| API Info
	|--------------------------------------------------------------------------
	|
	| Metadata for the generated Swagger @OA\OpenApi block. These values
	| populate the API title, description, contact info, and server URL
	| in the generated AppBaseController.
	|
	*/

	'api' => [
		'title'       => 'API',
		'version'     => '1.0.0',
		'description' => '',
		'terms_url'   => null,
		'contact'     => [
			'name'  => null,
			'email' => null,
		],
		'server_url'  => '/api',
	],

	/*
	|--------------------------------------------------------------------------
	| Registration Fields
	|--------------------------------------------------------------------------
	|
	| Controls the compound registration lifecycle. The universal auth
	| fields (email, password, password_confirm, device_name, is_agreed)
	| are always included — only list additional fields here.
	|
	| Field metadata (type, description, max length) is derived from the
	| database schema at generation time — only the field name and
	| whether it's required need to be specified.
	|
	*/

	'register_fields' => [

		/*
		| Additional fields on the users table included in registration.
		*/
		'user' => [
			// 'first_name'  => ['required' => true],
			// 'last_name'   => ['required' => true],
			// 'born_on'     => ['required' => true],
			// 'timezone'    => ['required' => true],
		],

		/*
		| Forced defaults applied to the User on creation.
		| These are not submitted by the user.
		*/
		'user_defaults' => [
			// 'is_active' => 1,
		],

		/*
		| Related models created alongside the User during registration.
		| Key is the model name, value is an array of field => required.
		*/
		'related' => [
			// 'Persona' => [
			//     'name'         => ['required' => true],
			//     'display_name' => ['required' => true],
			//     'world_id'     => ['required' => false],
			//     'chapter_id'   => ['required' => false],
			//     'pronoun_id'   => ['required' => false],
			// ],
		],

		/*
		| Agreements/waivers created during registration.
		|
		| context_model: The model with is_waiver_* boolean flags (e.g. World)
		| context_field: Registration input field identifying the context instance
		| agreement_model: The model that stores signed waivers
		| waiver_types: Types to check — maps to is_waiver_{type} on context
		|               and {type}_signed on the registration input
		|
		| supporting_models: Models created from input whose FK goes on the
		|                    Agreement. Key is model name.
		|   fields: input fields used to create this model
		|   fk_field: foreign key field name stored on the Agreement
		|
		| data_fields: Fields stored directly on Agreement from input
		| computed_fields: Fields composed from other input values
		|
		| Set to null to disable agreement processing.
		*/
		'agreements' => null,
		// Example for ELF:
		// 'agreements' => [
		//     'context_model'   => 'World',
		//     'context_field'   => 'world_id',
		//     'agreement_model' => 'Agreement',
		//     'waiver_types'    => ['Harmless', 'Media', 'Site'],
		//     'supporting_models' => [
		//         'Location' => [
		//             'fields'   => ['address', 'city', 'province', 'postal_code', 'country'],
		//             'fk_field' => 'location_id',
		//         ],
		//     ],
		//     'data_fields' => [
		//         'emergency_name', 'emergency_relationship', 'emergency_phone',
		//         'medical', 'guardian', 'phone',
		//     ],
		//     'computed_fields' => [
		//         'name'       => "first_name + ' ' + last_name",
		//         'email'      => 'email',
		//         'born_on'    => 'born_on',
		//         'pronoun_id' => 'pronoun_id',
		//     ],
		// ],

		/*
		| Role assigned to newly registered users.
		*/
		'default_role' => 'User',

		/*
		| Eager loads returned with the login response.
		*/
		'login_with' => [],
	],

	/*
	|--------------------------------------------------------------------------
	| Invitations
	|--------------------------------------------------------------------------
	|
	| When enabled, generates invite-related endpoints: generateInvite,
	| decodeInvite, sendInvite, and accept. The invitation system uses
	| encrypted tokens with optional expiry and usage limits.
	|
	*/

	'invitations' => [
		'enabled'     => false,
		'token_field' => 'invite_token',
	],

	/*
	|--------------------------------------------------------------------------
	| Global Search
	|--------------------------------------------------------------------------
	|
	| When enabled, generates a /search endpoint that fans out across
	| the configured models using Laravel Scout. Each model listed here
	| gets the Searchable trait added by the model generator.
	|
	| Per-model 'with' controls eager loads on search results.
	|
	*/

	'search' => [
		'enabled' => false,
		'models'  => [
			// 'Achievement' => ['with' => []],
			// 'Chapter'     => ['with' => ['collective', 'world']],
			// 'Persona'     => ['with' => ['chapter.world']],
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Account Deletion
	|--------------------------------------------------------------------------
	|
	| When enabled, generates a /delete endpoint that anonymizes user
	| data rather than hard-deleting. The 'anonymize' array maps field
	| names to their replacement values. Use {id} as a placeholder
	| for the user's ID.
	|
	*/

	'delete_account' => [
		'enabled'   => true,
		'anonymize' => [
			// 'first_name'  => 'Deleted',
			// 'last_name'   => 'User',
			// 'email'       => 'deleted_user_{id}@nowhere.net',
			// 'is_active'   => false,
		],
	],

];