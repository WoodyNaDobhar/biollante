<?php

namespace Biollante;

use Biollante\Console\Commands\ScaffoldCommand;
use Illuminate\Support\ServiceProvider;

class BiollanteServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->mergeConfigFrom(
			__DIR__ . '/../config/laravel_generator.php',
			'laravel_generator'
		);
	}

	public function boot(): void
	{
		$this->loadViewsFrom(__DIR__ . '/../resources/views', 'biollante');

		if ($this->app->runningInConsole()) {
			$this->publishes([
				__DIR__ . '/../config/laravel_generator.php' => config_path('laravel_generator.php'),
			], 'biollante-config');

			$this->publishes([
				__DIR__ . '/../resources/views' => resource_path('views/vendor/biollante'),
			], 'biollante-views');

			$this->commands([
				ScaffoldCommand::class,
			]);
		}
	}
}