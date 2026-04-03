<?php

namespace Biollante\Generator\Generators\API;

use Biollante\Helpers\BiollanteHelper;

/**
 * Generates base API routes (auth, registration, search, invitations)
 * into routes/api.php.
 *
 * These routes are injected into a dedicated section marked by
 * //generated base routes begins / //generated base routes ends
 * and are regenerated on each scaffold run.
 *
 * Unlike per-model routes, these run once and are not model-specific.
 */
class BaseAPIRoutesGenerator
{
	private string $path;

	public function __construct()
	{
		$this->path = config('biollante.path.api_routes', base_path('routes/api.php'));
	}

	public function generate(): void
	{
		$routeContents = BiollanteHelper::instance()->g_filesystem()->getFile($this->path);

		$invitations = config('biollante.invitations', []);
		$search = config('biollante.search', []);
		$deleteAccount = config('biollante.delete_account', []);
		$controllerNamespace = config('biollante.namespace.api_controller', 'App\\Http\\Controllers\\API');
		$controller = $controllerNamespace . '\\BaseAPIController';

		// Build the route block
		$routes = $this->buildRouteBlock($controller, $invitations, $search, $deleteAccount);

		// Ensure the use statement exists
		$useStatement = "use {$controller};";
		if (!str_contains($routeContents, $useStatement)) {
			// Insert after the last 'use' statement
			$lastUsePos = strrpos($routeContents, "use ");
			if ($lastUsePos !== false) {
				$endOfLine = strpos($routeContents, "\n", $lastUsePos);
				if ($endOfLine !== false) {
					$routeContents = substr($routeContents, 0, $endOfLine + 1)
						. $useStatement . "\n"
						. substr($routeContents, $endOfLine + 1);
				}
			} else {
				// No use statements at all — insert after <?php
				$routeContents = str_replace("<?php\n", "<?php\n\n{$useStatement}\n", $routeContents);
			}
		}

		// Insert or replace the base routes section
		$sectionStart = '//generated base routes begins';
		$sectionEnd = '//generated base routes ends';

		if (str_contains($routeContents, $sectionStart)) {
			// Replace existing section
			$startPos = strpos($routeContents, $sectionStart);
			$endPos = strpos($routeContents, $sectionEnd) + strlen($sectionEnd);
			$routeContents = substr($routeContents, 0, $startPos)
				. $sectionStart . "\n" . $routes . "\n" . $sectionEnd
				. substr($routeContents, $endPos);
		} else {
			// Find where to insert — before the generated restricted routes section if it exists,
			// otherwise before the generated public routes section, otherwise at the end
			$insertBefore = null;
			foreach (['//generated restricted routes begins', '//generated public routes begins'] as $marker) {
				if (str_contains($routeContents, $marker)) {
					$insertBefore = strpos($routeContents, $marker);
					break;
				}
			}

			$section = "\n{$sectionStart}\n{$routes}\n{$sectionEnd}\n\n";

			if ($insertBefore !== null) {
				$routeContents = substr($routeContents, 0, $insertBefore)
					. $section
					. substr($routeContents, $insertBefore);
			} else {
				$routeContents .= $section;
			}
		}

		BiollanteHelper::instance()->g_filesystem()->createFile($this->path, $routeContents);
	}

	protected function buildRouteBlock(string $controller, array $invitations, array $search, array $deleteAccount): string
	{
		$lines = [];

		// Public routes (no auth required)
		$lines[] = "// Base auth routes (public)";
		$lines[] = "Route::post('register', [{$controller}::class, 'register']);";
		$lines[] = "Route::post('login', [{$controller}::class, 'login']);";
		$lines[] = "Route::post('forgot', [{$controller}::class, 'forgot'])->middleware('throttle:1,5');";
		$lines[] = "Route::post('reset', [{$controller}::class, 'reset']);";
		$lines[] = "Route::post('check', [{$controller}::class, 'check'])->middleware('throttle:1,1');";

		if ($invitations['enabled'] ?? false) {
			$lines[] = "Route::post('decodeInvite', [{$controller}::class, 'decodeInvite']);";
		}

		if ($search['enabled'] ?? false) {
			$lines[] = "Route::post('search', [{$controller}::class, 'search']);";
		}

		$lines[] = "";

		// Authenticated routes
		$lines[] = "// Base auth routes (authenticated)";
		$lines[] = "Route::middleware('auth:sanctum')->group(function () {";
		$lines[] = "\tRoute::get('logout', [{$controller}::class, 'logout']);";
		$lines[] = "\tRoute::post('checkpass', [{$controller}::class, 'checkpass']);";
		$lines[] = "\tRoute::post('resend', [{$controller}::class, 'resend'])->middleware('throttle:1,5');";
		$lines[] = "\tRoute::post('email/verify', [{$controller}::class, 'verify'])->middleware('throttle:1,5');";

		if ($invitations['enabled'] ?? false) {
			$lines[] = "\tRoute::post('generateInvite', [{$controller}::class, 'generateInvite']);";
			$lines[] = "\tRoute::post('sendInvite', [{$controller}::class, 'sendInvite']);";
			$lines[] = "\tRoute::post('accept', [{$controller}::class, 'accept']);";
		}

		if ($deleteAccount['enabled'] ?? true) {
			$lines[] = "\tRoute::post('delete', [{$controller}::class, 'delete']);";
		}

		$lines[] = "});";

		return implode("\n", $lines);
	}
}
