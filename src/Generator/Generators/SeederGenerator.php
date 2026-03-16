<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Biollante\Generator\Utils\GeneratorFieldsInputUtil;
use Biollante\Generator\Common\GeneratorConfig;
use Carbon\Carbon;
use Faker\Factory as Faker;

class SeederGenerator extends BaseGenerator
{
	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;

		$this->path = $this->config->paths->seeder;
		$this->fileName = $this->config->modelNames->name . 'Seeder.php';
	}

	public function generate()
	{
		//Skip the excluded
		if(in_array($this->config->tableName, config('laravel_generator.options.excluded_seeds', []))){

			$this->config->commandComment(BiollanteHelper::instance()->format_nl() . 'Seeder skipped');

			// If the seed file already exists, delete it
			$seedPath = $this->path . $this->fileName;
			if (file_exists($seedPath)) {
				unlink($seedPath);
				$this->config->commandInfo('Existing seeder file deleted: ' . $this->fileName);
			}
		}else{

			// Generate seeds dynamically
			$fields = $this->config->fields;
			$recordCount = $this->config->options->recordCount ?? 5; // Default 5 records
	
			$fieldValues = $this->generateFieldValues($fields);
			$seeds = $this->generateSeeds($fieldValues, $recordCount);
	
			// Render the seeder template
			$templateData = view('biollante::generator.model.seeder', [
				'config' => $this->config,
				'seeds' => $seeds,
			])->render();
	
			// Create the seeder file
			BiollanteHelper::instance()->g_filesystem()->createFile($this->path . $this->fileName, $templateData);
	
			$this->config->commandComment(BiollanteHelper::instance()->format_nl() . 'Seeder created: ');
			$this->config->commandInfo($this->fileName);
		}
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('Seeder file deleted: ' . $this->fileName);
		}
	}

	/**
	 * Generate field values based on field types.
	 *
	 * @param array $fields
	 * @return array
	 */
	private function generateFieldValues(array $fields): array
	{
		// List of fields to exclude
		$excludedFields = ['id', 'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at', 'deleted_by'];

		// Filter out excluded fields and virtual fields
		return array_filter($fields, function ($field) use ($excludedFields) {
			return !in_array($field->name, $excludedFields, true) && 
				(empty($field->fieldDetails->is_virtual) || $field->fieldDetails->is_virtual != 1);
		});
	}

	/**
	 * Generate individual seed entries.
	 *
	 * @param array $fieldValues
	 * @param int $recordCount
	 * @return string
	 */
	private function generateSeeds(array $fieldValues, int $recordCount): string
	{
		$seeds = [];
		for ($i = 1; $i <= $recordCount; $i++) {
			//We've already got an Admin User
			if($i === 1 && $this->config->modelNames->name === 'User'){
				continue;
			}
			$seeds[] = view('biollante::generator.model.fields', [
				'id' => $i,
				'fields' => array_map(fn($field) => [
					'name' => $field->name,
					'value' => $this->generateSeedValue($field, $i), // Pass seed ID here
				], $fieldValues),
			])->render();
		}
		return implode(',' . BiollanteHelper::format_nl_tab(1, 2), $seeds);
	}

	/**
	 * Generate a seed value based on field type.
	 *
	 * @param object $field
	 * @param int $seedId
	 * @return string
	 */
	private function generateSeedValue(object $field, int $seedId = 1): string
	{
		
		$faker = Faker::create();
		$type = $field->fieldDetails->type;

		switch ($type) {
			case 'bigint':
				return $seedId;
			case 'int':
				return random_int(1, 1000);
			case 'mediumint':
				return random_int(1, 100);
			case 'smallint':
				return random_int(1, 10);
			case 'tinyint':
				return 1;
			case 'float':
			case 'decimal':
				return number_format(mt_rand(100, 1000) / 10, 2);
			case 'double':
				if (str_contains($field->name, 'latitude')) {
					return $faker->latitude;
				} elseif (str_contains($field->name, 'longitude')) {
					return $faker->longitude;
				} else {
					return number_format(mt_rand(100, 1000) / 10, 2);
				}
			case 'json':
				return "json_encode([])";
			case 'enum':
				if (!empty($field->fieldDetails->enumValues)) {
					$options = $field->fieldDetails->enumValues;
					$index = ($seedId - 1) % count($options); // Cycle through the options
					return "'{$options[$index]}'";
				}
				return "'unknown_enum_value'";
			case 'varchar':
				$length = $field->fieldDetails->length ?? 191;
				if (str_contains($field->name, 'slug')) {
					return "'" . substr('example-slug-' . $seedId, 0, $length) . "'";
				} elseif (str_contains($field->name, 'email')) {
					return "'" . substr($faker->unique()->safeEmail, 0, $length) . "'";
				} elseif (str_contains($field->name, 'url')) {
					return "'" . substr($faker->url, 0, $length) . "'";
				} elseif (str_contains($field->name, 'first_name')) {
					return "'" . addslashes(substr($faker->firstName, 0, $length)) . "'";
				} elseif (str_contains($field->name, 'last_name')) {
					return "'" . addslashes(substr($faker->lastName, 0, $length)) . "'";
				} elseif (str_contains($field->name, 'name')) {
					return "'" . addslashes(substr($faker->name, 0, $length)) . "'";
				} else {
					return "'" . substr($faker->sentence(5), 0, $length) . "'";
				}
			case 'char':
				$length = $field->fieldDetails->length ?? 191;
				if (str_contains($field->name, 'uuid')) {
					return "'" . substr($faker->uuid, 0, $length) . "'";
				} else {
					return "'" . substr($faker->word, 0, $length) . "'";
				}
			case 'text':
			case 'mediumtext':
			case 'longtext':
				return "'{$faker->paragraph}'";
			case 'date':
				return "now()->toDateString()";
			case 'datetime':
			case 'timestamp':
				$randomDate = Carbon::now()->subYears(random_int(0, 30))->subDays(random_int(0, 365))->subSeconds(random_int(0, 86400));
				return "'".$randomDate->toDateTimeString()."'";
			case 'time':
				return "now()->toTimeString()";
			default:
				return "FIXMESEEDTYPE";
		}
	}
}
