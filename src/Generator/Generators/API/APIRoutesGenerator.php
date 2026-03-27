<?php

namespace Biollante\Generator\Generators\API;

use Biollante\Helpers\BiollanteHelper;
use Illuminate\Support\Str;
use Biollante\Generator\Generators\BaseGenerator;
use Biollante\Generator\Common\GeneratorConfig;
use Spatie\Permission\Models\Permission;

class APIRoutesGenerator extends BaseGenerator
{

	public function __construct(GeneratorConfig $config)
	{
		parent::__construct();
		$this->config = $config;
		$this->path = $this->config->paths->apiRoutes;
		$this->modelName = strtolower($this->config->modelNames->name);
		$this->controller = $this->config->namespaces->apiController . "\\" . $this->config->modelNames->name . "APIController";

		$this->routes = [
			'list' => "Route::get('" . $this->config->modelNames->dashedPlural . "', [" . $this->controller . "::class, 'index']);",
			'store' => "Route::post('" . $this->config->modelNames->dashedPlural . "', [" . $this->controller . "::class, 'store']);",
			'display' => "Route::get('" . $this->config->modelNames->dashedPlural . "/{" . $this->config->modelNames->camel . "}', [" . $this->controller . "::class, 'show']);",
			'update' => "Route::put('" . $this->config->modelNames->dashedPlural . "/{" . $this->config->modelNames->camel . "}', [" . $this->controller . "::class, 'update']);",
			'remove' => "Route::delete('" . $this->config->modelNames->dashedPlural . "/{" . $this->config->modelNames->camel . "}', [" . $this->controller . "::class, 'destroy']);",
		];
	}

	public function generate()
	{
		// Retrieve existing route content
		$routeContents = BiollanteHelper::instance()->g_filesystem()->getFile($this->path);

		// Retrieve roles and their permissions
		$roles = \Spatie\Permission\Models\Role::with('permissions')
			->where('name', '!=', 'Admin')
			->get();

		// Initialize route groups
		$restrictedRoutes = [];
		$publicRoutes = [];

		foreach ($this->routes as $action => $route) {
			// Skip if the route already exists in the file
			if (Str::contains($routeContents, $route)) {
				continue;
			}

			// Construct the required permission string
			$requiredPermissions = [
				"{$action} " . $this->config->modelNames->dashedPlural,
				"{$action}Own " . $this->config->modelNames->dashedPlural,
				"{$action}Related " . $this->config->modelNames->dashedPlural
			];

			// Check if any role has any of these permissions
			$hasPermission = $roles->some(function ($role) use ($requiredPermissions) {
				return $role->permissions->pluck('name')->intersect($requiredPermissions)->isNotEmpty();
			});

			if ($hasPermission) {
				$restrictedRoutes[] = $route;
			} else {
				$publicRoutes[] = $route;
			}
		}

		// Render restricted and public route groups
		$restrictedRouteGroup = view('biollante::generator.api.routes', ['config' => $this->config, 'routes' => $restrictedRoutes])->render();
		$publicRouteGroup = view('biollante::generator.api.routes', ['config' => $this->config, 'routes' => $publicRoutes])->render();

		// Update sections with sorted routes
		$routeContents = $this->insertUniqueInSection($routeContents, '//generated restricted routes begins', '//generated restricted routes ends', $restrictedRouteGroup, TRUE);
		$routeContents = $this->insertUniqueInSection($routeContents, '//generated public routes begins', '//generated public routes ends', $publicRouteGroup);

		// Write back to the file
		BiollanteHelper::instance()->g_filesystem()->createFile($this->path, $routeContents);

		// Log success
		$this->config->commandComment("API routes for $this->modelName generated successfully.");
	}
	
	private function insertUniqueInSection(string $content, string $sectionMarkerBegins, string $sectionMarkerEnds, string $newContent, bool $doTab = FALSE): string
	{
		$sectionStart = strpos($content, $sectionMarkerBegins);
		$sectionEnd = strpos($content, $sectionMarkerEnds);

		if ($sectionStart !== false && $sectionEnd !== false && $sectionEnd > $sectionStart) {
			// Extract the before, within, and after sections
			$before = substr($content, 0, $sectionStart + strlen($sectionMarkerBegins));
			$after = substr($content, $sectionEnd);

			// Extract existing routes between the markers
			$existingRoutes = array_filter(array_map('trim', explode(PHP_EOL, substr($content, $sectionStart + strlen($sectionMarkerBegins), $sectionEnd - $sectionStart - strlen($sectionMarkerBegins)))));
			$newRoutes = array_filter(array_map('trim', explode(PHP_EOL, trim($newContent))));

			// Merge, deduplicate, and sort routes by model name
			$mergedRoutes = array_unique(array_merge($existingRoutes, $newRoutes));
			usort($mergedRoutes, function ($a, $b) {
				// Extract model names for sorting
				$modelNameA = $this->extractModelName($a);
				$modelNameB = $this->extractModelName($b);

				return strcmp($modelNameA, $modelNameB);
			});

			// Add a blank line between different model groups
			$groupedRoutes = [];
			$previousModel = '';
			foreach ($mergedRoutes as $route) {
				$currentModel = $this->extractModelName($route);
				if ($previousModel !== '' && $currentModel !== $previousModel) {
					$groupedRoutes[] = ''; // Add a blank line
				}
				$groupedRoutes[] = $route;
				$previousModel = $currentModel;
			}

			// Reconstruct the section with sorted and grouped routes
			return $before . PHP_EOL . ($doTab ? BiollanteHelper::format_tab() : '') . implode(PHP_EOL . ($doTab ? BiollanteHelper::format_tab() : ''), $groupedRoutes) . PHP_EOL . $after;
		}

		// If markers are not properly found, append the new content
		return $content . PHP_EOL . $newContent;
	}

	private function extractModelName(string $route): string
	{
		// Match route patterns like "Route::get('accounts', ..."
		preg_match("#Route::(?:get|post|put|delete)\('([^/']+)#", $route, $matches);
		return $matches[1] ?? ''; // Return the matched model name or an empty string
	}

	public function rollback()
	{
		// Retrieve existing route content
		$routeContents = BiollanteHelper::instance()->g_filesystem()->getFile($this->path);
	
		// Iterate through defined routes
		foreach ($this->routes as $action => $route) {
			// Check if the route exists in the file and remove it
			$routePattern = preg_quote(trim($route), '/'); // Escape special characters for regex
			$routeContents = preg_replace("/^.*{$routePattern}.*$/m", '', $routeContents);
		}
	
		// Remove any extra blank lines introduced
		$routeContents = preg_replace("/\n+/", "\n", $routeContents);
	
		// Write back the updated content
		BiollanteHelper::instance()->g_filesystem()->createFile($this->path, trim($routeContents));
	
		// Log success
		$this->config->commandComment("API routes for $this->modelName removed successfully.");
	}
}
