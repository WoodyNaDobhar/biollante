<?php

namespace Biollante\Generator\Generators;

use Biollante\Helpers\BiollanteHelper;
use Illuminate\Support\Str;
use Biollante\Generator\Utils\TableFieldsGenerator;
use Biollante\Generator\Common\GeneratorConfig;

class RulesGenerator extends BaseGenerator
{

	private string $fileName;

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->rules;
		$this->fileName = $this->config->modelNames->name . '.ts';
	}

	/**
	 * Generate rules files for the model.
	 */
	public function generate()
	{
		
		$modelName = $this->config->modelNames->name;

		// Generate rules
		$rulesContent = $this->generateRulesContent($modelName);

		// Create or update the file
		$filePath = $this->path . $this->fileName;
		BiollanteHelper::instance()->g_filesystem()->createFile($filePath, $rulesContent);

		// Update the index.ts file
		if (!file_exists($this->path . 'index.ts')) {
			BiollanteHelper::instance()->g_filesystem()->createFile($this->path . 'index.ts', '');
		}
		$this->updateIndexFile();

		$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Rules created: ');
		$this->config->commandInfo($this->fileName);

		// Extension stub
		$extDir = $this->config->paths->rules . 'extensions/';
		$extensionFileName = $this->config->modelNames->name . '.ts';
		$extensionFile = $extDir . $this->config->modelNames->name . '.ts';

		// Make sure the directory exists
		if (!is_dir($extDir)) {
			BiollanteHelper::instance()->g_filesystem()->createDirectory($extDir);
		}

		if (!file_exists($extensionFile)) {
			$modelName = $this->config->modelNames->name;
			$fnName = 'extend' . $modelName . 'Rules';

			BiollanteHelper::instance()->g_filesystem()->createFile(
				$extensionFile,
				<<<TS
export const {$fnName} = <T>(rules: T): T => rules;
TS
			);
			$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Rules Extension created: ');
			$this->config->commandInfo($extensionFileName);
		}else{
			$this->config->commandComment(BiollanteHelper::instance()->format_nl().'Rules Extension already exists, skipping: ');
			$this->config->commandInfo($extensionFileName);
		}

		$this->config->commandComment(BiollanteHelper::instance()->format_nl() . 'Vuelidate Rules created: ');
		$this->config->commandInfo($this->config->modelNames->name . '.ts');
	}

	/**
	 * Generate the content for rules.
	 *
	 * @param string $rulesName
	 * @return string
	 */
	protected function generateRulesContent(string $rulesName): string
	{
		$fields = $this->getFieldsForRules($rulesName);
		$imports = $this->buildImportsFromFields($fields);
		$helpers = $this->buildHelpersFromFields($fields);

		// Render the Blade template
		return view('biollante::generator.rules.rules', [
			'model' => $rulesName,
			'imports' => $imports,
			'helpers' => $helpers,
			'fields' => $fields,
		])->render();
	}

	/**
	 * Build per-field structures for the rules template using $field->validations.
	 *
	 * @param string $rulesName
	 * @return array<int, array{name:string, validations:array<int|string,string>}>
	 */
	protected function getFieldsForRules(string $rulesName): array
	{
		// Helpers to infer types from db_type
		$isIntType = function (?string $dbType): bool {
			$t = strtolower((string)$dbType);
			return (bool)preg_match('/int|bigint|smallint|mediumint/', $t);
		};
		$isBoolType = function (?string $dbType): bool {
			$t = strtolower((string)$dbType);
			return (bool)preg_match('/tinyint\(1\)|boolean|bool|bit\(1\)/', $t);
		};
		$isStringType = function (?string $dbType): bool {
			$t = strtolower((string)$dbType);
			return str_starts_with($t, 'string') || str_starts_with($t, 'varchar');
		};
		$isTextType = function (?string $dbType): bool {
			$t = strtolower((string)$dbType);
			return (bool)preg_match('/^text$|^longtext$|^mediumtext$|^tinytext$/', $t);
		};
		$isDateType = function (?string $dbType): bool {
			return strtolower((string)$dbType) === 'date';
		};
		$isDateTimeType = function (?string $dbType): bool {
			$t = strtolower((string)$dbType);
			return in_array($t, ['datetime', 'timestamp'], true);
		};

		$fields = array_map(function ($field) use ($rulesName, $isIntType, $isBoolType, $isStringType, $isTextType, $isDateType, $isDateTimeType) {
			// 1) Skip logic if crud tracking or is_virtual ...
			if (
				in_array($field->name, [
					'created_at', 'created_by',
					'updated_at', 'updated_by',
					'deleted_at', 'deleted_by',
				], true)
				|| ($field->fieldDetails->is_virtual ?? false)
			) {
				return null;
			}

			$dbType = $field->fieldDetails->type ?? null;
			$rawValidations = (string)($field->validations ?? '');
			$tokens = array_values(array_filter(array_map('trim', explode('|', $rawValidations)), 'strlen'));

			$validations = [];
			$isNullable = false;
			$hasRequired = false;
			$hasInteger = false;
			$hasMinLength = false;
			$hasMinValue = false;
			$hasDateFormat = false;
			$hasBoolean = false;

			foreach ($tokens as $token) {
				[$name, $paramStr] = array_pad(explode(':', $token, 2), 2, null);
				$name = strtolower($name);

				switch ($name) {
					case 'nullable':
						$isNullable = true;
						break;

					case 'required':
						$validations[] = 'required';
						$hasRequired = true;
						break;

					case 'integer':
						$validations[] = 'integer';
						$hasInteger = true;
						break;

					case 'boolean':
						$validations['booleanish'] = 'helpers.withMessage("Must be a boolean value (0/1 or true/false).", isBooleanish)';
						$hasBoolean = true;
						break;

					case 'email':
						$validations[] = 'email';
						break;

					case 'string':
						// no-op for Vuelidate; we’ll add length rules below if present
						break;

					case 'min': {
						$param = is_null($paramStr) ? null : (int)trim($paramStr);
						if ($param !== null) {
							if ($isIntType($dbType) || $hasInteger) {
								$validations['minValue'] = "minValue({$param})";
								$hasMinValue = true;
							} else {
								$validations['minLength'] = "minLength({$param})";
								$hasMinLength = true;
							}
						}
						break;
					}

					case 'max': {
						$param = is_null($paramStr) ? null : (int)trim($paramStr);
						if ($param !== null) {
							if ($isIntType($dbType) || $hasInteger) {
								$validations['maxValue'] = "maxValue({$param})";
							} else {
								$validations['maxLength'] = "maxLength({$param})";
							}
						}
						break;
					}

					case 'date':
						// Prefer date vs datetime helper by db column type
						if ($isDateTimeType($dbType)) {
							$validations['dateTimeFormat'] = 'helpers.withMessage("Date/time must be a valid ISO date-time (e.g., 2024-08-20 14:30).", isISODateTime)';
						} else {
							$validations['dateFormat'] = 'helpers.withMessage("Date must be in the format YYYY-MM-DD.", isISODate)';
						}
						$hasDateFormat = true;
						break;

					case 'date_format': {
						$format = (string)($paramStr ?? '');
						$norm = strtolower(str_replace([' ', '\\'], '', $format));
						if ($norm === 'y-m-d') {
							$validations['dateFormat'] = 'helpers.withMessage("Date must be in the format YYYY-MM-DD.", isISODate)';
							$hasDateFormat = true;
						} elseif (in_array($norm, ['y-m-dh:i:s','y-m-dth:i','y-m-dth:i:s'], true)) {
							$validations['dateTimeFormat'] = 'helpers.withMessage("Date/time must be a valid ISO date-time (e.g., 2024-08-20 14:30).", isISODateTime)';
							$hasDateFormat = true;
						}
						break;
					}
					case 'unique': {
						$table = null;
						$column = $field->name;

						if (!is_null($paramStr)) {
							$parts = array_map('trim', explode(',', $paramStr));
							if (!empty($parts[0])) $table  = $parts[0];
							if (!empty($parts[1])) $column = $parts[1];
						}

						$model		= $rulesName; // e.g. "Gathering"
						$resource	= $table ?: static::inferResourceFromModel($model); // e.g. "gatherings"

						$validations['unique'] =
							'helpers.withMessage("That value is already in use.", helpers.withAsync(uniqueRemote("'
							. addslashes($column) . '","' . addslashes($resource) . '")))';
						$validations['urlSafe'] =
							'helpers.withMessage("Use only letters, numbers, \'-\', \'_\', \'.\', or \'~\' (no spaces or slashes).", isUrlSegmentSafe)';
						break;
					}
					case 'exists':
					case 'sometimes':
						break;

					default:
						// Unknown rule; ignore gracefully
						break;
				}
			}

			// Slug must not be purely numeric (e.g. "123" invalid; "12a3" ok)
			if ($field->name === 'slug') {
				$validations['notNumericOnly'] =
					'helpers.withMessage("Slug cannot be only numbers.", isNotNumericOnly)';
			}

			// Integer-like or *_id fields should not be < 1 unless explicitly allowed
			if (
				!$hasBoolean && // prevent minValue for booleanish fields
				(($isIntType($dbType) || str_ends_with(strtolower($field->name), '_id')) && !$hasMinValue)
			) {
				$validations['minValue'] = 'minValue(1)';
			}

			// Required strings should have at least 1 char if no minLength provided
			if (($isStringType($dbType) || $isTextType($dbType)) && $hasRequired && !$hasMinLength) {
				$validations['minLength'] = 'minLength(1)';
			}

			// Date-ish types without explicit date_format still get a soft ISO-8601 date check
			if ($isDateType($dbType) && !$hasDateFormat) {
				$validations['dateFormat'] = 'helpers.withMessage("Date must be in the format YYYY-MM-DD.", isISODate)';
			}
			if ($isDateTimeType($dbType) && !$hasDateFormat) {
				$validations['dateTimeFormat'] = 'helpers.withMessage("Date/time must be a valid ISO date-time (e.g., 2024-08-20T14:30).", isISODateTime)';
			}

			// Enforce hex color (# + 6 hex) on *_color fields
			if (str_ends_with(strtolower($field->name), '_color')) {
				$validations['hexColor'] = 'helpers.withMessage("Use a hex color like #A1B2C3.", isHexColorHash)';
			}

			return (object)[
				'name' => $field->name,
				'validations' => $validations,
			];
		}, $this->config->fields);

		// Remove nulls and reindex
		return array_values(array_filter($fields, fn($field) => $field !== null));
	}

	/**
	 * Derive @vuelidate/validators imports based on the rules used in $fields.
	 *
	 * @param array<int, array|object> $fields
	 * @return array<int, string>
	 */
	protected function buildImportsFromFields(array $fields): array
	{
		$want = [];

		$mark = function (string $name) use (&$want) {
			$want[$name] = true;
		};

		$scanChunk = function (string $chunk) use ($mark) {
			// direct validators
			if (preg_match('/\brequired\b/', $chunk)) $mark('required');
			if (preg_match('/\brequiredIf\b/', $chunk)) $mark('requiredIf');
			if (preg_match('/\binteger\b/', $chunk)) $mark('integer');
			if (preg_match('/\bemail\b/', $chunk)) $mark('email');

			// function-style validators
			if (preg_match('/\bminLength\s*\(/', $chunk)) $mark('minLength');
			if (preg_match('/\bmaxLength\s*\(/', $chunk)) $mark('maxLength');
			if (preg_match('/\bminValue\s*\(/', $chunk)) $mark('minValue');
			if (preg_match('/\bmaxValue\s*\(/', $chunk)) $mark('maxValue');
			if (preg_match('/\bsameAs\s*\(/', $chunk)) $mark('sameAs');

			// helpers.withMessage usage triggers helpers import
			if (strpos($chunk, 'helpers.') !== false) $mark('helpers');
		};

		foreach ($fields as $field) {
			$validations = is_array($field) ? ($field['validations'] ?? []) : ($field->validations ?? []);
			foreach ($validations as $key => $value) {
				// consider both keyed and unkeyed rules
				$scanChunk(is_int($key) ? (string)$value : (string)$value);
				if (!is_int($key)) $scanChunk((string)$key);
			}
		}

		// Stable import order
		$order = ['required','requiredIf','minLength','maxLength','integer','email','minValue','maxValue','sameAs','helpers'];
		$collected = array_keys($want);
		$imports = array_values(array_unique(array_merge(
			array_values(array_intersect($order, $collected)),
			array_diff($collected, $order)
		)));

		return $imports;
	}

	/**
	 * Collect helper function snippets required by rules (e.g., isISODate, isBooleanish).
	 *
	 * @param array<int, array|object> $fields
	 * @return array<int, string>  Raw helper code strings to inject in the template.
	 */
	protected function buildHelpersFromFields(array $fields): array
	{
		$knownHelpers = [
			'isISODate',
			'notFutureDate',
			'isSlug',
			'isBooleanish',
			'isUTM',
			'isPhone',
			'isPostalCode',
			'isISODateTime',
			'isUrlSegmentSafe',
			'uniqueRemote',
			'isHexColorHash',
			'isNotNumericOnly',
		];

		$needed = [];

		foreach ($fields as $field) {
			$validations = is_array($field) ? ($field['validations'] ?? []) : ($field->validations ?? []);
			foreach ($validations as $key => $value) {
				$chunk = (is_int($key) ? '' : (string)$key . ' ') . (string)$value;

				foreach ($knownHelpers as $name) {
					if (strpos($chunk, $name) !== false) {
						$needed[$name] = true;
					}
				}
			}
		}

		$names = array_keys($needed);
		sort($names);

		return $names;
	}

	/**
	 * Update the index.ts file to include the rules, if required.
	 *
	 */
	protected function updateIndexFile()
	{
		$files = collect(scandir($this->path))
			->filter(fn($file) => Str::endsWith($file, '.ts') && $file !== 'index.ts');
	
		$exports = $files->map(fn($file) => "export { " . pathinfo($file, PATHINFO_FILENAME) . "Rules } from './" . pathinfo($file, PATHINFO_FILENAME) . "';");
	
		$content = $exports->implode("\n");
		BiollanteHelper::instance()->g_filesystem()->createFile($this->path . '/index.ts', $content);
	}

	protected static function inferResourceFromModel(string $model): string
	{
		$m = strtolower(trim($model));
		if ($m === '') return '';
		if (str_ends_with($m, 'y')) return substr($m, 0, -1) . 'ies';
		if (str_ends_with($m, 's')) return $m . 'es';
		return $m . 's';
	}

	public function rollback()
	{
		if ($this->rollbackFile($this->path, $this->fileName)) {
			$this->config->commandComment('Rules file deleted: '.$this->fileName);
		}
	}
}
