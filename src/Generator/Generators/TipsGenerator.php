<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Illuminate\Support\Str;
use Biollante\Generator\Utils\TableFieldsGenerator;
use Biollante\Generator\Common\GeneratorConfig;

class TipsGenerator extends BaseGenerator
{

	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->tips;
		$this->fileName = $this->config->modelNames->name . '.ts';
	}

	/**
	 * Generate tips files for the model.
	 */
	public function generate()
	{
		
		$modelName = $this->config->modelNames->name;

		// Generate tips
		$tipsContent = $this->generateTipsContent($modelName);

		// Create or update the file
		$filePath = $this->path . $this->fileName;
		BiollanteHelper::instance()->g_filesystem()->createFile($filePath, $tipsContent);

		// Update the index.ts file
		if (!file_exists($this->path . 'index.ts')) {
			BiollanteHelper::instance()->g_filesystem()->createFile($this->path . 'index.ts', '');
		}
		$this->updateIndexFile();

		$this->config->commandComment(BiollanteHelper::instance()->format_nl() . 'Tips created: ');
		$this->config->commandInfo($this->config->modelNames->name . '.ts');
	}

	/**
	 * Generate the content for an tips.
	 *
	 * @param string $tipsName
	 * @return string
	 */
	protected function generateTipsContent(string $tipsName): string
	{
		$fields = $this->getFieldsForTips($tipsName);

		// Render the Blade template
		return view('biollante::generator.tips.tips', [
			'tipsName' => $tipsName,
			'fields' => $fields,
		])->render();
	}

	protected function getFieldsForTips(string $tipsName): array
	{

		$fields = array_map(function ($field) {

			// 1) Skip logic if crud tracking or is_virtual ...
			if (
				(
					in_array($field->name, [
						'created_at', 'created_by', 
						'updated_at', 'updated_by', 
						'deleted_at', 'deleted_by'
					])
				) ||
				$field->fieldDetails->is_virtual
			) {
				return null;
			}

			$tip = !$field->fieldDetails->is_nullable ? $field->description . ' Required!' : $field->description;

			// 5) If field is NOT nullable, add notification to tip
			$name = $field->name;

			return [
				'name' => $name,
				'tip' => $tip,
			];
		}, $this->config->fields);

		// Remove null
		return array_filter($fields, fn($field) => $field !== null);
	}

	/**
	 * Update the index.ts file to include the tips, if required.
	 *
	 */
	protected function updateIndexFile()
	{
		$files = collect(scandir($this->path))
			->filter(fn($file) => Str::endsWith($file, '.ts') && $file !== 'index.ts');
	
		$exports = $files->map(fn($file) => "export { " . pathinfo($file, PATHINFO_FILENAME) . "Tips } from './" . pathinfo($file, PATHINFO_FILENAME) . "';");
	
		$content = $exports->implode("\n");
		BiollanteHelper::instance()->g_filesystem()->createFile($this->path . '/index.ts', $content);
	}
}
