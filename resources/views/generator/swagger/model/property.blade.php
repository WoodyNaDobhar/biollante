 *		@OA\Property(
 *			property="{{ $fieldName }}",
 *			description="{{ $description }}",
 *			type="{{ $type }}",
@if($format)
 *			format="{{ $format }}",
@endif
@if($enum)
 *			enum={!! $enum !!},
@endif
@if($format === 'double' && $length)@php
	$lengthParts = explode(',', $length);
	$integerDigits = isset($lengthParts[0]) ? (int)$lengthParts[0] : 0;
	$decimalDigits = isset($lengthParts[1]) ? (int)$lengthParts[1] : 0;
	$scaleFactor = pow(10, $integerDigits + $decimalDigits - $decimalDigits); // Total digits before the decimal point
	$maximum = $scaleFactor - pow(10, -$decimalDigits);
	$minimum = -1 * $maximum;
@endphp
 *			minimum={{ str_replace(',', '', number_format($minimum, $decimalDigits)) }},
 *			maximum={{ str_replace(',', '', number_format($maximum, $decimalDigits)) }},
@elseif($length)
 *			maxLength={{ $length }},
@endif
 *			readOnly={{ $readOnly }},
 *			nullable={{ $nullable }},
@if($default !== null)
@if(is_numeric($default))
 *			default={{ $default }}
@else
 *			default="{{ $default }}"
@endif
@endif
 *		)