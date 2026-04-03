<?php

namespace Biollante\Generator\Generators\API;

use Biollante\Helpers\BiollanteHelper;

/**
 * Generates AppBaseController.php into the consuming app.
 *
 * Unlike per-model generators, this runs once and does not require
 * GeneratorConfig (no model context needed). It reads directly from
 * the biollante config for API metadata and registration fields.
 *
 * Output: app/Http/Controllers/AppBaseController.php (overwritable)
 */
class AppBaseControllerGenerator
{
	private string $path;
	private string $fileName = 'AppBaseController.php';

	public function __construct()
	{
		$this->path = config('biollante.path.controller', app_path('Http/Controllers/'));
	}

	public function generate(): void
	{
		$templateData = view('biollante::generator.api.controller.app_base_controller', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path . $this->fileName, $templateData);
	}

	protected function variables(): array
	{
		$api = config('biollante.api', []);
		$registerFields = config('biollante.register_fields', []);
		$search = config('biollante.search', []);
		$invitations = config('biollante.invitations', []);
		$deleteAccount = config('biollante.delete_account', []);
		$appNamespace = rtrim(app()->getNamespace(), '\\');

		// Build register Swagger properties from config
		$registerProperties = $this->buildRegisterSwaggerProperties($registerFields);

		// Build search Swagger response properties from config
		$searchProperties = $this->buildSearchSwaggerProperties($search);

		return [
			'appNamespace'       => $appNamespace,
			'apiTitle'           => $api['title'] ?? 'API',
			'apiVersion'         => $api['version'] ?? '1.0.0',
			'apiDescription'     => $api['description'] ?? '',
			'apiTermsUrl'        => $api['terms_url'] ?? null,
			'apiContactName'     => $api['contact']['name'] ?? null,
			'apiContactEmail'    => $api['contact']['email'] ?? null,
			'apiServerUrl'       => $api['server_url'] ?? '/api',
			'registerProperties' => $registerProperties,
			'registerRequired'   => $this->buildRegisterRequired($registerFields),
			'searchEnabled'      => $search['enabled'] ?? false,
			'searchProperties'   => $searchProperties,
			'invitationsEnabled' => $invitations['enabled'] ?? false,
			'deleteEnabled'      => $deleteAccount['enabled'] ?? true,
		];
	}

	/**
	 * Build Swagger @OA\Property entries for the register RequestBody.
	 * Uses DB column metadata when available, falls back to config field names.
	 */
	protected function buildRegisterSwaggerProperties(array $registerFields): array
	{
		$properties = [];

		// Universal auth fields (always present)
		$properties[] = ['name' => 'email',            'type' => 'string', 'format' => 'email',    'required' => true,  'description' => 'User email.',           'example' => 'nobody@nowhere.net', 'maxLength' => 191];
		$properties[] = ['name' => 'password',         'type' => 'string', 'format' => 'password',  'required' => true,  'description' => 'User password.',        'example' => 'MyP@ssw0rd!'];
		$properties[] = ['name' => 'password_confirm', 'type' => 'string', 'format' => 'password',  'required' => true,  'description' => 'Password confirmation.','example' => 'MyP@ssw0rd!'];
		$properties[] = ['name' => 'is_agreed',        'type' => 'integer','format' => 'int32',     'required' => true,  'description' => 'Agreed to Terms & Privacy Policy (0 or 1).', 'example' => 1];
		$properties[] = ['name' => 'device_name',      'type' => 'string', 'format' => null,        'required' => true,  'description' => 'User device information.', 'example' => "Device"];

		// User fields from config
		foreach ($registerFields['user'] ?? [] as $field => $opts) {
			$meta = $this->getFieldMeta('users', $field);
			$properties[] = [
				'name'        => $field,
				'type'        => $meta['type'],
				'format'      => $meta['format'],
				'required'    => $opts['required'] ?? false,
				'description' => $meta['description'],
				'example'     => $meta['example'],
				'maxLength'   => $meta['maxLength'],
			];
		}

		// Related model fields
		foreach ($registerFields['related'] ?? [] as $model => $fields) {
			$table = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($model));
			foreach ($fields as $field => $opts) {
				$meta = $this->getFieldMeta($table, $field);
				$properties[] = [
					'name'        => $field,
					'type'        => $meta['type'],
					'format'      => $meta['format'],
					'required'    => $opts['required'] ?? false,
					'description' => $meta['description'],
					'example'     => $meta['example'],
					'maxLength'   => $meta['maxLength'],
				];
			}
		}

		// Agreement waiver signed fields
		$agreements = $registerFields['agreements'] ?? null;
		if ($agreements) {
			foreach ($agreements['waiver_types'] ?? [] as $type) {
				$properties[] = [
					'name'        => strtolower($type) . '_signed',
					'type'        => 'boolean',
					'format'      => null,
					'required'    => false,
					'description' => 'Agreed to ' . $type . ' Waiver.',
					'example'     => true,
				];
			}

			// Supporting model fields
			foreach ($agreements['supporting_models'] ?? [] as $model => $modelConfig) {
				$table = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($model));
				foreach ($modelConfig['fields'] ?? [] as $field) {
					$meta = $this->getFieldMeta($table, $field);
					$properties[] = [
						'name'        => $field,
						'type'        => $meta['type'],
						'format'      => $meta['format'],
						'required'    => false,
						'description' => $meta['description'],
						'example'     => $meta['example'],
						'maxLength'   => $meta['maxLength'],
					];
				}
			}

			// Direct data fields
			foreach ($agreements['data_fields'] ?? [] as $field) {
				// Try to find from agreement model table
				$agreementTable = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($agreements['agreement_model'] ?? 'Agreement'));
				$meta = $this->getFieldMeta($agreementTable, $field);
				$properties[] = [
					'name'        => $field,
					'type'        => $meta['type'],
					'format'      => $meta['format'],
					'required'    => false,
					'description' => $meta['description'],
					'example'     => $meta['example'],
					'maxLength'   => $meta['maxLength'],
				];
			}
		}

		// Invite token (if invitations enabled)
		if (config('biollante.invitations.enabled', false)) {
			$tokenField = config('biollante.invitations.token_field', 'invite_token');
			$properties[] = [
				'name'        => $tokenField,
				'type'        => 'string',
				'format'      => null,
				'required'    => false,
				'description' => 'Optional invitation token.',
				'example'     => 'ab12cd34ef56',
			];
		}

		return $properties;
	}

	/**
	 * Build the 'required' array for the register RequestBody.
	 */
	protected function buildRegisterRequired(array $registerFields): array
	{
		$required = ['email', 'password', 'password_confirm', 'is_agreed', 'device_name'];

		foreach ($registerFields['user'] ?? [] as $field => $opts) {
			if ($opts['required'] ?? false) {
				$required[] = $field;
			}
		}

		foreach ($registerFields['related'] ?? [] as $model => $fields) {
			foreach ($fields as $field => $opts) {
				if ($opts['required'] ?? false) {
					$required[] = $field;
				}
			}
		}

		return $required;
	}

	/**
	 * Build Swagger response properties for the search endpoint.
	 */
	protected function buildSearchSwaggerProperties(array $search): array
	{
		$properties = [];
		foreach ($search['models'] ?? [] as $model => $opts) {
			$plural = \Illuminate\Support\Str::plural($model);
			$properties[] = [
				'name'   => $plural,
				'schema' => $model . 'Simple',
			];
		}
		return $properties;
	}

	/**
	 * Get column metadata from the database for Swagger property generation.
	 * Falls back to sensible defaults if the column can't be found.
	 */
	protected function getFieldMeta(string $table, string $field): array
	{
		$defaults = [
			'type'        => 'string',
			'format'      => null,
			'description' => ucfirst(str_replace('_', ' ', $field)) . '.',
			'example'     => null,
			'maxLength'   => null,
		];

		try {
			$column = \DB::select("
				SELECT COLUMN_TYPE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_COMMENT, IS_NULLABLE
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
			", [\DB::getDatabaseName(), $table, $field]);

			if (empty($column)) {
				return $defaults;
			}

			$col = $column[0];

			$type = match ($col->DATA_TYPE) {
				'int', 'bigint', 'smallint', 'mediumint', 'tinyint' => 'integer',
				'decimal', 'float', 'double' => 'number',
				'tinyint' => 'boolean',
				'date' => 'string',
				'datetime', 'timestamp' => 'string',
				'text', 'mediumtext', 'longtext' => 'string',
				default => 'string',
			};

			$format = match ($col->DATA_TYPE) {
				'bigint', 'int' => 'int64',
				'smallint', 'mediumint', 'tinyint' => 'int32',
				'date' => 'date',
				'datetime', 'timestamp' => 'date-time',
				'decimal', 'float', 'double' => 'float',
				default => null,
			};

			return [
				'type'        => $type,
				'format'      => $format,
				'description' => !empty($col->COLUMN_COMMENT) ? $col->COLUMN_COMMENT : $defaults['description'],
				'example'     => $defaults['example'],
				'maxLength'   => $col->CHARACTER_MAXIMUM_LENGTH,
			];
		} catch (\Throwable $e) {
			return $defaults;
		}
	}
}
