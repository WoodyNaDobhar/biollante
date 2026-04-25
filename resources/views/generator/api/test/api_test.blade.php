@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->apiTests }};

use Illuminate\Support\Str;
use {{ $config->namespaces->tests }}\TestCase;
use Biollante\Testing\ApiTestTrait;
@if($config->modelNames->name !== 'User')
use {{ $config->namespaces->model }}\{{ $config->modelNames->name }};
@endif
use {{ $config->namespaces->model }}\User;

class {{ $config->modelNames->name }}ApiTest extends TestCase
{
	use ApiTestTrait;

	private $ext = null;

	protected function setUp(): void
	{
		parent::setUp();
		$extClass = \{{ $config->namespaces->tests }}\APIs\Extensions\{{ $config->modelNames->name }}ApiTestExtension::class;
		if (\class_exists($extClass)) {
			$this->ext = new $extClass();
			if (\method_exists($this->ext, 'setUp')) {
				$this->ext->setUp($this);
			}
		}
	}

	protected function tearDown(): void
	{
		if ($this->ext && \method_exists($this->ext, 'tearDown')) {
			$this->ext->tearDown($this);
		}
		parent::tearDown();
	}

	/**
	 * Call $ext->$method($this) if available; otherwise run $default().
	 */
	private function runOrExt(string $method, \Closure $default)
	{
		if ($this->ext && \method_exists($this->ext, $method)) {
			return $this->ext->{$method}($this);
		}
		return $default();
	}

	#[Test]
	public function test_create_{{ $config->modelNames->snake }}()
	{
		return $this->runOrExt(__FUNCTION__, function () {
			$this->actingAs(User::where('id', 1)->first(), 'api');

			${{ $config->modelNames->camel }} = {{ $config->modelNames->name }}::factory()->make()->toArray();

			$this->response = $this->json(
				'POST',
				'/{{ $config->apiPrefix }}/{{ $config->modelNames->dashedPlural }}', ${{ $config->modelNames->camel }}
			);
			
			if (
				(property_exists($this->response->getData(), 'success') && $this->response->getData()->success === 0) || 
				(property_exists($this->response->getData(), 'exception') && $this->response->getData()->exception === 'Error')
			) {
				$this->assertEquals(1, 2, $this->response['message']);
			}

			$this->assertApiResponse(${{ $config->modelNames->camel }});
			{{ $config->modelNames->name }}::where('id', $this->response->getData()->data->id)->forceDelete();
		});
	}

	#[Test]
	public function test_read_{{ $config->modelNames->snake }}()
	{
		return $this->runOrExt(__FUNCTION__, function () {
			$this->actingAs(User::where('id', 1)->first(), 'api');

			${{ $config->modelNames->camel }} = {{ $config->modelNames->name }}::factory()->create();

			$this->response = $this->json(
				'GET',
				'/{{ $config->apiPrefix }}/{{ $config->modelNames->dashedPlural }}/'.${{ $config->modelNames->camel }}->{{ $config->primaryName }}
			);
			
			if ((property_exists($this->response->getData(), 'success') && $this->response->getData()->success == 0) || 
				(property_exists($this->response->getData(), 'exception') && $this->response->getData()->exception == 'Error')
			) {
				$this->assertEquals(1, 2, $this->response['message']);
			}

			$this->assertApiResponse(${{ $config->modelNames->camel }}->toArray());
			{{ $config->modelNames->name }}::where('id', ${{ $config->modelNames->camel }}->id)->forceDelete();
		});
	}

	#[Test]
	public function test_update_{{ $config->modelNames->snake }}()
	{
		return $this->runOrExt(__FUNCTION__, function () {
			$this->actingAs(User::where('id', 1)->first(), 'api');

			${{ $config->modelNames->camel }} = {{ $config->modelNames->name }}::factory()->create();
			$edited{{ $config->modelNames->name }} = {{ $config->modelNames->name }}::factory()->make()->toArray();

			$this->response = $this->json(
				'PUT',
				'/{{ $config->apiPrefix }}/{{ $config->modelNames->dashedPlural }}/'.${{ $config->modelNames->camel }}->{{ $config->primaryName }},
				$edited{{ $config->modelNames->name }}
			);
			
			if ((property_exists($this->response->getData(), 'success') && $this->response->getData()->success == 0) || 
				(property_exists($this->response->getData(), 'exception') && $this->response->getData()->exception == 'Error')
			) {
				$this->assertEquals(1, 2, $this->response['message']);
			}

			$this->assertApiResponse($edited{{ $config->modelNames->name }});
			{{ $config->modelNames->name }}::where('id', ${{ $config->modelNames->camel }}->id)->forceDelete();
		});
	}

	#[Test]
	public function test_delete_{{ $config->modelNames->snake }}()
	{
		return $this->runOrExt(__FUNCTION__, function () {
			$this->actingAs(User::where('id', 1)->first(), 'api');

			${{ $config->modelNames->camel }} = {{ $config->modelNames->name }}::factory()->create();

			$this->response = $this->json(
				'DELETE',
				'/{{ $config->apiPrefix }}/{{ $config->modelNames->dashedPlural }}/'.${{ $config->modelNames->camel }}->{{ $config->primaryName }}
			);
			
			if ((property_exists($this->response->getData(), 'success') && $this->response->getData()->success == 0) || 
				(property_exists($this->response->getData(), 'exception') && $this->response->getData()->exception == 'Error')
			) {
				$this->assertEquals(1, 2, $this->response['message']);
			}

			$this->assertApiSuccess();
			$this->response = $this->json(
				'GET',
				'/{{ $config->apiPrefix }}/{{ $config->modelNames->dashedPlural }}/'.${{ $config->modelNames->camel }}->{{ $config->primaryName }}
			);

			$this->response->assertStatus(404);
			$this->response->assertJson(['success' => false]);
			{{ $config->modelNames->name }}::where('id', ${{ $config->modelNames->camel }}->id)->withTrashed()->forceDelete();
		});
	}
}
