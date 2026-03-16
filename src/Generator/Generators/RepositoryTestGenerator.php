<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Generator\Common\GeneratorConfig;

class RepositoryTestGenerator extends BaseGenerator
{
	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = config('laravel_generator.path.repository_test', base_path('tests/Repositories/'));
		$this->fileName = $this->config->modelNames->name.'RepositoryTest.php';
	}

	public function variables(): array
	{
		return [
			'config'	=> $this->config,
		];
	}

	public function generate()
	{
		$templateData = view('biollante::generator.repository.repository_test', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->fileName, $templateData);

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'RepositoryTest created: ');
		$this->config->commandInfo($this->fileName);
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('Repository Test file deleted: '.$this->fileName);
		}
	}
}
