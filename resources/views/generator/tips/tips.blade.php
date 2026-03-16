export const {{ $tipsName }}Tips = {
@foreach ($fields as $field)
	{{ $field['name'] }}: "{!! $field['tip'] !!}",
@endforeach
}