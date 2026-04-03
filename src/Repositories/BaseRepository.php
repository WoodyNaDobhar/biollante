<?php

namespace Biollante\Repositories;

use Exception;
use Illuminate\Container\Container as Application;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

abstract class BaseRepository
{

	/**
	 * @var Model
	 */
	protected $model;
	
	/**
	 * @var Application
	 */
	protected $app;
	
	protected array $withConstraints = [];
	
	/**
	 * @param Application $app
	 *
	 * @throws Exception
	 */
	public function __construct(Application $app)
	{
		$this->app = $app;
		$this->makeModel();
	}
	
	/**
	 * Make Model instance.
	 *
	 * @throws Exception
	 *
	 * @return Model
	 */
	public function makeModel()
	{
		$model = $this->app->make($this->model());
		
		if (!$model instanceof Model) {
			throw new Exception("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
		}
		
		return $this->model = $model;
	}
	
	/**
	 * Configure the Model.
	 *
	 * @return string
	 */
	abstract public function model();
	
	/**
	 * Build a query for retrieving all records.
	 *
	 * @param  array  $search
	 * @param  array  $between
	 *
	 * @return Builder
	 */
	public function allQuery($search = [], $between = [])
	{
		$this->withConstraints = [];
		$query = $this->model->newQuery();

		if (count($search)) {
			foreach ($search as $key => $value) {
				// Dot-notation: relation[.relation...].column
				if (strpos($key, '.') === false && strpos($key, '__') !== false) {
					[$relationPathPart, $columnPart] = explode('__', $key, 2);
					$key = $relationPathPart . '.' . $columnPart;
				}
				if (strpos($key, '.') !== false) {
					$segments = explode('.', $key);
					$column = array_pop($segments);
					$relationPath = implode('.', $segments);

					// 1) Filter parent rows
					$query->whereHas($relationPath, function ($q) use ($column, $value) {
						if (is_array($value)) {
							$plain = array_filter($value, fn($v) => $v !== 'null' && !(is_string($v) && (str_starts_with($v, '%') || str_ends_with($v, '%'))));
							if (count($plain) === count($value)) {
								$q->whereIn($column, $value);
							} else {
								$q->where(function ($q2) use ($column, $value) {
									foreach ($value as $v) {
										if ($v === 'null') {
											$q2->orWhereNull($column);
										} elseif (is_string($v) && (str_starts_with($v, '%') || str_ends_with($v, '%'))) {
											$q2->orWhere($column, 'LIKE', $v);
										} else {
											$q2->orWhere($column, '=', $v);
										}
									}
								});
							}
						} else {
							if ($value === 'null') {
								$q->whereNull($column);
							} elseif (is_string($value) && (str_starts_with($value, '%') || str_ends_with($value, '%'))) {
								$q->where($column, 'LIKE', $value);
							} else {
								$q->where($column, '=', $value);
							}
						}
					});

					// 2) Remember a constraint for constrained eager loading
					$this->withConstraints[$relationPath][] = function ($q) use ($column, $value) {
						if (is_array($value)) {
							$plain = array_filter($value, fn($v) => $v !== 'null' && !(is_string($v) && (str_starts_with($v, '%') || str_ends_with($v, '%'))));
							if (count($plain) === count($value)) {
								$q->whereIn($column, $value);
							} else {
								$q->where(function ($q2) use ($column, $value) {
									foreach ($value as $v) {
										if ($v === 'null') {
											$q2->orWhereNull($column);
										} elseif (is_string($v) && (str_starts_with($v, '%') || str_ends_with($v, '%'))) {
											$q2->orWhere($column, 'LIKE', $v);
										} else {
											$q2->orWhere($column, '=', $v);
										}
									}
								});
							}
						} else {
							if ($value === 'null') {
								$q->whereNull($column);
							} elseif (is_string($value) && (str_starts_with($value, '%') || str_ends_with($value, '%'))) {
								$q->where($column, 'LIKE', $value);
							} else {
								$q->where($column, '=', $value);
							}
						}
					};

					continue;
				}

				// Base-table fields
				if (is_array($value) && in_array($key, $this->getFieldsSearchable())) {
					$query->where(function ($q) use ($key, $value) {
						foreach ($value as $v) {
							if ($v === 'null') {
								$q->orWhereNull($key);
							} elseif (str_starts_with($v, '%') || str_ends_with($v, '%')) {
								$q->orWhere($key, 'LIKE', $v);
							} else {
								$q->orWhere($key, $v);
							}
						}
					});
				} else {
					if (in_array($key, $this->getFieldsSearchable())) {
						if ($value === 'null') {
							$query->whereNull($key);
						} elseif (str_starts_with($value, '%') || str_ends_with($value, '%')) {
							$query->where($key, 'LIKE', $value);
						} else {
							$query->where($key, $value);
						}
					}
				}
			}
		}

		if (count($between)) {
			$startFields = $between['start'] ?? [];
			$endFields = $between['end'] ?? [];

			foreach ($startFields as $field => $startValue) {
				if (in_array($field, $this->getFieldsSearchable())) {
					$query->where($field, '>=', $startValue);
				}
			}

			foreach ($endFields as $field => $endValue) {
				if (in_array($field, $this->getFieldsSearchable())) {
					$query->where($field, '<=', $endValue);
				}
			}
		}

		return $query;
	}
	
	/**
	 * Get searchable fields array.
	 *
	 * @return array
	 */
	abstract public function getFieldsSearchable();
	
	/**
	 * Retrieve all records with given filter criteria.
	 *
	 * @param  array  $search
	 * @param  array  $between
	 * @param int|null $limit
	 * @param  array  $columns
	 * @param  array  $with
	 * @param  array  $sort
	 *
	 * @return LengthAwarePaginator|Builder[]|Collection
	 */
	public function all($search = [], $between = [], $limit = null, $columns = ['*'], $with = null, $sort = ['id' => 'asc'])
	{
		
		$query = $this->allQuery($search, $between);
		
		if ($with) {
			// Bucket nested withs by root relation: e.g. 'gatheringIdioms.passes.prices'
			$bucket = [];
			foreach ($with as $w) {
				$parts = explode('.', $w);
				$root = array_shift($parts);
				$rest = implode('.', $parts);
				if (!isset($bucket[$root])) $bucket[$root] = [];
				if ($rest !== '') $bucket[$root][] = $rest;
			}

			foreach ($bucket as $root => $nested) {
				if (isset($this->withConstraints[$root])) {
					$query->with([$root => function ($relQ) use ($nested, $root) {
						// apply remembered constraints for this relation
						foreach ($this->withConstraints[$root] as $fn) {
							$fn($relQ);
						}
						// pass through any nested eager loads
						if (!empty($nested)) {
							$relQ->with($nested);
						}
					}]);
				} else {
					// No constraints: still preserve nested eager loads cleanly
					if (!empty($nested)) {
						$query->with([$root => function ($relQ) use ($nested) {
							$relQ->with($nested);
						}]);
					} else {
						$query->with($root);
					}
				}
			}
		}
		
		if ($sort) {
			foreach($sort as $key => $s){
				$query = $query->orderBy($key, $s);
			}
		}
		
		if(!is_null($limit)){
			return $query->paginate($limit, $columns);
		}else{
			return $query->get($columns);
		}
	}
	
	/**
	 * Create model record.
	 *
	 * @param array $input
	 *
	 * @return Model
	 */
	public function create(array $input): Model
	{
		$model = $this->model->newInstance($input);

		$this->relatedSave($this->model, $input, $model);
		
		return $model;
	}
	
	/**
	 * Find model record for given id.
	 *
	 * @param  int  $id
	 * @param array $columns
	 * @param array $with
	 *
	 * @return Builder|Builder[]|Collection|Model|null
	 */
	public function find($id, $columns = ['*'], $with = null)
	{
		$query = $this->model->newQuery();
		
		if($with){
			if(is_array($with)){
				foreach($with as $w){
					$query->with($w);
				}
			}else{
				$query->with($with);
			}
		}
		
		return $query->find($id, $columns);
	}
	
	/**
	 * Find model record for given other unique column value.
	 *
	 * @param  string  $column
	 * @param  mixed  $value
	 * @param array $columns
	 * @param array $with
	 *
	 * @return Builder|Builder[]|Collection|Model|null
	 */
	public function firstWhere($column, $value, $columns = ['*'], $with = null)
	{
		$query = $this->model->newQuery();
		
		if($with){
			if(is_array($with)){
				foreach($with as $w){
					$query->with($w);
				}
			}else{
				$query->with($with);
			}
		}
		return $query->where($column, $value)->first($columns);
	}
	
	/**
	 * Update model record for given id.
	 *
	 * @param array $input
	 * @param  int  $id
	 *
	 * @return Builder|Builder[]|Collection|Model
	 */
	public function update($input, $id)
	{
		$query = $this->model->newQuery();
		
		$model = $query->findOrFail($id);
		
		$model->fill($input);
		
		$this->relatedSave($this->model, $input, $model);
		
		return $model;
	}
	
	/**
	 * @param int $id
	 *
	 * @throws Exception
	 *
	 * @return bool|mixed|null
	 */
	public function delete($id)
	{
		$query = $this->model->newQuery();
		
		$model = $query->findOrFail($id);
		
		return $this->relatedDelete($this->model, $model);
	}
	
	/**
	 * @param  int  $id
	 * @param  array  $with
	 *
	 * @return mixed
	 */
	public function findOrFail($id, $with = [])
	{
		if (! empty($with)) {
			$record = $this->model::with($with)->find($id);
		} else {
			$record = $this->model::find($id);
		}
		if (empty($record)) {
			throw new ModelNotFoundException(class_basename($this->model).' not found.');
		}
		
		return $record;
	}
	
	/**
	 * @param  int  $id
	 * @param  array  $columns
	 *
	 * @return mixed
	 */
	public function findWithoutFail($id, $columns = ['*'])
	{
		return $this->find($id, $columns);
	}

	/**
	 * Resolve the fully qualified model class name for a related model.
	 *
	 * @param string $modelName
	 * @return string
	 */
	protected function resolveModelClass(string $modelName): string
	{
		$namespace = config('biollante.namespace.model', 'App\\Models');
		return $namespace . '\\' . $modelName;
	}
	
	protected function relatedSave($model, $input, $parent)
	{
		//if any exist
		if (count($model::$relationships) > 0) {
			
			//for the pre-save items
			foreach ($model::$relationships as $name => $relationData) {
				
				//clean up non-standard relationships
				$relatedObject = $relationData['model'];
				
				//BelongsTo
				if (
					$relationData['type'] === 'BelongsTo' &&
					array_key_exists($name, $input)
				) {
					$thisInput = $input[$name];
					$thisModelBase = $this->app->make($this->resolveModelClass($relatedObject));
					if (array_key_exists($name, $input)) {
						//exists?
						if (
							array_key_exists('id', $thisInput) &&
							$thisInput['id'] != null &&
							$thisInput['id'] != 0
						) {
							//update it
							$thisModel = $thisModelBase->findOrFail($thisInput['id']);
							$thisModel->fill($thisInput);
							$thisModel->save();
							$this->relatedSave($thisModelBase, $thisInput, $thisModel);
						} else {
							//create and add it to the parent
							$thisModel = $thisModelBase->newInstance($thisInput);
							$this->relatedSave($thisModelBase, $thisInput, $thisModel);
							$thisModel->save();
							$attribute = $name . '_id';
							$parent->$attribute = $thisModel->id;
						}
					}
				}
			}
			$parent->save();
			
			//for the post-save items
			foreach ($model::$relationships as $name => $relationData) {
				
				//clean up non-standard relationships
				$relatedObject = $relationData['model'];

				// HasMany
				if (
					$relationData['type'] === 'HasMany' &&
					array_key_exists($name, $input) &&
					is_array($input[$name]) &&
					$parent->id != null &&
					$parent->id != 0
				) {
					$thisInput = $input[$name];
					$thisModelBase = $this->app->make($this->resolveModelClass($relatedObject));
					foreach ($thisInput as $item) {
						if (isset($item['id']) && $item['id'] != null) {
							// Update existing record
							$thisModel = $thisModelBase->findOrFail($item['id']);
							$thisModel->fill($item);
							$thisModel->save();
							$this->relatedSave($thisModelBase, $item, $thisModel);
						} elseif (array_filter($item)) {
							// Create new instance and set the parent's foreign key.
							$thisModel = $thisModelBase->newInstance($item);
							$foreignKey = $parent->getForeignKey();
							$thisModel->$foreignKey = $parent->id;
							$thisModel->save();
							$this->relatedSave($thisModelBase, $item, $thisModel);
						}
					}
				}

				// MorphMany
				if (
					$relationData['type'] === 'MorphMany' &&
					array_key_exists($name, $input) &&
					is_array($input[$name]) &&
					$parent->id != null
				) {
					$thisInput = $input[$name];
					$thisModelBase = $this->app->make($this->resolveModelClass($relatedObject));
					$relation = method_exists($parent, $name) ? $parent->$name() : null;

					foreach ($thisInput as $item) {
						if (isset($item['id']) && $item['id'] != null) {
							// Update existing record
							$thisModel = $thisModelBase->findOrFail($item['id']);
							$thisModel->fill($item);
							$thisModel->save();
							$this->relatedSave($thisModelBase, $item, $thisModel);
						} elseif (array_filter($item)) {
							// Create new instance and attach via morph relation
							$thisModel = $thisModelBase->newInstance($item);
							if ($relation) {
								$relation->save($thisModel);
							} else {
								$thisModel->save();
							}
							$this->relatedSave($thisModelBase, $item, $thisModel);
						}
					}
				}
				
				//BelongsToMany
				if (
					($relationData['type'] === 'BelongsToMany' || $relationData['type'] === 'MorphToMany' || $relationData['type'] === 'MorphedByMany') &&
					array_key_exists($name, $input) &&
					$parent->id != null
				) {
					$thisInput = $input[$name];
					if ($thisInput && is_array($thisInput)) {
						$pivotData = [];
						foreach ($thisInput as $value) {
							if ($value === '' || $value === null) continue;

							// If we were handed an object/array, assume it's a model and take its id.
							if (is_array($value)) {
								if (array_key_exists('id', $value) && $value['id'] != null && $value['id'] !== '') {
									$pivotData[] = $value['id'];
								}
								continue;
							}

							// Otherwise assume it's already an id.
							$pivotData[] = $value;
						}

						$parent->$name()->sync($pivotData);
					}
				}
						
				//HasOne
				if (
					 $relationData['type'] == 'HasOne' &&
					 array_key_exists($name, $input) &&
					 $input[$name] != null &&
					 $parent->id != null
				) {
					 $thisInput = $input[$name];
					 $thisModelBase = $this->app->make($this->resolveModelClass($relatedObject));
					 $thisModel = $thisModelBase->findOrFail($thisInput['id']);
					 $parentIDField = $parent->table . '_id';
					 $thisModel->$parentIDField = $parent->id;
					 $thisModel->save();
				}
			}
		}else{
			$parent->save();
		}
	}
	
	protected function relatedDelete($model, $parent)
	{
		$relationships = $model::$relationships ?? [];

		if(count($relationships) > 0){
			foreach($relationships as $name => $relationData){
				$type = $relationData['type'] ?? null;
				if($type === 'HasMany' || $type === 'MorphMany'){
					if(!method_exists($parent, $name)){
						continue;
					}
					$relation = $parent->$name();
					foreach($relation->get() as $relatedModel){
						$this->relatedDelete($relatedModel, $relatedModel);
					}
					continue;
				}else if(
					$type === 'BelongsToMany' || $type === 'MorphToMany' || $type === 'MorphedByMany'
				){
					$parent->$name()->sync([]);
					continue;
				}
			}
		}

		$parent->delete();
		return true;
	}
}
