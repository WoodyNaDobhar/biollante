@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->repository }}\Extensions;

trait {{ $config->modelNames->name }}RepositoryExtension
{
	// Add custom repository methods here
}
