<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Illuminate\Support\Str;
use Biollante\Generator\Utils\TableFieldsGenerator;
use Biollante\Generator\Common\GeneratorConfig;

class InterfacesGenerator extends BaseGenerator
{

	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->interfaces;
		$this->fileName = $this->config->modelNames->name . '.ts';
	}

	/**
	 * Generate interfaces files for the model.
	 */
	public function generate()
	{
		
		$modelName = $this->config->modelNames->name;

		// Generate interface
		$interfaces = [
			$modelName => $this->generateInterfaceContent($modelName),
			$modelName . 'Simple' => $this->generateInterfaceContent($modelName . 'Simple'),
			$modelName . 'SuperSimple' => $this->generateInterfaceContent($modelName . 'SuperSimple'),
		];

		$interfaceContent = implode('', $interfaces);

		// Create or update the file
		$filePath = $this->path . $this->fileName;
		BiollanteHelper::instance()->g_filesystem()->createFile($filePath, $interfaceContent);

		// Update the index.ts file
		if (!file_exists($this->path . 'index.ts')) {
			BiollanteHelper::instance()->g_filesystem()->createFile($this->path . 'index.ts', '');
		}
		$this->updateIndexFile();

		$this->config->commandComment(BiollanteHelper::instance()->format_nl() . 'Interface created: ');
		$this->config->commandInfo($this->config->modelNames->name . '.ts');
	}

	/**
	 * Generate the content for an interface.
	 *
	 * @param string $interfaceName
	 * @return string
	 */
	protected function generateInterfaceContent(string $interfaceName): string
	{
		$fields = $this->getFieldsForInterface($interfaceName);
		$relations = $this->getRelationsForInterface($interfaceName);
		$imports = $this->getImportsForInterface($relations, $interfaceName);

		// Render the Blade template
		return view('biollante::generator.interface.interface', [
			'interfaceName' => $interfaceName,
			'fields' => $fields,
			'relations' => $relations,
			'imports' => $imports,
		])->render();
	}

	protected function getFieldsForInterface(string $interfaceName): array
	{
		$isSuperSimple = Str::endsWith($interfaceName, 'SuperSimple');

		static $clientInterfaces = null;
		if (is_null($clientInterfaces)) {
			$clientInterfaces = $this->getInterfacesFromClientFolder();
		}

		// Step 1: Extract appended fields from Extension file
		$extendedFields = [];
		$extensionPath = base_path("app/Models/Extensions/{$this->config->modelNames->name}Extension.php");

		if (file_exists($extensionPath)) {
			$code = file_get_contents($extensionPath);

			if (preg_match('/static::retrieved\s*\(\s*function\s*\(\$model\)\s*\{(.*?)\}\s*\);/s', $code, $retrievedBlock)) {
				preg_match_all('/\$model->append\([\'"](\w+)[\'"]\)/', $retrievedBlock[1], $matches);
				$extendedFields = $matches[1] ?? [];
				sort($extendedFields);
			}
		}

		// SuperSimple: do not allow appended fields that effectively introduce relations
		// (i.e. accessor return types that reference known interfaces/models)
		if ($isSuperSimple && !empty($extendedFields)) {
			$extendedFields = array_values(array_filter($extendedFields, function ($fieldName) use ($clientInterfaces) {
				$tsTypeInfo = $this->mapExtensionTypeToTsType($fieldName);
				$type = $tsTypeInfo['type'];

				// Split unions and strip null/array markers
				$parts = array_map('trim', explode('|', $type));
				foreach ($parts as $part) {
					$base = trim($part);
					$base = Str::replace('[]', '', $base);
					$base = trim($base);

					if ($base === '' || strtolower($base) === 'null') {
						continue;
					}

					// If the accessor returns a known interface/model type, treat it as a relation-like field and exclude it
					if (in_array($base, $clientInterfaces, true)) {
						return false;
					}
				}

				return true;
			}));
		}

		$remainingExtendedFields = $extendedFields;

		// Step 2: Build fields list with patching in extended fields
		$fields = [];

		foreach ($this->config->fields as $field) {
			// Skip logic
			if (
				(
					$isSuperSimple && in_array($field->name, [
						'created_at', 'created_by',
						'updated_at', 'updated_by',
						'deleted_at', 'deleted_by'
					])
				) ||
				$field->fieldDetails->is_virtual
			) {
				continue;
			}

			// Insert extended fields before this field alphabetically
			while (!empty($remainingExtendedFields) && strcmp($remainingExtendedFields[0], $field->name) < 0) {
				$extra = array_shift($remainingExtendedFields);
				$tsTypeInfo = $this->mapExtensionTypeToTsType($extra);
				$fields[] = [
					'name' => $tsTypeInfo['nullable'] ? "{$extra}?" : $extra,
					'type' => $tsTypeInfo['nullable'] ? "{$tsTypeInfo['type']} | null" : $tsTypeInfo['type']
				];
			}

			// Default mapped TS type
			$type = $this->mapDbTypeToTsType($field->fieldDetails->type);

			// Nullable
			if ($field->fieldDetails->is_nullable) {
				$type .= ' | null';
			}

			// Enum handling
			if ($field->fieldDetails->type === 'enum') {
				$enumValues = array_map(function ($val) use ($clientInterfaces) {
					return "\"$val\"";
				}, $field->fieldDetails->enumValues);

				$type = implode(' | ', $enumValues);
			}

			$name = $field->fieldDetails->is_nullable ? "{$field->name}?" : $field->name;

			$fields[] = [
				'name' => $name,
				'type' => $type,
			];
		}

		// Step 3: Any remaining extended fields go at the end
		foreach ($remainingExtendedFields as $extra) {
			$tsTypeInfo = $this->mapExtensionTypeToTsType($extra);
			$fields[] = [
				'name' => $tsTypeInfo['nullable'] ? "{$extra}?" : $extra,
				'type' => $tsTypeInfo['nullable'] ? "{$tsTypeInfo['type']} | null" : $tsTypeInfo['type']
			];
		}

		return $fields;
	}
	
	protected function mapDbTypeToTsType(string $dbType): string
	{
		// Map database types to TypeScript types
		$map = [
			'bigint' => 'number',
			'mediumint' => 'number',
			'smallint' => 'number',
			'int' => 'number',
			'decimal' => 'number',
			'float' => 'number',
			'double' => 'number',
			'tinyint' => '0 | 1',
			'varchar' => 'string',
			'text' => 'string',
			'char' => 'string',
			'mediumtext' => 'string',
			'longtext' => 'string',
			'string' => 'string',
			'timestamp' => 'string',
			'datetime' => 'string',
			'date' => 'string',
			'time' => 'string',
			'json' => 'Record<string, unknown> | unknown[]',
		];
		return $map[$dbType] ?? 'FIXMETYPE1';
	}

	protected function mapExtensionTypeToTsType(string $fieldName): array
	{
		$extensionPath = base_path("app/Models/Extensions/{$this->config->modelNames->name}Extension.php");
	
		if (!file_exists($extensionPath)) {
			return ['type' => 'any', 'nullable' => false];
		}
	
		$code = file_get_contents($extensionPath);
	
		// Match all docblock + method pairs
		preg_match_all('/\/\*\*(.*?)\*\/\s*public function (get\w+Attribute)\s*\(\s*\)/s', $code, $matches, PREG_SET_ORDER);
	
		foreach ($matches as $match) {
			$docblock = $match[1];
			$method = $match[2];
			$expectedMethod = 'get' . Str::studly($fieldName) . 'Attribute';
			
			if ($method === $expectedMethod) {
				if (preg_match('/@return\s+([^\n\r]+)/', $docblock, $returnMatch)) {
					$returnTypeFull = trim($returnMatch[1]);
					$parts = array_map('trim', explode('|', $returnTypeFull));
	
					$types = [];
					$isNullable = false;
	
					foreach ($parts as $part) {
						$baseType = strtolower(trim($part, '\\'));
	
						switch (true) {
							case $baseType === 'null':
								$isNullable = true;
								break;
							case $baseType === 'string':
								$types[] = 'string';
								break;
							case in_array($baseType, ['int', 'integer', 'float', 'double']):
								$types[] = 'number';
								break;
							case in_array($baseType, ['bool', 'boolean']):
								$types[] = 'boolean';
								break;
							case $baseType === 'array':
								$types[] = 'any[]';
								break;
							case str_contains($part, 'Collection'):
								if (preg_match('/<([^>]+)>/', $part, $collectionMatch)) {
									$model = class_basename($collectionMatch[1]);
									$types[] = "{$model}[]";
								} else {
									$types[] = 'any[]';
								}
								break;
							case str_contains($part, 'Carbon'):
								$types[] = 'string';
								break;
							case str_starts_with($part, '\\Biollante\\Models\\'):
								$types[] = class_basename($part);
								break;
							default:
								$types[] = 'any';
						}
					}
	
					return [
						'type' => implode(' | ', array_unique($types)),
						'nullable' => $isNullable,
					];
				}
			}
		}
	
		return ['type' => 'any', 'nullable' => false];
	}	
	
	/**
	 * Gather all interface names from resources/@client/interfaces.
	 *
	 * Example: If "Guest.ts" and "User.ts" exist, it returns ["Guest", "User"].
	 *
	 * @return array<string>
	 */
	protected function getInterfacesFromClientFolder(): array
	{
		$interfacesDir = base_path('resources/@client/interfaces');
		$allFiles = @scandir($interfacesDir) ?: [];  // @ to suppress warning if missing

		$modelNames = [];

		foreach ($allFiles as $file) {
			if (Str::endsWith($file, '.ts')) {
				// e.g. "User.ts" => "User"
				$name = pathinfo($file, PATHINFO_FILENAME);
				$modelNames[] = $name;
			}
		}

		// remove duplicates, reindex
		$modelNames = array_unique($modelNames);
		return array_values($modelNames);
	}

	/**
	 * Get the relationships for the given interface.
	 *
	 * @param string $interfaceName
	 * @return array
	 */
	protected function getRelationsForInterface(string $interfaceName): array
	{
		// Exclude for Simple and SuperSimple
		if (Str::endsWith($interfaceName, 'Simple') || Str::endsWith($interfaceName, 'SuperSimple')) {
			return [];
		}

		$relations = array_map(function ($relation) {
			$relationName = $relation->relationName ?? $relation->inputs[0];
			$relatedModel = in_array($relationName, [
					'created_at', 'createdBy', 
					'updated_at', 'updatedBy', 
					'deleted_at', 'deletedBy'
				]) ? $relation->inputs[2] . 'Simple' : $relation->inputs[2];
			$relationType = $relation->type;

			// Determine the TypeScript type based on relation type
			$type = match ($relationType) {
				// Single-related references
				'mt1',          // Many-to-One
				'1t1',          // One-to-One
				'morphOne',     // Single polymorphic relation
					=> "{$relatedModel}",

				// Array-based references
				'1tm',          // One-to-Many
				'morphMany',    // Polymorphic many
				'mtm',          // Many-to-Many
				'hmt' 			// Has Many Through
					=> "{$relatedModel}[]",

				// MorphTo => union type, e.g. "UserSimple | AccountSimple"
				'morphTo' => $this->generateMorphToType($relatedModel),

				default => 'FIXMETYPE2', // Fallback for unsupported types
			};

			// Relationships are always optional
			return [
				'name' => "{$relationName}?",
				'type' => $type,
			];
		}, $this->config->relations);

		// Parse dynamic relationships from extension trait
		$extensionRelations = $this->extractStaticRelationshipsFromExtension($this->config->modelNames->name);
		$relationNames = array_column($relations, 'name');
		$filteredExtensionRelations = array_filter($extensionRelations, fn($extRel) =>
			!in_array($extRel['name'], $relationNames)
		);

		// Merge filtered extensions
		return array_merge($relations, array_values($filteredExtensionRelations));
	}

	/**
	 * Generate the TypeScript type for a MorphTo relationship.
	 *
	 * @param array $relatedModels
	 * @return string
	 */
	protected function generateMorphToType(array $relatedModels): string
	{

		// Gather recognized TS interface names from the client folder,
		// e.g. ["User","Account","Chapter","World"].
		$recognizedModels = $this->getInterfacesFromClientFolder();
	
		// First, filter out relations whose related model(s) isn't recognized.
		$filteredRelations = array_filter($relatedModels, function ($relation) use ($recognizedModels) {
			if (in_array($relation, $recognizedModels)) {
				return true;
			}
			return false; // none recognized
		});

		// Generate a union type of all possible related models as Simple interfaces
		$types = array_map(fn($model) => $model, $filteredRelations);
		return implode(' | ', $types); // Join types with '|' for a TypeScript union type
	}

	/**
	 * Extract the import model names from the array of relations,
	 * skipping the current model itself.
	 *
	 * @param array $relations Each relation has:
	 *    [
	 *      'name' => 'splits?',     // e.g. "splits?"
	 *      'type' => 'SplitSimple[]'
	 *    ]
	 * @param string $interfaceName
	 * @return string[] A list of base model names, e.g. ["Split", "Ledger", "User"]
	 */
	protected function getImportsForInterface(array $relations, string $interfaceName): array
	{
		$models = [];
		$currentModelName = $this->config->modelNames->name; // e.g. "User"

		//just the first one
		if(!str_contains($interfaceName, 'Simple')){

			foreach ($relations as $relation) {
				$type = $relation['type'];
	
				// Split on union operator "|", in case there's a morphTo union
				$possibleTypes = explode('|', $type);
	
				foreach ($possibleTypes as $modelName) {
					$modelName = trim($modelName);
					$modelName = Str::replace('[]', '', $modelName);
					$modelName = Str::replace('?', '', $modelName);
					$models[] = $modelName;
				}
			}

			// Step 2: Add imports for types from extension attributes (docblocks)
			$extensionPath = base_path("app/Models/Extensions/{$currentModelName}Extension.php");

			if (file_exists($extensionPath)) {
				$code = file_get_contents($extensionPath);

				// Match all docblock + method pairs
				preg_match_all('/\/\*\*(.*?)\*\/\s*public function (get\w+Attribute)\s*\(\s*\)/s', $code, $matches, PREG_SET_ORDER);

				foreach ($matches as $match) {
					$docblock = $match[1];

					if (preg_match('/@return\s+([^\n\r]+)/', $docblock, $returnMatch)) {
						$returnTypeFull = trim($returnMatch[1]);
						$parts = array_map('trim', explode('|', $returnTypeFull));

						foreach ($parts as $part) {
							if (str_starts_with($part, '\\Biollante\\Models\\')) {
								$model = class_basename($part);
								if (!empty($model) && $model !== $currentModelName) {
									$models[] = $model;
								}
							} elseif (str_contains($part, 'Collection')) {
								if (preg_match('/<([^>]+)>/', $part, $collectionMatch)) {
									$model = class_basename($collectionMatch[1]);
									if (!empty($model) && $model !== $currentModelName) {
										$models[] = $model;
									}
								}
							}
						}
					}
				}
			}
		}

		// Remove duplicates and filter out the current model (e.g. "User" shouldn't import "UserSimple")
		$filtered = array_filter(array_unique($models), function ($model) use ($currentModelName) {
			$modelBase = Str::replaceLast('Simple', '', Str::replace('[]', '', trim($model)));
			return $modelBase !== $currentModelName;
		});

		return array_values($filtered);
	}

	/**
	 * Update the index.ts file to include the interface, if required.
	 *
	 */
	protected function updateIndexFile()
	{
		$files = collect(scandir($this->path))
			->filter(fn($file) => Str::endsWith($file, '.ts') && $file !== 'index.ts');
	
		$exports = $files->map(fn($file) => "export { " . pathinfo($file, PATHINFO_FILENAME) . " } from './" . pathinfo($file, PATHINFO_FILENAME) . "';");
	
		$content = $exports->implode("\n");
		BiollanteHelper::instance()->g_filesystem()->createFile($this->path . '/index.ts', $content);
	}

	/**
	 * Extracts statically declared relationships from a model's Extension trait.
	 *
	 * Specifically looks for assignments to `self::$relationships` inside `static::retrieved`
	 * or `static::created` hooks, in the form of:
	 *     self::$relationships = array_merge(self::$relationships ?? [], [
	 *         'relationName' => ["type" => "BelongsTo", "model" => "ModelName"],
	 *     ]);
	 *
	 * This supports relationships added dynamically at runtime for inclusion in generated interfaces.
	 *
	 * @param string $modelName The base model name (e.g., "Persona").
	 * @return array<int, array{name: string, type: string}> A list of relations with names and TypeScript types.
	 */
	protected function extractStaticRelationshipsFromExtension(string $modelName): array
	{
		$path = base_path("app/Models/Extensions/{$modelName}Extension.php");
		$relationships = [];

		if (!file_exists($path)) {
			return [];
		}

		$code = file_get_contents($path);

		// Match static::retrieved or static::created with array_merge([...])
		preg_match_all('/self::\$relationships\s*=\s*array_merge\(.*?\[(.*?)\]\s*\)/s', $code, $matches);

		foreach ($matches[1] as $block) {
			preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*\[(.*?)\]/s', $block, $entries, PREG_SET_ORDER);

			foreach ($entries as $entry) {
				$name = $entry[1];
				$args = $entry[2];

				$type = null;
				$model = null;

				if (preg_match('/["\']type["\']\s*=>\s*["\'](\w+)["\']/', $args, $typeMatch)) {
					$type = $typeMatch[1];
				}
				if (preg_match('/["\']model["\']\s*=>\s*["\'](\w+)["\']/', $args, $modelMatch)) {
					$model = $modelMatch[1];
				}

				if ($type && $model && !isset($relationships[$name])) {
					$relationships[$name] = [
						'name' => "{$name}?",
						'type' => match ($type) {
							'BelongsTo', 'HasOne', 'MorphOne' => $model,
							'HasMany', 'BelongsToMany', 'MorphMany', 'HasManyThrough' => "{$model}[]",
							default => 'FIXMETYPE3'
						},
					];
				}
			}
		}

		return array_values($relationships);
	}
}
