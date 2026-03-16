<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Illuminate\Support\Str;
use Biollante\Generator\Utils\TableFieldsGenerator;
use Biollante\Generator\Common\GeneratorConfig;

class ModelGenerator extends BaseGenerator
{
	/**
	 * Fields not included in the generator by default.
	 */
	protected array $excludedFields = [
		'password',
		'created_at',
		'created_by',
		'updated_at',
		'updated_by',
		'deleted_at',
		'deleted_by',
	];

	private string $fileName;
	private array $extensionDefinedRelations = [];

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->model;
		$this->fileName = $this->config->modelNames->name . '.php';
		$this->extensionDefinedRelations = $this->extractExtensionRelations();
	}

	public function generate()
	{
		$vars = $this->variables();
		
		// Generate core model
		$corePath = $this->config->paths->model . '/Core/';
		$coreFileName = $this->config->modelNames->name . '.php';
		$templateData = view('biollante::generator.model.model', $vars)->render();
		BiollanteHelper::instance()->g_filesystem()->createFile($corePath . $coreFileName, $templateData);
		$this->config->commandComment(BiollanteHelper::instance()->format_nl() . 'Core Model created: ');
		$this->config->commandInfo($coreFileName);

		// Generate model extension
		$extensionPath = $this->config->paths->model . '/Extensions/';
		$extensionFileName = $this->config->modelNames->name . 'Extension.php';
		$extensionFilePath = $extensionPath . $extensionFileName;
		if (!file_exists($extensionFilePath)) {
			$extensionTemplateData = view('biollante::generator.model.extension', $vars)->render();
			BiollanteHelper::instance()->g_filesystem()->createFile($extensionFilePath, $extensionTemplateData);
			$this->config->commandComment(BiollanteHelper::instance()->format_nl() . 'Model Extension created: ');
			$this->config->commandInfo($extensionFileName);
		} else {
			$this->config->commandComment(BiollanteHelper::instance()->format_nl() . 'Model Extension already exists, skipping: ');
			$this->config->commandInfo($extensionFileName);
		}

		// Generate constants
		$this->generateEnumConstants();

		$this->updateAppServiceProvider();

		// Generate model wrapper
		$wrapperPath = $this->config->paths->model;
		$wrapperFileName = $this->config->modelNames->name . '.php';
		$wrapperTemplateData = view('biollante::generator.model.wrapper', $vars)->render();
		BiollanteHelper::instance()->g_filesystem()->createFile($wrapperPath . $wrapperFileName, $wrapperTemplateData);
		$this->config->commandComment(BiollanteHelper::instance()->format_nl() . 'Wrapper Model created: ');
		$this->config->commandInfo($wrapperFileName);
	}

	public function variables(): array
	{
		return [
			'config'		=> $this->config,
			'fillables'		=> implode(','.BiollanteHelper::instance()->format_nl_tab(1, 2), $this->generateFillables()),
			'required'		=> implode(','.BiollanteHelper::instance()->format_nl_tab(1, 2), $this->generaterequired()),
			'hidden'		=> implode(','.BiollanteHelper::instance()->format_nl_tab(1, 2), $this->generateHiddenFields()),
			'casts'			=> implode(','.BiollanteHelper::instance()->format_nl_tab(1, 2), $this->generateCasts()),
			'updateRules'	=> implode(','.BiollanteHelper::instance()->format_nl_tab(1, 3), $this->generateRules('update')),
			'createRules'	=> implode(','.BiollanteHelper::instance()->format_nl_tab(1, 3), $this->generateRules('create')), // create needs to be last, to preserve 'required' for schema et al
			'relationships' => implode(
				',' . BiollanteHelper::instance()->format_nl_tab(1, 2),
				array_map(
					fn($rel) => isset($rel['property'], $rel['type'], $rel['model'])
						? "'{$rel['property']}' => [\"type\" => \"{$rel['type']}\", \"model\" => " .
						(is_array($rel['model'])
							? '[' . implode(', ', array_map(fn($m) => "\"{$m}\"", $rel['model'])) . ']'
							: "\"{$rel['model']}\"") . ']'
					: null,
					$this->generateRelationships()
				)
			),
			'swaggerDocs'		=> $this->fillDocs(),
			'customCreatedAt'	=> $this->customCreatedAt(),
			'customUpdatedAt'	=> $this->customUpdatedAt(),
			'customSoftDelete'	=> $this->customSoftDelete(),
			'relations'			=> $this->generateRelations(),
			'timestamps'		=> true,
			'autoOrders'		=> implode(
				BiollanteHelper::instance()->format_nl_tab(2),
				$this->generateAutoOrderAccessors()
			),
			'hasSlug' => $this->modelHasSlug(),
		];
	}

	protected function customSoftDelete()
	{
		$deletedAt = config('laravel_generator.timestamps.deleted_at', 'deleted_at');

		if ($deletedAt === 'deleted_at') {
			return null;
		}

		return $deletedAt;
	}

	protected function customCreatedAt()
	{
		$createdAt = config('laravel_generator.timestamps.created_at', 'created_at');

		if ($createdAt === 'created_at') {
			return null;
		}

		return $createdAt;
	}

	protected function customUpdatedAt()
	{
		$updatedAt = config('laravel_generator.timestamps.updated_at', 'updated_at');

		if ($updatedAt === 'updated_at') {
			return null;
		}

		return $updatedAt;
	}

	protected function generateFillables(): array
	{
		$fillables = [];
		if (isset($this->config->fields) && !empty($this->config->fields)) {
			foreach ($this->config->fields as $field) {
				if (
					$field->isFillable && 
					!$field->fieldDetails->is_virtual && 
					(
						$field->name === 'password' ||
						!in_array($field->name, config('laravel_generator.options.hidden_fields'))
					)
				) {
					$fillables[] = "'".$field->name."'";
				}
			}
		}

		return $fillables;
	}

	protected function generaterequired(): array
	{
		$required = [];
		if (isset($this->config->fields) && !empty($this->config->fields)) {
			foreach ($this->config->fields as $field) {
				if (
					$field->isNotNull &&
					$field->isFillable && 
					!$field->fieldDetails->is_virtual && 
					!in_array($field->name, config('laravel_generator.options.hidden_fields'))
				) {
					$required[] = "'".$field->name."'";
				}
			}
		}

		return $required;
	}

	protected function generateHiddenFields(): array
	{
		$hidden = [];
		if (isset($this->config->fields) && !empty($this->config->fields)) {
			foreach ($this->config->fields as $field) {
				if (in_array($field->name, config('laravel_generator.options.hidden_fields'))) {
					$hidden[] = "'".$field->name."'";
				}
			}
		}

		return $hidden;
	}

	public function updateAppServiceProvider()
	{
		$appServiceProviderPath = $this->config->paths->appServiceProvider;

		$modelBind = 
			"			'" . $this->config->modelNames->name . "' => 'Biollante\\Models\\" . $this->config->modelNames->name . "'," . BiollanteHelper::instance()->format_nl() .
			"			'Core" . $this->config->modelNames->name . "' => 'Biollante\\Models\\Core\\" . $this->config->modelNames->name . "'," . BiollanteHelper::instance()->format_nl();

		// Check if the file exists
		if (!file_exists($appServiceProviderPath)) {
			throw new \Exception('AppServiceProvider file not found at: ' . $appServiceProviderPath);
		}

		// Read the file contents
		$contents = file_get_contents($appServiceProviderPath);

		// Check if the mapping already exists
		if (strpos($contents, "'" . $this->config->modelNames->name . "' => 'Biollante\\Models\\" . $this->config->modelNames->name . "'") !== false) {
			// If already exists, return without making changes
			return;
		}

		// Split the lines of the file into an array
		$lines = explode(PHP_EOL, $contents);

		// Find where to insert the new bind in alphabetical order
		$inserted = false;
		$foundStart = false;
		$foundStop = false;
		$newContents = '';

		foreach ($lines as $line) {

			$linesToInsert = explode(PHP_EOL, trim($modelBind));
			preg_match("/'([^']+)'/", $linesToInsert[0], $firstBindKeyMatch);
			$newModelKey = $firstBindKeyMatch[1] ?? '';

			if(!$foundStart){
				$foundStart = str_contains($line, '// Wrapper and Core Models');
			}else{
				$foundStop = str_contains($line, '// Additional third-party models');
			}

			// Check if this is the place to insert (alphabetically)
			if (
				$foundStart &&
				!$foundStop &&
				!$inserted &&
				trim($line) !== '' &&
				preg_match("/'([^']+)' => 'Biollante\\\\Models\\\\[^']+'/", $line, $lineMatch) &&
				!Str::startsWith($lineMatch[1], 'Core') && // only compare against non-Core lines
				strcmp($newModelKey, $lineMatch[1]) < 0
			) {
				$newContents .= $modelBind;
				$inserted = true;
			}

			// If not inserted yet (e.g., all lines are before it alphabetically), append at the end
			if ($foundStop && !$inserted) {
				$newContents .= $modelBind;
				$inserted = true;
			}
			
			$newContents .= $line . PHP_EOL;
		}

		// Write the updated contents back to the file
		BiollanteHelper::instance()->g_filesystem()->createFile($appServiceProviderPath, $newContents);
	}

	protected function fillDocs(): string
	{
		if (!$this->config->options->swagger) {
			return '';
		}

		return $this->generateSwagger();
	}

	public function generateSwagger(): string
	{
		$requiredFields = $this->generateRequiredFields();
		$description = $this->generateDescription();
		$properties = [];
		$simpleProperties = [];
		$superSimpleProperties = [];
		$fullProperties = [];
		$excludedFields = $this->excludedFields;
		unset($excludedFields['password']);
	
		$fieldTypes = SwaggerGenerator::generateTypes($this->config->fields);
		
		foreach ($fieldTypes as $fieldType) {
			if (!in_array($fieldType['fieldName'], $excludedFields)) {
				$superSimpleProperties[] = view(
					'biollante::generator.swagger.model.property',
					array_merge($fieldType, ['config' => $this->config])
				)->render();
			}
			$simpleProperties[] = view(
				'biollante::generator.swagger.model.property',
				array_merge($fieldType, ['config' => $this->config])
			)->render();
		}
	
		// Generate related field types for the full model view
		$relatedFieldTypes = SwaggerGenerator::generateRelatedTypes($this->generateRelationships());
		$fullProperties = $simpleProperties;
		foreach ($relatedFieldTypes as $relatedFieldType) {
			$fullProperties[] = view(
				'biollante::generator.swagger.model.modelObject',
				[
					'fieldName' => $relatedFieldType['fieldName'],
					'description' => $relatedFieldType['description'],
					'type' => $relatedFieldType['type'],
					'ref' => $relatedFieldType['ref']
				]
			)->render();
		}
	
		// Generate required fields string
		$requiredFields = '{'.implode(',', $requiredFields).'}';
	
		// Full model with relations
		$fullModel = view('biollante::generator.swagger.model.model', [
			'config' => $this->config,
			'subName' => '',
			'requiredFields' => $requiredFields,
			'description' => $description,
			'properties'	 => implode(',' . BiollanteHelper::instance()->format_nl().' ', $fullProperties),
		])->render();
	
		// xSimple model without relations
		$xSimpleModel = view('biollante::generator.swagger.model.model', [
			'config' => $this->config,
			'subName' => 'Simple',
			'requiredFields' => $requiredFields,
			'description' => "Attachable " . $this->config->modelNames->name . " object with no attachments itself.",
			'properties'	 => implode(',' . BiollanteHelper::instance()->format_nl().' ', $simpleProperties),
		])->render();
	
		// xSuperSimple model without related fields, permissions, or CUD data
		$xSuperSimpleModel = view('biollante::generator.swagger.model.model', [
			'config' => $this->config,
			'subName' => 'SuperSimple',
			'requiredFields' => $requiredFields,
			'description' => "Attachable " . $this->config->modelNames->name . " object with no attachments itself, nor CUD or permission data.",
			'properties'	 => implode(',' . BiollanteHelper::instance()->format_nl().' ', $superSimpleProperties), // Only basic field types
		])->render();

		//add RequestBody to results
		$requestBodyModel = view('biollante::generator.swagger.model.modelRequest', [
			'config' => $this->config
		])->render();
		
		return "/**\n " . $fullModel . "\n " . $xSimpleModel . "\n " . $xSuperSimpleModel . "\n " . $requestBodyModel . "\n */";
	}	

	protected function generateRequiredFields(): array
	{
		$requiredFields = [];
		$excludedFields = $this->excludedFields;
		unset($excludedFields['password']);
		$excludedFields[] = 'id';

		if (isset($this->config->fields) && !empty($this->config->fields)) {
			foreach ($this->config->fields as $field) {
				if(
					$field->fieldDetails->is_nullable === false &&
					!in_array($field->name, $excludedFields)
				){
					$requiredFields[] = '"'.$field->name.'"';
				}
			}
		}

		return $requiredFields;
	}

	public function generateDescription($indent=0): string
	{
		// Start with the table comment
		$description = $this->config->tableComment . "  \nThe following relationships can be attached, and in the case of plural relations, searched:  \n";

		// Iterate over each relation to build the description dynamically
		foreach ($this->getTableRelations() as $relation) {
			
			// Extract information
			$relatedLabel = $relation->inputs[0];
			$relatedField = $relation->inputs[1];
			$relatedModel = array_key_exists(2, $relation->inputs) && !is_array($relation->inputs[2]) ? $relation->inputs[2] : null;
			
			$singularRelation = Str::camel($relatedField);
			$pluralRelation = Str::camel(Str::plural($relatedField));
			
			// Set a default description if no comment is found
			$descriptionText = 'Associated relationship with ' . $this->config->modelNames->name;

			switch ($relation->type) {
				case '1t1':
					$functionName = $singularRelation;
					$relationClass = 'HasOne';
					break;
				case '1tm':
					$functionName = Str::plural($relatedLabel);
					$relationClass = 'HasMany';
					$descriptionText = ucfirst(Str::plural($relatedLabel)) . ' for the ' . $this->config->modelNames->name . ' (if any).';
					break;
				case 'mt1':
					if (!empty($relation->relatedField)) {
						$singularRelation = $relation->relatedField;
					} elseif (isset($relation->inputs[1])) {
						$singularRelation = Str::camel(str_replace('_id', '', strtolower($relation->inputs[1])));
					}
					$functionName = $singularRelation;
					$relationClass = 'BelongsTo';
					foreach ($this->config->fields as $field) {
						if ($field->name === $relatedField) {
							if(!empty($field->description)){
								$descriptionText = $field->description;
							}else{
								switch ($field->name) {
									case 'created_by':
										$descriptionText = 'User that created the record.';
										break;
									case 'updated_by':
										$descriptionText = 'User that last updated the record (if any).';
										break;
									case 'deleted_by':
										$descriptionText = 'User that deleted the record (if any).';
										break;
								}
							}
							break;
						}
					}
					break;
				case 'mtm':
					$functionName = Str::plural($relatedLabel);
					$relationClass = 'BelongsToMany';
					break;
				case 'hmt':
					$functionName = Str::plural($relatedLabel);
					$relationClass = 'HasManyThrough';
					break;
				case 'morphTo':
					$functionName = strtolower($relatedLabel);
					$relatedModel = $this->getPolymorphicTypes(strtolower($relatedLabel));
					$relationClass = 'MorphTo';
					foreach ($this->config->fields as $field) {
						if ($field->name === $relatedLabel . '_type') {
							$descriptionText = $field->description;
							break;
						}
					}
					break;
				case 'morphOne':
					$functionName = strtolower($relatedLabel);
					$relationClass = 'MorphOne';
					$descriptionText = ucfirst($relatedLabel) . ' for the ' . $this->config->modelNames->name . ' (if any).';
					break;
				case 'morphMany':
					$functionName = strtolower($relatedLabel);
					$relationClass = 'MorphMany';
					$descriptionText = ucfirst(Str::plural($relatedLabel)) . ' for the ' . $this->config->modelNames->name . ' (if any).';
					break;
				case 'morphToMany':
					$functionName = strtolower($relatedLabel);
					$relationClass = 'MorphToMany';
					$descriptionText = ucfirst(Str::plural($relatedLabel)) . ' for the ' . $this->config->modelNames->name . ' (if any).';
					break;
				default:
					$functionName = '';
					$relationClass = '';
					break;
			}

			// Build the line for this relationship
			$description .= "\n" . str_repeat("\t", $indent) . " * $functionName ($relatedModel) ($relationClass): $descriptionText";
		}
	
		return $description;
	}

	/**
	 * Fetches possible types for a polymorphic relationship based on the ENUM options of the related `_type` field.
	 *
	 * @param string $relatedLabel
	 * @return string
	 */
	private function getPolymorphicTypes(string $relatedLabel): string
	{

		$enumOptions = '';
		foreach ($this->config->fields as $field) {
			if ($field->name === "{$relatedLabel}_type" && isset($field->fieldDetails->enumValues)) {
				$enumOptions = implode(', ', $field->fieldDetails->enumValues);
				break;
			}
		}

		return $enumOptions ?: 'FIXME';
	}
	
	protected function generateRelationships(): array
	{
		$relationships = [];
		
		foreach ($this->getTableRelations() as $relation) {
			
			// Determine the relationship class based on the relation type
			$relationClass = match($relation->type) {
				'1t1' => 'HasOne',
				'1tm' => 'HasMany',
				'mt1' => 'BelongsTo',
				'mtm' => 'BelongsToMany',
				'hmt' => 'HasManyThrough',
				'morphTo' => 'MorphTo',
				'morphOne' => 'MorphOne',
				'morphMany' => 'MorphMany',
				'morphToMany' => 'MorphToMany',
				default => ''
			};

			// Structure the relationship data
			$relationships[] = [
				'property' => $relation->inputs[0],
				'type'     => $relationClass,
				'model'    => isset($relation->inputs[2])
					? (is_string($relation->inputs[2])
						? (preg_match('/^[A-Z][a-z]+/', $relation->inputs[2], $match) ? $match[0] : $relation->inputs[2])
						: $relation->inputs[2]) // Leave array as-is for polymorphics
					: Str::studly($relation->inputs[0]),
				'field'    => $relation->inputs[1] ?? '',
			];			
		}
		
		return $relationships;
	}

	protected function generateRules($context): array
	{
		$rules = [];
		
		foreach ($this->config->fields as $field) {
			
			if (
				!$field->isPrimary && 
				!in_array($field->name, $this->excludedFields) && 
				!$field->fieldDetails->is_virtual
			) {
				$field->validations = '';
				if ($field->isNotNull && $context !== 'update') {
					$field->validations = 'required';
				}

				$rule = empty($field->validations) ? [] : explode('|', $field->validations);

				if (!$field->isNotNull) {
					$rule[] = 'nullable';
				}

				switch ($field->fieldDetails->type) {
					case 'bigint':
					case 'integer':
						$rule[] = 'integer';
						break;
					case 'boolean':
					case 'tinyint':
						$rule[] = 'boolean';
						break;
					case 'float':
					case 'double':
					case 'decimal':
						$rule[] = 'numeric';
						break;
					case 'string':
					case 'text':
					case 'varchar':
						$rule[] = 'string';

						// Enforce a maximum string length if possible.
						if ((int) $field->fieldDetails->length > 0) {
							$rule[] = 'max:'.$field->fieldDetails->length;
						}
						// If the field is an email, add the email validation rules
						if($field->fieldDetails->name === 'email'){
							$rule[] = 'email';
							$rule[] = 'regex:/^[\w\-\.\+]+\@[a-zA-Z0-9\.\-]+\.[a-zA-z0-9]{2,4}$/';
						}
						// Slug: must not be purely numeric (e.g. "123" is invalid; "12a3" is ok)
						if ($field->name === 'slug') {
							$rule[] = 'regex:/^(?!\d+$).+$/';
						}
						break;
					case 'date':
						$rule[] = 'date_format:Y-m-d';
						break;
					case 'timestamp':
						$rule[] = 'date_format:Y-m-d H:i:s';
						break;
					case 'enum':
						$rule[] = "in:" . implode(',', $field->fieldDetails->enumValues);
				}

				$field->validations = implode('|', $rule);

				// Add the `exists` rule for belongsTo fields
				if (str_ends_with($field->name, '_id')) {
					$relatedRelation = collect($this->getTableRelations())->first(function ($relation) use ($field) {
						return $relation->type === 'mt1' && $relation->inputs[1] === $field->name;
					});
					if ($relatedRelation) {
						$tableName = lcfirst(Str::plural($relatedRelation->inputs[2]));
						$field->validations .= '|exists:' . $tableName . ',id';
					}
				}
				if (str_ends_with($field->name, '_by')) {
					$field->validations .= '|exists:users,id';
				}

				// Uniqe must be at the end
				if($context !== 'update'){
					if ($field->fieldDetails->is_unique) {
						$field->validations .= '|unique:'.$this->config->tableName.','.$field->name;
					} elseif ($field->fieldDetails->is_unique_with) {
						$field->validations .= "|unique_with:" . STR::singular(Str::studly($this->config->tableName)) . ",{$field->fieldDetails->is_unique_with}";
					}
				}

				$rules[] = "'".$field->name."' => '".$field->validations."'";
			}
		}

		return $rules;
	}

	public function generateCasts(): array
	{
		$casts = [];

		$timestamps = TableFieldsGenerator::getTimestampFieldNames();

		foreach ($this->config->fields as $field) {

			if($field->fieldDetails->is_virtual){
				continue;
			}
			
			$cast = "'".$field->name."' => ";

			switch (strtolower($field->fieldDetails->type)) {
				case 'integer':
				case 'int':
				case 'increments':
				case 'smallinteger':
				case 'smallint':
				case 'mediumint':
				case 'long':
				case 'bigint':
				case 'biginteger':
					$cast .= "'integer'";
					break;
				case 'double':
					$cast .= "'double'";
					break;
				case 'decimal':
					$cast .= sprintf("'decimal:%d'", $field->numberDecimalPoints);
					break;
				case 'float':
					$cast .= "'float'";
					break;
				case 'tinyint':
				case 'boolean':
					$cast .= "'boolean'";
					break;
				case 'time':
					$cast .= "'datetime:H:i:s'";
					break;
				case 'date':
					$cast .= "'datetime:Y-m-d'";
					break;
				case 'timestamp':
				case 'datetime':
				case 'datetimetz':
					$cast .= "'datetime:Y-m-d H:i:s'";
					break;
				case 'enum':
				case 'string':
				case 'char':
				case 'varchar':
				case 'text':
				case 'mediumtext':
				case 'longtext':
					$cast .= "'string'";
					break;
				case 'json':
					$cast .= "'array'";
					break;
				default:
					dd($field->fieldDetails->type);
					break;
			}

			if (!empty($cast)) {
				$casts[] = $cast;
			}
		}

		return $casts;
	}

	protected function generateRelations(): string
	{
		$relations = [];

		$count = 1;
		$fieldsArr = [];
		if (null !== $this->getTableRelations() && !empty($this->getTableRelations())) {
			foreach ($this->getTableRelations() as $relation) {
				$field = (isset($relation->inputs[0])) ? $relation->inputs[0] : null;

				$relationShipText = $field;
				if (in_array($field, $fieldsArr)) {
					$relationShipText = $relationShipText.'_'.$count;
					$count++;
				}

				$relationText = $relation->getRelationFunctionText($relationShipText, $this->config);
				
				if (!empty($relationText)) {
					$fieldsArr[] = $field;
					$relations[] = $relationText;
				}
			}
		}
		
		return implode(BiollanteHelper::instance()->format_nl_tab(2), $relations);
	}

	protected function generateAutoOrderAccessors(): array
	{
		$accessors = [];

		foreach ($this->getTableRelations() as $relation) {
			$relationName = $relation->inputs[0] ?? null;
			$relationType = $relation->type;
			$relatedModel = $relation->inputs[2] ?? null;

			if (!in_array($relationType, ['1tm', 'morphMany'])) {
				continue;
			}

			// Build full model class path to check its fields
			$relatedModelClass = "Biollante\\Models\\{$relatedModel}";
			if (!class_exists($relatedModelClass)) {
				continue;
			}

			$relatedModelInstance = new $relatedModelClass();
			if (
				property_exists($relatedModelInstance, 'table') &&
				(
					\Schema::hasColumn($relatedModelInstance->getTable(), 'order') ||
					\Schema::hasColumn($relatedModelInstance->getTable(), 'retrorder')
				)
			) {
				$methodName = Str::camel("get_{$relationName}_attribute");
				$accessors[] = "public function {$methodName}(\$value)
	{
		if (! \$this->relationLoaded('{$relationName}')) {
			return \$this->getAttributeFromArray('{$relationName}');
		}
		\$relation = \$this->getRelationValue('{$relationName}');
		if (! \$relation instanceof \\Illuminate\\Support\\Collection) {
			return \$relation;
		}
		\$first = \$relation->first();
		if (\$first && array_key_exists('order', \$first->getAttributes())) {
			return \$relation->sortBy('order')->values();
		}
		if (\$first && array_key_exists('retrorder', \$first->getAttributes())) {
			return \$relation->sortByDesc('retrorder')->values();
		}
		return \$relation;
	}";
			}
		}

		return $accessors;
	}

	protected function getTableRelations(): array
	{
		return array_filter($this->config->relations, function ($relation) {
			$relationName = $relation->inputs[0] ?? null;
			return !in_array($relationName, $this->extensionDefinedRelations, true);
		});
	}

	protected function extractExtensionRelations(): array
	{
		$path = base_path("app/Models/Extensions/{$this->config->modelNames->name}Extension.php");
		$relationNames = [];

		if (!file_exists($path)) {
			return [];
		}

		$code = file_get_contents($path);

		// Match self::$relationships = array_merge(...[ ... ]);
		preg_match_all('/self::\$relationships\s*=\s*array_merge\(.*?\[(.*?)\]\s*\)/s', $code, $matches);

		foreach ($matches[1] as $block) {
			preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*\[.*?\]/s', $block, $entries);
			foreach ($entries[1] as $name) {
				$relationNames[] = $name;
			}
		}

		return $relationNames;
	}

	protected function generateEnumConstants(): void
	{
		// Bail if constants path or fields are missing
		if (
			!isset($this->config->paths->constants) ||
			!$this->config->paths->constants ||
			!isset($this->config->fields) ||
			empty($this->config->fields)
		) {
			return;
		}

		$constantPath = $this->config->paths->constants;

		foreach ($this->config->fields as $field) {
			$fieldType = strtolower($field->fieldDetails->type ?? '');

			// Only ENUMs whose name doesn't end with '_type'
			if (
				$fieldType !== 'enum' ||
				str_ends_with($field->name, '_type') ||
				!isset($field->fieldDetails->enumValues) ||
				!is_array($field->fieldDetails->enumValues) ||
				empty($field->fieldDetails->enumValues)
			) {
				continue;
			}

			$modelName = $this->config->modelNames->name;
			$fieldName = $field->name;

			// File name: modelField.ts => passTypes.ts, gatheringStatuses.ts, etc.
			$fileBaseName = lcfirst($modelName) . Str::studly(Str::Plural($fieldName));
			$fileName = $fileBaseName . '.ts';

			// Constant + type names:
			$constantName = strtoupper(Str::snake($modelName.' '.$fieldName)).'_OPTIONS';
			$aliasName = $modelName . Str::studly($fieldName);
			$enumValues = $field->fieldDetails->enumValues;

			$templateData = view('biollante::generator.model.constant', [
				'modelName'		=> $modelName,
				'fieldName'		=> $fieldName,
				'fileBaseName'	=> $fileBaseName,
				'constantName'	=> $constantName,
				'aliasName'		=> $aliasName,
				'enumValues'	=> $enumValues,
			])->render();

			BiollanteHelper::instance()->g_filesystem()->createFile($constantPath.$fileName, $templateData);
			$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Constant created: ');
			$this->config->commandInfo($fileName);
		}
	}

	protected function modelHasSlug(): bool
	{
		if (!isset($this->config->fields) || empty($this->config->fields)) return false;

		foreach ($this->config->fields as $field) {
			if (($field->name ?? null) === 'slug') return true;
		}

		return false;
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('Model file deleted: '.$this->fileName);
		}
	}
}
