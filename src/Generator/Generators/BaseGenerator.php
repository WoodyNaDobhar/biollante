<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Generator\Common\GeneratorConfig;

abstract class BaseGenerator
{
	public GeneratorConfig $config;

	public string $path;

	public function __construct()
	{
		$this->config = app(GeneratorConfig::class);
	}

	public function initializeConfig(Command $command): void
	{
		$this->config->setCommand($command); // Sets the command reference
		$this->config->init(); // Initializes config after setting command
	}

	public function rollbackFile($path, $fileName): bool
	{
		if (file_exists($path.$fileName)) {
			return BiollanteHelper::instance()->g_filesystem()->deleteFile($path, $fileName);
		}

		return false;
	}

	public function variables(): array
	{
		return [];
	}
}
