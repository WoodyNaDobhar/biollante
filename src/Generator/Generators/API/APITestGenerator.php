<?php

namespace Biollante\Generator\Generators\API;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Generator\Generators\BaseGenerator;
use Biollante\Generator\Common\GeneratorConfig;

class APITestGenerator extends BaseGenerator
{
	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->apiTests;
		$this->fileName = $this->config->modelNames->name.'ApiTest.php';
	}

	public function variables(): array
	{
		return [
			'config' => $this->config,
		];
	}

	public function generate()
	{
		$templateData = view('biollante::generator.api.test.api_test', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->fileName, $templateData);

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'ApiTest created: ');
		$this->config->commandInfo($this->fileName);
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('API Test file deleted: '.$this->fileName);
		}
	}
}
