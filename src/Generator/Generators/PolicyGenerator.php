<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Helpers\RoleFieldResolver;
use Illuminate\Support\Str;
use Biollante\Generator\Utils\TableFieldsGenerator;
use Biollante\Generator\Common\GeneratorConfig;

class PolicyGenerator extends BaseGenerator
{

	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->policy;
		$this->fileName = $this->config->modelNames->name.'Policy.php';
	}

	public function generate()
	{
		$templateData = view('biollante::generator.policy.policy', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->fileName, $templateData);

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Policy created: ');
		$this->config->commandInfo($this->fileName);
	}

	public function variables(): array
	{
		$dedupeByPath = fn($fields) => collect($fields)->unique('path')->values()->all();
		return [
			'config'				=> $this->config,
			'modelName'				=> $this->config->modelNames->name,
			'hasListPermissions' 	=> $this->config->hasPermissions->listPermissions,
			'hasDisplayPermissions' => $this->config->hasPermissions->displayPermissions,
			'hasUpdatePermissions'  => $this->config->hasPermissions->updatePermissions,
			'hasRemovePermissions'  => $this->config->hasPermissions->removePermissions,
			'displayOwnerFields'	=> $dedupeByPath($this->config->relationFields->displayOwnerFields),
			'updateOwnerFields'		=> $dedupeByPath($this->config->relationFields->updateOwnerFields),
			'removeOwnerFields'		=> $dedupeByPath($this->config->relationFields->removeOwnerFields),
			'displayRelationFields'	=> $dedupeByPath($this->config->relationFields->displayRelationFields),
			'updateRelationFields'	=> $dedupeByPath($this->config->relationFields->updateRelationFields),
			'removeRelationFields'	=> $dedupeByPath($this->config->relationFields->removeRelationFields),
		];
	}	

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('Policy file deleted: '.$this->fileName);
		}
	}
}
