	/**
	* @param Request $request
	* @return Response
	*
	* @OA\Get(
	*	path="/{{ $config->modelNames->dashedPlural }}/{{ '{' . $routeKeyName . '}' }}",
	*	summary="get{{ $config->modelNames->name }}Item",
	*	security=@if($permVisitors === 'Full'){}@else@{{"bearer_token":{}}}@endif,
	*	tags={"{{ $config->modelNames->name }}"},
	*	description="Get {{ $config->modelNames->name }}<br><b>Access</b>:<br>Visitors: {{$permVisitors}}<br>Users: {{$permUsers}}<br>@foreach($organizerPermissions as $roleName => $perm){{ $roleName }} Organizers: {{$perm}}<br>@endforeach Admins: {{$permAdmins}}<br>{{ $relations }}",
	*	@OA\Parameter(
	*		ref="#/components/parameters/columns"
	*	),
	*	@OA\Parameter(
	*		ref="#/components/parameters/with"
	*	),
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
	*						example="{{ $config->modelNames->name }}s retrieved successfully."
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
	*							ref="#/components/schemas/QueryParameters"
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