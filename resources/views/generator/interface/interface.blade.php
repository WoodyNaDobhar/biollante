@foreach ($imports as $import)
import { {{ $import }} } from "./{{str_replace('Simple', '', $import)}}";
@endforeach

export interface {{ $interfaceName }} {
@foreach ($fields as $field)
	{{ $field['name'] }}: {!! $field['type'] !!};
@endforeach
@foreach ($relations as $relation)
	{{ $relation['name'] }}: {{ $relation['type'] }};
@endforeach
@if (!Str::endsWith($interfaceName, 'SuperSimple'))
	can_list: 0 | 1;
	can_view: 0 | 1;
	can_create: 0 | 1;
	can_update: 0 | 1;
	can_delete: 0 | 1;
	can_restore: 0 | 1;
	can_nuke: 0 | 1;
@endif
}
