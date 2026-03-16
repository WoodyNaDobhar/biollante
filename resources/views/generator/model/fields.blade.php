[
			'id' => {{ $id }}, 
@foreach ($fields as $field)
			'{{ $field['name'] }}' => {!! $field['value'] !!},
@endforeach
		]