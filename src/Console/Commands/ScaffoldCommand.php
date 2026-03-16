<?php

namespace Biollante\Console\Commands;

use Biollante\Helpers\BiollanteHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Biollante\Generator\Common\GeneratorConfig;
use Biollante\Generator\Common\GeneratorField;
use Biollante\Generator\Common\GeneratorFieldRelation;
use Biollante\Generator\Events\GeneratorFileCreated;
use Biollante\Generator\Events\GeneratorFileCreating;
use Biollante\Generator\Events\GeneratorFileDeleted;
use Biollante\Generator\Events\GeneratorFileDeleting;
use Biollante\Generator\Generators\API\APIControllerGenerator;
use Biollante\Generator\Generators\API\APIRequestGenerator;
use Biollante\Generator\Generators\API\APIResourceGenerator;
use Biollante\Generator\Generators\API\APIRoutesGenerator;
use Biollante\Generator\Generators\API\APITestGenerator;
use Biollante\Generator\Generators\FactoryGenerator;
use Biollante\Generator\Generators\InterfacesGenerator;
use Biollante\Generator\Generators\ModelGenerator;
use Biollante\Generator\Generators\PermissionTestGenerator;
use Biollante\Generator\Generators\PolicyGenerator;
use Biollante\Generator\Generators\RepositoryGenerator;
use Biollante\Generator\Generators\RepositoryTestGenerator;
use Biollante\Generator\Generators\UnitTestGenerator;
use Biollante\Generator\Generators\SeederGenerator;
use Biollante\Generator\Generators\TipsGenerator;
use Biollante\Generator\Generators\RulesGenerator;
use Biollante\Generator\Utils\GeneratorFieldsInputUtil;
use Biollante\Generator\Utils\TableFieldsGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\VarExporter\VarExporter;

class ScaffoldCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'make:scaffold {model?} {--dump-script} {--table : Table Name, if different from model} {--ignoreFields : Fields to ignore while generating from table} {--skip : Steps to skip (model,policy,repository,tests,api_controller,api_requests,api_routes,dump-autoload,interfaces,tips,rules)}';
 
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create MCR API scaffold for a given object (if any), or all objects.';

	public GeneratorConfig $config;

	public Composer $composer;

	public function __construct()
	{
		parent::__construct();

		$this->composer = app()['composer'];
	}

	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function handle()
	{
		if ($this->option('dump-script')) {
			$tables = $this->getDatabaseTables();
			$eligibleTables = $this->filterEligibleTables($tables);
	
			$commands = [];
	
			foreach ($eligibleTables as $table) {
				$model = Str::studly(Str::singular($table));
				$logFile = "logs/scaffold_{$model}.log";
				$commands[] = "(nohup sail artisan make:scaffold {$model} > {$logFile} 2>&1 &)";
			}
	
			$script = implode(" && \\\n", $commands);
			$this->info("\nCopy & Paste the following command into your terminal:\n");
			$this->line($script);
			return;
		}

		if(!$this->argument('model')){
			$tables = $this->getDatabaseTables();
			$eligibleTables = $this->filterEligibleTables($tables);

			foreach ($eligibleTables as $table) {
				$this->comment("Beginning scaffolding generation for table: $table");

				$this->input->setOption('table', $table);
				$this->input->setArgument('model', Str::studly(Str::singular($table)));

				$this->config = app(GeneratorConfig::class);
				$this->config->setCommand($this);
		
				$this->config->init();
				$this->getFields();
		
				$this->fireFileCreatingEvent('scaffold');
				$this->generateItems();
				
				$this->comment("Completed scaffolding generation for table: $table");
				$this->info('');
			}
			$this->performPostActions();
			$this->fireFileCreatedEvent('scaffold');
		}else{
			$this->config = app(GeneratorConfig::class);
			$this->config->setCommand($this);
	
			$this->config->init();
			$this->getFields();
	
			$this->fireFileCreatingEvent('scaffold');
			$this->generateItems();
			$this->performPostActions();
			$this->fireFileCreatedEvent('scaffold');
		}
		$this->performWrapupActions();
	}

	public function generateItems()
	{
		if (!$this->isSkip('model')) {
			$modelGenerator = new ModelGenerator($this->config);
			$modelGenerator->generate();
		}

		if (!$this->isSkip('policy')) {
			$policyGenerator = new PolicyGenerator($this->config);
			$policyGenerator->generate();
		}

		if (!$this->isSkip('repository') && $this->config->options->repositoryPattern) {
			$repositoryGenerator = new RepositoryGenerator($this->config);
			$repositoryGenerator->generate();
		}
		
		if ($this->config->options->factory || (!$this->isSkip('tests') && $this->config->options->tests)) {
			$factoryGenerator = new FactoryGenerator($this->config);
			$factoryGenerator->generate();
		}

		if ($this->config->options->seeder) {
			$seederGenerator = new SeederGenerator($this->config);
			$seederGenerator->generate();
		}
		
		if (!$this->isSkip('api_requests')) {
			$requestGenerator = new APIRequestGenerator($this->config);
			$requestGenerator->generate();
		}

		if (!$this->isSkip('api_controller')) {
			$controllerGenerator = new APIControllerGenerator($this->config);
			$controllerGenerator->generate();
		}

		if (!$this->isSkip('api_routes')) {
			$routesGenerator = new APIRoutesGenerator($this->config);
			$routesGenerator->generate();
		}

		if (!$this->isSkip('tests') and $this->config->options->tests) {
			$permissionTestGenerator = new PermissionTestGenerator($this->config);
			$permissionTestGenerator->generate();

			$apiTestGenerator = new APITestGenerator($this->config);
			$apiTestGenerator->generate();

			if ($this->config->options->repositoryPattern) {
				$repositoryTestGenerator = new RepositoryTestGenerator($this->config);
				$repositoryTestGenerator->generate();
			}

			$unitTestGenerator = new UnitTestGenerator($this->config);
			$unitTestGenerator->generate();
		}

		if ($this->config->options->resources) {
			$apiResourceGenerator = new APIResourceGenerator($this->config);
			$apiResourceGenerator->generate();
		}
		
		if (!$this->isSkip('interfaces')) {
			$interfacesGenerator = new InterfacesGenerator($this->config);
			$interfacesGenerator->generate();
		}
		
		if (!$this->isSkip('tips')) {
			$tipsGenerator = new TipsGenerator($this->config);
			$tipsGenerator->generate();
		}
		
		if (!$this->isSkip('rules')) {
			$rulesGenerator = new RulesGenerator($this->config);
			$rulesGenerator->generate();
		}
	}

	public function performPostActions()
	{
		if ($this->config->options->saveSchemaFile) {
			$this->saveSchemaFile();
		}

		if ($this->config->options->localized) {
			$this->saveLocaleFile();
		}

		if (!$this->isSkip('dump-autoload')) {
			$this->info('Generating autoload files and refereshing the cache');
			\Artisan::call('config:clear');
			\Artisan::call('cache:clear');
			\Artisan::call('route:clear');
			\Artisan::call('view:clear');
			$this->composer->dumpOptimized();
		}
	}

	public function performWrapupActions()
	{
		$this->info('Generating Swagger documentation');
		try {
			\Artisan::call('l5-swagger:generate');
			$this->comment('Swagger documentation generated successfully.');
		} catch (\Exception $e) {
			$this->error('Failed to generate Swagger documentation: ' . $e->getMessage());
		}

		$this->info('Generating IDE Helpers');
		try {
			\Artisan::call('ide-helper:generate');
			\Artisan::call('ide-helper:models --nowrite');
			\Artisan::call('ide-helper:meta');
			$this->comment('IDE helpers generated successfully.');
		} catch (\Exception $e) {
			$this->error('Failed to generate IDE helper: ' . $e->getMessage());
		}
	}

	public function isSkip($skip): bool
	{
		if ($this->option('skip')) {
			return in_array($skip, explode(',', $this->option('skip') ?? ''));
		}

		return false;
	}

	protected function saveSchemaFile()
	{
		$fileFields = [];
		
		foreach ($this->config->fields as $field) {
			if ($field->fieldDetails->is_virtual) {
				continue;
			}
			$fileFields[] = [
				'name'		=> $field->name,
				'dbType'	  => $field->dbType,
				'htmlType'	=> $field->htmlType,
				'validations' => $field->validations,
				'searchable'  => $field->isSearchable,
				'fillable'	=> $field->isFillable,
				'primary'	 => $field->isPrimary,
				'inForm'	  => $field->inForm,
				'inIndex'	 => $field->inIndex,
				'inView'	  => $field->inView,
			];
		}

		foreach ($this->config->relations as $relation) {
			// Check if the third input exists and is an array, and if so, convert it to a string
			$inputs = $relation->inputs;
			if (isset($inputs[2]) && is_array($inputs[2])) {
				$inputs[2] = implode(':', $inputs[2]);
			}
			
			$fileFields[] = [
				'type' => 'relation',
				'relation' => $relation->type.','.implode(',', $inputs),
			];
		}
		
		$path = config('laravel_generator.path.schema_files', resource_path('model_schemas/'));

		$fileName = $this->config->modelNames->name.'.json';
		
		BiollanteHelper::instance()->g_filesystem()->createFile($path.$fileName, json_encode($fileFields, JSON_PRETTY_PRINT));
		$this->comment("\nSchema File saved: ");
		$this->info($fileName);
	}

	protected function saveLocaleFile()
	{
		$locales = [
			'singular' => $this->config->modelNames->name,
			'plural'   => $this->config->modelNames->plural,
			'fields'   => [],
		];

		foreach ($this->config->fields as $field) {
			$locales['fields'][$field->name] = Str::title(str_replace('_', ' ', $field->name));
		}

		$path = lang_path('en/models/');

		$fileName = $this->config->modelNames->snakePlural.'.php';

		$locales = VarExporter::export($locales);
		$end = ';'.BiollanteHelper::instance()->format_nl();
		$content = "<?php\n\nreturn ".$locales.$end;
		BiollanteHelper::instance()->g_filesystem()->createFile($path.$fileName, $content);
		$this->comment("\nModel Locale File saved.");
		$this->info($fileName);
	}
	
/**
 * Get all database tables.
 *
 * @return array
 */
protected function getDatabaseTables(): array
{
	$connection = $this->config->connection ?? config('database.default');
	$databaseName = \DB::connection($connection)->getDatabaseName();

	return \DB::connection($connection)
		->table('information_schema.tables')
		->where('table_schema', $databaseName)
		->select('table_name as name') // Ensure compatibility with property naming
		->pluck('name')
		->toArray();
}


	/**
	 * Filter tables eligible for scaffolding.
	 *
	 * @param array $tables
	 * @return array
	 */
	protected function filterEligibleTables(array $tables): array
	{
		return array_filter($tables, function ($table) {
			// Example: Exclude pivot tables and tables handled by packages
			return Str::endsWith($table, ['s']) &&
				!in_array($table, config('laravel_generator.options.excluded_tables', [])) &&
				!in_array($table, [
					'cache', 
					'cache_locks', 
					'failed_jobs', 
					'jobs', 
					'job_batches', 
					'levy_pass', 
					'migrations', 
					'model_has_permissions', 
					'model_has_roles', 
					'password_reset_tokens',
					'permissions',
					'personal_access_tokens',
					'promocode_pass',
					'roles',
					'role_has_permissions',
				]);
		});
	}


	public function getFields()
	{
		$this->config->fields = [];
		$tableName = $this->config->tableName;
		
		$ignoredFields = array_key_exists('ignoreFields', $this->option()) ? $this->option('ignoreFields') : null;
		if (!empty($ignoredFields)) {
			$ignoredFields = explode(',', trim($ignoredFields));
		} else {
			$ignoredFields = [];
		}

		$tableFieldsGenerator = new TableFieldsGenerator($tableName, $ignoredFields, $this->config->connection);
		$tableFieldsGenerator->prepareFieldsFromTable();
		$tableFieldsGenerator->prepareRelations();
		
		$this->config->fields = $tableFieldsGenerator->fields;
		$this->config->relations = $tableFieldsGenerator->relations;
	}

	private function prepareEventsData(): array
	{
		return [
			'modelName' => $this->config->modelNames->name,
			'tableName' => $this->config->tableName,
			'nsModel'   => $this->config->namespaces->model,
		];
	}

	public function fireFileCreatingEvent($commandType)
	{
		event(new GeneratorFileCreating($commandType, $this->prepareEventsData()));
	}

	public function fireFileCreatedEvent($commandType)
	{
		event(new GeneratorFileCreated($commandType, $this->prepareEventsData()));
	}

	public function fireFileDeletingEvent($commandType)
	{
		event(new GeneratorFileDeleting($commandType, $this->prepareEventsData()));
	}

	public function fireFileDeletedEvent($commandType)
	{
		event(new GeneratorFileDeleted($commandType, $this->prepareEventsData()));
	}
}
