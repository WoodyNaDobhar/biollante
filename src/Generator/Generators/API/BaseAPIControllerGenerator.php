<?php

namespace Biollante\Generator\Generators\API;

use Biollante\Helpers\BiollanteHelper;
use Illuminate\Support\Str;

/**
 * Generates BaseAPIController.php into the consuming app.
 *
 * Unlike per-model generators, this runs once and does not require
 * GeneratorConfig (no model context needed). It reads directly from
 * the biollante config for auth endpoints, registration fields,
 * search, invitations, and account deletion.
 *
 * Output: app/Http/Controllers/API/BaseAPIController.php (overwritable)
 */
class BaseAPIControllerGenerator
{
	private string $path;
	private string $fileName = 'BaseAPIController.php';

	public function __construct()
	{
		$this->path = config('biollante.path.api_controller', app_path('Http/Controllers/API/'));
	}

	public function generate(): void
	{
		$templateData = view('biollante::generator.api.controller.base_api_controller', $this->variables())->render();

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path . $this->fileName, $templateData);
	}

	protected function variables(): array
	{
		$registerFields = config('biollante.register_fields', []);
		$search = config('biollante.search', []);
		$invitations = config('biollante.invitations', []);
		$deleteAccount = config('biollante.delete_account', []);
		$appNamespace = rtrim(app()->getNamespace(), '\\');
		$modelNamespace = config('biollante.namespace.model', 'App\\Models');
		$repoNamespace = config('biollante.namespace.repository', 'App\\Repositories');
		$organizerRoles = config('biollante.organizer_roles', []);

		// Build the list of repositories needed in the constructor
		$repositories = $this->buildRepositoryList($registerFields, $search, $invitations);

		// Build the access description string for Swagger
		$accessDescription = $this->buildAccessDescription($organizerRoles);

		return [
			'appNamespace'        => $appNamespace,
			'modelNamespace'      => $modelNamespace,
			'repoNamespace'       => $repoNamespace,
			'registerFields'      => $registerFields,
			'searchConfig'        => $search,
			'searchEnabled'       => $search['enabled'] ?? false,
			'invitationsEnabled'  => $invitations['enabled'] ?? false,
			'invitationTokenField'=> $invitations['token_field'] ?? 'invite_token',
			'deleteEnabled'       => $deleteAccount['enabled'] ?? true,
			'deleteAnonymize'     => $deleteAccount['anonymize'] ?? [],
			'repositories'        => $repositories,
			'organizerRoles'      => $organizerRoles,
			'accessDescription'   => $accessDescription,
			'loginWith'           => $registerFields['login_with'] ?? [],
			'userFields'          => $registerFields['user'] ?? [],
			'userDefaults'        => $registerFields['user_defaults'] ?? [],
			'relatedModels'       => $registerFields['related'] ?? [],
			'agreements'          => $registerFields['agreements'] ?? null,
			'defaultRole'         => $registerFields['default_role'] ?? 'User',
		];
	}

	/**
	 * Build the list of repositories the controller needs in its constructor.
	 */
	protected function buildRepositoryList(array $registerFields, array $search, array $invitations): array
	{
		$repos = ['User']; // Always need UserRepository

		// Related models from registration
		foreach ($registerFields['related'] ?? [] as $model => $fields) {
			if (!in_array($model, $repos)) {
				$repos[] = $model;
			}
		}

		// Agreement model
		$agreements = $registerFields['agreements'] ?? null;
		if ($agreements) {
			$agreementModel = $agreements['agreement_model'] ?? 'Agreement';
			if (!in_array($agreementModel, $repos)) {
				$repos[] = $agreementModel;
			}
			// Supporting models
			foreach ($agreements['supporting_models'] ?? [] as $model => $config) {
				if (!in_array($model, $repos)) {
					$repos[] = $model;
				}
			}
			// Context model
			$contextModel = $agreements['context_model'] ?? null;
			if ($contextModel && !in_array($contextModel, $repos)) {
				$repos[] = $contextModel;
			}
		}

		// Search models — we don't need repositories for search (uses Scout directly)

		// Invitation models
		if ($invitations['enabled'] ?? false) {
			if (!in_array('Invitation', $repos)) {
				$repos[] = 'Invitation';
			}
		}

		sort($repos);
		return $repos;
	}

	/**
	 * Build the Swagger access description string.
	 */
	protected function buildAccessDescription(array $organizerRoles): string
	{
		$parts = ['Visitors: Full', 'Users: Full'];
		foreach ($organizerRoles as $role) {
			$parts[] = $role . ' Organizers: Full';
		}
		$parts[] = 'Admins: Full';
		return '<b>Access</b>:<br>' . implode('<br>', $parts);
	}
}
