@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->model }};

use {{ $config->namespaces->model }}\Core\{{ $config->modelNames->name }} as Core{{ $config->modelNames->name }};
use {{ $config->namespaces->model }}\Extensions\{{ $config->modelNames->name }}Extension;

@if(isset($swaggerDocs))
{!! $swaggerDocs !!}
@endif

class {{ $config->modelNames->name }} extends Core{{ $config->modelNames->name }}
{
	use {{ $config->modelNames->name }}Extension;
}
