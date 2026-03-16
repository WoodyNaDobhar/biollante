 * @OA\Schema(
 *		schema="{{ $config->modelNames->name }}{{ $subName }}",
 *		title="{{ $config->modelNames->name }}{{ $subName }}",
@if($subName === '')
 *		required={!! $requiredFields !!},
@endif
 *		description="{!! $description !!}",
 {!! $properties !!}
 * )