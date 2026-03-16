@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->apiController }};

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use {{ $config->namespaces->apiRequest }}\Create{{ $config->modelNames->name }}APIRequest;
use {{ $config->namespaces->apiRequest }}\Update{{ $config->modelNames->name }}APIRequest;
use {{ $config->namespaces->model }}\{{ $config->modelNames->name }};
use {{ $config->namespaces->repository }}\{{ $config->modelNames->name }}Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use {{ $config->namespaces->app }}\Http\Controllers\AppBaseController;
use {{ $config->namespaces->apiResource }}\{{ $config->modelNames->name }}Resource;

{!! $docController !!}
class {{ $config->modelNames->name }}APIController extends AppBaseController
{
	
	use AuthorizesRequests;

	/** @var  {{ $config->modelNames->name }}Repository */
	private ${{ $config->modelNames->camel }}Repository;

	public function __construct({{ $config->modelNames->name }}Repository ${{ $config->modelNames->camel }}Repo)
	{
		$this->{{ $config->modelNames->camel }}Repository = ${{ $config->modelNames->camel }}Repo;
	}

	{!! $docIndex !!}
	public function index(Request $request): JsonResponse
	{
		try {

			$this->authorize('viewAny', {{ $config->modelNames->camel }}::class);

			${{ $config->modelNames->camelPlural }} = $this->{{ $config->modelNames->camel }}Repository->all(
				$request->has('search') ? $request->get('search') : [],
				$request->has('between') ? $request->get('between') : [],
				$request->has('limit') ? $request->get('limit') : null,
				$request->has('columns') ? $request->get('columns') : ['*'],
				$request->has('with') ? $request->get('with') : null,
				$request->has('sort') ? $request->get('sort') : null
			);
			
			if(method_exists(${{ $config->modelNames->camelPlural }}, 'perPage')){
				$meta = [
					'perPage' => ${{ $config->modelNames->camelPlural }}->perPage(),
					'currentPage' => ${{ $config->modelNames->camelPlural }}->currentPage(),
					'total' => ${{ $config->modelNames->camelPlural }}->total(),
					'lastPage' => ${{ $config->modelNames->camelPlural }}->lastPage(),
				];
			}else{
				$meta = null;
			}

			return $this->sendResponse({{ $config->modelNames->name }}Resource::collection(${{ $config->modelNames->camelPlural }}), '{{ $config->modelNames->humanPlural }} retrieved successfully.', $meta);
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}

	{!! $docStore !!}
	public function store(Create{{ $config->modelNames->name }}APIRequest $request): JsonResponse
	{
		try {

			$this->authorize('create', {{ $config->modelNames->name }}::class);
			
			$input = $request->all();

			${{ $config->modelNames->camel }} = $this->{{ $config->modelNames->camel }}Repository->create($input);

			return $this->sendResponse(new {{ $config->modelNames->name }}Resource(${{ $config->modelNames->camel }}), '{{ $config->modelNames->name }} saved successfully.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}

	{!! $docShow !!}
	public function show({{ $config->modelNames->name }} ${{ $config->modelNames->camel }}, Request $request): JsonResponse
	{
		try {
			$id = ${{ $config->modelNames->camel }}->id;

			/** @var {{ $config->modelNames->name }} ${{ $config->modelNames->camel}} */
			${{ $config->modelNames->camel}} = $this->{{ $config->modelNames->camel}}Repository->find(
				$id,
				$request->has('columns') ? $request->get('columns') : ['*'],
				$request->has('with') ? $request->get('with') : null
			);
			
			if (empty(${{ $config->modelNames->camel}})) {
				return $this->sendError('{{ $config->modelNames->name }} (' . $id . ') not found.', ['id' => $id] + $request->all(), 404);
			}
		
			$this->authorize('view', ${{ $config->modelNames->camel}});

			return $this->sendResponse(new {{ $config->modelNames->name }}Resource(${{ $config->modelNames->camel}}), '{{ $config->modelNames->human }} retrieved successfully.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), null, $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}

	{!! $docUpdate !!}
	public function update({{ $config->modelNames->name }} ${{ $config->modelNames->camel }}, Update{{$config->modelNames->name }}APIRequest $request): JsonResponse
	{
		try {
			$input = $request->all();
			$id = ${{ $config->modelNames->camel }}->id;
		
			$this->authorize('update', ${{$config->modelNames->camel }});

			${{$config->modelNames->camel }} = $this->{{ $config->modelNames->camel }}Repository->update($input, $id);

			return $this->sendResponse(new {{$config->modelNames->name }}Resource(${{$config->modelNames->camel }}), '{{$config->modelNames->name }} updated successfully.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? null : $request->all(), $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}

	{!! $docDestroy !!}
	public function destroy({{ $config->modelNames->name }} ${{ $config->modelNames->camel }}): JsonResponse
	{
		try {
		
			$this->authorize('delete', ${{ $config->modelNames->camel }});

			$this->{{ $config->modelNames->camel }}Repository->delete($id);

			return $this->sendSuccess('{{ $config->modelNames->human }} deleted successfully.');
		} catch (Throwable $e) {
			$trace = $e->getTrace()[BiollanteHelper::instance()->search_multi_array(__FILE__, 'file', $e->getTrace())];
			Log::error($e->getMessage() . " (" . $trace['file'] . ":" . $trace['line'] . ")\r\n" . '[stacktrace]' . "\r\n" . $e->getTraceAsString());
			return $this->sendError($e->getMessage(), null, $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 400);
		}
	}
}
