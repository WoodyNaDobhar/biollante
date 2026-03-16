 *	@OA\RequestBody(
 *		request="{{ $config->modelNames->name }}",
 *		description="{{ $config->modelNames->name }} object that needs to be added or updated.",
 *		required=true,
 *		@OA\MediaType(
 *			mediaType="multipart/form-data",
 *			@OA\Schema(ref="#/components/schemas/{{ $config->modelNames->name }}Simple")
 *		)
 *	)