@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->apiResource }};

use Biollante\Helpers\BiollanteHelper;
use Biollante\Policies\{{ $config->modelNames->name }}Policy;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class {{ $config->modelNames->name }}Resource extends JsonResource
{
	/**
	 * The relationships associated with the resource.
	 *
	 * @var array
	 */
	protected array $relationships = [];

	/**
	 * Constructor.
	 *
	 * @param  mixed  $resource
	 */
	public function __construct($resource)
	{
		parent::__construct($resource);
		$this->relationships = \Biollante\Models\{{ $config->modelNames->name }}::$relationships ?? [];
	}

	/**
	 * Transform the resource into an array.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return array
	 */
	public function toArray($request)
	{
		$data = [
			{!! $fields !!}
		];

		/**
		 * policies
		 */
		if(auth('sanctum')->check()){
			${{ $config->modelNames->camel }}Policy = new {{ $config->modelNames->name }}Policy();
			$data['can_list'] = ${{ $config->modelNames->camel }}Policy->viewAny(auth('sanctum')->user(), $this->resource) ? 1 : 0;
			$data['can_view'] = ${{ $config->modelNames->camel }}Policy->view(auth('sanctum')->user(), $this->resource) ? 1 : 0;
			$data['can_create'] = ${{ $config->modelNames->camel }}Policy->create(auth('sanctum')->user(), $this->resource) ? 1 : 0;
			$data['can_update'] = ${{ $config->modelNames->camel }}Policy->update(auth('sanctum')->user(), $this->resource) ? 1 : 0;
			$data['can_delete'] = ${{ $config->modelNames->camel }}Policy->delete(auth('sanctum')->user(), $this->resource) ? 1 : 0;
			$data['can_restore'] = ${{ $config->modelNames->camel }}Policy->restore(auth('sanctum')->user(), $this->resource) ? 1 : 0;
			$data['can_nuke'] = ${{ $config->modelNames->camel }}Policy->forceDelete(auth('sanctum')->user(), $this->resource) ? 1 : 0;
		}

		/**
		 * related
		 */
		if($request->has('with')){
			foreach ($this->relationships as $name => $relationData) {
				$resourceClass = null;
				$attachName = $name;
				$plural = $name === Str::plural(Str::singular($name));
				$resolvedName = null;
				if (array_key_exists($name . '_type', $data)) {
					$resolvedName = $plural ? Str::plural(strtolower($data[$name . '_type'])) : strtolower($data[$name . '_type']);
				}

				foreach ($request->with as $withItem) {
					$withItems = explode('.', $withItem);

					while(count($withItems) > 1){
						$head = $withItems[0] ?? null;
						if(!$head) break;
						if(array_key_exists($head, $this->relationships)){
							break;
						}
						$headClass = 'Biollante\\Models\\' . Str::studly(Str::singular($head));
						if(class_exists($headClass)){
							break;
						}
						array_shift($withItems);
					}
					
					// we’re at the root if the URL’s first model segment matches our model
					$path   = trim($request->getPathInfo() ?: $request->path(), '/'); // e.g. "api/worlds/1"
					$parts  = $path === '' ? [] : explode('/', $path);
					$isRoot = isset($parts[0], $parts[1])
						&& $parts[0] === 'api'
						&& $parts[1] === Str::plural(Str::snake(class_basename($this->resource)));

					// where, if anywhere, are we in the chain?
					$indexInChain = $isRoot ? -1 : null;
					foreach($withItems as $index => $withItem){
						if($withItem === $this->table || $withItem === Str::singular($this->table)){
							$indexInChain = $index;
							break;
						}
					}

					if($indexInChain === null && isset($withItems[0]) && array_key_exists($withItems[0], $this->relationships)){
						$indexInChain = -1;
					}

					// if there's no $indexInChain yet, we need to make two arrays: one of those items in the chain that are a model name, and one of those that are alieases.
					if($indexInChain === null){
						$modelNames = [];
						$aliases = [];
						$currentModelShort = class_basename($this->resource);

						//	split withItems into model names vs aliases
						foreach($withItems as $seg){
							$segSingular = Str::singular($seg);
							$segClass = 'Biollante\\Models\\' . Str::studly($segSingular);
							if (class_exists($segClass)) {
								$modelNames[] = $seg;
							} else {
								$aliases[] = $seg;
							}
						}
						//	iterate model-name segments; for each, look in that model's $relationships for any alias in this chain
						foreach($modelNames as $modelSeg){
							$modelFqcn = 'Biollante\\Models\\' . Str::studly(Str::singular($modelSeg));

							//	skip if the model doesn't declare relationships
							if (!property_exists($modelFqcn, 'relationships')) {
								continue;
							}

							$rels = $modelFqcn::$relationships ?? [];
							if (!is_array($rels) || empty($rels)) {
								continue;
							}

							//	for each alias in the chain, see if this model declares that alias and that alias points to *this* resource's model
							foreach($aliases as $alias){
								if (!array_key_exists($alias, $rels)) {
									continue;
								}

								$relCfg = $rels[$alias] ?? null;
								if (!is_array($relCfg)) {
									continue;
								}

								$relModel = $relCfg['model'] ?? null;

								$matchesCurrent = false;
								if (is_string($relModel)) {
									$matchesCurrent = ($relModel === $currentModelShort);
								} elseif (is_array($relModel)) {
									$matchesCurrent = in_array($currentModelShort, $relModel, true);
								}

								if ($matchesCurrent) {
									//	we've found where we are (e.g., 'image' in Persona points to File)
									$indexInChain = array_search($alias, $withItems, true);
									break 2; //	leave both foreach loops
								}
							}
						}
					}

					// now that we know where we are, let's make it $withItem
					if($indexInChain !== null){
						$withItem = array_key_exists($indexInChain + 1, $withItems) ? $withItems[$indexInChain + 1] : '';
					}
					
					if (
						$indexInChain !== null &&
						(
							$name === $withItem ||
							(isset($resolvedName) && $resolvedName === $withItem)
						)
					){
						$relation = $this->whenLoaded($attachName);

						// Handle MorphTo
						if ($relationData['type'] === 'MorphTo' && $relation) {
							$modelClass = get_class($relation);
							$resourceClass = 'Biollante\\Http\\Resources\\' . class_basename($modelClass) . 'Resource';
						}

						// Handle non-polymorphic
						elseif (!is_array($relationData['model'])) {
							$resourceClass = 'Biollante\\Http\\Resources\\' . $relationData['model'] . 'Resource';
						}

						if (class_exists($resourceClass)) {
							if ($plural) {
								$modelClass = $relation instanceof \Illuminate\Support\Collection && $relation->isNotEmpty()
									? get_class($relation->first())
									: null;

								if ($modelClass) {
									/** @var \Illuminate\Database\Eloquent\Model $modelInstance */
									$modelInstance = new $modelClass;
						
									// Check if the model has 'order' in its $casts or $fillable or $attributes
									$hasOrder = property_exists($modelInstance, 'casts') && array_key_exists('order', $modelInstance->getCasts());
									$hasOrder = $hasOrder || array_key_exists('order', $modelInstance->getAttributes());
						
									if ($hasOrder || \Schema::hasColumn($modelInstance->getTable(), 'order')) {
										$relation = $relation->sortBy('order')->values(); // reindex after sort
									}
								}
								$data[$attachName] = $resourceClass::collection($relation);
							} else {
								$data[$attachName] = $resourceClass::make($relation);
							}
						}
					}
				}
			}
		}
		
		return $data;
	}
}
