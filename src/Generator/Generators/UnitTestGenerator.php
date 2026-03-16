<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Generator\Common\GeneratorConfig;

class UnitTestGenerator extends BaseGenerator
{
	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = config('laravel_generator.path.unit_test', base_path('tests/Units/'));
		$this->fileName = $this->config->modelNames->name.'UnitTest.php';
	}

	public function variables(): array
	{
		return [
			'config'	=> $this->config,
		];
	}

	public function generate()
	{
		if (file_exists($this->path . $this->fileName)) {
			$this->config->commandComment('UnitTest already exists: ' . $this->fileName);
			return;
		}else{
			$templateData = view('biollante::generator.model.unit_test', $this->variables())->render();

			BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->fileName, $templateData);

			$this->config->commandComment(BiollanteHelper::instance()->format_nl().'UnitTest created: ');
			$this->config->commandInfo($this->fileName);
		}
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('Unit Test file deleted: '.$this->fileName);
		}
	}
}
