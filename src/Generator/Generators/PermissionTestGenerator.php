<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Helpers\RoleFieldResolver;
use Illuminate\Support\Str;
use Biollante\Generator\Utils\TableFieldsGenerator;
use Biollante\Generator\Common\GeneratorConfig;
use Spatie\Async\Pool;
use Spatie\Permission\Models\Role;

class PermissionTestGenerator extends BaseGenerator
{

	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = config('biollante.path.permission_test', base_path('tests/Permissions/'));
		$this->fileName = $this->config->modelNames->name.'PermissionsTest.php';
	}

	public function variables(): array
	{
		return [
			'config'		=> $this->config,
			'modelName'		=> $this->config->modelNames->name,
			'camelName'		=> $this->config->modelNames->camel,
			'relationPaths' => $this->getRelationPaths(),
			'ownPaths' 		=> $this->getOwnPaths(),
			'roles'			=> $this->getRoles()
		];
	}

	public function generate()
	{
		$templateData = view('biollante::generator.policy.permission_test', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->fileName, $templateData);

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Permission created: ');
		$this->config->commandInfo($this->fileName);
	}

	public function getRelationPaths()
	{
		$allRelationPaths = [];
		$table = $this->config->tableName;
		
		foreach ($this->config->relationFields as $relationField) {
			$paths = array_map(function ($field) use ($table) {
				// Reject paths ending in 'user_id', 'user', or 'users'
				if (
					$field->type === 'user' ||
					$field->path === ''
				) {
					return null;
				}

				// If field path ends with '<table>_id', truncate it
				if (Str::endsWith($field->path, Str::singular(lcfirst($table)) . '_id')) {
					$pathArray = explode('->', $field->path);
					$field->path = $pathArray[0];
				}

				return $field->path;
			}, $relationField);

			// Filter out only null values, not empty strings
			$paths = array_filter($paths, fn($p) => !is_null($p));

			$allRelationPaths = array_merge($allRelationPaths, $paths);
		}

		// Remove duplicate paths
		$uniqueRelationPaths = array_unique($allRelationPaths);
		sort($uniqueRelationPaths);

		return $uniqueRelationPaths;
	}

	public function getOwnPaths()
	{
		$allRelationPaths = [];
		$table = $this->config->tableName;
		
		foreach ($this->config->relationFields as $relationField) {
			$paths = array_map(function ($field) use ($table) {
				// Reject where path isn't empty and isn't of type 'user'
				if ($field->type !== 'user' &&
					$field->path !== ''
				) {
					return null;
				}

				// I dunno if this is right, but I believe paths that are more than two items long should be rejected
				if(count(explode('->', $field->path)) > 2) {
					return null;
				}

				// If field path ends with '<table>_id', truncate it
				if (Str::endsWith($field->path, Str::singular(lcfirst($table)) . '_id')) {
					$pathArray = explode('->', $field->path);
					$field->path = $pathArray[0];
				}

				return $field->path;
			}, $relationField);
			
			// Filter out only null values, not empty strings
			$paths = array_filter($paths, fn($p) => !is_null($p));

			$allRelationPaths = array_merge($allRelationPaths, $paths);
		}

		// Remove duplicate paths
		$uniqueRelationPaths = array_unique($allRelationPaths);
		sort($uniqueRelationPaths);

		return $uniqueRelationPaths;
	}

	public function getRoles(){
		$roles = Role::all();

		// Start with "Visitor" as a special case
		$rolesArray = [
			'Visitor' => null
		];

		// Add each role to the array with its name as the index
		foreach ($roles as $role) {
			$rolesArray[$role->name] = "Role::findByName('{$role->name}')";
		}

		return $rolesArray;
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('Permission file deleted: '.$this->fileName);
		}
	}
}
