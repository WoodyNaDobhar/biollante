@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->model }}\Extensions;

trait {{ $config->modelNames->name }}Extension
{
	// Add custom accessors, mutators, and methods here
}
