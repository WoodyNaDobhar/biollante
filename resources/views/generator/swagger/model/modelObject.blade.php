 *		@OA\Property(
 *			property="{{$fieldName}}",
 *			description="{!! $description !!}",
 *			type="{{$type}}",
@if($type === 'object' && !is_array($ref))
 *			ref="#/components/schemas/{{$ref}}Simple",
@elseif($type === 'object')
 *			oneOf={
@foreach($ref as $r)
 *				@OA\Schema(ref="#/components/schemas/{{$r}}Simple"),
@endforeach
 *			},
@elseif($type === 'array' && !is_array($ref))
 *			@OA\Items(
 *				title="{{$ref}}",
 *				type="object",
 *				ref="#/components/schemas/{{$ref}}Simple"
 *			),
@else
	@foreach($ref as $r)
 *			@OA\Items(
 *				title="{{$r}}",
 *				type="object",
 *				ref="#/components/schemas/{{$r}}Simple"
 *			),
	@endforeach
 @endif
 *			readOnly=true
 *		)