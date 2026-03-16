<?php

namespace Biollante\Generator\Common;

use Illuminate\Support\Str;

class GeneratorFieldRelation
{
	public $type;
	public array $inputs;
	public string $relationName;

	public static function parseRelation($relationInput): self
	{
		$inputs = explode(',', $relationInput);

		$relation = new self();
		$relation->type = array_shift($inputs);
		
		$modelWithRelation = array_key_exists(2, $inputs) ? (strpos($inputs[2], ':') !== false ? explode(':', array_pop($inputs)) : $inputs[2]) : null;

		if (is_array($modelWithRelation) && count($modelWithRelation)) {
			$inputs[] = $modelWithRelation;
		}
		
		$relation->inputs = $inputs;

		return $relation;
	}

	public function getRelationFunctionText(string $relationText = null, $config): string
	{
		$singularRelation = (!empty($this->relationName)) ? $this->relationName : Str::camel($relationText);
		$pluralRelation = (!empty($this->relationName)) ? $this->relationName : Str::camel(Str::plural($relationText));

		switch ($this->type) {
			case '1t1':
				$functionName = $singularRelation;
				$relation = 'hasOne';
				$relationClass = 'HasOne';
				break;
			case '1tm':
				$functionName = $pluralRelation;
				$relation = 'hasMany';
				$relationClass = 'HasMany';
				break;
			case 'mt1':
				if (!empty($this->relationName)) {
					$singularRelation = $this->relationName;
				} elseif (isset($this->inputs[1])) {
					$singularRelation = Str::camel(str_replace('_id', '', strtolower($this->inputs[1])));
				}
				$functionName = $singularRelation;
				$relation = 'belongsTo';
				$relationClass = 'BelongsTo';
				break;
			case 'mtm':
				$functionName = $pluralRelation;
				$relation = 'belongsToMany';
				$relationClass = 'BelongsToMany';
				break;
			case 'hmt':
				$functionName = $pluralRelation;
				$relation = 'hasManyThrough';
				$relationClass = 'HasManyThrough';
				break;
			case 'morphTo':
				$functionName = $singularRelation;
				$relation = 'morphTo';
				$relationClass = 'MorphTo';
				break;
			case 'morphOne':
				$functionName = $singularRelation;
				$relation = 'morphOne';
				$relationClass = 'MorphOne';
				break;
			case 'morphMany':
				$functionName = $singularRelation;
				$relation = 'morphMany';
				$relationClass = 'MorphMany';
				break;
			case 'morphToMany':
				$functionName = $singularRelation;
				$relation = 'morphToMany';
				$relationClass = 'MorphToMany';
				break;
			default:
				$functionName = '';
				$relation = '';
				$relationClass = '';
				break;
		}

		$description = ucfirst($functionName) . ' for the ' . ucfirst(STR::singular($config->tableName));

		if (!empty($functionName) and !empty($relation)) {
			return $this->generateRelation($functionName, $relation, $relationClass, $description, $config);
		}

		return '';
	}

	protected function generateRelation($functionName, $relation, $relationClass, $description, $config)
	{
		$inputs = $this->inputs;
		$relatedModelName = array_pop($inputs);
		$isMorphTo = ($relation === 'morphTo');
		$inputFields = '';
	
		if ($relation === 'belongsToMany') {
			// Skip adding input fields for belongsToMany relationships
			$inputFields = '';
		} elseif (str_contains($relation, 'morph')) {
			$field = isset($inputs[1]) ? str_replace('_type', '', $inputs[1]) : '';
			$inputFields = $field ? ", '$field'" : '';
		} elseif (count($inputs) > 0) {
			if (in_array($relation, ['hasMany', 'belongsTo', 'hasOne'])) {
				$inputFields = $inputs[1] === lcfirst($config->modelNames->name) . '_id' ? null : ", '" . $inputs[1] . "'";
			} else {
				$inputFields = implode("', '", $inputs);
				$inputFields = ", '" . $inputFields . "'";
			}
		}
	
		return view('biollante::generator.model.relationship', [
			'description'   => $description,
			'config'        => $config,
			'relationClass' => $relationClass,
			'functionName'  => $functionName,
			'relation'      => $relation,
			'relatedModel'  => $relatedModelName,
			'fields'        => $inputFields,
			'isMorphTo'     => $isMorphTo,
		])->render();
	}
}
