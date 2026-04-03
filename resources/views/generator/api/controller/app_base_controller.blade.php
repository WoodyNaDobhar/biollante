@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $appNamespace }}\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 *	@@OA\OpenApi(
 *		@@OA\Info(
 *			title="{{ $apiTitle }}",
 *			version="{{ $apiVersion }}",
@if(!empty($apiDescription))
 *			description="{!! $apiDescription !!}",
@endif
@if($apiTermsUrl)
 *			termsOfService="{{ $apiTermsUrl }}",
@endif
@if($apiContactName || $apiContactEmail)
 *			contact={
@if($apiContactName)
 *				"name"="{{ $apiContactName }}",
@endif
@if($apiContactEmail)
 *				"email"="{{ $apiContactEmail }}",
@endif
 *			}
@endif
 *		)
 *	),
 *	@@OA\Server(url="{{ $apiServerUrl }}"),
 *	@@OA\SecurityScheme(
 *		securityScheme="bearer_token",
 *		type="http",
 *		scheme="bearer",
 *		bearerFormat="JWT",
 *	),
 *	@@OA\Schema(
 *		schema="QueryParameters",
 *		@@OA\Property(
 *			property="search[]",
 *			type="object",
 *			example={
 *				"id": "42"
 *			},
 *		),
 *		@@OA\Property(
 *			property="between[]",
 *			type="object",
 *			@@OA\Property(
 *				property="start",
 *				type="object",
 *				example={
 *					"starts_on": "2025-04-05"
 *				}
 *			),
 *			@@OA\Property(
 *				property="end",
 *				type="object",
 *				example={
 *					"starts_on": "2025-05-05"
 *				}
 *			)
 *		),
 *		@@OA\Property(
 *			property="columns[]",
 *			type="array",
 *			uniqueItems=true,
 *			@@OA\Items(
 *				type="string",
 *				format="snake_case",
 *				example="id"
 *			)
 *		),
 *		@@OA\Property(
 *			property="with[]",
 *			type="array",
 *			uniqueItems=true,
 *			@@OA\Items(
 *				type="string",
 *				format="dotNotation",
 *				example="createdBy.name"
 *			)
 *		),
 *		@@OA\Property(
 *			property="limit",
 *			type="integer",
 *			format="int32",
 *			example=5
 *		),
 *		@@OA\Property(
 *			property="page",
 *			type="integer",
 *			format="int32",
 *			example=5
 *		),
 *		@@OA\Property(
 *			property="sort[]",
 *			type="array",
 *			uniqueItems=true,
 *			@@OA\Items(
 *				type="string",
 *				format="snake_case",
 *				example="id"
 *			)
 *		),
 *		@@OA\Property(
 *			property="direction",
 *			type="string",
 *			format="enum",
 *			enum={"asc", "desc"},
 *			example="asc"
 *		)
 *	),
 *	@@OA\Schema(
 *		schema="SimpleQueryParameters",
 *		@@OA\Property(
 *			property="id",
 *			type="integer",
 *			format="int32",
 *			example=42
 *		),
 *		@@OA\Property(
 *			property="columns[]",
 *			type="array",
 *			uniqueItems=true,
 *			@@OA\Items(
 *				type="string",
 *				format="snake_case",
 *				example="id"
 *			)
 *		),
 *		@@OA\Property(
 *			property="with[]",
 *			type="array",
 *			uniqueItems=true,
 *			@@OA\Items(
 *				type="string",
 *				format="dotNotation",
 *				example="createdBy.name"
 *			)
 *		)
 *	),
 *	@@OA\Parameter(
 *		parameter="search",
 *		name="search",
 *		in="query",
 *		style="deepObject",
 *		@@OA\Schema( 
 *			type="object",
 *			example={
 *				"id": "42"
 *			},
 *		),
 *		description="Search any column for value. Ex: search[column]=value.",
 *		required=false
 *	),
 *	@@OA\Parameter(
 *		parameter="between",
 *		name="between",
 *		in="query",
 *		style="deepObject",
 *		@@OA\Schema(
 *			type="object",
 *			example={
 *				"start": {
 *					"starts_on": "1974-11-03"
 *				},
 *				"end": {
 *					"starts_on": "2025-05-05"
 *				}
 *			}
 *		),
 *		description="Search column for value inclusively between two values. Ex: between[start][starts_on]=YYYY-MM-DD & between[end][starts_on]=YYYY-MM-DD",
 *		required=false
 *	),
 *	@@OA\Parameter(
 *		parameter="columns",
 *		name="columns[]",
 *		in="query",
 *		@@OA\Schema(
 *			type="array",
 *			uniqueItems=true,
 *			@@OA\Items(
 *				type="string",
 *				format="snake_case",
 *				example="id"
 *			)
 *		),
 *		description="Restrict results to given column(s). If used with 'with', you must also include the id column and related foreign key. Ex: columns[]=id",
 *		required=false
 *	),
 *	@@OA\Parameter(
 *		parameter="with",
 *		name="with[]",
 *		in="query",
 *		@@OA\Schema(
 *			type="array",
 *			uniqueItems=true,
 *			@@OA\Items(
 *				type="string",
 *				format="dotNotation",
 *				example="createdBy.name"
 *			)
 *		),
 *		description="Attach given related objects (nestable with dot notation ['parent.child']) to the results. Ex: with[]=createdBy.chapter",
 *		required=false
 *	),
 *	@@OA\Parameter(
 *		parameter="limit",
 *		name="limit",
 *		in="query",
 *		@@OA\Schema(
 *			type="integer",
 *			format="int32",
 *		),
 *		description="Maximum number of results to return. Ex: limit=5",
 *		required=false
 *	),
 *	@@OA\Parameter(
 *		parameter="page",
 *		name="page",
 *		in="query",
 *		@@OA\Schema(
 *			type="integer",
 *			format="int32",
 *		),
 *		description="For pagination, the page of results requested. Must be used with 'limit' or it will be ignored. Ex: page=5&limit=5",
 *		required=false
 *	),
 *	@@OA\Parameter(
 *		parameter="sort",
 *		name="sort",
 *		in="query",
 *		style="deepObject",
 *		@@OA\Schema( 
 *			type="object",
 *			example={
 *				"id": "desc"
 *			},
 *		),
 *		description="Field (key, field name) and direction (value, either 'asc' or 'desc') in which the results should be sorted by column. Ex: direction[column1]=desc&direction[column2]=asc",
 *		required=false
 *	),
 *	@@OA\RequestBody(
 *		request="register",
 *		description="Register a new account.",
 *		required=true,
 *		@@OA\MediaType(
 *			mediaType="multipart/form-data",
 *			@@OA\Schema(
 *				required={ {!! '"' . implode('","', $registerRequired) . '"' !!} },
@foreach($registerProperties as $prop)
 *				@@OA\Property(
 *					property="{{ $prop['name'] }}",
 *					type="{{ $prop['type'] }}",
@if($prop['format'] ?? null)
 *					format="{{ $prop['format'] }}",
@endif
 *					description="{{ $prop['description'] }}",
@if(($prop['example'] ?? null) !== null)
@if(is_bool($prop['example']))
 *					example={{ $prop['example'] ? 'true' : 'false' }},
@elseif(is_int($prop['example']))
 *					example={{ $prop['example'] }},
@else
 *					example="{{ $prop['example'] }}",
@endif
@endif
@if($prop['maxLength'] ?? null)
 *					maxLength={{ $prop['maxLength'] }}
@endif
 *				),
@endforeach
 *			)
 *		)
 *	),
 *	@@OA\RequestBody(
 *		request="login",
 *		description="Login data.",
 *		required=true,
 *		@@OA\MediaType(
 *			mediaType="multipart/form-data",
 *			@@OA\Schema(
 *				required={"email","password","password_confirm","device_name"},
 *				@@OA\Property(
 *					property="email",
 *					description="Login email.",
 *					type="string",
 *					format="email",
 *					example="nobody@nowhere.net",
 *					maxLength=191
 *				),
 *				@@OA\Property(
 *					property="password",
 *					description="Login password.",
 *					type="string",
 *					format="password"
 *				),
 *				@@OA\Property(
 *					property="password_confirm",
 *					description="Login password, confirmed.",
 *					type="string",
 *					format="password"
 *				),
 *				@@OA\Property(
 *					property="device_name",
 *					description="Login device information.",
 *					type="string",
 *					example="Device"
 *				)
 *			)
 *		)
 *	),
 *	@@OA\RequestBody(
 *		request="forgot",
 *		description="Forgot Password Request.",
 *		required=true,
 *		@@OA\MediaType(
 *			mediaType="multipart/form-data",
 *			@@OA\Schema(
 *				required={"email","device_name"},
 *				@@OA\Property(
 *					property="email",
 *					description="User email.",
 *					type="string",
 *					format="email",
 *					example="nobody@nowhere.net",
 *					maxLength=191
 *				),
 *				@@OA\Property(
 *					property="device_name",
 *					description="User device information.",
 *					type="string",
 *					example="Device"
 *				)
 *			)
 *		)
 *	),
 *	@@OA\RequestBody(
 *		request="reset",
 *		description="Reset Password Request.",
 *		required=true,
 *		@@OA\MediaType(
 *			mediaType="multipart/form-data",
 *			@@OA\Schema(
 *				required={"email","token","password","password_confirm","device_name"},
 *				@@OA\Property(
 *					property="email",
 *					description="User email.",
 *					type="string",
 *					format="email",
 *					example="nobody@nowhere.net",
 *					maxLength=191
 *				),
 *				@@OA\Property(
 *					property="token",
 *					description="Password reset token.",
 *					type="string",
 *					maxLength=191
 *				),
 *				@@OA\Property(
 *					property="password",
 *					description="New password.",
 *					type="string",
 *					format="password"
 *				),
 *				@@OA\Property(
 *					property="password_confirm",
 *					description="New password, confirmed.",
 *					type="string",
 *					format="password"
 *				),
 *				@@OA\Property(
 *					property="device_name",
 *					description="User device information.",
 *					type="string",
 *					example="Device"
 *				)
 *			)
 *		)
 *	),
 *	@@OA\RequestBody(
 *		request="check",
 *		description="Active check data.",
 *		required=true,
 *		@@OA\MediaType(
 *			mediaType="multipart/form-data",
 *			@@OA\Schema(
 *				required={"user_id","device_name"},
 *				@@OA\Property(
 *					property="user_id",
 *					description="User being checked.",
 *					type="integer",
 *					format="int32",
 *					example=42
 *				),
 *				@@OA\Property(
 *					property="device_name",
 *					description="Login device information.",
 *					type="string",
 *					example="Device"
 *				)
 *			)
 *		)
 *	),
 *	@@OA\RequestBody(
 *		request="checkpass",
 *		description="Security check.",
 *		required=true,
 *		@@OA\MediaType(
 *			mediaType="multipart/form-data",
 *			@@OA\Schema(
 *				required={"password","password_confirm","device_name"},
 *				@@OA\Property(
 *					property="password",
 *					description="Login password.",
 *					type="string",
 *					format="password"
 *				),
 *				@@OA\Property(
 *					property="password_confirm",
 *					description="Login password, confirmed.",
 *					type="string",
 *					format="password"
 *				),
 *				@@OA\Property(
 *					property="device_name",
 *					description="Device information.",
 *					type="string",
 *					example="Device"
 *				)
 *			)
 *		)
 *	),
@if($searchEnabled)
 *	@@OA\RequestBody(
 *		request="search",
 *		description="Search Request.",
 *		required=true,
 *		@@OA\MediaType(
 *			mediaType="multipart/form-data",
 *			@@OA\Schema(
 *				required={"search"},
 *				@@OA\Property(
 *					property="search",
 *					description="Term being searched.",
 *					type="string",
 *					maxLength=191
 *				)
 *			)
 *		)
 *	),
@endif
@if($deleteEnabled)
 *	@@OA\RequestBody(
 *		request="delete",
 *		description="Delete Account Request.",
 *		required=true,
 *		@@OA\MediaType(
 *			mediaType="multipart/form-data",
 *			@@OA\Schema(
 *				required={"is_confirmed","password","password_confirm","device_name"},
 *				@@OA\Property(
 *					property="is_confirmed",
 *					description="User has agreed to having their personal data removed.",
 *					type="integer",
 *					format="int32",
 *					enum={0, 1},
 *					example=1
 *				),
 *				@@OA\Property(
 *					property="password",
 *					description="User password.",
 *					type="string",
 *					format="password"
 *				),
 *				@@OA\Property(
 *					property="password_confirm",
 *					description="User password, confirmed.",
 *					type="string",
 *					format="password"
 *				),
 *				@@OA\Property(
 *					property="device_name",
 *					description="User device information.",
 *					type="string",
 *					example="Device"
 *				)
 *			)
 *		)
 *	),
@endif
 * @@OA\Schema(
 *	 schema="SessionSimple",
 *	 title="SessionSimple",
 *	 description="Attachable Session object.",
 *	 @@OA\Property(property="id", type="string", maxLength=255, readOnly=true, nullable=false),
 *	 @@OA\Property(property="user_id", type="integer", format="int32", nullable=true),
 *	 @@OA\Property(property="ip_address", type="string", maxLength=45, nullable=true),
 *	 @@OA\Property(property="user_agent", type="string", nullable=true),
 *	 @@OA\Property(property="payload", type="string", nullable=false),
 *	 @@OA\Property(property="last_activity", type="integer", format="int32", nullable=false),
 *	 @@OA\Property(property="created_at", type="string", format="date-time", readOnly=true, nullable=false, default="CURRENT_TIMESTAMP"),
 *	 @@OA\Property(property="created_by", type="integer", format="int32", readOnly=true, nullable=false),
 *	 @@OA\Property(property="updated_at", type="string", format="date-time", readOnly=true, nullable=true),
 *	 @@OA\Property(property="updated_by", type="integer", format="int32", readOnly=true, nullable=true),
 *	 @@OA\Property(property="deleted_at", type="string", format="date-time", readOnly=true, nullable=true),
 *	 @@OA\Property(property="deleted_by", type="integer", format="int32", readOnly=true, nullable=true)
 * )
 * @@OA\Schema(
 *	 schema="AuditSimple",
 *	 title="AuditSimple",
 *	 description="A log entry of changes made in the system.",
 *	 @@OA\Property(property="id", type="integer", format="int64", readOnly=true, nullable=false),
 *	 @@OA\Property(property="role", type="string", maxLength=25, nullable=false),
 *	 @@OA\Property(property="user_id", type="integer", format="int64", nullable=false),
 *	 @@OA\Property(property="event", type="string", maxLength=10, nullable=false),
 *	 @@OA\Property(property="auditable_type", type="string", maxLength=255, nullable=false),
 *	 @@OA\Property(property="auditable_id", type="integer", format="int64", nullable=false),
 *	 @@OA\Property(property="old_values", type="string", format="json", nullable=true),
 *	 @@OA\Property(property="new_values", type="string", format="json", nullable=true),
 *	 @@OA\Property(property="url", type="string", format="url", nullable=true),
 *	 @@OA\Property(property="ip_address", type="string", maxLength=45, nullable=true),
 *	 @@OA\Property(property="user_agent", type="string", maxLength=1023, nullable=true),
 *	 @@OA\Property(property="tags", type="string", maxLength=255, nullable=true),
 *	 @@OA\Property(property="created_by", type="integer", format="int64", readOnly=true, nullable=false),
 *	 @@OA\Property(property="created_at", type="string", format="date-time", readOnly=true, nullable=false, default="CURRENT_TIMESTAMP"),
 *	 @@OA\Property(property="updated_by", type="integer", format="int64", readOnly=true, nullable=true),
 *	 @@OA\Property(property="updated_at", type="string", format="date-time", readOnly=true, nullable=true),
 *	 @@OA\Property(property="deleted_by", type="integer", format="int64", readOnly=true, nullable=true),
 *	 @@OA\Property(property="deleted_at", type="string", format="date-time", readOnly=true, nullable=true)
 * )
 */
class AppBaseController extends Controller
{
	
	public function sendResponse($result, $message, $meta = null, $success = true)
	{
		return response()->json([
			'success' => $success,
			'data'	=> $result,
			'message' => $message,
			'meta' => $meta,
		], 200);
	}
	
	public function sendError($message, $data = null, $code = 400)
	{
		if (!empty($data)) {
			$response = [
				'success' => false,
				'message' => $message,
				'data' => $data
			];
		}else{
			$response = [
				'success' => false,
				'message' => $message
			];
		}
		
		return response()->json($response, $code);
	}
	
	public function sendSuccess($message)
	{
		return Response::json([
			'success' => true,
			'message' => $message
		], 200);
	}
}
