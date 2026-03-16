import {
@foreach ($imports as $import)
	{{ $import }}@if(!$loop->last),@endif

@endforeach
} from "@vuelidate/validators";
import { extend{{ $model }}Rules } from "@/rules/extensions/{{ $model }}";
@if (!empty($helpers))
import {
@foreach ($helpers as $helper)
	{{ $helper }}@if(!$loop->last),@endif

@endforeach
} from "@/utils/helper";
@endif

const base{{ $model }}Rules = {
@foreach ($fields as $field)
	{{ $field->name }}: {
@php
	$vals = is_array($field->validations ?? []) ? $field->validations : [];
@endphp
@foreach ($vals as $key => $value)
@if (is_int($key))
		{{ $value }}@if(!$loop->last){!! "," !!}@endif
@else
		{{ $key }}: {!! $value !!}@if(!$loop->last){!! "," !!}@endif
@endif

@endforeach
	}@if(!$loop->last){!! "," !!}@endif

@endforeach
};
export const {{ $model }}Rules = extend{{ $model }}Rules(base{{ $model }}Rules);
