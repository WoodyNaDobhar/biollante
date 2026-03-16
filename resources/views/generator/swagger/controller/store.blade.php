	 /**
	 * @param Create{{ $config->modelNames->name }}APIRequest $request
	 * @return Response
	 *
	 * @OA\Post(
	 *	path="/{{ $config->modelNames->dashedPlural }}",
	 *	summary="create{{ $config->modelNames->name }}",
	 *	security=@if($permVisitors === 'Full'){}@else@{{"bearer_token":{}}}@endif,
	 *	tags={"{{ $config->modelNames->name }}"},
	 *	description="Create {{ $config->modelNames->name }}<br><b>Access</b>:<br>Visitors: {{$permVisitors}}<br>Users: {{$permUsers}}<br>Thread Organizers: {{$permUsers}}<br>Chapter Organizers: {{$permChapterOrganizers}}<br>Collective Organizers: {{$permCollectiveOrganizers}}<br>Team Organizers: {{$permTeamOrganizers}}<br>Vendor Organizers: {{$permVendors}}<br>World Organizers: {{$permWorldOrganizers}}<br>Admins: {{$permAdmins}}<br>{{ $relations }}",
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
	 *						example="{{ $config->modelNames->name }} saved successfully."
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