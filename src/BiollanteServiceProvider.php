<?php

namespace Biollante;

use Biollante\Console\Commands\ScaffoldCommand;
use Biollante\Contracts\ScopeResolver;
use Illuminate\Support\ServiceProvider;

class BiollanteServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->mergeConfigFrom(
			__DIR__ . '/../config/biollante.php',
			'biollante'
		);

		$this->app->singleton(ScopeResolver::class, function ($app) {
			$class = config('biollante.scope_resolver');

			if (!$class) {
				return null;
			}

			return $app->make($class);
		});
	}

	public function boot(): void
	{
		$this->loadViewsFrom(__DIR__ . '/../resources/views', 'biollante');

		if ($this->app->runningInConsole()) {
			$this->publishes([
				__DIR__ . '/../config/biollante.php' => config_path('biollante.php'),
			], 'biollante');

			$this->publishes([
				__DIR__ . '/../resources/views' => resource_path('views/vendor/biollante'),
			], 'biollante-views');

			$this->publishes([
				__DIR__ . '/../stubs/HandleInertiaRequests.php' => app_path('Http/Middleware/HandleInertiaRequests.php'),
			], 'biollante-inertia');

			$this->publishes([
				__DIR__ . '/../stubs/frontend/usePermissions.ts' => resource_path('js/composables/usePermissions.ts'),
			], 'biollante-frontend');

			$this->commands([
				ScaffoldCommand::class,
			]);
		}
	}
}