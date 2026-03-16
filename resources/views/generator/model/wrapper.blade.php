@php
	echo '<?php'.PHP_EOL;
@endphp

namespace Biollante\Models;

use Biollante\Models\Core\{{ $config->modelNames->name }} as Core{{ $config->modelNames->name }};
use Biollante\Models\Extensions\{{ $config->modelNames->name }}Extension;

@if(isset($swaggerDocs))
{!! $swaggerDocs !!}
@endif

class {{ $config->modelNames->name }} extends Core{{ $config->modelNames->name }}
{
	use {{ $config->modelNames->name }}Extension;
}
