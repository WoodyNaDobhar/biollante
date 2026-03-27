@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->apiRequest }};

use {{ $config->namespaces->model }}\{{ $config->modelNames->name }};
use {{ $config->namespaces->apiRequest }}\APIRequest;

class Create{{ $config->modelNames->name }}APIRequest extends APIRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize()
	{
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return (new {{ $config->modelNames->name }}())->getCreateRules();
	}
}
