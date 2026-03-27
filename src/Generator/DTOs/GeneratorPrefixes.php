<?php

namespace Biollante\Generator\DTOs;

use Illuminate\Support\Str;

class GeneratorPrefixes
{
	public string $namespace = '';

	public function mergeNamespacePrefix(array $prefixes)
	{
		foreach ($prefixes as $prefix) {
			if (empty($prefix)) {
				continue;
			}

			$this->namespace .= '\\'.Str::title($prefix);
		}

		$this->namespace = ltrim($this->namespace, '\\');
	}
}
