<?php

namespace Biollante\Generator\Utils;

use DB;
use Biollante\Helpers\BiollanteHelper;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Biollante\Generator\Common\GeneratorField;
use Biollante\Generator\Common\GeneratorFieldRelation;

class GeneratorForeignKey
{
	/** @var string */
	public $name;
	public $localField;
	public $foreignField;
	public $foreignTable;
	public $onUpdate;
	public $onDelete;
}

class GeneratorTable
{
	/** @var string */
	public $primaryKey;

	/** @var GeneratorForeignKey[] */
	public $foreignKeys;
}

class TableFieldsGenerator
{
	/** @var string */
	public $tableName;
	public $primaryKey;

	/** @var bool */
	public $defaultSearchable;

	/** @var array */
	public $timestamps;

	/** @var AbstractSchemaManager */
	private $schemaManager;

	/** @var Column[] */
	private $columns;

	/** @var GeneratorField[] */
	public $fields;

	/** @var GeneratorFieldRelation[] */
	public $relations;

	/** @var array */
	public $ignoredFields;

	public function __construct($tableName, $ignoredFields, $connection = '')
	{
		$this->tableName = $tableName;
		$this->ignoredFields = $ignoredFields;

		$platform = DB::getDriverName();
		$defaultMappings = [
			'json' => 'text',
			'bit'  => 'boolean',
		];

		$columns = $this->fetchTableDetails($tableName);

		$this->columns = [];
		foreach ($columns as $column) {
			if (!in_array($column->name, $ignoredFields)) {
				$this->columns[] = $column;
			}
		}

		$this->primaryKey = $this->getPrimaryKeyOfTable($tableName);
		$this->timestamps = static::getTimestampFieldNames();
		$this->defaultSearchable = config('biollante.options.tables_searchable_default', false);
	}

	/**
	 * Prepares array of GeneratorField from table columns.
	 */
	public function prepareFieldsFromTable()
	{
		if (empty($this->columns)) {
			echo "The table '{$this->tableName}' does not exist or has no columns." . PHP_EOL;
			exit;
		}
		foreach ($this->columns as $column) {
			$type = $column->type;

			switch ($type) {
				case 'integer':
					$field = $this->generateIntFieldInput($column, 'integer');
					break;
				case 'smallint':
					$field = $this->generateIntFieldInput($column, 'smallInteger');
					break;
				case 'bigint':
					$field = $this->generateIntFieldInput($column, 'bigInteger');
					break;
				case 'boolean':
					$name = Str::title(str_replace('_', ' ', $column->name));
					$field = $this->generateField($column, 'boolean', 'checkbox');
					break;
				case 'datetime':
					$field = $this->generateField($column, 'datetime', 'date');
					break;
				case 'datetimetz':
					$field = $this->generateField($column, 'dateTimeTz', 'date');
					break;
				case 'date':
					$field = $this->generateField($column, 'date', 'date');
					break;
				case 'time':
					$field = $this->generateField($column, 'time', 'text');
					break;
				case 'decimal':
					$field = $this->generateNumberInput($column, 'decimal');
					break;
				case 'float':
					$field = $this->generateNumberInput($column, 'float');
					break;
				case 'text':
					$field = $this->generateField($column, 'text', 'textarea');
					break;
				case 'enum':
					$field = $this->generateField($column, 'string', 'enum');
					break;
				default:
					$field = $this->generateField($column, 'string', 'text');
					break;
			}
			
			if (strtolower($field->name) == 'password') {
				$field->htmlType = 'password';
				$field->isSearchable = false;
			} elseif (in_array($field->name, config('biollante.options.hidden_fields'))) {
				$field->isSearchable = false;
			}elseif (strtolower($field->name) == 'email') {
				$field->htmlType = 'email';
			} elseif (in_array($field->name, $this->timestamps)) {
				$field->isSearchable = false;
				$field->isFillable = false;
				$field->inForm = false;
				$field->inIndex = false;
				$field->inView = false;
			}
			
			$field->isNotNull = $column->is_nullable ? 0 : 1;
			$field->description = $column->comment ?? '';
			$field->default = $column->default;

			$this->fields[] = $field;
		}

		// Sort fields
		$this->sortFields();
	}

	/**
	 * Sorts fields alphabetically, with specific fields at the beginning and end in a specified order,
	 * '_id' fields sorted alphabetically after the prefix fields, and 'is_' fields after '_id' fields.
	 */
	private function sortFields()
	{
		// Special field configurations
		$prefix = ['id', 'name', 'slug'];
		$suffix = ['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_at', 'deleted_by'];
		$prefixFields = [];
		$suffixFields = [];
		$idFields = [];
		$isFields = [];
		$otherFields = [];

		// Separate prefix, suffix, '_id' fields, 'is_' fields, and other fields
		foreach ($this->fields as $field) {
			$fieldName = $field->name ?? '';
			if (in_array($fieldName, $prefix)) {
				$prefixFields[] = $field;
			} elseif (in_array($fieldName, $suffix)) {
				$suffixFields[] = $field;
			} elseif (str_ends_with($fieldName, '_id')) {
				$idFields[] = $field;
			} elseif (str_starts_with($fieldName, 'is_')) {
				$isFields[] = $field;
			} else {
				$otherFields[] = $field;
			}
		}

		// Sort prefix fields according to the specific order in $prefix
		$sortedPrefixFields = [];
		foreach ($prefix as $prefixFieldName) {
			foreach ($prefixFields as $field) {
				if ($field->name === $prefixFieldName) {
					$sortedPrefixFields[] = $field;
					break;
				}
			}
		}

		// Sort '_id' fields alphabetically
		usort($idFields, function ($a, $b) {
			return strcmp($a->name ?? '', $b->name ?? '');
		});

		// Sort 'is_' fields alphabetically
		usort($isFields, function ($a, $b) {
			return strcmp($a->name ?? '', $b->name ?? '');
		});

		// Sort other fields alphabetically
		usort($otherFields, function ($a, $b) {
			return strcmp($a->name ?? '', $b->name ?? '');
		});

		// Sort suffix fields according to the specific order in $suffix
		$sortedSuffixFields = [];
		foreach ($suffix as $suffixFieldName) {
			foreach ($suffixFields as $field) {
				if ($field->name === $suffixFieldName) {
					$sortedSuffixFields[] = $field;
					break;
				}
			}
		}

		// Merge sorted fields in the correct order: prefix, '_id' fields, 'is_' fields, other, and suffix
		$this->fields = array_merge($sortedPrefixFields, $idFields, $isFields, $otherFields, $sortedSuffixFields);
	}

	/**
	 * Get primary key of given table.
	 *
	 * @param string $tableName
	 *
	 * @return string|null The column name of the (simple) primary key
	 */
	public function getPrimaryKeyOfTable($tableName)
	{
		$databaseName = DB::getDatabaseName();

		// Query to retrieve the primary key column name
		$primaryKey = DB::selectOne("
			SELECT COLUMN_NAME
			FROM information_schema.key_column_usage
			WHERE table_name = :table
			AND table_schema = :schema
			AND constraint_name = 'PRIMARY'
		", [
			'table' => $tableName,
			'schema' => $databaseName
		]);
		return $primaryKey ? $primaryKey->COLUMN_NAME : null;
	}

	/**
	 * Get timestamp columns from config.
	 *
	 * @return array the set of [created_at column name, updated_at column name]
	 */
	public static function getTimestampFieldNames()
	{
		if (!config('biollante.timestamps.enabled', true)) {
			return [];
		}

		$createdAtName = config('biollante.timestamps.created_at', 'created_at');
		$createdByName = config('biollante.timestamps.created_by', 'created_by');
		$updatedAtName = config('biollante.timestamps.updated_at', 'updated_at');
		$updatedByName = config('biollante.timestamps.updated_by', 'updated_by');
		$deletedAtName = config('biollante.timestamps.deleted_at', 'deleted_at');
		$deletedByName = config('biollante.timestamps.deleted_by', 'deleted_by');

		return [$createdAtName, $createdByName, $updatedAtName, $updatedByName, $deletedAtName, $deletedByName];
	}

	/**
	 * Generates integer text field for database.
	 *
	 * @param string $dbType
	 * @param Column $column
	 *
	 * @return GeneratorField
	 */
	private function generateIntFieldInput($column, $dbType)
	{
		$field = new GeneratorField();
		$field->name = $column->name;
		$field->parseDBType($dbType);
		$field->htmlType = 'number';

		if ($column->is_autoincrement) {
			$field->dbType .= ',true';
		} else {
			$field->dbType .= ',false';
		}

		if ($column->is_unsigned) {
			$field->dbType .= ',true';
		}
		$field->fieldDetails = (new Collection($this->columns))->firstWhere('name', $field->name);
		
		return $this->checkForPrimary($field);
	}

	/**
	 * Check if key is primary key and sets field options.
	 *
	 * @param GeneratorField $field
	 *
	 * @return GeneratorField
	 */
	private function checkForPrimary(GeneratorField $field)
	{
		if ($field->name == $this->primaryKey) {
			$field->isPrimary = true;
			$field->isFillable = false;
			$field->isSearchable = false;
			$field->inIndex = false;
			$field->inForm = false;
			$field->inView = false;
		}

		return $field;
	}

	/**
	 * Generates field metadata for the given column.
	 *
	 * @param object $column Column metadata from information_schema.
	 * @param string $dbType Database type for the column.
	 * @param string $htmlType HTML input type for the field.
	 * @return GeneratorField
	 */
	private function generateField($column, $dbType, $htmlType)
	{
		$field = new GeneratorField();
		$field->name = $column->name;
		$field->fieldDetails = (new Collection($this->columns))->firstWhere('name', $field->name);

		// Parse database type and HTML input type for the field
		$field->parseDBType($dbType);
		$field->parseHtmlInput($htmlType);

		return $this->checkForPrimary($field);
	}

	/**
	 * Fetches and structures table details for a given table name.
	 *
	 * @param string $tableName The name of the table.
	 * @return array Associative array of column metadata.
	 */
	private function fetchTableDetails($tableName)
	{
		$databaseName = DB::getDatabaseName();

		// Fetch table column metadata
		$columns = DB::select("
			SELECT DISTINCT
				c.COLUMN_NAME as name,
				c.DATA_TYPE as type,
				c.CHARACTER_MAXIMUM_LENGTH as max_length,
				c.IS_NULLABLE as is_nullable,
				c.COLUMN_DEFAULT as default_value,
				c.COLUMN_KEY as column_key,
				c.COLUMN_COMMENT as comment,
				c.EXTRA as extra,
				c.COLUMN_TYPE as column_type,
				c.NUMERIC_PRECISION as numeric_precision,
				c.NUMERIC_SCALE as numeric_scale,
				CASE 
					WHEN tc.CONSTRAINT_TYPE = 'UNIQUE' THEN 1
					ELSE 0
				END as is_unique
			FROM information_schema.columns c
			LEFT JOIN information_schema.key_column_usage kcu 
				ON c.TABLE_NAME = kcu.TABLE_NAME 
				AND c.COLUMN_NAME = kcu.COLUMN_NAME 
				AND c.TABLE_SCHEMA = kcu.TABLE_SCHEMA
				AND kcu.REFERENCED_TABLE_NAME IS NOT NULL -- Ensures we're only linking valid foreign keys
			LEFT JOIN information_schema.table_constraints tc 
				ON kcu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME 
				AND kcu.TABLE_SCHEMA = tc.TABLE_SCHEMA
				AND tc.CONSTRAINT_TYPE IN ('UNIQUE', 'PRIMARY KEY') -- Only link relevant constraints
			WHERE c.TABLE_NAME = :table 
				AND c.TABLE_SCHEMA = :schema
		", [
			'table' => $tableName,
			'schema' => $databaseName,
		]);

		// Fetch unique constraints
		$uniqueConstraints = DB::select("
			SELECT 
				tc.CONSTRAINT_NAME, 
				GROUP_CONCAT(DISTINCT kcu.COLUMN_NAME ORDER BY kcu.ORDINAL_POSITION) AS columns
			FROM 
				information_schema.table_constraints tc
			JOIN 
				information_schema.key_column_usage kcu
				ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
				AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
			WHERE 
				tc.TABLE_NAME = :table
				AND tc.TABLE_SCHEMA = :schema
				AND tc.CONSTRAINT_TYPE = 'UNIQUE'
			GROUP BY 
				tc.CONSTRAINT_NAME
		", [
			'table' => $tableName,
			'schema' => $databaseName,
		]);

		// Map unique constraints
		$uniqueConstraintsMap = [];
		$uniques = [];
		foreach ($uniqueConstraints as $constraint) {
			// Parse the columns for the current constraint
			$constraintColumns = explode(',', $constraint->columns);
			if (count($constraintColumns) > 1) {
				// Set `is_unique_with` for the second column in the unique constraint
				$secondColumn = $constraintColumns[1];
				$firstColumn = $constraintColumns[0];
				$uniqueConstraintsMap[$secondColumn] = $firstColumn;
			}else{
				$uniques[] = $constraintColumns[0];
			}
		}

		// Process column details
		$details = [];
		foreach ($columns as $column) {
			// Attach comments to CUD fields
			$comment = $column->comment;
			switch ($column->name) {
				case 'created_at':
					$comment = 'When the record was created.';
					break;
				case 'created_by':
					$comment = 'User that created the record.';
					break;
				case 'updated_at':
					$comment = 'When the record was last updated (if any).';
					break;
				case 'updated_by':
					$comment = 'User that last updated the record (if any).';
					break;
				case 'deleted_at':
					$comment = 'When the record was deleted (if any).';
					break;
				case 'deleted_by':
					$comment = 'User that deleted the record (if any).';
					break;
			}

			$columnDetails = new \stdClass();
			$columnDetails->name = $column->name;
			$columnDetails->type = $column->type;
			$columnDetails->length = in_array($column->type, ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double', 'real', 'numeric'])
				? $column->numeric_precision
				: $column->max_length;
			$columnDetails->is_nullable = $column->is_nullable === 'YES';
			$columnDetails->is_virtual = 0;
			$columnDetails->default = $column->default_value;
			$columnDetails->is_primary = $column->column_key === 'PRI';
			$columnDetails->comment = $comment;
			$columnDetails->is_autoincrement = $column->extra === 'auto_increment';
			$columnDetails->is_unsigned = str_contains($column->column_type, 'unsigned');
			$columnDetails->is_unique = in_array($column->name, $uniques, true);
			$columnDetails->is_unique_with = $uniqueConstraintsMap[$column->name] ?? false;
			$columnDetails->precision = $column->numeric_precision;
			$columnDetails->scale = $column->numeric_scale;
			$details[] = $columnDetails;

			// Capture ENUM values if the column is an enum type
			if ($column->type === 'enum') {
				preg_match("/^enum\((.*)\)$/", $column->column_type, $matches);
				if (isset($matches[1])) {
					// Extract each value and trim surrounding quotes
					$columnDetails->enumValues = array_map(function ($value) {
						return trim($value, "'");
					}, explode(',', $matches[1]));
				}
			}
		}

		return $details;
	}

	/**
	 * Generates number field.
	 *
	 * @param Column $column
	 * @param string $dbType
	 *
	 * @return GeneratorField
	 */
	private function generateNumberInput($column, $dbType)
	{
		$field = new GeneratorField();
		$field->name = $column->name;
		$field->parseDBType($dbType.','.$column->precision.','.$column->scale);
		$field->htmlType = 'number';

		if ($dbType === 'decimal') {
			$field->numberDecimalPoints = $column->scale;
		}
		$field->fieldDetails = (new Collection($this->columns))->firstWhere('name', $field->name);

		return $this->checkForPrimary($field);
	}

	/**
	 * Prepares relations (GeneratorFieldRelation) array from table foreign keys.
	 */
	public function prepareRelations()
	{
		$foreignKeys = $this->prepareForeignKeys();
		$this->checkForRelations($foreignKeys);
	}

	/**
	 * Prepares foreign keys from all tables with required details.
	 *
	 * @return array Associative array of table names and their foreign keys.
	 */
	public function prepareForeignKeys()
	{
		$databaseName = DB::getDatabaseName();

		// Step 1: Fetch all tables in the database
		$excludedTablesArray = config('biollante.options.excluded_tables', []);
		$excludedTables = implode(',', array_fill(0, count($excludedTablesArray), '?'));
		$notLikeConditions = [];
		$notLikeClause = '';
		foreach ($excludedTablesArray as $table) {
			$tableMod = STR::singular($table);
			$notLikeConditions[] = "table_name NOT LIKE '%_" . $tableMod . "'";  // Matches tables like `product_session`
			$notLikeConditions[] = "table_name NOT LIKE '" . $tableMod . "_%'"; // Matches tables like `session_product`
		}
		if(count($notLikeConditions) > 0){
			$notLikeClause = 'AND ' . implode(' AND ', $notLikeConditions);
		}
		
		$bindings = array_merge([$databaseName], $excludedTablesArray);

		$tables = DB::select("
			SELECT table_name
			FROM information_schema.tables
			WHERE table_schema = ?
			AND table_name NOT IN ($excludedTables)
			$notLikeClause
		", $bindings);

		$fields = [];

		foreach ($tables as $table) {
			
			$tableName = $table->TABLE_NAME;

			// Fetch primary key for the current table
			$primaryKey = $this->getPrimaryKeyOfTable($tableName);

			// Fetch foreign keys for the current table
			$foreignKeys = DB::select("
				SELECT DISTINCT 
					kcu.CONSTRAINT_NAME AS name,
					kcu.COLUMN_NAME AS local_column,
					kcu.REFERENCED_TABLE_NAME AS foreign_table,
					kcu.REFERENCED_COLUMN_NAME AS foreign_column,
					rc.UPDATE_RULE AS on_update,
					rc.DELETE_RULE AS on_delete
				FROM information_schema.key_column_usage AS kcu
				JOIN information_schema.referential_constraints AS rc
					ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
					AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
				WHERE kcu.table_name = :table 
				AND kcu.table_schema = :schema
				AND kcu.REFERENCED_TABLE_NAME IS NOT NULL;
			", [
				'table' => $tableName,
				'schema' => $databaseName
			]);

			// Format each foreign key for the generator
			$formattedForeignKeys = [];
			foreach ($foreignKeys as $fk) {
				$generatorForeignKey = new GeneratorForeignKey();
				$generatorForeignKey->name = $fk->name;
				$generatorForeignKey->localField = $fk->local_column;
				$generatorForeignKey->foreignField = $fk->foreign_column;
				$generatorForeignKey->foreignTable = $fk->foreign_table;
				$generatorForeignKey->onUpdate = $fk->on_update;
				$generatorForeignKey->onDelete = $fk->on_delete;

				$formattedForeignKeys[] = $generatorForeignKey;
			}

			// Set up the GeneratorTable for this table
			$generatorTable = new GeneratorTable();
			$generatorTable->primaryKey = $primaryKey;
			$generatorTable->foreignKeys = $formattedForeignKeys;

			$fields[$tableName] = $generatorTable;
		}

		return $fields;
	}

	/**
	 * Prepares relations array from table foreign keys, including polymorphic relations.
	 *
	 * @param GeneratorTable[] $tables
	 */
	private function checkForRelations($tables)
	{
		$modelTableName = $this->tableName;
		$modelTable = $tables[$modelTableName];

		$this->relations = [];

		// Detect many-to-one relationships for model table
		$manyToOneRelations = $this->detectManyToOne($tables, $modelTable);
		if (count($manyToOneRelations) > 0) {
			$this->relations = array_merge($this->relations, $manyToOneRelations);
		}

		// Detect morphTo from $this->columns
		foreach ($this->columns as $column) {
			
			$field = $column->name;

			//skip if not '_id'
			if(!str_ends_with($field, '_id')){
				continue;
			}

			// Check if the column has a matching '_type' column
			if (collect($this->columns)->pluck('name')->contains(str_replace('_id', '_type', $field))) {
				$polymorphicName = Str::camel(str_replace('_id', '', $field));

				// Ensure that this polymorphic relation isn't already in $this->relations
				if (!collect($this->relations)->first(function ($relation) use ($polymorphicName) {
					return in_array($relation->type, ['morphTo']) && $relation->inputs[0] === $polymorphicName;
				})) {
					
					// Locate the column with the potential type field to access enum values
					$typeColumn = collect($this->columns)->firstWhere('name', str_replace('_id', '_type', $field));
					if ($typeColumn && isset($typeColumn->enumValues)) {
						$enumValuesString = implode(':', $typeColumn->enumValues);
						$this->relations[] = GeneratorFieldRelation::parseRelation(
							"morphTo,{$polymorphicName},{$field}," . $enumValuesString
						);
					}

				}
				continue;
			}
		}

		foreach ($tables as $tableName => $table) {
			$foreignKeys = $table->foreignKeys;
			$primary = $table->primaryKey;

			$manyToManyRelation = $this->isManyToMany($tables, $tableName, $modelTable, $modelTableName);
			if ($manyToManyRelation) {
				$this->relations[] = $manyToManyRelation;
				continue;
			}
			
			// Detection for morphOne and morphMany based on _type enums in other tables
			$checkColumns = $this->fetchTableDetails($tableName);
			foreach($checkColumns as $checkColumn){
				if(str_ends_with($checkColumn->name, '_type') && isset($checkColumn->enumValues)){
					
					// Detect morphOne or morphMany if the model name is in enum values
					if (in_array(ucfirst(Str::singular($this->tableName)), $checkColumn->enumValues)) {
						// Assume morphMany if other foreign keys are present, otherwise morphOne
						$relationName = ucfirst(Str::plural($this->tableName));
						
						// Detect morphToMany
						if(str_ends_with($tableName, 'ables')){

							// Add detected morphToMany relation
							$this->relations[] = GeneratorFieldRelation::parseRelation(
								"morphToMany,{$tableName},{$checkColumn->name}," . BiollanteHelper::instance()->model_name_from_table_name($tableName)
							);

						// Detect morphOne
						}elseif(stripos($checkColumn->comment, 'morphOne') !== false){

							$relationKey = Str::camel(Str::singular($tableName));
							$exists = collect($this->relations)->first(function ($relation) use ($relationKey) {
								return ($relation->inputs[0] ?? null) === $relationKey;
							});

							if ($exists) {
								$relationKey = Str::camel('context_' . Str::singular($tableName));
							}

							$this->relations[] = GeneratorFieldRelation::parseRelation(
								"morphOne,{$relationKey},{$checkColumn->name}," . BiollanteHelper::instance()->model_name_from_table_name($tableName)
							);

						// Assume morphMany
						}else{

							// If the inferred morphMany name would collide with an existing relation
							// (common when there's ALSO a pivot mtm relation), rename this one.
							$relationKey = Str::camel($tableName);
							$exists = collect($this->relations)->first(function ($relation) use ($relationKey) {
								return ($relation->inputs[0] ?? null) === $relationKey;
							});

							if ($exists) {
								$relationKey = Str::camel('context_' . $tableName);
							}

							// Add assumed morphMany relation
							$this->relations[] = GeneratorFieldRelation::parseRelation(
								"morphMany,{$relationKey},{$checkColumn->name}," . BiollanteHelper::instance()->model_name_from_table_name($tableName)
							);
						}
					}
				}
			}

			// Regular foreign key-based relationships (one-to-one, one-to-many)
			foreach ($foreignKeys as $foreignKey) {
				if ($foreignKey->foreignTable === $modelTableName) {
					// Check if one-to-one relationship
					$isOneToOne = $this->isOneToOne($primary, $foreignKey, $modelTable->primaryKey);
					if ($isOneToOne) {
						$modelName = strtolower(BiollanteHelper::instance()->model_name_from_table_name($tableName));
						$this->relations[] = GeneratorFieldRelation::parseRelation('1t1,' . $modelName);
						continue;
					}
					
					// Check if one-to-many relationship
					$isOneToMany = $this->isOneToMany($primary, $foreignKey, $modelTable->primaryKey);
					if ($isOneToMany) {
						$modelLabel = STR::plural(strtolower(BiollanteHelper::instance()->model_name_from_table_name($tableName)));
						$hasParentId = collect($this->columns)->pluck('name')->contains('parent_id');
						$isSameAsTableName = $modelLabel === $this->tableName;

						// Handle '_by' suffix in localField
						if (str_ends_with($foreignKey->localField, '_by')) {
							$prefix = str_replace('_by', '', $foreignKey->localField);
							$prefixParts = explode('_', $prefix);
							$camelCasePrefix = array_shift($prefixParts);
							foreach ($prefixParts as $part) {
								$camelCasePrefix .= ucfirst($part);
							}
							$modelLabel = $camelCasePrefix . ucfirst($modelLabel);
						}
						
						// Handle '_id' suffix in localField with special conditions
						elseif (str_ends_with($foreignKey->localField, '_id')) {
							$fieldBase = str_replace('_id', '', $foreignKey->localField);
							$pluralField = STR::plural($fieldBase);

							if ($pluralField !== strtolower($foreignKey->foreignTable)) {
								$camelCaseField = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $pluralField))));
								$modelLabel = Str::singular($modelLabel) . ucfirst($camelCaseField);
							}							
						}
						if($hasParentId && $isSameAsTableName){
							$modelLabel = 'descendants';
						}

						// Add relation to the array
						$this->relations[] = GeneratorFieldRelation::parseRelation(
							'1tm,' . $modelLabel . ',' . $foreignKey->localField . ',' . BiollanteHelper::instance()->model_name_from_table_name($tableName)
						);
						continue;
					}
				}
			}
		}

		// Merge in extension-based relationships (if any)
		$extensionPath = base_path("app/Models/Extensions/" . Str::studly(Str::singular($modelTableName)) . "Extension.php");
		if (file_exists($extensionPath)) {
			$code = file_get_contents($extensionPath);
			if (preg_match_all('/self::\\$relationships\s*=\s*array_merge\(.*?\[(.*?)\]\s*\)/s', $code, $matches)) {
				foreach ($matches[1] as $block) {
					preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*\[(.*?)\]/s', $block, $entries, PREG_SET_ORDER);
					foreach ($entries as $entry) {
						$name = $entry[1];
						$args = $entry[2];
						$laravelType = null;
						$model = null;

						if (preg_match('/["\']type["\']\s*=>\s*["\'](\w+)["\']/', $args, $typeMatch)) {
							$laravelType = $typeMatch[1];
						}
						if (preg_match('/["\']model["\']\s*=>\s*["\'](\w+)["\']/', $args, $modelMatch)) {
							$model = $modelMatch[1];
						}

						// Map Laravel method to internal relation code
						$internalType = match (strtolower($laravelType)) {
							'hasone' => '1t1',
							'hasmany' => '1tm',
							'belongsto' => 'mt1',
							'belongstomany' => 'mtm',
							'hasmanythrough' => 'hmt',
							'morphone' => 'morphOne',
							'morphmany' => 'morphMany',
							'morphto' => 'morphTo',
							'morphtomany' => 'morphToMany',
							default => 'unknown'
						};

						if ($internalType !== 'unknown' && $model && !collect($this->relations)->first(fn($r) => $r->inputs[0] === $name)) {
							$this->relations[] = \Biollante\Generator\Common\GeneratorFieldRelation::parseRelation(
								"{$internalType},{$name},{$name},{$model}"
							);
						}
					}
				}
			}
		}
		
		// Sort relations
		$this->sortRelations();
	}

	/**
	 * Sorts relations alphabetically, with created_by, updated_by, and deleted_by at the end.
	 */
	private function sortRelations()
	{
		// Separate created_by, updated_by, and deleted_by
		$prefixFields = ['id'];
		$suffixFields = ['createdBy', 'updatedBy', 'deletedBy'];
		$suffixRelations = [];
		$otherRelations = [];

		foreach ($this->relations as $relation) {
			$relationName = $relation->inputs[0] ?? '';
			if (in_array($relationName, $suffixFields)) {
				$suffixRelations[$relationName] = $relation;
			} else {
				$otherRelations[] = $relation;
			}
		}

		// Sort other relations alphabetically
		usort($otherRelations, function ($a, $b) {
			return strcmp($a->inputs[0] ?? '', $b->inputs[0] ?? '');
		});

		// Append the special fields in the specific order
		foreach ($suffixFields as $field) {
			if (isset($suffixRelations[$field])) {
				$otherRelations[] = $suffixRelations[$field];
			}
		}

		// Update the sorted relations array
		$this->relations = $otherRelations;
	}

	/**
	 * Detects many to many relationship
	 * If table has only two foreign keys
	 * Both foreign keys are primary key in foreign table
	 * Also one is from model table and one is from diff table.
	 *
	 * @param GeneratorTable[] $tables
	 * @param string		   $tableName
	 * @param GeneratorTable   $modelTable
	 * @param string		   $modelTableName
	 *
	 * @return bool|GeneratorFieldRelation
	 */
	private function isManyToMany($tables, $tableName, $modelTable, $modelTableName)
	{
		// get table details
		$table = $tables[$tableName];

		$isAnyKeyOnModelTable = false;

		// many to many model table name
		$manyToManyTable = '';

		$foreignKeys = $table->foreignKeys;
		$primary = $table->primaryKey;

		// check if any foreign key is there from model table
		foreach ($foreignKeys as $foreignKey) {
			if ($foreignKey->foreignTable == $modelTableName) {
				$isAnyKeyOnModelTable = true;
			}
		}

		// if foreign key is there
		if (!$isAnyKeyOnModelTable) {
			return false;
		}

		foreach ($foreignKeys as $foreignKey) {
			$foreignField = $foreignKey->foreignField;
			$foreignTableName = $foreignKey->foreignTable;

			// if foreign table is model table
			if ($foreignTableName == $modelTableName) {
				$foreignTable = $modelTable;
			} else {
				if(array_key_exists($foreignTableName, $tables)){
					$foreignTable = $tables[$foreignTableName];
					// get the many to many model table name
					$manyToManyTable = $foreignTableName;
				}
			}

			// if foreign field is not primary key of foreign table
			// then it can not be many to many
			if ($foreignField != $foreignTable->primaryKey) {
				return false;
				break;
			}

			// if foreign field is primary key of this table
			// then it can not be many to many
			if ($foreignField == $primary) {
				return false;
			}
		}

		if (empty($manyToManyTable)) {
			return false;
		}

		$modelName = Str::plural(strtolower(BiollanteHelper::instance()->model_name_from_table_name($manyToManyTable)));

		return GeneratorFieldRelation::parseRelation('mtm,' . $modelName . ',' . $tableName . ',' . BiollanteHelper::instance()->model_name_from_table_name($manyToManyTable));
	}

	/**
	 * Detects if one to one relationship is there
	 * If foreign key of table is primary key of foreign table
	 * Also foreign key field is primary key of this table.
	 *
	 * @param string			  $primaryKey
	 * @param GeneratorForeignKey $foreignKey
	 * @param string			  $modelTablePrimary
	 *
	 * @return bool
	 */
	private function isOneToOne($primaryKey, $foreignKey, $modelTablePrimary)
	{
		if ($foreignKey->foreignField == $modelTablePrimary) {
			if ($foreignKey->localField == $primaryKey) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detects if one to many relationship is there
	 * If foreign key of table is primary key of foreign table
	 * Also foreign key field is not primary key of this table.
	 *
	 * @param string			  $primaryKey
	 * @param GeneratorForeignKey $foreignKey
	 * @param string			  $modelTablePrimary
	 *
	 * @return bool
	 */
	private function isOneToMany($primaryKey, $foreignKey, $modelTablePrimary)
	{
		if (
			$foreignKey->foreignField == $modelTablePrimary &&
			$foreignKey->localField !== $primaryKey
		) 
		{
			return true;
		}

		return false;
	}

	/**
	 * Detect many to one relationship on model table
	 * If foreign key of model table is primary key of foreign table.
	 *
	 * @param GeneratorTable[] $tables
	 * @param GeneratorTable   $modelTable
	 *
	 * @return array
	 */
	private function detectManyToOne($tables, $modelTable)
	{
		$manyToOneRelations = [];

		$foreignKeys = $modelTable->foreignKeys;

		foreach ($foreignKeys as $foreignKey) {
			$foreignTable = $foreignKey->foreignTable;
			$foreignField = $foreignKey->foreignField;

			if (!isset($tables[$foreignTable])) {
				continue;
			}

			if ($foreignField == $tables[$foreignTable]->primaryKey) {
				$localFieldBase = str_replace('_id', '', $foreignKey->localField);
				if (str_ends_with($foreignKey->localField, '_by')) {
					$modelName = Str::camel($foreignKey->localField);
				} else {
					$modelName = strtolower(BiollanteHelper::instance()->model_name_from_table_name($foreignTable));
				}

				// Use the local field name if it differs from the foreign table name
				$relationName = (strtolower($localFieldBase) !== $modelName) ? Str::camel($localFieldBase) : $modelName;

				$manyToOneRelations[] = GeneratorFieldRelation::parseRelation(
					'mt1,' . $relationName . ',' . $foreignKey->localField. ',' . BiollanteHelper::instance()->model_name_from_table_name($foreignTable)
				);
			}
		}

		return $manyToOneRelations;
	}
}