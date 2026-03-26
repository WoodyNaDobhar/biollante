	/**
	* @param int $id
	* @param Update{{ $config->modelNames->name }}APIRequest $request
	* @return Response
	*
	* @OA\Post(
	*	path="/{{ $config->modelNames->dashedPlural }}/{{ '{' . $routeKeyName . '}' }}",
	*	summary="update{{ $config->modelNames->name }}",
	*	security=@if($permVisitors === 'Full'){}@else@{{"bearer_token":{}}}@endif,
	*	tags={"{{ $config->modelNames->name }}"},
	*	description="Update {{ $config->modelNames->name }}<br><b>Access</b>:<br>Visitors: {{$permVisitors}}<br>Users: {{$permUsers}}<br>@foreach($organizerPermissions as $roleName => $perm){{ $roleName }} Organizers: {{$perm}}<br>@endforeach Admins: {{$permAdmins}}",
	*	@OA\Parameter(
	*		in="path",
	*		name="{{ $routeKeyName }}",
	*		description="{{ $routeKeyDescription }}",
	*		@OA\Schema(
	*			type="{{ $routeKeyType }}"
	*		),
	*		required=true,
	*		example=@if($routeKeyExampleIsString)"{{ $routeKeyExample }}"@else{{ $routeKeyExample }}@endif 
	*	),
	*	@OA\Parameter(
	*		in="query",
	*		name="_method",
	*		description="This is a patch for swagger-ui, to send form data.  If you're sending json content, and using PUT method, it's not really required.",
	*		@OA\Schema(
	*			type="string"
	*		),
	*		required=true,
	*		example="Put"
	*	),
	*	requestBody={"$ref": "#/components/requestBodies/{{ $config->modelNames->name }}"},
	*	@OA\Response(
	*		response=200,
	*		description="successful operation",
	*		content={
	*			@OA\MediaType(
	*				mediaType="application/json",
	*				@OA\Schema(
	*					type="object",
	*					@OA\Property(
	*						property="success",
	*						default="true",
	*						type="boolean"
	*					),
	*					@OA\Property(
	*						property="data",
	*						type="array",
	*						@OA\Items(
	*							ref="#/components/schemas/{{ $config->modelNames->name }}Simple"
	*						)
	*					),
	*					@OA\Property(
	*						property="message",
	*						type="string",
	*						example="{{ $config->modelNames->name }} updated successfully."
	*					),
	*					@OA\Property(
	*						property="meta",
	*						type="object",
	*						description="Optional metadata for pagination or context",
	*						example={"total":100,"page":1,"per_page":10}
	*					)
	*				)
	*			)
	*		}
	*	),
	*	@OA\Response(
	*		response=400,
	*		description="unsuccessful operation",
	*		content={
	*			@OA\MediaType(
	*				mediaType="application/json",
	*				@OA\Schema(
	*					type="object",
	*					@OA\Property(
	*						property="success",
	*						default="false",
	*						type="boolean"
	*					),
	*					@OA\Property(
	*						property="message",
	*						type="string",
	*						example="Exception"
	*					),
	*					@OA\Property(
	*						property="data",
	*						type="array",
	*						@OA\Items(
	*							ref="#/components/schemas/{{ $config->modelNames->name }}SuperSimple"
	*						)
	*					)
	*				)
	*			)
	*		}
	*	),
	*	@OA\Response(
	*		response=401,
	*		description="unauthenticated",
	*		content={
	*			@OA\MediaType(
	*				mediaType="application/json",
	*				@OA\Schema(
	*					type="object",
	*					@OA\Property(
	*						property="success",
	*						default="false",
	*						type="boolean"
	*					),
	*					@OA\Property(
	*						property="message",
	*						type="string",
	*						example="Unauthenticated."
	*					)
	*				)
	*			)
	*		}
	*	),
	*	@OA\Response(
	*		response=403,
	*		description="unauthorized",
	*		content={
	*			@OA\MediaType(
	*				mediaType="application/json",
	*				@OA\Schema(
	*					type="object",
	*					@OA\Property(
	*						property="success",
	*						default="false",
	*						type="boolean"
	*					),
	*					@OA\Property(
	*						property="message",
	*						type="string",
	*						example="This action is unauthorized."
	*					)
	*				)
	*			)
	*		}
	*	)
	* )
	*/