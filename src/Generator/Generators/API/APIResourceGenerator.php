<?php

namespace Biollante\Generator\Generators\API;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Generator\Generators\BaseGenerator;
use Biollante\Generator\Common\GeneratorConfig;

class APIResourceGenerator extends BaseGenerator
{
	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->apiResource;
		$this->fileName = $this->config->modelNames->name.'Resource.php';
	}

	public function variables(): array
	{
		return [
			'config' => $this->config,
			'fields' => implode(','.BiollanteHelper::instance()->format_nl_tab(1, 3), $this->generateResourceFields())
		];
	}

	public function generate()
	{
		$templateData = view('biollante::generator.api.resource.resource', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->fileName, $templateData);

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'API Resource created: ');
		$this->config->commandInfo($this->fileName);
	}

	protected function generateResourceFields(): array
	{
		$resourceFields = [];
	
		$attachments = [];
		$extensionPath = base_path("app/Models/Extensions/{$this->config->modelNames->name}Extension.php");
	
		if (file_exists($extensionPath)) {
			$code = file_get_contents($extensionPath);
	
			if (preg_match('/static::retrieved\s*\(\s*function\s*\(\$model\)\s*\{(.*?)\}\s*\);/s', $code, $retrievedBlock)) {
				preg_match_all('/\$model->append\([\'"](\w+)[\'"]\)/', $retrievedBlock[1], $matches);
				$attachments = $matches[1] ?? [];
				sort($attachments);
			}
		}
	
		$remainingAttachments = $attachments;
	
		foreach ($this->config->fields as $field) {
			if ($field->fieldDetails->is_virtual) {
				continue;
			}
	
			while (!empty($remainingAttachments) && strcmp($remainingAttachments[0], $field->name) < 0) {
				$attachment = array_shift($remainingAttachments);
				$resourceFields[] = $this->formatField($attachment);
			}
	
			$resourceFields[] = $this->formatField($field->name);
		}
	
		// Final sweep: any remaining attachments that go after the last field
		while (!empty($remainingAttachments)) {
			$attachment = array_shift($remainingAttachments);
			$resourceFields[] = $this->formatField($attachment);
		}
	
		return $resourceFields;
	}

	private function formatField($fieldName){
		if (str_ends_with($fieldName, '_at')) {
			return "'{$fieldName}' => \$this->{$fieldName} ? \$this->{$fieldName}->format('Y-m-d H:i:s') : null";
		} elseif (str_ends_with($fieldName, '_on')) {
			return "'{$fieldName}' => \$this->{$fieldName} ? \$this->{$fieldName}->format('Y-m-d') : null";
		}
		if ($this->isNullable($fieldName)) {
			return "'{$fieldName}' => \$this->{$fieldName} ? \$this->{$fieldName} : null";
		}
		return "'{$fieldName}' => \$this->{$fieldName}";
	}

	private function isNullable(string $fieldName): bool
	{
		foreach ($this->config->fields as $f) {
			if ($f->name === $fieldName) {
				$details = $f->fieldDetails ?? (object)[];
				return (bool)($details->is_nullable ?? $details->nullable ?? false);
			}
		}
		return false;
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('API Resource file deleted: '.$this->fileName);
		}
	}
}
