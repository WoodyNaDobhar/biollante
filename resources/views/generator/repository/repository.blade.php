@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->repository }};

use {{ $config->namespaces->model }}\{{ $config->modelNames->name }};
use {{ $config->namespaces->app }}\Repositories\BaseRepository;
use Biollante\Repositories\Extensions\{{ $config->modelNames->name }}RepositoryExtension;

class {{ $config->modelNames->name }}Repository extends BaseRepository
{
	use {{ $config->modelNames->name }}RepositoryExtension;

	protected $fieldSearchable = [
		{!! $fieldSearchable !!}
	];

	public function getFieldsSearchable(): array
	{
		return $this->fieldSearchable;
	}

	public function model(): string
	{
		return {{ $config->modelNames->name }}::class;
	}
}
