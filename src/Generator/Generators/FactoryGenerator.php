<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Illuminate\Support\Str;
use Biollante\Generator\Utils\GeneratorFieldsInputUtil;
use Biollante\Generator\Common\GeneratorConfig;

class FactoryGenerator extends BaseGenerator
{
	private string $fileName;

	private array $relations = [];

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->factory;
		$this->fileName = $this->config->modelNames->name.'Factory.php';

		//setup relations if available
		//assumes relation fields are tailed with _id if not supplied
		if (property_exists($this->config, 'relations')) {
			foreach ($this->config->relations as $r) {
				if (
					$r->type == 'mt1' && 
					$r->inputs[1] != 'created_by' &&
					$r->inputs[1] != 'updated_by' &&
					$r->inputs[1] != 'deleted_by'
				) {
					$relation = (isset($r->inputs[0])) ? ucfirst($r->inputs[0]) : null;
					$label = $r->inputs[0];
					if($r->inputs[0] . '_id' !== $r->inputs[1]){
						if (str_ends_with($r->inputs[1], '_by')) {
							$relation = 'User';
							$label = $r->inputs[1];
						} else {
							$relation = $r->inputs[2];
							$label = $r->inputs[0];
						}
					}
					if (isset($r->inputs[1])) {
						$field = $r->inputs[1];
					} else {
						$field = Str::snake($relation).'_id';
					}
				
					// Detect mismatch and update label and model_class accordingly
					if ((!$relation || $relation !== 'User') && isset($r->inputs[2]) && !is_array($r->inputs[2]) && ucfirst(Str::singular($label)) !== $r->inputs[2]) {
						$relation = $r->inputs[2];
					}

					if ($field) {
						$rel = $relation;
						$this->relations[$field] = [
							'label'		 => $label,
							'relation'	  => $rel,
							'model_class'   => $this->config->namespaces->model.'\\'.$relation,
						];
					}
				}
			}
		}
	}

	public function variables(): array
	{
		$relations = $this->getRelationsBootstrap();

		return [
			'config'		=> $this->config,
			'fields'		=> $this->generateFields(),
			'relations'		=> $relations['text'],
			'usedRelations'	=> $relations['uses'],
		];
	}

	public function generate()
	{
		$templateData = view('biollante::generator.model.factory', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path.$this->fileName, $templateData);

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Factory created: ');
		$this->config->commandInfo($this->fileName);
	}

	protected function generateFields(): string
	{
		$fields = [];

		// Get model validation rules
		$class = $this->config->namespaces->model . '\\' . $this->config->modelNames->name;
		$rules = [];
		if (class_exists($class)) {
			$rules = (new $class())->getUpdateRules();
		}

		$relations = array_keys($this->relations);

		foreach ($this->config->fields as $field) {

			if (
				$field->isPrimary ||
				$field->name === 'created_at' ||
				$field->name === 'created_by' ||
				$field->name === 'updated_at' ||
				$field->name === 'updated_by' ||
				$field->name === 'deleted_at' ||
				$field->name === 'deleted_by' 
			) {
				continue;
			}

			$fieldData = "'" . $field->name . "' => ";
			$rule = $rules[$field->name] ?? null;

			// Detect polymorphic type and id pairs
			if (str_ends_with($field->name, '_type')) {
				// Handle `_type` field: set to the generated variable
				$idVariable = '$' . Str::snake($field->name);
				$fieldData .= $idVariable;
			} elseif (str_ends_with($field->name, '_id') || str_ends_with($field->name, '_by')) {
				// Check if this `_id` field matches a related `_type` field
				$typeField = substr($field->name, 0, -3) . '_type';
				$idVariable = '$' . Str::snake(str_replace('_type', '_id', $typeField));
				if (in_array($typeField, array_column($this->config->fields, 'name'))) {
					$fieldData .= $idVariable; // Use the generated ID variable
				} else {
					// Handle as a normal relation or integer
					$fieldData = $this->getValidRelation($field->name);
				}
			} elseif (str_contains(strtolower($field->name), '_token')) {
				$fieldData .= 'Str::random(32)';
			} elseif (strtolower($field->name) === 'latitude') {
				$fieldData .= '$this->faker->latitude(-90, 90)'; // Generate valid latitude
			} elseif (strtolower($field->name) === 'longitude') {
				$fieldData .= '$this->faker->longitude(-180, 180)'; // Generate valid longitude
			} elseif (strtolower($field->name) === 'email') {
				$fieldData .= '$this->faker->unique()->safeEmail';
			} elseif (strtolower($field->name) === 'password' || strtolower($field->name) === 'password_confirm') {
				$fieldData .= "'password'";
			} elseif (strtolower($field->name) === 'is_agreed') { //special case for registration
				$fieldData .= 1;
			} elseif (in_array($field->name, $relations)) {
				$fieldData .= $this->getValidRelation($field->name);
			} else {
				// Generate faker data based on field type
				switch (strtolower($field->fieldDetails->type)) {
					case 'int':
						$minValue = $field->fieldDetails->is_unsigned == 1 ? 1 : -2147483648;
						$maxValue = 2147483647;
						$fakerData = $this->getValidNumber($minValue, $maxValue);
						break;
					case 'smallint':
						$minValue = $field->fieldDetails->is_unsigned == 1 ? 1 : -32768;
						$maxValue = 32768;
						$fakerData = $this->getValidNumber($minValue, $maxValue);
						break;
					case 'mediumint':
						$minValue = $field->fieldDetails->is_unsigned == 1 ? 1 : -8388608;
						$maxValue = 8388608;
						$fakerData = $this->getValidNumber($minValue, $maxValue);
						break;
					case 'bigint':
						$minValue = $field->fieldDetails->is_unsigned == 1 ? 1 : -9223372036854775807;
						$maxValue = 9223372036854775807;
						$fakerData = $this->getValidNumber($minValue, $maxValue);
						break;
					case 'tinyint':
						$minValue = $field->fieldDetails->is_unsigned == 1 ? 1 : -255;
						$maxValue = 255;
						$fakerData = $rule && strpos($rule, 'boolean') !== false ? 'boolean' : $this->getValidNumber($rule, $maxValue);
						break;
					case 'decimal':
					case 'numeric':
					case 'float':
					case 'double':
					case 'real':
						$fakerData = 'randomFloat(2, 0, 1000)';
						break;
					case 'char':
					case 'varchar':
						$maxLength = isset($field->fieldDetails->length)
							? $field->fieldDetails->length
							: 255; // Default varchar max length
						$rule = $maxLength ? "max:$maxLength" : null;
						if (strtolower($field->name) === 'slug') {
							$fakerData = $this->getValidSlug($rule);
						} else {
							$fakerData = $this->getValidText($rule);
						}
						break;
					case 'text':
					case 'mediumtext':
					case 'longtext':
						$rule = isset($field->fieldDetails->length)
							? "max:{$field->fieldDetails->length}"
							: null;
						$fakerData = $rule ? $this->getValidText($rule) : 'text(500)';
						break;
					case 'boolean':
						$fakerData = 'boolean';
						break;
					case 'date':
						$fakerData = "date('Y-m-d')";
						break;
					case 'datetime':
					case 'timestamp':
						$fakerData = "date('Y-m-d H:i:s')";
						break;
					case 'time':
						$fakerData = "date('H:i:s')";
						break;
					case 'enum':
						// Non-polymorphic enums
						$fakerData = 'randomElement(' . 
							GeneratorFieldsInputUtil::prepareValuesArrayStr($field->fieldDetails->enumValues) . 
							')';
						break;
					default:
						$fakerData = 'word';
				}

				if ($fakerData === ':relation') {
					$fieldData = $this->getValidRelation($field->name);
				} else {
					if($field->fieldDetails->is_unique){
						$fakerData = 'unique()->' . $fakerData;
					}
					$fieldData .= '$this->faker->' . $fakerData;
				}
			}

			$fields[] = $fieldData;
		}

		return implode(',' . BiollanteHelper::instance()->format_nl_tab(1, 3), $fields);
	}	

	/**
	 * Generates a valid number based on applicable model rule.
	 *
	 * @param string $rule The applicable model rule
	 * @param int	$max  The maximum number to generate.
	 *
	 * @return string
	 */
	public function getValidNumber($rule = null, $max = 5000): string
	{
		if ($rule) {
			$max = $this->extractMinMax($rule, 'max') ?? $max;
			$min = $this->extractMinMax($rule, 'min') ?? 0;

			return "numberBetween($min, $max)";
		} else {
			return 'randomDigitNotNull';
		}
	}

	/**
	 * Generates a valid relation if applicable
	 * This method assumes the related field primary key is id.
	 */
	public function getValidRelation(string $fieldName): string
	{
		if(!array_key_exists($fieldName, $this->relations)){
			return "'" . $fieldName . "' => \$this->faker->numberBetween(1, 5)";
		}
		$relation = $this->relations[$fieldName]['label'];
		$variable = Str::camel($relation);

		return "'".$fieldName."' => ".'$'.$variable;
	}

	/**
	 * Generates a valid text based on applicable model rule.
	 *
	 * @param string $rule The applicable model rule.
	 */
	public function getValidText($rule = null): string
	{
		$globalMaxLength = 5000;
		if ($rule) {
			$max = $this->extractMinMax($rule, 'max') ?? $globalMaxLength;
			$min = $this->extractMinMax($rule) ?? 5;

			if ($max > $globalMaxLength) {
				$max = $globalMaxLength; // Enforce the global maximum
			}

			if ($max < 51) {
				//faker text requires at least 5 characters
				return "lexify('" . str_repeat('?', $max) . "')";
			}
			if ($min < 5) {
				//faker text requires at least 5 characters
				$min = 5;
			}

			return 'text('.'$this->faker->numberBetween('.$min.', '.$max.'))';
		} else {
			return "text($globalMaxLength)";
		}
	}

	/**
	 * Generates URL-safe, unique-ish slug source within a max length.
	 * Returns a code string that will be emitted into the factory.
	 */
	public function getValidSlug($rule = null): string
	{
		$globalMaxLength = 5000;

		$max = $rule ? ($this->extractMinMax($rule, 'max') ?? $globalMaxLength) : $globalMaxLength;
		$max = max(1, min($max, $globalMaxLength));

		// Tiny fields: just letters, guaranteed length
		if ($max <= 2) {
			return "lexify(str_repeat('?', {$max}))";
		}

		// Choose a safe number of segments s such that 2*s - 1 <= $max
		// (at least one letter per segment, plus hyphens between)
		$segments = max(2, min(6, intdiv($max + 1, 2)));

		// Distribute letters evenly across segments
		$letters = $max - ($segments - 1); // total letters after reserving hyphens
		$base = intdiv($letters, $segments);
		$extra = $letters % $segments;

		$parts = [];
		for ($i = 0; $i < $segments; $i++) {
			$len = $base + ($i < $extra ? 1 : 0);
			if ($len < 1) {
				$len = 1;
			}
			$parts[] = str_repeat('?', $len);
		}

		$pattern = implode('-', $parts);

		// Emits: $this->faker->unique()->lexify('??-???-??')
		return "lexify('{$pattern}')";
	}

	/**
	 * Extracts min or max rule for a laravel model.
	 */
	public function extractMinMax($rule, $t = 'min')
	{
		$i = strpos($rule, $t);
		$e = strpos($rule, '|', $i);
		if ($e === false) {
			$e = strlen($rule);
		}
		if ($i !== false) {
			$len = $e - ($i + 4);

			return substr($rule, $i + 4, $len);
		}

		return null;
	}

	/**
	 * Generate valid model so we can use the id where applicable
	 * This method assumes the model has a factory.
	 */
	public function getRelationsBootstrap(): array
	{
		$text = '';
		$uses = '';
		$uniqueQualifiers = []; // Track unique qualifiers for imports
		
		//relations
		$declaredVariables = [];
		foreach ($this->relations as $field => $data) {
			$relation = $data['relation'];
			$variable = Str::camel($data['label']);
			$model = Str::studly($relation);

			$configField = collect($this->config->fields)->firstWhere('name', $field);
			if ($configField->fieldDetails->is_unique_with) {
				$relatedField = $configField->fieldDetails->is_unique_with;
				$idVarName = str_replace('_id', '', $relatedField);
				$idVariable = '$' . $idVarName;

				// Declare related variable only once
				if (!in_array($idVarName, $declaredVariables)) {
					$text .= "{$idVariable} = \$this->faker->randomElement([1, 2, 3, 4, 5]);" . BiollanteHelper::instance()->format_nl_tab(1, 2);
					$declaredVariables[] = $idVarName;
				}

				$model = Str::studly(Str::singular($this->config->tableName));
				$relatedModel = Str::studly(str_replace('_id', '', $configField->name));

				$text .= "\$takenIds = {$model}::where('{$relatedField}', {$idVariable})->pluck('{$configField->name}')->toArray();" .
					BiollanteHelper::instance()->format_nl_tab(1, 2) .
					"\${$variable} = {$relatedModel}::whereNotIn('id', \$takenIds)->inRandomOrder()->value('id');" .
					BiollanteHelper::instance()->format_nl_tab(1, 2) .
					"if (!\${$variable}) {" .
					BiollanteHelper::instance()->format_nl_tab(1, 3) .
					"\$ids = array_diff([1, 2, 3, 4, 5], \$takenIds);" .
					BiollanteHelper::instance()->format_nl_tab(1, 3) .
					"if (!\$ids) {" .
					BiollanteHelper::instance()->format_nl_tab(1, 4) .
					"\${$idVarName} = {$relatedModel}::factory()->create();" .
					BiollanteHelper::instance()->format_nl_tab(1, 3) .
					"} else {" .
					BiollanteHelper::instance()->format_nl_tab(1, 4) .
					"\${$variable} = \$this->faker->randomElement(\$ids);" .
					BiollanteHelper::instance()->format_nl_tab(1, 3) .
					"}" .
					BiollanteHelper::instance()->format_nl_tab(1, 2) .
					"}" .
					BiollanteHelper::instance()->format_nl();
			} else {
				if (!in_array($variable, $declaredVariables)) {
					$text .= "\${$variable} = \$this->faker->randomElement([1, 2, 3, 4, 5]);";
					$declaredVariables[] = $variable;
				}
			}
			if (!in_array($relation, $uniqueQualifiers) && ucfirst(Str::singular($this->config->tableName)) !== $relation) {
				$uses .= BiollanteHelper::instance()->format_nl() . "use Biollante\\Models\\{$relation};";
				$uniqueQualifiers[] = $relation;  // Mark qualifier as added
			}
		}

		// Look for polymorphic enum fields
		foreach ($this->config->fields as $field) {
			if ($field->fieldDetails->type === 'enum' && str_ends_with($field->name, '_type')) {
				$typeVariable = '$' . Str::snake($field->name);
				$relatedIdField = str_replace('_type', '_id', $field->name);
				$idVariable = '$' . Str::snake($relatedIdField);
				$polymorphicTypes = $field->fieldDetails->enumValues;
				$text .= BiollanteHelper::instance()->format_nl_tab(1, 2) . "{$typeVariable} = \$this->faker->randomElement(" . GeneratorFieldsInputUtil::prepareValuesArrayStr($polymorphicTypes) . ");";
				
				$relatedFieldExists = collect($this->config->fields)->firstWhere('name', $relatedIdField);
				if ($relatedFieldExists) {
					$text .= BiollanteHelper::instance()->format_nl_tab(1, 2) . "{$idVariable} = \$this->faker->randomElement([1, 2, 3, 4, 5]);" . BiollanteHelper::instance()->format_nl();
				}
			}
		}

		return [
			'text' => $text,
			'uses' => $uses,
		];
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('Factory file deleted: '.$this->fileName);
		}
	}
}
