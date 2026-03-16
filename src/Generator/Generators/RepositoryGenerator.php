<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Generator\Common\GeneratorConfig;

class RepositoryGenerator extends BaseGenerator
{
	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->repository;
		$this->fileName = $this->config->modelNames->name.'Repository.php';
	}

	public function variables(): array
	{
		return [
			'config'		  => $this->config,
			'fieldSearchable' => $this->getSearchableFields(),
		];
	}

	public function generate()
	{
		$templateData = view('biollante::generator.repository.repository', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->fileName, $templateData);

		$this->updateAppServiceProvider();

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Repository created: ');
		$this->config->commandInfo($this->fileName);

		// Generate repository extension (once)
		$vars = $this->variables();
		$extensionPath = rtrim(($this->config->paths->repository ?? $this->path), '/').'/Extensions/';
		$extensionFileName = $this->config->modelNames->name.'RepositoryExtension.php';
		$extensionFilePath = $extensionPath.$extensionFileName;
		if (!file_exists($extensionFilePath)) {
			$extensionTemplateData = view('biollante::generator.repository.extension', $vars)->render();
			BiollanteHelper::instance()->g_filesystem()->createFile($extensionFilePath, $extensionTemplateData);
			$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Repository Extension created: ');
			$this->config->commandInfo($extensionFileName);
		} else {
			$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Repository Extension already exists, skipping: ');
			$this->config->commandInfo($extensionFileName);
		}
	}

	public function updateAppServiceProvider()
	{
		$appServiceProviderPath = $this->config->paths->appServiceProvider;
		$repositoryBind = '		$this->app->bind(' . $this->config->modelNames->name . 'Repository::class, ' . $this->config->modelNames->name . 'RepositoryExtension::class);' . BiollanteHelper::instance()->format_nl();

		// Check if the file exists
		if (!file_exists($appServiceProviderPath)) {
			throw new \Exception('AppServiceProvider file not found at: ' . $appServiceProviderPath);
		}

		// Read the file contents
		$contents = file_get_contents($appServiceProviderPath);

		// Check if the bind already exists
		if (strpos($contents, $this->config->modelNames->name . 'Repository::class') !== false) {
			// If already exists, return without making changes
			return;
		}

		// Split the lines of the file into an array
		$lines = explode(PHP_EOL, $contents);

		// Find where to insert the new bind in alphabetical order
		$inserted = false;
		$newContents = '';
		foreach ($lines as $line) {
			// Check if this is the place to insert (alphabetically)
			if (!$inserted && strpos($line, '$this->app->bind') !== false && strcmp(trim($repositoryBind), trim($line)) < 0) {
				$newContents .= $repositoryBind;
				$inserted = true;
			}
			$newContents .= $line . PHP_EOL;
		}

		// If not inserted yet (e.g., all lines are before it alphabetically), append at the end
		if (!$inserted) {
			$newContents .= $repositoryBind;
		}

		// Write the updated contents back to the file
		BiollanteHelper::instance()->g_filesystem()->createFile($appServiceProviderPath, $newContents);
	}

	protected function getSearchableFields()
	{
		$searchables = [];

		foreach ($this->config->fields as $field) {
			if ($field->isSearchable && !$field->fieldDetails->is_virtual) {
				$searchables[] = "'".$field->name."'";
			}
		}

		return implode(','.BiollanteHelper::instance()->format_nl_tab(1, 2), $searchables);
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('Repository file deleted: '.$this->fileName);
		}
	}
}
