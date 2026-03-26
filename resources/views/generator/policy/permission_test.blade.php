@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->permissionTests }};

use Biollante\Models\{{ $modelName }};
@if($modelName !== 'User')
use Biollante\Models\User;
@endif
use Faker\Factory as Faker;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class {{ $modelName }}PermissionsTest extends TestCase
{
	protected $ext = null;
	protected $faker;
	protected $admin;
	protected $roles;
	protected $permissions;
	protected array $createdModels = [];
	
	private static function getModelName(): string
	{
		return '{{ $modelName }}';
	}
	
	private static function getModelClass(): string
	{
		return {{ $modelName }}::class;
	}
	
	private static function getOrganizerRoles(): array
	{
		return config('biollante.roles.organizer_roles', []);
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->faker = Faker::create();
		$this->admin = User::findOrFail(1);
		$this->roles = [
@foreach($roles as $roleName => $role)
			'{{$roleName}}' => {!!$role ? $role : 'NULL'!!},
@endforeach
		];

		$this->permissions = [
			'list ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'index',
			'store ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'store',
			'display ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'show',
			'displayOwn ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'show',
			'displayRelated ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'show',
			'update ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'update',
			'updateOwn ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'update',
			'updateRelated ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'update',
			'remove ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'destroy',
			'removeOwn ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'destroy',
			'removeRelated ' . Str::snake(Str::plural(lcfirst(static::getModelName()))) => 'destroy',
		];

		$extClass = \Tests\Permissions\Extensions\{{ $modelName }}PermissionsTestExtension::class;
		if (\class_exists($extClass)) {
			$this->ext = new $extClass();
			if (\method_exists($this->ext, 'setUp')) {
				$this->ext->setUp($this);
			}
		}
	}

	/**
	 * Call $ext->$method($this) if available; otherwise run $default().
	 */
	private function runOrExt(string $method, \Closure $default)
	{
		if ($this->ext && \method_exists($this->ext, $method)) {
			return $this->ext->{$method}($this);
		}
		return $default();
	}

@foreach($roles as $role => $trash)
	public function test_{{ $camelName }}_{{strtolower(preg_replace('/\s+/', '_', $role))}}_permissions()
	{
		return $this->runOrExt(__FUNCTION__, function () {
			$user = @if($trash) User::factory()->create([
				'is_active'	=> 1
			])@if($role !== 'User')->assignRole('User')@endif->assignRole('{{$role}}'); @else null; @endif

			foreach ($this->permissions as $permission => $action) {
				$this->checkPermission($user, $permission, '{{$role}}', $action);
			}

			if ($user) {
				$this->actingAs($this->admin);
				$user->refresh();
				$user->updated_by = null;
				$user->deleted_by = null;
				$user->save();
				Schema::disableForeignKeyConstraints();
				$user->forceDelete();
				Schema::enableForeignKeyConstraints();
			}
		});
	}

@endforeach
	protected function checkPermission(?User $user, string $permission, string $roleName, string $action)
	{
		$url = $this->getActionUrl($action);
		$method = $this->getActionMethod($action);
		$payload = [];
		$this->createdModels = [];
		$newModel = null;
		foreach ($this->roles as $name => $role) {
			if (strpos($name, ' Organizer') !== false) {
				$trimmedRoleName = str_replace(' Organizer', '', $name);
				$organizerRoles[] = $trimmedRoleName;
			}
		}
	
		if ($user) {
			$this->actingAs($user);
		}

		$modelClass = static::getModelClass();
		$modelName = static::getModelName();

		if (in_array($action, ['store', 'update'])) {
			$payload = $modelClass::factory()->make([
				'created_by' => 1,
			])->toArray();

			$rules = $action === 'store' 
				? (new $modelClass)->getCreateRules()
				: (new $modelClass)->getUpdateRules();

			foreach ($rules as $field => $ruleSet) {
				if (!array_key_exists($field, $payload)) {
					if (is_array($ruleSet)) {
						$ruleSet = collect($ruleSet)
							->map(fn($r) => is_string($r) ? $r : get_class($r))
							->implode('|');
					}

					if (str_contains($ruleSet, 'required')) {
						switch (true) {
							case str_contains($ruleSet, 'Illuminate\Validation\Rules\Password'):
								$seed = rand(1,1000);
								$payload['password'] = "D!RF'Uu@)zt#:r7_sv>M{" . $seed;
								$payload['password_confirm'] = "D!RF'Uu@)zt#:r7_sv>M{" . $seed;
								break;
							case str_contains($ruleSet, 'array'):
								$payload[$field] = [1];
								break;
							case str_contains($ruleSet, 'boolean'):
								$payload[$field] = true;
								break;
							case str_contains($ruleSet, 'integer'):
								$payload[$field] = 1;
								break;
							case str_contains($ruleSet, 'string'):
								$payload[$field] = 'test';
								break;
							case str_contains($ruleSet, 'date'):
								$payload[$field] = now()->toDateTimeString();
								break;
							case str_contains($ruleSet, 'exists:'):
								$table = explode(':', explode('|', $ruleSet)[array_search(true, array_map(fn($r) => str_starts_with($r, 'exists:'), explode('|', $ruleSet)))])[1];
								$tableName = explode(',', $table)[0];
								$payload[$field] = \DB::table($tableName)->value('id') ?? 1;
								break;
							default:
								$payload[$field] = 'filler';
						}
					}
				}
			}
		}

		if (in_array($action, ['update'])) {
			foreach((new (static::getModelClass()))->getUpdateRules() as $ruleName => $rule){
				if(strpos($rule, 'unique:') > -1){
					unset($payload[$ruleName]);
				}
				if (strpos($rule, 'unique_with:') > -1) {
					preg_match('/unique_with:[^,]+,([^,]+)/', $rule, $matches);
					if (!empty($matches[1])) {
						unset($payload[$ruleName]);
						unset($payload[$matches[1]]);
					}
				}
			}
		}
		$defaultPayload = $payload;
	
		if (in_array($action, ['update', 'show', 'destroy'])) {
			if($roleName === 'Admin'){
				$newModel = static::getModelClass()::factory()->create();
				$this->createdModels[] = $newModel;
				$url = str_replace('{id}', $newModel->id, $url);
				$response = $this->executeAndAssertResponse($method, $url, $payload, $user, $permission, $roleName, $action);
				foreach (array_reverse($this->createdModels) as $model) {
					$model->forceDelete();
				}
			} else
			if (
				$user && 
				(
					str_contains($permission, 'Related ') ||
					str_contains($permission, 'Own ')
				)
			) 
			{
				$relationPaths = str_contains($permission, 'Related ') ? [
@foreach($relationPaths as $relationPath)
					'{!!$relationPath!!}',
@endforeach
				] :
				[
@foreach($ownPaths as $ownPath)
					'{!!$ownPath!!}',
@endforeach
				];
				
				foreach ($relationPaths as $relationPath) {
					$url = $this->getActionUrl($action);
					$newModel = null;
					$payload = $defaultPayload;
					$createdPayload = [];
					$createRelatedPayload = [];
					$baseRole = lcfirst(str_replace(' Organizer', '', $roleName));
					$roleField = $baseRole . '_id';
					$pathParts = explode('->', $relationPath);
					$pathPartsReversed = array_reverse($pathParts);
					$this->createdModels = [];
					$previousPart = null;
					$previousInstance = null;
					$relationData = null;
					$roleInstance = null;
					$positionable = null;
					$positionableType = null;
					$positionableId = null;
					$roleInstance = null;
					$useRoleInstance = false;

					if(
						(
							$roleName === 'User' && str_contains($permission, 'Related ')
						) ||
						(
							$roleName !== 'User' && str_contains($permission, 'Own ')
						)
					){
						continue;
					}

					if($relationPath !== '' && str_contains($relationPath, '->') && $this->isPlural(explode('->', $relationPath)[0])){

						$part = explode('->', $relationPath)[1];
						$table = explode('->', $relationPath)[0];

						if(
							(
								Str::endsWith($part, '_id') && 
								$part === $roleField
							) ||
							(
								$this->isPolyFieldOption($table, $part, $baseRole)
							)
						){

							if(
								(
									Schema::hasColumn(Str::snake(Str::plural(lcfirst(static::getModelName()))), $roleField) &&
									self::isNonNullableColumn(Str::snake(Str::plural(lcfirst(static::getModelName()))), $roleField)
								) ||
								(
									$this->isPolyFieldOption(Str::snake(Str::plural(lcfirst(static::getModelName()))), null, $baseRole)
								)
							){
                                $roleInstance = $baseRole !== 'user' ? $this->createRelatedInstance($baseRole) : $user;
								if($baseRole !== 'user'){
									$this->createdModels[] = $roleInstance;
								}
								$useRoleInstance = true;
								if (
									Schema::hasColumn(Str::snake(Str::plural(lcfirst(static::getModelName()))), $roleField)
								) {
									$createdPayload[$roleField] = $roleInstance->id;
								}else{
									$matchingTypeField = $this->isPolyFieldOption(Str::snake(Str::plural(lcfirst(static::getModelName()))), null, $baseRole);
									$matchingIdField = Str::replaceLast('_type', '_id', $matchingTypeField);
									$createdPayload[$matchingTypeField] = ucfirst($baseRole);
									$createdPayload[$matchingIdField] = $roleInstance->id;
								}
							}
							
							$newModel = static::getModelClass()::factory($createdPayload)->create();
							$this->createdModels[] = $newModel;

							if (!$roleInstance) {
								if($baseRole === lcfirst(static::getModelName())){
									$roleInstance = $newModel;
								}elseif($baseRole === 'user'){
									$roleInstance = $user;
								}else{
									$roleInstance = $this->createRelatedInstance($baseRole);
									$this->createdModels[] = $roleInstance;
								}
							}
	
							if (str_contains($part, '_id')) {
								$createRelatedPayload[$roleField] = $roleInstance->id;
							} else {
								$createRelatedPayload[$part . '_type'] = ucfirst($baseRole);
								$createRelatedPayload[$part . '_id'] = $roleInstance->id;
							}
	
							if($roleName !== 'User' && $roleName !== 'Admin'){
								$position = \Biollante\Models\Position::factory()->create([
									'name' => 'Organizer',
									'positionable_type' => ucfirst($baseRole),
									'positionable_id' => $roleInstance->id,
								]);
								$this->createdModels[] = $position;
								$orgPersona = \Biollante\Models\Persona::factory()->create([
									'user_id' => $user->id,
									'is_active'	=> 1
								]);
								$this->createdModels[] = $orgPersona;
								$organizer = \Biollante\Models\Organizer::factory()->create([
									'persona_id' => $orgPersona->id,
									'presideable_type' => ucfirst($baseRole),
									'presideable_id' => $roleInstance->id,
									'position_id' => $position->id,
								]);
								$this->createdModels[] = $organizer;
								$user->refresh();
							}
	
							$instance = $this->createRelatedInstance($table, $newModel, $roleInstance, $createRelatedPayload);

							if(!$instance){
								$modelsToRemove = array_reverse($this->createdModels);
								foreach ($modelsToRemove as $model) {
									$model->forceDelete();
								}
								continue;
							}elseif(is_object($instance) && $baseRole !== 'user'){
								$this->createdModels[] = $instance;
							}
	
							$url = str_replace('{id}', $newModel->id, $url);
							$response = $this->executeAndAssertResponse($method, $url, $payload, $user, $permission, $roleName, $action);
			
							$modelsToRemove = array_reverse($this->createdModels);

							foreach ($modelsToRemove as $i => $modelA) {
								foreach ($modelsToRemove as $j => $modelB) {
									if ($i === $j) continue;
									$tableA = Str::snake(Str::singular(class_basename($modelA)));
									$tableB = Str::snake(Str::singular(class_basename($modelB)));
									$pivot = collect([$tableA, $tableB])->sort()->implode('_');
									if (Schema::hasTable($pivot)) {
										\DB::table($pivot)->where([
											"{$tableA}_id" => $modelA->id,
											"{$tableB}_id" => $modelB->id,
										])->delete();
									}
								}
							}
							foreach ($modelsToRemove as $model) {
								$model->forceDelete();
							}
						} else {
							// skip path not relevant to this role
						}
					}else{
						$previousInstance = null;
						foreach ($pathPartsReversed as $i => $part) {
							if($i === 0){
								$table = array_key_exists($i+1, $pathPartsReversed) ? Str::plural($pathPartsReversed[$i+1]) : null;
								if(
									(
										$part === '' && 
										$baseRole === lcfirst(static::getModelName())
									) ||
									(
										Str::endsWith($part, '_id') && 
										$part === $roleField
									) ||
									(
										$this->isPolyFieldOption($table, $part, $baseRole)
									)
								){
									$previousInstance = ($baseRole === 'user') ?
										$user :
										$this->createRelatedInstance($baseRole);
									$previousPart = $part;

									if($roleName !== 'User'){
										$position = \Biollante\Models\Position::factory()->create([
											'name' => 'Organizer',
											'positionable_type' => ucfirst($baseRole),
											'positionable_id' => $previousInstance->id,
										]);
										$this->createdModels[] = $position;
										$orgPersona = \Biollante\Models\Persona::factory()->create([
											'user_id' => $user->id,
											'is_active'	=> 1
										]);
										$this->createdModels[] = $orgPersona;
										$organizer = \Biollante\Models\Organizer::factory()->create([
											'persona_id' => $orgPersona->id,
											'presideable_type' => ucfirst($baseRole),
											'presideable_id' => $previousInstance->id,
											'position_id' => $position->id,
										]);
										$this->createdModels[] = $organizer;
										$user->refresh();
									}
								}else{
									continue 2;
								}
							}else{
								$relatedType = $this->determineRelatedType($part, $previousInstance, array_key_exists($i+1, $pathPartsReversed) ? $pathPartsReversed[$i+1] : '', $pathPartsReversed[$i-1]);
								$newPreviousInstance = $this->createRelatedInstance($relatedType,  ($i === 1 ? null : $previousInstance), ($i === 1 ? $previousInstance : null));
								$previousInstance = $newPreviousInstance;
								$previousPart = $part;
								$this->createdModels[] = $newPreviousInstance;
							}
						}
			
						$user->refresh();
		
						$url = $this->getActionUrl($action);
	
						if(str_contains($previousPart, '_id')){
							$payload[$previousPart] = $previousInstance->id;
						}elseif(class_exists("Biollante\\Models\\" . ucfirst(Str::singular($previousPart)))){
							$payload[$previousPart . '_id'] = $previousInstance->id;
						}else{
							$payload[$previousPart . '_type'] = str_replace('Biollante\\Models\\', '', $previousInstance::class);
							$payload[$previousPart . '_id'] = $previousInstance->id;
						}
	
						if(!in_array($action, ['show','update','destroy']) && $roleName !== 'User'){
							if (Schema::hasColumn(Str::snake(Str::plural(lcfirst(static::getModelName()))), 'user_id')) {
								$payload['user_id'] = $user->id;
							}
						}

						if($roleName === 'User' && static::getModelName() === 'User'){
							$newModel = $user;
						}else{
							$newModel = static::getModelClass()::factory()->create($payload);
							$this->createdModels[] = $newModel;
						}
						
						$url = str_replace('{id}', $newModel->id, $url);
						$response = $this->executeAndAssertResponse($method, $url, $payload, $user, $permission, $roleName, $action);
		
						$modelsToRemove = array_reverse($this->createdModels);
						foreach ($modelsToRemove as $model) {
							$model->forceDelete();
						}
					}
				}
			}
		} else {
			$response = $this->executeAndAssertResponse($method, $url, $payload, $user, $permission, $roleName, $action);
			$modelsToRemove = array_reverse($this->createdModels);
			foreach ($modelsToRemove as $model) {
				$model->forceDelete();
			}
			if ($action === 'store') {
				$responseData = $response->json();
				if (isset($responseData['data']['id'])) {
					Schema::disableForeignKeyConstraints();
					static::getModelClass()::find($responseData['data']['id'])->forceDelete();
					Schema::enableForeignKeyConstraints();
				}
			}
		}
	}
	
	private function findFirstTableWithField(string $field): ?string
	{
		$tables = collect(\DB::select("SHOW TABLES"))
			->map(fn($table) => array_values((array) $table)[0])
			->toArray();

		foreach ($tables as $table) {
			if (Schema::hasColumn($table, $field)) {
				return $table;
			}
		}

		return null;
	}

	private function isPolyFieldOption(?string $table, ?string $part, string $baseRole): ?string
	{
		$targetValue = ucfirst($baseRole);
		$columnName = null;
		if(!Schema::hasTable($table)){
			return null;
		}
		if($part){
			$columnName = $part . '_type';
			if(!self::isNonNullableColumn($table, $columnName)){
				return null;
			}
		}else{
			$columns = Schema::getColumnListing($table);
			foreach ($columns as $column) {
				if (
					$column && 
					Str::endsWith($column, '_type') &&
					self::isNonNullableColumn($table, $column) && 
					in_array($targetValue, $this->getEnumValues($table, $column))
				) {
					$columnName = $column;
					break;
				}
			}
		}
		if($columnName){
			$enumOptions = $this->getEnumValues($table, $columnName);
			if (in_array($targetValue, $enumOptions, true)) {
				return $columnName;
			}
		}

		return null;
	}
	
	private function createRelatedInstance(string $modelType, ?object $previousInstance = null, ?object $roleInstance = null, ?array $attributes = [])
	{
		$tableName = null;
		$fieldName = null;
		$modelClass = "\\Biollante\\Models\\" . ucfirst(Str::singular($modelType));
		$model_id = array_key_exists(Str::snake(lcfirst(static::getModelName())) . '_id', $attributes) ? $attributes[Str::snake(lcfirst(static::getModelName())) . '_id'] : null;
		$pivotAttributes = null;
		$pivotTable = null;
		if (!class_exists($modelClass)) {
			$isPlural = $this->isPlural($modelType);
			$segments = explode('_', Str::snake($modelType));
			$tableName = Str::plural(array_shift($segments));
			$modelType = !$isPlural ? Str::singular($tableName) : $tableName;
			$modelClass = "\\Biollante\\Models\\" . Str::singular(ucfirst($tableName));
			$fieldName = array_shift($segments) ?? '';
			if (!class_exists($modelClass)) {
				dd('CHECKME');
			}
		}

		if ($previousInstance){
			$tableName = $tableName ? $tableName : Str::plural(Str::snake($modelType));
			$previousModel = Str::snake(class_basename($previousInstance));

			if($this->isPlural($modelType)){
				$pivotTable = collect([
					Str::singular(Str::snake($modelType)),
					Str::singular(Str::snake($previousModel))
				])->sort()->implode('_');
				if(Schema::hasTable($pivotTable)){
					$pivotAttributes[Str::singular(Str::snake($previousModel)) . '_id'] = $previousInstance->id;
				}
			}

			if (Schema::hasColumn($tableName, $previousModel . "_id")) {
				$attributes[$previousModel . "_id"] = $previousInstance->id;
			} else {
				$columns = Schema::getColumnListing($tableName);
				$polyField = null;
				foreach ($columns as $column) {
					if ($column && Str::endsWith($column, '_type') && in_array(ucfirst($previousModel), $this->getEnumValues($tableName, $column))) {
						$polyField = $column;
						break;
					}
				}
				if ($polyField) {
					$attributes = array_merge($attributes ?? [], [
						$polyField => ucfirst($previousModel),
						Str::replaceLast('_type', '_id', $polyField) => $previousInstance->id,
					]);
				}
			}
		}

		if ($roleInstance && empty($attributes)){
			$tableName = $tableName ? $tableName : Str::plural(Str::snake($modelType));
			$roleModel = Str::snake(class_basename($roleInstance));
			if (Schema::hasColumn($tableName, $roleModel . "_id")) {
				$attributes[$roleModel . "_id"] = $roleInstance->id;
			} 
			$columns = Schema::getColumnListing($tableName);
			$polyField = null;
			foreach ($columns as $column) {
				if ($column && Str::endsWith($column, '_type') && in_array(ucfirst($roleModel), $this->getEnumValues($tableName, $column))) {
					$polyField = $column;
					break;
				}
			}
			if ($polyField) {
				$attributes[$polyField] = ucfirst($roleModel);
				$attributes[Str::replaceLast('_type', '_id', $polyField)] = $roleInstance->id;
			}
		}

		$mainInstance = $modelClass::factory()->create($attributes);
		$this->createdModels[] = $mainInstance;

		if ($pivotAttributes){
			$pivotAttributes[Str::singular(Str::snake($modelType)) . '_id'] = $mainInstance->id;
			\DB::table($pivotTable)->insert($pivotAttributes);
		}
		
		return $mainInstance;
	}

	private function determineRelatedType(string $part, ?object $previousModel, ?string $parent, ?string $child)
	{
		if (class_exists("\\Biollante\\Models\\" . ucfirst(Str::singular($part)))) {
			return ucfirst(Str::singular($part));
		} else
		if ($this->isPlural(($part))) {
			$tableName = Str::snake($part);
			$segments = explode('_', $tableName);
			$baseSegment = array_shift($segments);
			$fieldName = Str::singular(implode('_', $segments)) . '_id';
			$candidateTable = Str::plural($baseSegment);
			if (Schema::hasTable($candidateTable)) {
				$columns = Schema::getColumnListing($candidateTable);
				if (in_array($fieldName, $columns) && in_array($child, $columns)) {
					return ucfirst(Str::singular($candidateTable));
				}
			}
			dd('CHECKME');
		} else
		if(class_exists("\\Biollante\\Models\\" . ucfirst($parent))) {
			$parentEnumOptions = $this->getEnumValues(Str::plural($parent), $part . '_type');
			foreach($parentEnumOptions as $parentEnumOption){
				if($parentEnumOption === ucfirst($child)){
					return $parentEnumOption;
				}
			}
			foreach($parentEnumOptions as $parentEnumOption){
				if(
					Schema::hasColumn(lcfirst(Str::plural($parentEnumOption)), $child . '_id') || 
					Schema::hasColumn(lcfirst(Str::plural($parentEnumOption)), $child)
				){
					return $parentEnumOption;
				}
			}
			dd("CHECKME");
		}else{
			$parentTable = $this->findFirstTableWithField($part . '_type');
			$parentEnumOptions = $this->getEnumValues($parentTable, $part . '_type' );
			foreach($parentEnumOptions as $parentEnumOption){
				if(Schema::hasColumn(lcfirst(Str::plural($parentEnumOption)), $child . '_id') || Schema::hasColumn(lcfirst(Str::plural($parentEnumOption)), $child)){
					return $parentEnumOption;
				}
			}
			dd("CHECKME");
		}
	}
	
	private function getEnumValues(string $table, string $field): array
	{
		$enumQuery = \DB::select("SELECT COLUMN_TYPE 
			FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_NAME = ? AND COLUMN_NAME = ? AND DATA_TYPE = 'enum'", 
			[$table, $field]);
	
		if (empty($enumQuery)) {
			return [];
		}
	
		$enumDefinition = $enumQuery[0]->COLUMN_TYPE;
		preg_match('/enum\((.*)\)/', $enumDefinition, $matches);
	
		return isset($matches[1]) ? array_map(fn($value) => trim($value, "'"), explode(',', $matches[1])) : [];
	}

	private function executeAndAssertResponse(string $method, string $url, array $payload, ?User $user, string $permission, string $roleName, string $action): \Illuminate\Testing\TestResponse
	{
		if ($user) {
			$this->actingAs($user);
		}

		$response = $this->$method($url, $payload);

		if (!$user) {
			$rolesWithPermission = Role::whereHas('permissions', function ($query) use ($permission) {
				preg_match('/^(store|update|display|remove)(Own|Related)?/', $permission, $matches);
				$basePermission = $matches[1] ?? $permission;
				$query->where('name', 'LIKE', "{$basePermission}%");
			})->exists();

			if (!$rolesWithPermission) {
                $response->assertStatus(200, "Visitor should have permission to perform {$action} because no role explicitly has this permission.");
			} else {
				$validStatuses = [401, 403];
				$this->assertTrue(
					in_array($response->getStatusCode(), $validStatuses),
					"Visitor should not have permission to perform {$action} because a role explicitly has this permission, but received status code {$response->getStatusCode()}."
				);
			}
		} else {
			$hasPermission = $user->can($permission);
			if (!$hasPermission) {
				$response->assertStatus(403, "{$roleName} should not have permission to perform {$action}.");
			} else {
				$response->assertStatus(200, "{$roleName} should have permission to perform {$action}.");
			}
		}

		return $response;
	}

	protected function getActionMethod(string $action): string
	{
		$methods = [
			'index' => 'getJson',
			'store' => 'postJson',
			'show' => 'getJson',
			'update' => 'putJson',
			'destroy' => 'deleteJson',
		];

		return $methods[$action] ?? 'get';
	}

	protected function getActionUrl(string $action): string
	{
		$baseUrl = '/api/' . Str::kebab(Str::plural(static::getModelName()));

		$routes = [
			'index' => $baseUrl,
			'store' => $baseUrl,
			'show' => "{$baseUrl}/{id}",
			'update' => "{$baseUrl}/{id}",
			'destroy' => "{$baseUrl}/{id}",
		];

		return $routes[$action] ?? '/';
	}

	protected function isPlural(string $string): bool
	{
		return $string === Str::plural(Str::singular($string));
	}
	
	protected static function isNonNullableColumn(string $table, string $column): bool
	{
		if (!Schema::hasColumn($table, $column)) {
			return false;
		}

		$schema = \DB::selectOne("
			SELECT IS_NULLABLE 
			FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_NAME = ? AND COLUMN_NAME = ?
		", [$table, $column]);

		return isset($schema) && $schema->IS_NULLABLE === 'NO';
	}
}
