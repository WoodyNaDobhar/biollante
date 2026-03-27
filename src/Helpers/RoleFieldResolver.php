<?php

namespace Biollante\Helpers;

use Exception;
use Biollante\Generator\Utils\TableFieldsGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Async\Pool;
use Spatie\Permission\Models\Role;

class RoleFieldResolver
{
	
	const TYPE_OWN = 'Own';
	const TYPE_RELATED = 'Related';
	private static ?array $organizerRoles = null;
	private static array $columnCache = [];

	/**
	 * Resolves all permission-related field paths for a given table based on user roles and access type.
	 *
	 * For Own-type access, this method identifies direct paths where a User owns the resource.
	 * For Related-type access, it asynchronously resolves all applicable access paths for organizer roles.
	 *
	 * Duplicates are removed based on the combination of path and role type.
	 *
	 * @param string $table The base database table to evaluate.
	 * @param array $roles An array of role names to evaluate (e.g., ['Chapter Organizer', 'Vendor Organizer']).
	 * @param string $type Access type being evaluated. Use self::TYPE_OWN or self::TYPE_RELATED.
	 * @return array<int, object> Array of field path objects, each with:
	 *							 - string $path: The relationship or column path.
	 *							 - string $type: The base role type (e.g., 'user', 'chapter').
	 */
	public static function resolvePermissionPaths(string $table, array $roles, string $type): array
	{
		self::bootLaravelIfNeeded();
		$fields = [];
		if($type === self::TYPE_OWN){
			$role = 'User';
			$fields = self::resolveRoleFieldPathsRecursive($table, $role, $type);
			// fresh self::logDebugInfo('fields', 'all', $type, $fields);
		}else{
			$pool = Pool::create();
			$results = [];
			// fresh self::logDebugInfo($table, 'all', $type, $roles);
			foreach ($roles as $i => $role) {
				$pool->add(function () use ($table, $role, $type) {
					return self::resolveRoleFieldPathsRecursive($table, $role, $type);
				})->then(function ($result) use (&$results, $table, $role, $type) {
					// fresh self::logDebugInfo($table, $role, $type, $result);
					$results[] = $result;
				})->catch(function ($exception) use ($table, $role, $type) {
					// fresh self::logDebugInfo('ERROR:' . $table, $role, $type, $exception);
				});
			}
			$pool->wait(); // Ensure all tasks complete
			
			// fresh self::logDebugInfo($table, 'all', $type, $results);
			// Merge results into $fields
			foreach($results as $result){
				$fields = array_merge($fields, $result);
			}
			// fresh self::logDebugInfo($table, 'all', $type, $fields);
		}

		// Remove duplicates
		$uniqueFields = [];
		$fields = array_filter($fields, function ($item) use (&$uniqueFields) {
			$key = $item->path . '|' . $item->type; // Combine path and type as a unique key
			if (isset($uniqueFields[$key])) {
				return false; // Duplicate, so exclude it
			}
			$uniqueFields[$key] = true;
			return true;
		});
		// fresh self::logDebugInfo($table, 'all', $type, $fields);

		// Sort the fields consistently by 'path' and then by 'type'
		usort($fields, function ($a, $b) {
			return [$a->path, $a->type] <=> [$b->path, $b->type];
		});

		return !empty($fields) ? array_values($fields) : [];
	}

	/**
	 * Recursively resolves all role-related field paths for a given table and role context.
	 *
	 * This method traces relationships to identify how a user with a given role may access
	 * or control records in the specified table, based on direct foreign keys, enum-based
	 * polymorphic references, ancestor relationships, descendant tables, and many-to-many links.
	 *
	 * Skips recursion if a circular table dependency is detected.
	 *
	 * @param string $currentTable The name of the table currently being evaluated.
	 * @param string $role The role being evaluated (e.g., 'User', 'World Organizer').
	 * @param string $type The access type: self::TYPE_OWN or self::TYPE_RELATED.
	 * @param string $currentPath The dot-path from the origin table to the current table (used for recursion).
	 * @param array $checkedTables A list of tables already evaluated in this recursive chain to prevent infinite loops.
	 * @return array<int, object>|null Array of field path objects, each with:
	 *								  - string $path: The full path to the relationship or column.
	 *								  - string $type: The base role type (e.g., 'user', 'chapter').
	 */
	private static function resolveRoleFieldPathsRecursive(string $currentTable, string $role, string $type, string $currentPath = '', array $checkedTables = []): ?array
	{
		self::bootLaravelIfNeeded();
		$fields = [];
		$baseRole = Str::snake(Str::before($role, ' Organizer'));
		$organizerRoles = self::getOrganizerRoles();
		// fresh self::logDebugInfo($currentTable, $role, $type, $currentTable);
		// fresh self::logDebugInfo($currentTable, $role, $type, $currentPath);
		// fresh self::logDebugInfo($currentTable, $role, $type, $role);
		// fresh self::logDebugInfo($currentTable, $role, $type, $baseRole);
		// fresh self::logDebugInfo($currentTable, $role, $type, $checkedTables);

		if(in_array($currentTable, $checkedTables)){
			return $fields;
		}else{
			$checkedTables[] = $currentTable;
		}

		// Case 1: Role table itself
		if ($currentTable === Str::plural($baseRole)) {
			$field = new \stdClass();
			$field->path = $currentPath;
			$field->type = $baseRole;
			$fields[] = $field;
			// fresh self::logDebugInfo($currentTable, $role, $type, $field);
		}

		// Case 2: Field exists in model, not nullable if self::TYPE_RELATED
		$columns = self::getFieldsForTable($currentTable);
		// fresh self::logDebugInfo($currentTable, $role, $type, $columns);
		if (array_filter($columns, fn($column) => $column->Field === $baseRole . '_id' && $column->Null === 'NO')) { 
			$field = new \stdClass();
			$field->path = ($currentPath != '' ? $currentPath . '->' : '') . $baseRole . '_id';
			$field->type = $baseRole;
			$fields[] = $field;
			// fresh self::logDebugInfo($currentTable, $role, $type, $field);
			return $fields;
		}

		// Case 3: Enum field in current model, not nullable
		$enumField = self::findEnumFieldForRole($columns, $role);
		// fresh self::logDebugInfo($currentTable, $role, $type, $enumField);
		if ($enumField && !self::isColumnNullable($currentTable, $enumField->path)) {
			$field = new \stdClass();
			$field->path = ($currentPath != '' ? $currentPath . '->' : '') . str_replace('_type', '', $enumField->path);
			$field->type = $baseRole;
			$fields[] = $field;
			// fresh self::logDebugInfo($currentTable, $role, $type, $field);
			return $fields;
		}
		
		// if $type is self::TYPE_OWN, and $currentPath isn't empty, then don't do cases 4, 5, or 6
		if($type === self::TYPE_OWN && $currentPath !== ''){
			return $fields;
		}
		
		// Case 4: Ancestor has $role . '_id' field, or _type field with an option matching $role
		$ancestorTables = self::getAncestorRoleTables($currentTable, $currentPath, $role, $type);
		// fresh self::logDebugInfo($currentTable, $role, $type, $ancestorTables);
		foreach ($ancestorTables as $ancestorTable => $relationship) {
			if($relationship === 'parent'){
				continue;
			}
			$fullPath = ($currentPath != '' ? $currentPath . '->' . $relationship : $relationship);
			// fresh self::logDebugInfo($currentTable, $role, $type, $ancestorTable);
			// fresh self::logDebugInfo($currentTable, $role, $type, $fullPath);
			// fresh self::logDebugInfo($currentTable, $role, $type, $role);

			$ancestors = self::resolveRoleFieldPathsRecursive($ancestorTable, $role, $type, $fullPath, $checkedTables);
			// fresh self::logDebugInfo($currentTable, $role, $type, $ancestors);
			if ($ancestors) {
				foreach ($ancestors as $ancestor) {
					// fresh self::logDebugInfo($currentTable, $role, $type, $ancestor);
					$fields[] = $ancestor;
				}
			}
		}

		// fresh self::logDebugInfo($currentTable, $role, $type, $fields);

		// Case 5: On first iteration, if $baseRole is relateable, and Decendant has either $baseRole . '_id' field, or _type field with an option matching $baseRole
		if(
			$currentPath === '' && 
			(
				in_array(ucfirst($baseRole), $organizerRoles) &&
				$type === self::TYPE_RELATED
			)
			 ||

			// see: Missing related paths issue of ELF Development
			// we commented the above out because the Related descendant walk was over-matching and slow.
			// The longer memory-jogger:
			// Security overreach: the Case-5 descendant sweep was granting updateRelated/removeRelated through very loose multi-hop paths (mtm + polymorphics). On high-degree tables like files, organizers were picking up rights via chains like files -> campaigns -> owner -> teamable (or similar) even when one or more links were nullable or not actually scoping to their org. We saw tests where organizers got rights to unrelated files.
			// Correctness noise: the walk produced a ton of duplicate/near-duplicate paths and false positives (nullable FKs, broad morph enums that include User, mixed organizer types). It made FilePolicy/Test expectations unstable and hard to assert.
			// Performance: the initial sweep on files (with all those mtm relations) blew up traversal breadth, spiking generator time and async worker churn. Turning off Related’s Case-5 branch immediately calmed the biollante::generator.
			// So we parked it (commented the TYPE_RELATED arm) to ship stable Owner-based checks while we tightened filters (nullable gating, organizer scoping) and planned to reintroduce Related with stricter guards (depth limit, path-based cycle checks, stricter morph filtering, and better dedupe).

			(
				$baseRole === 'user' &&
				$type === self::TYPE_OWN
			)
		){
			$decendents = self::findDecendantRoleFields($currentTable, $role, $type);
			// fresh self::logDebugInfo($currentTable, $role, $type, $decendents);
			if(count($decendents) > 0){
				foreach ($decendents as $decendent) {
					$field = new \stdClass();
					$field->path = $decendent->path . ($decendent->name !== 'id' ? '->' . $decendent->name : '');
					$field->type = $baseRole;
					$fields[] = $field;
					// fresh self::logDebugInfo($currentTable, $role, $type, $field);
				}
			}
		}

		// Case 6: On first iteration, If the role has a model and that model has m2m relationships, return them.
		if ($currentPath === '') {
			$m2mRelations = self::getManyToManyRelations($currentTable, $role, $type);
			// fresh self::logDebugInfo($currentTable, $role, $type, $m2mRelations);
			foreach ($m2mRelations as $path => $relation) {
				$field = new \stdClass();
				$field->path = $path;
				$field->type = $baseRole;
				$fields[] = $field;
				// fresh self::logDebugInfo($currentTable, $role, $type, $field);
			}
		}

		// fresh self::logDebugInfo($currentTable, $role, $type, $fields);
		return $fields;
	}

	/**
	 * Retrieves ancestor tables that relate to the given table through mt1, 1t1, or morphTo relationships.
	 *
	 * This method identifies parent or owning tables that may establish a permission relationship
	 * through a direct foreign key, polymorphic type field, or standard 1-to-1 or many-to-1 relations.
	 * 
	 * Excludes nullable foreign keys and relationships where the polymorphic type includes 'User' but no relevant organizer role.
	 * TODO: reconsider the above logic.  It's intended to deal with situations where a polymorphic relationship includes both 'User' and organizer roles, creating conflicts
	 *
	 * @param string $tableName The name of the table being evaluated.
	 * @param string $currentPath The path leading to this table (used to identify its parent).
	 * @param string $role The permission role being evaluated (e.g., 'User', 'Chapter Organizer').
	 * @param string $type The type of access context (self::TYPE_OWN or self::TYPE_RELATED).
	 * @return array<string, string> An associative array where the key is the ancestor table name and the value is the local relationship name.
	 */
	private static function getAncestorRoleTables(string $tableName, string $currentPath, string $role, string $type): array
	{

		// fresh self::logDebugInfo($tableName, $role, $type, $tableName);
		// fresh self::logDebugInfo($tableName, $role, $type, $currentPath);
		if (!self::isTable($tableName, $role, $type)) {
			return [];
		}
		
		$relations = self::getRelationsFromTable($tableName);

		// fresh self::logDebugInfo($tableName, $role, $type, $relations);

		// Remove mt1 relationships where the field in $tableName ($relation->inputs[1]) is nullable and it's not the parent
		$parent = empty($currentPath) ? null : explode('->', $currentPath)[0];

		$organizerRoles = self::getOrganizerRoles();

		$filteredRelations = collect($relations)
			->filter(fn($relation) => in_array($relation->type, ['mt1','1t1','morphTo']))
			->reject(function ($relation) use ($organizerRoles, $role, $type, $tableName) {
				// fresh self::logDebugInfo($tableName, $role, $type, $relation);
				$targetModel = is_array($relation->inputs[1]) ? $relation->inputs[1] : $relation->inputs[2];
				// fresh self::logDebugInfo($tableName, $role, $type, $targetModel);
				$fieldId = is_array($relation->inputs[1]) ? $relation->inputs[0] : $relation->inputs[1];
				if(!str_contains($fieldId, '_')){
					$fieldId = $fieldId . '_id';
				}
				// fresh self::logDebugInfo($tableName, $role, $type, $fieldId);
				return 
					(
						is_array($targetModel) ?
						self::isColumnNullable($tableName, $fieldId) :
						self::isColumnNullable(Str::plural(lcfirst($tableName)), $fieldId)
					)
					 || 
					(
						is_array($targetModel) ?
						(in_array('User', $targetModel) && !collect($targetModel)->intersect($organizerRoles)->count()) :
						$targetModel === 'User'
					)
					;
			})
		;
		// fresh self::logDebugInfo($tableName, $role, $type, $filteredRelations);

		$relationsMap = collect($filteredRelations)
			->mapWithKeys(function ($relation) use($tableName, $role, $type) {
				// fresh self::logDebugInfo($tableName, $role, $type, $relation); //TODO: when this is in an error state, it fails without log or notice
				if ($relation->type === 'morphTo') {
					// MorphTo relationships store multiple possible related models
					$relationName = $relation->inputs[0]; // e.g., "thingable"
					$relatedTables = collect($relation->inputs[2])->reject(function ($relationInputs) {
						return $relationInputs === 'User' || $relationInputs === 'Guest';
					})->mapWithKeys(function ($modelName) use ($relationName) {
						$relatedTable = Str::snake(Str::plural($modelName));
						return [$relatedTable => $relationName];
					})->toArray();

					return $relatedTables;
				} else {
					$relatedTable = Str::snake(Str::plural($relation->inputs[2])); // Get related table name from model name
					$relationName = $relation->inputs[0]; // Relationship name in the current model
					return [$relatedTable => $relationName];
				}
			})
			->toArray();
		// fresh self::logDebugInfo($tableName, $role, $type, $relationsMap);

		// Map relations to return relevant ancestor tables
		return $relationsMap;
	}

	/**
	 * Determines whether a given column in a specific database table is nullable.
	 *
	 * This method checks the INFORMATION_SCHEMA to see if the specified column
	 * allows NULL values.
	 *
	 * @param string $table The name of the database table.
	 * @param string $column The name of the column within the table.
	 * @return bool True if the column is nullable, false otherwise.
	 */
	private static function isColumnNullable(string $table, string $column): bool
	{
		$schema = DB::select("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
		return !empty($schema) && $schema[0]->IS_NULLABLE === 'YES';
	}

	/**
	 * Checks whether a given table contains a non-nullable field that references the specified role.
	 *
	 * This method looks for either a direct foreign key field (e.g., `role_id`) or an enum-type
	 * polymorphic type field (e.g., `owner_type`) that includes the specified role.
	 *
	 * If a match is found, it returns an object representing the relationship field and the access path.
	 * If no valid role reference is found, it returns null.
	 *
	 * @param string $tableName The name of the table to inspect.
	 * @param string $relationship The relationship path leading to this table.
	 * @param string $role The role name to check for (e.g., 'Chapter', 'User').
	 * @return object|null An object with 'name' and 'path' properties if a valid match is found; otherwise null.
	 */
	private static function checkTableForRoleField(string $tableName, string $relationship, string $role): ?object
	{
		// dump($tableName);
		if (!self::isTable($tableName)) {
			return null;
		}
		
		// if the table is the role, we're good here
		if($tableName === Str::plural(lcfirst(str_replace(' Organizer', '', $role)))){
			return null;
		}

		$fields = self::getFieldsForTable($tableName);

		foreach ($fields as $field) {
			// self::logDebugInfo('users', $role, self::TYPE_RELATED, $field);
			if ($field->Field === lcfirst(str_replace(' Organizer', '', $role)) . '_id' && $field->Null === 'NO') {
				return (object) [
					'name' => $field->Field,
					'path' => Str::replaceLast("->{$field->Field}", "->" . Str::replaceLast('_type', '_id', $field->Field), $relationship),
				];
			}elseif (Str::startsWith($field->Type, 'enum') && Str::endsWith($field->Field, '_type') && $field->Null === 'NO') {
				$enumVals = self::getEnumValuesFromType($field->Type);
				if(in_array(Str::studly(str_replace(' Organizer', '', $role)), $enumVals)){
					return (object) [
						'name' => str_replace('_type', '', $field->Field),
						'path' => Str::replaceLast("->{$field->Field}", "->" . Str::replaceLast('_type', '_id', $field->Field), $relationship),
					];
				}else{
					$otherModels = self::parseEnumToRelatedRolesArray($field->Type);
					foreach($otherModels as $otherModel){
						$enumCheck = self::checkTableForRoleField(Str::snake(Str::plural($otherModel)), $relationship . '->' . Str::replaceLast('_type', '', $field->Field), $role);
						if($enumCheck){
							return $enumCheck;
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Parses an ENUM field definition to extract related role names, excluding organizers and users.
	 *
	 * This is primarily used to identify polymorphic enum references that may point to 
	 * roles not directly associated with the current permission model (e.g., non-organizer types).
	 *
	 * Organizer roles (e.g., 'Chapter Organizer') and 'User' are explicitly excluded from the result.
	 *
	 * @param string $enumDefinition The full ENUM definition string from the database (e.g., "enum('User','Vendor','Guest')").
	 * @return array A filtered list of role strings that are *not* organizers or users.
	 */
	private static function parseEnumToRelatedRolesArray(string $enumDefinition): array
	{
		// Extract the content inside the parentheses
		preg_match("/^enum\((.*)\)$/", $enumDefinition, $matches);
	
		if (!isset($matches[1])) {
			return [];
		}
	
		// Convert to an array and trim quotes
		$items = array_map(fn($item) => trim($item, "'"), explode(',', $matches[1]));
	
		// Get all roles with ' Organizer' in their name and strip ' Organizer' from them
		$excludedItems = Role::where('name', 'LIKE', '% Organizer')
			->get()
			->map(fn($role) => Str::before($role->name, ' Organizer'))
			->toArray();
		$excludedItems[] = 'User';
	
		// Filter out excluded items
		return array_filter($items, fn($item) => !in_array($item, $excludedItems));
	}

	/**
	 * Retrieves all defined relationships for a given table.
	 *
	 * Uses the TableFieldsGenerator to introspect the table structure and extract 
	 * relationship definitions including morphs, mt1, 1tm, 1t1, and mtm types.
	 *
	 * @param string $tableName The name of the database table.
	 * @return array An array of relationship definitions extracted from the table.
	 */
	private static function getRelationsFromTable(string $tableName): array
	{
		
		if (!self::isTable($tableName)) {
			return [];
		}

		$tableFieldsGenerator = new TableFieldsGenerator($tableName, [], null);
		$tableFieldsGenerator->prepareFieldsFromTable();
		$tableFieldsGenerator->prepareRelations();

		return $tableFieldsGenerator->relations;
	}

	/**
	 * Extracts the list of values from a MySQL enum column type.
	 *
	 * Parses the enum definition string and returns an array of individual values,
	 * stripped of quotes and whitespace.
	 *
	 * @param string $type The enum column type string (e.g., "enum('A','B','C')").
	 * @return array An array of enum values as strings.
	 */
	private static function getEnumValuesFromType(string $type): array
	{
		// Extract enum values from the 'enum(...)' definition
		preg_match('/^enum\((.*)\)$/', $type, $matches);

		return isset($matches[1]) ? array_map(fn($value) => trim($value, "'"), explode(',', $matches[1])) : [];
	}

	/**
	 * Retrieves and caches the column definitions for a given table.
	 *
	 * Uses MySQL's DESCRIBE statement to get field metadata, and caches results
	 * to reduce redundant queries during runtime.
	 *
	 * @param string $tableName The name of the database table.
	 * @return array An array of column objects containing field definitions.
	 */
	private static function getFieldsForTable(string $tableName): array
	{
		
		// Return from cache if present (including cached "missing" = [])
		if (isset(self::$columnCache[$tableName])) {
			return self::$columnCache[$tableName];
		}

		// Respect the current connection + prefix
		$connection = DB::connection();
		$prefixed = $connection->getTablePrefix() . $tableName;

		// If the table (or view) doesn't exist, cache empty and return
		if (!Schema::connection($connection->getName())->hasTable($tableName)) {
			return self::$columnCache[$tableName] = [];
		}
		
		return self::$columnCache[$tableName] = DB::select("DESCRIBE {$tableName}");
	}

	/**
	 * Attempts to locate a non-nullable enum field in the given table's columns that matches the specified role.
	 *
	 * If found, returns an object describing the relationship path and role. This also recursively checks related
	 * polymorphic tables when direct matches are not found in the current table.
	 *
	 * @param array $fields Array of column definitions (from DESCRIBE).
	 * @param string $role The role name (e.g., "User", "Chapter Organizer").
	 * @return object|null A role field object with 'name' and 'path', or null if no match found.
	 */
	private static function findEnumFieldForRole(array $fields, string $role): ?object
	{
		$baseRole = ucfirst(Str::before($role, ' Organizer'));
		foreach ($fields as $field) {
			if (str_contains($field->Type, 'enum') && Str::endsWith($field->Field, '_type') && $field->Null === 'NO'){
				$enumValues = self::getEnumValuesFromType($field->Type);
				// self::logDebugInfo($currentTable, $role, $type, $baseRole);
				// self::logDebugInfo($currentTable, $role, $type, $enumValues);
				if(
					in_array($baseRole, $enumValues)
				) {
					return (object) [
						'name' => 'user',
						'path' => $field->Field,
					];
				}else{
					// Recursive lookup in related polymorphic tables
					foreach ($enumValues as $polymorphicType) {
	
						$relatedTable = Str::snake(Str::plural($polymorphicType));
						$relatedFields = self::getFieldsForTable($relatedTable);
	
						// Iterate through related fields to find a match
						foreach ($relatedFields as $relatedField) {
							if (Str::endsWith($relatedField->Field, '_id') &&
								Str::before($relatedField->Field, '_id') === Str::snake($baseRole)) {
								return (object) [
									'name' => $relatedField->Field,
									'path' => str_replace('_type', '', $field->Field) . "->{$relatedField->Field}",
								];
							}
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Determines if the given table exists in the database schema.
	 *
	 * This is used to avoid querying non-existent tables during relationship resolution.
	 *
	 * @param string $tableName The name of the table to check.
	 * @param string|null $role Optional role context (unused in check but provided for logging/debugging).
	 * @param string|null $type Optional type context (unused in check but provided for logging/debugging).
	 * @return bool True if the table exists, false otherwise.
	 */
	private static function isTable(string $tableName, string $role = null, string $type = null): bool
	{
		return Schema::hasTable($tableName);
	}

	/**
	 * Recursively locates descendant tables that reference the given table and return
	 * fields that establish a required relationship to the specified role.
	 *
	 * This is used primarily in resolving self::TYPE_RELATED or self::TYPE_OWN permission paths
	 * from the starting table down to related role tables through foreign keys.
	 * 
	 * It checks for:
	 * - Direct foreign keys pointing back to the current table.
	 * - Pivot or intermediate tables used in indirect relationships.
	 * - Polymorphic enum fields that may match the given role.
	 *
	 * @param string $currentTable The table from which to begin the recursive check.
	 * @param string $role The full role name (e.g., 'Chapter Organizer', 'User').
	 * @param string $type The permission type (e.g., self::TYPE_RELATED, self::TYPE_OWN).
	 * @param string $currentPath (optional) Used internally for recursion tracking.
	 * @return array An array of objects with 'path' and 'name' for each valid descendant field.
	 */
	private static function findDecendantRoleFields(string $currentTable, string $role,  string $type, string $currentPath = ''): array
	{
		$results = [];
		$baseRole = str_replace(' Organizer', '', $role);
		$decendentTables = self::getDescendantTables($currentTable, $role, $type);
		// fresh self::logDebugInfo($currentTable, $role, $type, $baseRole);
		// fresh self::logDebugInfo($currentTable, $role, $type, $decendentTables);
		if (array_key_exists(Str::plural(strtolower($baseRole)), $decendentTables)) {

			$tableName = $decendentTables[Str::plural(strtolower($baseRole))];
		
			// Find foreign key fields in $tableName that reference $currentTable
			$foreignKeys = DB::select("
				SELECT DISTINCT COLUMN_NAME
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
				WHERE TABLE_NAME = ? 
				AND REFERENCED_TABLE_NAME = ?",
				[$tableName, $currentTable]
			);
		
			if (!empty($foreignKeys)) {
				// fresh self::logDebugInfo($currentTable, $role, $type, $foreignKeys);
				$result = collect($foreignKeys)->map(function ($foreignKey) use ($tableName) {
					if(!in_array($foreignKey->COLUMN_NAME, ['created_by', 'updated_by', 'deleted_by'])){
						return (object) [
							'name' => str_replace('_type', '', $foreignKey->COLUMN_NAME),
							'path' => $tableName
						];
					}
				})->filter()->values()->toArray();
				// fresh self::logDebugInfo($currentTable, $role, $type, $result);
				$results = array_merge($results, $result);
			}
		}else{
			foreach ($decendentTables as $field => $decendentTable) {
				$pivotTable = collect([Str::singular($field), Str::singular($currentTable)])->sort()->join('_');
				if(self::isTable($pivotTable)){
					$decendentTable = $pivotTable;
				}
				$result = self::checkTableForRoleField($decendentTable, $field, $baseRole);
				if ($result) {
					// fresh self::logDebugInfo($currentTable, $role, $type, $result);
					$results[] = $result;
					// continue;
				}
			}
		}

		return $results;
	}
	
	/**
	 * Retrieves all child (descendant) tables that are related to the given table
	 * via one-to-many, many-to-many, or morphMany relationships.
	 *
	 * Filters out relationships that:
	 * - Are nullable
	 * - Involve system columns (e.g., created_by)
	 * - Involve the 'User' model in conflicting polymorphic contexts
	 * - Involve unrelated Organizer roles
	 *
	 * Used during permission resolution to find possible downward paths
	 * from the current model to others where permissions might propagate.
	 *
	 * @param string $tableName The name of the source table.
	 * @param string $role The full role name (e.g., 'Chapter Organizer').
	 * @param string $type The permission type (e.g., self::TYPE_RELATED, self::TYPE_OWN).
	 * @return array An associative array mapping relationship names to related table names.
	 */
	private static function getDescendantTables(string $tableName, string $role,  string $type): array
	{
		if (!self::isTable($tableName)) {
			return [];
		}
	
		// self::logDebugInfo($tableName, $role, $type, $tableName);
		
		$relations = self::getRelationsFromTable($tableName);
	
		// fresh self::logDebugInfo($tableName, $role, $type, $relations);

		// Process and return an associative array with relation names as keys and table names as values
		$organizerRoles = self::getOrganizerRoles();
		$filteredRelations = collect($relations)
			->filter(fn($relation) => in_array($relation->type, ['1tm', 'mtm', 'morphMany', 'hmt'])); // Keep only plural relations;
	
		// fresh self::logDebugInfo($tableName, $role, $type, $filteredRelations);
		
		$selectedRelations = collect($filteredRelations)
			->reject(function ($relation) use ($organizerRoles, $tableName, $role, $type) {
				$targetTable = Str::plural(lcfirst($relation->inputs[2]));
				$targetModel = $relation->inputs[2];
				$fieldId = $relation->inputs[1];
				// fresh self::logDebugInfo($tableName, $role, $type, $relation);

				// Work out conflicts between 'User' being in a polymorphic relation that overlaps with an organizer role
				if($relation->type === 'morphMany'){
					if (
						$tableName === 'users' &&
						Str::endsWith($fieldId, '_type')
					) {
						$fields = self::getFieldsForTable($targetTable);
						foreach ($fields as $field) {
							if ($field->Field === $fieldId && Str::startsWith($field->Type, 'enum')) {
								$values = \Biollante\Helpers\RoleFieldResolver::getEnumValuesFromType($field->Type);
								if (in_array('User', $values)) {
									return true;
								}
							}
						}
					}
					if($type === self::TYPE_OWN){
						return true;
					}
				// 1tm relationships
				}elseif($relation->type === '1tm'){
					if(Str::startsWith($relation->inputs[0], ['created', 'updated', 'deleted'])){
						return true;
					}
				}elseif($relation->type === 'mtm'){
					return false;
				}
			
				return (
					(
						in_array($targetModel, $organizerRoles) && 
						str_replace(' Organizer', '', $role) !== $targetModel
					) ||
					self::isColumnNullable(Str::plural(lcfirst($targetModel)), $fieldId) ||
					$targetModel === 'User'
				);
			})
			->mapWithKeys(fn($relation) => [
				$relation->inputs[0] => Str::plural(strtolower($relation->inputs[2])) // Key: relation name, Value: table name
			])
			->toArray();

		// fresh self::logDebugInfo($tableName, $role, $type, $selectedRelations);
	
		return $selectedRelations;
	}
	
	/**
	 * Retrieves valid many-to-many (mtm) relationships for a given table and role.
	 *
	 * This is used to determine if a user with a given role has a valid path to a resource
	 * via a pivot table (e.g., users <-> files via file_user).
	 *
	 * Filters out:
	 * - mtm relations not associated with the current role context
	 * - relations to 'User' when not relevant
	 * - unrelated Organizer roles based on the current role
	 *
	 * Once potential mtm relationships are found, it recursively checks if the related table
	 * links back to the target role (and if so, returns those relation paths).
	 *
	 * @param string $tableName The current table being evaluated.
	 * @param string $role The role being checked (e.g., 'World Organizer').
	 * @param string $type The permission type (self::TYPE_RELATED or self::TYPE_OWN).
	 * @return array An associative array of valid many-to-many paths and their associated roles.
	 */
	private static function getManyToManyRelations(string $tableName, string $role, string $type): array
	{
		// Ensure table exists before proceeding
		if (!self::isTable($tableName)) {
			return [];
		}

		$organizerRoles = self::getOrganizerRoles();
		
		// if $type doesn't match $role, skip this one
		if(
			(
				$type === self::TYPE_OWN &&
				$role !== 'User'
			) ||
			(
				$type === self::TYPE_RELATED &&
				!in_array(str_replace(' Organizer', '', $role), $organizerRoles)
			)
		){
			// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', 'Skipped: ');
			// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', $type);
			// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', $role);
			return [];
		}

		// Fetch all relations from the table
		$relations = self::getRelationsFromTable($tableName);
		// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', $relations);

		$m2mRelations = collect($relations)
			->filter(fn($relation) => in_array($relation->type, ['mtm']))
			->reject(function ($relation) use ($organizerRoles, $tableName, $role, $type) {
				$targetModel = $relation->inputs[2];
				$fieldId = $relation->inputs[1];
				$targetTable = $relation->inputs[0];
				// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', $targetModel);
				// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', $fieldId);
				return 
				(
					$type === self::TYPE_OWN &&
					$role !== 'User'
				)
				 ||
				(
					$type === self::TYPE_RELATED &&
					!in_array(str_replace(' Organizer', '', $role), $organizerRoles)
				)
				;
			})
			->mapWithKeys(fn($relation) => [
				$relation->inputs[0] => Str::plural(strtolower($relation->inputs[2])) // Key: relation name, Value: table name
			])
			->toArray();

		// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', $m2mRelations);

		$m2mRelationsFin = [];
		foreach($m2mRelations as $relation){
			$relationAncestors = self::resolveRoleFieldPathsRecursive($relation, $role, $type, $relation, [$tableName]);
			// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', $relationAncestors);
			
			if ($relationAncestors) {
				foreach ($relationAncestors as $ancestor) {
					// if the first item in the path === $relation, add it to $m2mRelationsFin
					$relationPath = explode('->', $ancestor->path)[0];
					if($relationPath === $relation){
						// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', $ancestor);
						$m2mRelationsFin[$ancestor->path] = $ancestor->type;
					}
				}
			}
		}

		// fresh self::logDebugInfo($tableName, $role, 'getManyToManyRelations', $m2mRelationsFin);
		return $m2mRelationsFin;
	}
	
	/**
	 * Boots the Laravel application context if running in a standalone or async process.
	 *
	 * This ensures that database connections, service providers, and application services
	 * are available for use within long-running, isolated, or Spatie async workers.
	 * Only executes if an active database connection is not already established.
	 *
	 * Used primarily for background role resolution logic when run outside normal request lifecycle.
	 *
	 * @return void
	 */
	private static function bootLaravelIfNeeded()
	{
		try {
			DB::connection()->getPdo();
		} catch (\Exception $e) {
			$basePath = dirname(__DIR__, 4);
			require_once $basePath . '/vendor/autoload.php'; 
			$app = require_once $basePath . '/bootstrap/app.php';
			$app->make(Kernel::class)->bootstrap();
		}
	}

	/**
	 * Returns the list of organizer role base names.
	 *
	 * Reads from the published Biollante config ('biollante.organizer_roles').
	 * The result is cached for the lifetime of the process so the config lookup
	 * only happens once, even across multiple async workers.
	 *
	 * Falls back to an empty array if the config key is missing, which will
	 * cause Biollante to generate simpler policies with no organizer-scoped
	 * permission checks.
	 *
	 * @return array<int, string>
	 */
	private static function getOrganizerRoles(): array
	{
		if (self::$organizerRoles === null) {
			self::bootLaravelIfNeeded();
			self::$organizerRoles = config('biollante.organizer_roles', []);
		}
 
		return self::$organizerRoles;
	}

	/**
	 * Logs debug information to a file specific to the current table, role, and permission type.
	 *
	 * Automatically captures the file and line number where the log was triggered.
	 * Creates the log directory if it doesn't exist. Supports logging of strings, arrays, objects, and other primitive types.
	 *
	 * Logs are written to: `storage/logs/async_debug/{table}_{role}_{type}.log`
	 *
	 * @param string $currentTable The table context for the log (used in filename).
	 * @param string $role The role being evaluated (used in filename).
	 * @param string $type The type of permission evaluation (e.g., 'Own', 'Related').
	 * @param mixed $message The data or message to log. Arrays/objects will be JSON encoded.
	 * @return void
	 */
	private static function logDebugInfo(string $currentTable, string $role, string $type, mixed $message)
	{
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
		$line = $trace['line'] ?? 'unknown';
		$file = $trace['file'] ?? 'unknown';

		$logDir = (function_exists('storage_path') ? storage_path('logs/async_debug') : dirname(__DIR__, 4) . '/storage/logs/async_debug');
		if (!is_dir($logDir)) {
			mkdir($logDir, 0777, true); // Ensure the directory exists
		}

		// Generate a log file name based on currentTable, role, and type
		$logFile = "{$logDir}/{$currentTable}_{$role}_{$type}.log";

		// Convert message to string based on its type
		if (is_array($message) || is_object($message)) {
			$message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		} elseif (!is_string($message)) {
			$message = var_export($message, true);
		}

		// Add a timestamp to each log entry
		$timestamp = date('Y-m-d H:i:s');
		$logEntry = "[{$timestamp}] [File: {$file}] [Line: {$line}] {$message}\n";

		// Append the log entry to the file
		file_put_contents($logFile, $logEntry, FILE_APPEND);
	}
}
