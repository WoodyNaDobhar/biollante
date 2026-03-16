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

		$variables = [];
		foreach ($methods as $method) {
			
			$permVisitors = 'None';
			$permUsers = 'None';
			$permChapterOrganizers = 'None';
			$permCollectiveOrganizers = 'None';
			$permTeamOrganizers = 'None';
			$permThreadOrganizers = 'None';
			$permVendorOrganizers = 'None';
			$permWorldOrganizers = 'None';
			$permAdmins = 'None';
			$routeKeyVars = $this->swaggerRouteKeyVars();
			
			if($method !== 'controller'){
				$permVisitors = $this->generatePermissions(null, $method);
				$permUsers = $this->generatePermissions('User', $method);
				$permChapterOrganizers = $this->generatePermissions('Chapter Organizer', $method);
				$permCollectiveOrganizers = $this->generatePermissions('Collective Organizer', $method);
				$permTeamOrganizers = $this->generatePermissions('Team Organizer', $method);
				$permThreadOrganizers = $this->generatePermissions('Thread Organizer', $method);
				$permVendorOrganizers = $this->generatePermissions('Vendor Organizer', $method);
				$permWorldOrganizers = $this->generatePermissions('World Organizer', $method);
				$permAdmins = $this->generatePermissions('Admin', $method);
			}

			$variable = 'doc' . Str::title($method);
			$variables[$variable] = view($templateType . '.' . $templatePrefix . '.' . $method, array_merge([
				'config'					=> $this->config,
				'permVisitors'				=> $permVisitors,
				'permUsers'					=> $permUsers,
				'permChapterOrganizers'		=> $permChapterOrganizers,
				'permCollectiveOrganizers'	=> $permCollectiveOrganizers,
				'permTeamOrganizers'		=> $permTeamOrganizers,
				'permThreadOrganizers'		=> $permThreadOrganizers,
				'permVendors'				=> $permVendorOrganizers,
				'permWorldOrganizers'		=> $permWorldOrganizers,
				'permAdmins'				=> $permAdmins,
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
				($field->name ?? null) === 'slug' &&
				isset($field->fieldDetails) &&
				empty($field->fieldDetails->is_virtual)
			) {
				return true;
			}
		}

		return false;
	}

	protected function swaggerRouteKeyVars(): array
	{
		$hasSlug = $this->hasSlugField();

		return [
			// what your api routes now use: /accounts/{account}
			'routeKeyName' => $this->config->modelNames->camel,

			// swagger schema type for the path param
			'routeKeyType' => $hasSlug ? 'string' : 'integer',

			// lets swagger show the truth
			'routeKeyDescription' => ($hasSlug ? 'ID or slug of ' : 'ID of ') . $this->config->modelNames->name,

			// used for correct quoting in blade
			'routeKeyExample' => $hasSlug ? 'example-slug' : '42',
			'routeKeyExampleIsString' => $hasSlug,
		];
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('API Controller file deleted: '.$this->fileName);
		}
	}
}
