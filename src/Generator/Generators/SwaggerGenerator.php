<?php

namespace Biollante\Generator\Generators;

use Biollante\Generator\Common\GeneratorField;
use Illuminate\Support\Str;

class SwaggerGenerator
{
	public static function generateTypes(array $inputFields): array
	{
		$fieldTypes = [];
		
		/** @var GeneratorField $field */
		foreach ($inputFields as $field) {
			
			if (!empty($field->fieldDetails->is_virtual) && $field->fieldDetails->is_virtual == 1) {
				continue;
			}
			
			$fieldData = self::getFieldType($field->fieldDetails);

			if (empty($fieldData['fieldType'])) {
				print_r('Fix this field type.');
				dd($field);
				continue;
			}

			$fieldTypes[] = [
				'fieldName'   => $field->name,
				'description' => (!empty($field->description)) ? $field->description : '',
				'type'		=> $fieldData['fieldType'],
				'format'	  => $fieldData['fieldFormat'],
				'enum'		=> array_key_exists('enum', $fieldData) ? $fieldData['enum'] : '',
				'length'	  => array_key_exists('length', $fieldData) ? $fieldData['length'] : '',
				'nullable'	=> !$field->isNotNull ? 'true' : 'false',
				'readOnly'	=> !$field->isFillable ? 'true' : 'false',
				'default'	 => property_exists($field->fieldDetails, 'default') ? $field->fieldDetails->default : '',
			];
		}

		return $fieldTypes;
	}
	
	public static function generateRelatedTypes(array $relatedFields): array
	{
		$relatedFieldTypes = [];
	
		foreach ($relatedFields as $relatedField) {
			
			if (in_array($relatedField['property'], ['createdBy', 'updatedBy', 'deletedBy'])) {
				continue;
			}
			
			$type = in_array($relatedField['type'], ['HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'HasManyThrough']) ? 'array' : 'object';
			
			switch ($relatedField['type']) {
				case 'BelongsTo':
					$ref = $relatedField['model'];
					$description = "Attachable " . $relatedField['model'] . " object related to the model.";
					break;
				case 'HasMany':
				case 'HasManyThrough':
				case 'BelongsToMany':
				case 'MorphMany':
				case 'MorphToMany':
					$ref = $relatedField['model'];
					$description = "Attachable & filterable array of " . STR::plural($relatedField['model']) . " for this model.";
					break;
				case 'MorphTo':
					$ref = $relatedField['model'] ?? [];
					$refList = implode(', ', array_slice($ref, 0, -1)) . (count($ref) > 1 ? ', or ' : '') . end($ref);
					$description = "Attachable object for the " . $relatedField['property'] . " of this model: " . $refList . " .";
					break;
				case 'MorphOne':
					$ref = $relatedField['model'];
					$description = "Attachable " . $relatedField['model']. " object related to the model.";
					break;
				default:
					$ref = null;
					$description = '';
					break;
			}
	
			$relatedFieldTypes[] = [
				'fieldName'   => $relatedField['property'],
				'description' => $description,
				'type'        => $type,
				'ref'         => $ref,
			];
		}
		
		return $relatedFieldTypes;
	}

	public static function getFieldType($fieldDetails): array
	{
		$fieldType = null;
		$fieldFormat = null;
		$enum = null;
		$length = null;
	
		switch ($fieldDetails->type) {
			case 'increments':
			case 'integer':
			case 'int':
			case 'unsignedinteger':
			case 'smallinteger':
			case 'smallint':
			case 'mediumint':
			case 'long':
			case 'bigint':
			case 'unsignedbiginteger':
				$fieldType = 'integer';
				$fieldFormat = 'int32';
				break;
			case 'double':
			case 'float':
			case 'real':
			case 'decimal':
				$fieldType = 'number';
				$fieldFormat = 'double';
				$length = $fieldDetails->precision . ',' . $fieldDetails->scale;
				break;
			case 'tinyint':
			case 'boolean':
				$fieldType = 'integer';
				$fieldFormat = 'enum';
				$enum = '{0, 1}';
				break;
			case 'string':
			case 'char':
			case 'varchar':
				$fieldType = 'string';
				$length = $fieldDetails->length;
				break;
			case 'text':
				$fieldType = 'string';
				$length = '16777215';
				break;
			case 'mediumtext':
			case 'longtext':
				$fieldType = 'string';
				break;
			case 'enum':
				$fieldType = 'string';
				$fieldFormat = 'enum';
				$enum = '{' . implode(',', array_map(fn($value) => "\"$value\"", $fieldDetails->enumValues)) . '}';
				break;
			case 'byte':
				$fieldType = 'string';
				$fieldFormat = 'byte';
				break;
			case 'binary':
				$fieldType = 'string';
				$fieldFormat = 'binary';
				break;
			case 'password':
				$fieldType = 'string';
				$fieldFormat = 'password';
				$length = $fieldDetails->length;
				break;
			case 'date':
				$fieldType = 'string';
				$fieldFormat = 'date';
				break;
			case 'datetime':
			case 'time':
			case 'timestamp':
				$fieldType = 'string';
				$fieldFormat = 'date-time';
				break;
			case 'json':
				$fieldType = 'string';
				$fieldFormat = 'json';
				break;
		}

		return ['fieldType' => $fieldType, 'fieldFormat' => $fieldFormat, 'enum' => $enum, 'length' => $length];
	}
}
