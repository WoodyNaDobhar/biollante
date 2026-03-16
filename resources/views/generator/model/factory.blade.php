@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->factory }};

use {{ $config->namespaces->model }}\{{ $config->modelNames->name }};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
{!! $usedRelations !!}

class {{ $config->modelNames->name }}Factory extends Factory
{
	/**
	 * The name of the factory's corresponding model.
	 *
	 * @var string
	 */
	protected $model = {{ $config->modelNames->name }}::class;

	/**
	 * Define the model's default state.
	 *
	 * @return array
	 */
	public function definition()
	{
		{!! $relations !!}

		$base = [
			{!! $fields !!}
		];

		return $this->applyFactoryExtension($base);
	}

	/**
	 * Allow an extension to override or mutate the generated attributes.
	 */
	private function applyFactoryExtension(array $base): array
	{
		$ext = '\\Database\\Factories\\Extensions\\' . class_basename(static::class) . 'Extension';

		if (class_exists($ext)) {
			// Full override: build the whole array yourself
			if (method_exists($ext, 'overrideDefinition')) {
				return $ext::overrideDefinition($this->faker, $base);
			}
			// Mutation: tweak keys from the generated $base
			if (method_exists($ext, 'mutateDefinition')) {
				return $ext::mutateDefinition($base);
			}
		}

		return $base;
	}
}
