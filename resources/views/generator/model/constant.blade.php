export const {{ $constantName }} = [
@foreach ($enumValues as $value)
	"{{ $value }}",
@endforeach
] as const;

export type {{ $aliasName }} = (typeof {{ $constantName }})[number];
