	/**
	 * {{$description}}
	 * @return \Illuminate\Database\Eloquent\Relations\{{ $relationClass }}
	 */
	public function {{ $functionName }}(): \Illuminate\Database\Eloquent\Relations\{{ $relationClass }}
	{
@if($isMorphTo)
		return $this->{{ $relation }}();
@else
		return $this->{{ $relation }}(\{{ $config->namespaces->model }}\{{ $relatedModel }}::class{!! $fields !!});
@endif
	}