<?php

namespace Biollante\Generator\Common;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Helpers\RoleFieldResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Biollante\Generator\DTOs\GeneratorNamespaces;
use Biollante\Generator\DTOs\GeneratorOptions;
use Biollante\Generator\DTOs\GeneratorPaths;
use Biollante\Generator\DTOs\GeneratorPrefixes;
use Biollante\Generator\DTOs\ModelNames;
use Spatie\Async\Pool;
use Spatie\Permission\Models\Role;

class GeneratorConfig
{
	public GeneratorNamespaces $namespaces;
	public GeneratorPaths $paths;
	public ModelNames $modelNames;
	public GeneratorPrefixes $prefixes;
	public GeneratorOptions $options;
	public Command $command;

	/** @var GeneratorField[] */
	public array $fields = [];

	/** @var GeneratorFieldRelation[] */
	public array $relations = [];

	public $relationFields = [];
	public $hasPermissions = [];

	protected static $dynamicVars = [];

	public $tableName;
	public $tableComment;
	public string $tableType;
	public string $apiPrefix;
	public $primaryName;
	public $connection;

	private $contexts;
	private $types;

	public function init()
	{
		$this->commandComment(BiollanteHelper::instance()->format_nl() . 'Getting Table Data: ');
		$this->commandInfo(lcfirst(Str::plural($this->command->argument('model'))));
		$this->contexts = [
			'display',
			'update',
			'remove'
		];
		$this->types = [
			'Own',
			'Related'
		];
		$this->loadModelNames();
		$this->loadPrefixes();
		$this->loadPaths();
		$this->tableType = config('biollante.tables', 'blade');
		$this->apiPrefix = config('biollante.api_prefix', 'api');
		$this->loadNamespaces();
		$this->prepareTable();
		$this->prepareOptions();
		$this->loadTableComment();
		$this->loadRelationFields();
		$this->loadHasPermissions();
	}

	public static function addDynamicVariable(string $name, $value)
	{
		self::$dynamicVars[$name] = $value;
	}

	public static function addDynamicVariables(array $vars)
	{
		foreach ($vars as $key => $value) {
			self::addDynamicVariable($key, $value);
		}
	}

	public function getDynamicVariable(string $name)
	{
		return self::$dynamicVars[$name];
	}

	public function setCommand(Command &$command)
	{
		$this->command = &$command;
	}

	public function loadModelNames()
	{
		$modelNames = new ModelNames();
		$modelNames->name = Str::singular($this->command->argument('model'));

		$modelNames->plural = Str::plural($modelNames->name);

		$modelNames->camel = Str::camel($modelNames->name);
		$modelNames->camelPlural = Str::camel($modelNames->plural);
		$modelNames->snake = Str::snake($modelNames->name);
		$modelNames->snakePlural = Str::snake($modelNames->plural);
		$modelNames->dashed = Str::kebab($modelNames->name);
		$modelNames->dashedPlural = Str::kebab($modelNames->plural);
		$modelNames->human = Str::title(str_replace('_', ' ', $modelNames->snake));
		$modelNames->humanPlural = Str::title(str_replace('_', ' ', $modelNames->snakePlural));

		$this->modelNames = $modelNames;
	}

	public function loadPrefixes()
	{
		$prefixes = new GeneratorPrefixes();

		$prefixes->route = config('biollante.prefixes.route', '');
		$prefixes->namespace = config('biollante.prefixes.namespace', '');
		$prefixes->view = config('biollante.prefixes.view', '');

		$this->prefixes = $prefixes;
	}

	public function loadPaths()
	{
		$paths = new GeneratorPaths();

		$namespacePrefix = $this->prefixes->namespace;
		$viewPrefix = $this->prefixes->view;

		if (!empty($namespacePrefix)) {
			$namespacePrefix .= '/';
		}

		if (!empty($viewPrefix)) {
			$viewPrefix .= '/';
		}

		$paths->repository = config(
			'laravel_generator.path.repository',
			app_path('Repositories/')
		).$namespacePrefix;

		$paths->model = config('biollante.path.model', app_path('Models/Core/')).$namespacePrefix;

		$paths->policy = config('biollante.path.policy', app_path('Policies/')).$namespacePrefix;

		$paths->apiController = config(
			'laravel_generator.path.api_controller',
			app_path('Http/Controllers/API/')
		).$namespacePrefix;

		$paths->apiResource = config(
			'laravel_generator.path.api_resource',
			app_path('Http/Resources/')
		).$namespacePrefix;

		$paths->apiRequest = config(
			'laravel_generator.path.api_request',
			app_path('Http/Requests/API/')
		).$namespacePrefix;

		$paths->apiRoutes = config(
			'laravel_generator.path.api_routes',
			base_path('routes/api.php')
		);

		$paths->appServiceProvider = config(
			'laravel_generator.path.app_service_provider',
			app_path('Providers/AppServiceProvider.php')
		);

		$paths->apiTests = config('biollante.path.api_test', base_path('tests/APIs/'));

		$paths->permissionTests = config('biollante.path.permission_test', base_path('tests/Permissions/'));

		$paths->factory = config('biollante.path.factory', database_path('factories/'));

		$paths->seeder = config('biollante.path.seeder', database_path('seeders/test/'));
		$paths->viewProvider = config(
			'laravel_generator.path.view_provider',
			app_path('Providers/ViewServiceProvider.php')
		);

		$paths->interfaces = config('biollante.path.interfaces', base_path('resources/@client/interfaces/'));

		$paths->constants = config('biollante.path.constant', base_path('resources/@client/constants/'));

		$paths->tips = config('biollante.path.tips', base_path('resources/@client/tips/'));

		$paths->rules = config('biollante.path.rules', base_path('resources/@client/rules/'));

		$this->paths = $paths;
	}

	public function loadNamespaces()
	{
		$prefix = $this->prefixes->namespace;

		if (!empty($prefix)) {
			$prefix = '\\'.$prefix;
		}

		$namespaces = new GeneratorNamespaces();

		$namespaces->app = app()->getNamespace();
		$namespaces->app = substr($namespaces->app, 0, strlen($namespaces->app) - 1);
		$namespaces->repository = config('biollante.namespace.repository', 'Biollante\Repositories').$prefix;
		$namespaces->model = config('biollante.namespace.model', 'Biollante\Models').$prefix;
		$namespaces->policy = config('biollante.namespace.policy', 'Biollante\Policies').$prefix;
		$namespaces->seeder = config('biollante.namespace.seeder', 'Database\Seeders').$prefix;
		$namespaces->factory = config('biollante.namespace.factory', 'Database\Factories').$prefix;
		$namespaces->dataTables = config('biollante.namespace.datatables', 'Biollante\DataTables').$prefix;
		$namespaces->livewireTables = config('biollante.namespace.livewire_tables', 'Biollante\Http\Livewire');
		$namespaces->modelExtend = config(
			'laravel_generator.model_extend_class',
			'Illuminate\Database\Eloquent\Model'
		);

		$namespaces->apiController = config(
			'laravel_generator.namespace.api_controller',
			'Biollante\Http\Controllers\API'
		).$prefix;
		$namespaces->apiResource = config(
			'laravel_generator.namespace.api_resource',
			'Biollante\Http\Resources'
		).$prefix;

		$namespaces->apiRequest = config(
			'laravel_generator.namespace.api_request',
			'Biollante\Http\Requests\API'
		).$prefix;

		$namespaces->request = config(
			'laravel_generator.namespace.request',
			'Biollante\Http\Requests'
		).$prefix;
		$namespaces->requestBase = config('biollante.namespace.request', 'Biollante\Http\Requests');
		$namespaces->baseController = config('biollante.namespace.controller', 'Biollante\Http\Controllers');
		$namespaces->controller = config(
			'laravel_generator.namespace.controller',
			'Biollante\Http\Controllers'
		).$prefix;

		$namespaces->apiTests = config('biollante.namespace.api_test', 'Tests\APIs');
		$namespaces->permissionTests = config('biollante.namespace.permission_test', 'Tests\Permissions');
		$namespaces->repositoryTests = config('biollante.namespace.repository_test', 'Tests\Repositories');
		$namespaces->unitTests = config('biollante.namespace.unit_test', 'Tests\Unit');
		$namespaces->tests = config('biollante.namespace.tests', 'Tests');

		$this->namespaces = $namespaces;
	}

	public function loadTableComment()
	{
		$database = $this->connection ?: config('database.default');
		$schema = config("database.connections.$database.database");

		$tableComment = \DB::selectOne("
			SELECT TABLE_COMMENT as comment
			FROM information_schema.tables
			WHERE table_schema = :schema AND table_name = :table
		", [
			'schema' => $schema,
			'table' => $this->tableName
		]);

		$this->tableComment = $tableComment ? $tableComment->comment : '';
	}

	private function loadHasPermissions()
	{
		$listPermissions = false;
		$displayPermissions = false;
		$updatePermissions = false;
		$removePermissions = false;
	
		foreach ($this->contexts as $context) {
			$varName = $context . "Permissions";
	
			// Initialize as false before checking types
			${$varName} = false;
	
			foreach ($this->types as $type) {
				if (!empty($this->getPermissionsForTable($context, $type))) {
					// If any type (Own or Related) has permissions, set it to true
					${$varName} = true;
					break; // No need to check further
				}
			}
		}
		
		$listPermissions = !empty($this->getPermissionsForTable('list'));
	
		$this->hasPermissions = (object) [
			'listPermissions'   => $listPermissions,
			'displayPermissions' => $displayPermissions,
			'updatePermissions'  => $updatePermissions,
			'removePermissions'  => $removePermissions,
		];
	}

	//TODO: this takes too long, but the pooled version fails Files.
	//When we do, we can also implement this in policy generator blade to check against m2m relationships:
	// @elseif($relationField->path !== '' && $relationField->path === Str::plural(Str::singular($relationField->path)))
	// 							return BiollanteHelper::instance()->safeIssetChain(${{ strtolower($modelName) }}, '{!! $relationField->path !!}') && 
	// 								$relationOrganizer->presideable_type === '{{ucfirst($relationField->type)}}' &&
	// 								in_array($relationOrganizer->presideable_id, ${{ strtolower($modelName) }}->{!! $relationField->path !!}->pluck('id')->toArray(), true); 
	// public function loadRelationFields()
	// {
	// 	$pool = Pool::create();
	// 	$cacheKeys = [];

	// 	foreach ($this->types as $type) {
	// 		foreach ($this->contexts as $context) {
	// 			// fetch roles for this (context,type)
	// 			$roles = $this->getPermissionsForTable($context, $type);
	// 			if (count($roles) === 0) {
	// 				continue;
	// 			}

	// 			$table = $this->tableName;
	// 			$varName = "{$context}" . ($type === 'Own' ? 'Owner' : 'Relation') . "Fields";

	// 			// Avoid nested async: run Related synchronously (resolver already fans out internally)
	// 			if ($type === 'Related') {
	// 				try {
	// 					$fields = \Biollante\Helpers\RoleFieldResolver::resolvePermissionPaths($table, $roles, $type);
	// 					$cacheKey = "role_fields_{$table}_{$type}_{$context}_" . uniqid();
	// 					Cache::put($cacheKey, $fields, now()->addMinutes(10));
	// 					$cacheKeys[$varName] = $cacheKey;
	// 				} catch (\Throwable $e) {
	// 					dd($e);
	// 				}
	// 				continue;
	// 			}

	// 			// Keep Own in the outer pool (resolver is synchronous for Own)
	// 			$pool->add(function () use ($table, $roles, $type, $context) {
	// 				$fields = \Biollante\Helpers\RoleFieldResolver::resolvePermissionPaths($table, $roles, $type);
	// 				$cacheKey = "role_fields_{$table}_{$type}_{$context}_" . uniqid();
	// 				Cache::put($cacheKey, $fields, now()->addMinutes(10));
	// 				return $cacheKey;
	// 			})->then(function ($cacheKey) use (&$cacheKeys, $varName) {
	// 				$cacheKeys[$varName] = $cacheKey;
	// 			})->catch(function ($exception) {
	// 				dd($exception);
	// 			});
	// 		}
	// 	}

	// 	$pool->wait();

	// 	$this->relationFields = (object) [
	// 		'displayOwnerFields'    => Cache::get($cacheKeys['displayOwnerFields'] ?? '', []),
	// 		'updateOwnerFields'     => Cache::get($cacheKeys['updateOwnerFields'] ?? '', []),
	// 		'removeOwnerFields'     => Cache::get($cacheKeys['removeOwnerFields'] ?? '', []),
	// 		'displayRelationFields' => Cache::get($cacheKeys['displayRelationFields'] ?? '', []),
	// 		'updateRelationFields'  => Cache::get($cacheKeys['updateRelationFields'] ?? '', []),
	// 		'removeRelationFields'  => Cache::get($cacheKeys['removeRelationFields'] ?? '', []),
	// 	];
	// }

	public function loadRelationFields()
	{
		$pool = Pool::create();
		$cacheKeys = [];
	
		foreach ($this->types as $type) {
			foreach ($this->contexts as $context) {
				$roles = $this->getPermissionsForTable($context, $type);
				// fresh dump($roles);
				if (count($roles) > 0) {
					$table = $this->tableName;
					$varName = "{$context}" . ($type === 'Own' ? 'Owner' : 'Relation') . "Fields";
	
					$pool->add(function () use ($table, $roles, $type, $context) {
						// Generate fields
						$fields = RoleFieldResolver::resolvePermissionPaths($table, $roles, $type);
						
						// Store in cache with a unique key
						$cacheKey = "role_fields_{$table}_{$type}_{$context}_" . uniqid();
						Cache::put($cacheKey, $fields, now()->addMinutes(10));
	
						// Return cache key
						return $cacheKey;
					})->then(function ($cacheKey) use (&$cacheKeys, $varName) {
						$cacheKeys[$varName] = $cacheKey;
					})->catch(function ($exception) {
						dd($exception);
					});
				}
			}
		}
	
		$pool->wait();

		$this->relationFields = (object) [
			'displayOwnerFields'    => Cache::get($cacheKeys['displayOwnerFields'] ?? '', []),
			'updateOwnerFields'     => Cache::get($cacheKeys['updateOwnerFields'] ?? '', []),
			'removeOwnerFields'     => Cache::get($cacheKeys['removeOwnerFields'] ?? '', []),
			'displayRelationFields' => Cache::get($cacheKeys['displayRelationFields'] ?? '', []),
			'updateRelationFields'  => Cache::get($cacheKeys['updateRelationFields'] ?? '', []),
			'removeRelationFields'  => Cache::get($cacheKeys['removeRelationFields'] ?? '', []),
		];
	}

	// Return just the roles with relevant permissions
	public function getPermissionsForTable(string $context, string $which = ' '): array
	{
		$tableName = $this->tableName;
		$roles = Role::with('permissions')
			->get()
			->mapWithKeys(function ($role) use ($tableName, $context, $which) {
				// fresh dump($role->name);
				// fresh dump($context);
				// fresh dump($which);
				$permissions = $role->permissions
					->pluck('name')
					->filter(fn($permission) => 
						Str::startsWith($permission, $context) && 
						(
							( //neither 'Related' or 'Own'
								!Str::contains($permission, 'Own ') &&
								!Str::contains($permission, 'Related ')
							) ||
							Str::contains($permission, $which)
						) && 
						Str::contains($permission, $tableName)
					)
					->toArray();
				return !empty($permissions) ? [$role->name => $permissions] : [];
			})
		->keys()
		->toArray();

		return $roles; 
	}

	public function prepareTable()
	{
		if ($this->getOption('table')) {
			$this->tableName = $this->getOption('table');
		} else {
			$this->tableName = $this->modelNames->snakePlural;
		}

		$this->primaryName = 'id';
	}

	public function prepareOptions()
	{
		$options = new GeneratorOptions();

		$options->softDelete = config('biollante.options.soft_delete', false);
		$options->saveSchemaFile = config('biollante.options.save_schema_file', true);
		$options->localized = config('biollante.options.localized', false);
		$options->repositoryPattern = config('biollante.options.repository_pattern', true);
		$options->resources = config('biollante.options.resources', false);
		$options->factory = config('biollante.options.factory', false);
		$options->seeder = config('biollante.options.seeder', false);
		$options->databaseSeeder = config('biollante.options.database_seeder', false);
		$options->swagger = config('biollante.options.swagger', false);
		$options->tests = config('biollante.options.tests', false);
		$options->excludedFields = config('biollante.options.excluded_fields', ['id']);
		$options->excludedTables = config('biollante.options.excluded_tables', []);

		$this->options = $options;
	}

	public function getOption($option)
	{
		return $this->command->option($option);
	}

	public function commandError($error)
	{
		$this->command->error($error);
	}

	public function commandComment($message)
	{
		$this->command->comment($message);
	}

	public function commandWarn($warning)
	{
		$this->command->warn($warning);
	}

	public function commandInfo($message)
	{
		$this->command->info($message);
	}
}
