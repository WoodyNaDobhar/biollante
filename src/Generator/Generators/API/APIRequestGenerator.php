<?php

namespace Biollante\Generator\Generators\API;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Generator\Generators\BaseGenerator;
use Biollante\Generator\Common\GeneratorConfig;

class APIRequestGenerator extends BaseGenerator
{
	private string $createFileName;

	private string $updateFileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->apiRequest;
		$this->createFileName = 'Create'.$this->config->modelNames->name.'APIRequest.php';
		$this->updateFileName = 'Update'.$this->config->modelNames->name.'APIRequest.php';
	}

	public function variables(): array
	{
		return [
			'config'		   => $this->config,
		];
	}

	public function generate()
	{
		$this->generateCreateRequest();
		$this->generateUpdateRequest();
	}

	protected function generateCreateRequest()
	{
		$templateData = view('biollante::generator.api.request.create', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->createFileName, $templateData);

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Create Request created: ');
		$this->config->commandInfo($this->createFileName);
	}

	protected function generateUpdateRequest()
	{	
		$templateData = view('biollante::generator.api.request.update', [
			'config'	  => $this->config,
		])->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->updateFileName, $templateData);

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Update Request created: ');
		$this->config->commandInfo($this->updateFileName);
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->createFileName)) {
			$this->config->commandComment('Create API Request file deleted: '.$this->createFileName);
		}

		if ($this->rollbackFile($this->path, $this->updateFileName)) {
			$this->config->commandComment('Update API Request file deleted: '.$this->updateFileName);
		}
	}
}
