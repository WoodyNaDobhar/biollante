<?php

namespace Biollante\Generator\Generators\API;

use Biollante\Helpers\BiollanteHelper;
use Illuminate\Support\Str;
use Biollante\Generator\Generators\BaseGenerator;
use Biollante\Generator\Generators\ModelGenerator;
use Biollante\Generator\Common\GeneratorConfig;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class APIControllerGenerator extends BaseGenerator
{
	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->apiController;
		$this->fileName = $this->config->modelNames->name.'APIController.php';
	}

	public function variables(): array
	{
		return array_merge(['config' => $this->config], $this->docsVariables());
	}

	public function getViewName(): string
	{
		if ($this->config->options->repositoryPattern) {
			$templateName = 'repository.controller';
		} else {
			$templateName = 'model.controller';
		}

		if ($this->config->options->resources) {
			$templateName .= '_resource';
		}

		return $templateName;
	}

	public function generate()
	{
		$viewName = $this->getViewName();

		$templateData = view('biollante::generator.api.controller.'.$viewName, $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->fileName, $templateData);

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'API Controller created: ');
		$this->config->commandInfo($this->fileName);
	}

	protected function docsVariables(): array
	{
		$methods = ['controller', 'index', 'store', 'show', 'update', 'destroy'];

		$modelGen = new ModelGenerator($this->config);
		$relationsText = $modelGen->generateDescription(1);

		if ($this->config->options->swagger) {
			$templatePrefix = 'controller';
			$templateType = 'biollante::generator.swagger';
		} else {
			$templatePrefix = 'api.docs.controller';
			$templateType = 'generator';
		}

		// Load organizer roles from config
		$organizerRoles = config('biollante.roles.organizer_roles', []);

		$variables = [];
		foreach ($methods as $method) {
			
			$permVisitors = 'None';
			$permUsers = 'None';
			$permAdmins = 'None';
			$routeKeyVars = $this->swaggerRouteKeyVars();

			// Build organizer permissions dynamically
			$organizerPermissions = [];
			foreach ($organizerRoles as $role) {
				$organizerPermissions[$role] = 'None';
			}
			
			if($method !== 'controller'){
				$permVisitors = $this->generatePermissions(null, $method);
				$permUsers = $this->generatePermissions('User', $method);
				$permAdmins = $this->generatePermissions('Admin', $method);

				foreach ($organizerRoles as $role) {
					$organizerPermissions[$role] = $this->generatePermissions($role . ' Organizer', $method);
				}
			}

			$variable = 'doc' . Str::title($method);
			$variables[$variable] = view($templateType . '.' . $templatePrefix . '.' . $method, array_merge([
				'config'					=> $this->config,
				'permVisitors'				=> $permVisitors,
				'permUsers'					=> $permUsers,
				'permAdmins'				=> $permAdmins,
				'organizerPermissions'		=> $organizerPermissions,
				'relations'					=> $relationsText
			], $routeKeyVars))->render();
		}

		return $variables;
	}

	public function generatePermissions($role = null, $method = null): string
	{
		$tableName = $this->config->tableName;

		// Determine the context of the permission based on the method
		$context = match ($method) {
			'index' => 'list',
			'store' => 'store',
			'show' => 'display',
			'update' => 'update',
			'destroy' => 'remove',
			default => null,
		};

		if (!$context) {
			return 'None'; // No valid method context provided
		}

		$fullPermission = "{$context} {$tableName}";
		$ownPermission = "{$context}Own {$tableName}";
		$relatedPermission = "{$context}Related {$tableName}";
		$permissionsArray = [
			$fullPermission,
			$ownPermission,
			$relatedPermission,
		];

		//if nobody has the permission (or derivitives), everyone has it.  Check permissions for any entries
		$hasPerms = Permission::whereIn('name', $permissionsArray)
			->whereHas('roles')
			->exists();

		if(!$hasPerms){
			return 'Full';
		}

		// Check for Full permission
		if ($role === 'Admin' || $this->hasRolePermission($role, $fullPermission)) {
			return 'Full';
		}

		// Check for Own permission
		if ($this->hasRolePermission($role, $ownPermission)) {
			return 'Own';
		}

		// Check for Related permission
		if ($this->hasRolePermission($role, $relatedPermission)) {
			return 'Related';
		}

		// If none of the above, return None
		return 'None';
	}
	
	// Helper to check if the role has a specific permission
	protected function hasRolePermission($role, $permission): bool
	{
		if ($role) {
			return Role::where('name', $role)
				->whereHas('permissions', function ($query) use ($permission) {
					$query->where('name', $permission);
				})
				->exists();
		}

		return false;
	}

	protected function hasSlugField(): bool
	{
		if (!isset($this->config->fields) || !is_iterable($this->config->fields)) {
			return false;
		}

		foreach ($this->config->fields as $field) {
			if (
				$field &&
				($field->name ?? null) === 'slug'
			) {
				return true;
			}
		}

		return false;
	}

	protected function swaggerRouteKeyVars(): array
	{
		$routeKeyName = $this->hasSlugField() ? 'slug' : 'id';
		$routeKeyType = $this->hasSlugField() ? 'string' : 'integer';
		$routeKeyDescription = $this->hasSlugField()
			? 'The slug of the ' . $this->config->modelNames->name
			: 'The ID of the ' . $this->config->modelNames->name;
		$routeKeyExample = $this->hasSlugField() ? 'example-slug' : 1;
		$routeKeyExampleIsString = $this->hasSlugField();

		return [
			'routeKeyName' => $routeKeyName,
			'routeKeyType' => $routeKeyType,
			'routeKeyDescription' => $routeKeyDescription,
			'routeKeyExample' => $routeKeyExample,
			'routeKeyExampleIsString' => $routeKeyExampleIsString,
		];
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('API Controller file deleted: '.$this->fileName);
		}
	}
}