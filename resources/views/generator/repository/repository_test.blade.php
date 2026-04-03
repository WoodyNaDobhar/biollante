@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->repositoryTests }};

use {{ $config->namespaces->model }}\{{ $config->modelNames->name }};
use {{ $config->namespaces->repository }}\{{ $config->modelNames->name }}Repository;
use PHPUnit\Framework\Attributes\Test;
use {{ $config->namespaces->tests }}\TestCase;
use Biollante\Testing\ApiTestTrait;

class {{ $config->modelNames->name }}RepositoryTest extends TestCase
{
	use ApiTestTrait;

	protected {{ $config->modelNames->name }}Repository ${{ $config->modelNames->camel }}Repo;

	private $ext = null;

	public function setUp() : void
	{
		parent::setUp();
		$this->{{ $config->modelNames->camel }}Repo = app({{ $config->modelNames->name }}Repository::class);

		$extClass = \{{ $config->namespaces->repositoryTests }}\Extensions\{{ $config->modelNames->name }}RepositoryTestExtension::class;
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
			$this->actingAs(\{{ $config->namespaces->model }}\User::where('id', 1)->first(), 'api');
			${{ $config->modelNames->camel }} = {{ $config->modelNames->name }}::factory()->make()->toArray();

			$created{{ $config->modelNames->name }} = $this->{{ $config->modelNames->camel }}Repo->create(${{ $config->modelNames->camel }});

			$created{{ $config->modelNames->name }} = $created{{ $config->modelNames->name }}->toArray();
			$this->assertArrayHasKey('id', $created{{ $config->modelNames->name }});
			$this->assertNotNull($created{{ $config->modelNames->name }}['id'], 'Created {{ $config->modelNames->name }} must have id specified');
			$this->assertNotNull({{ $config->modelNames->name }}::find($created{{ $config->modelNames->name }}['id']), '{{ $config->modelNames->name }} with given id must be in DB');
			$this->assertModelData(${{ $config->modelNames->camel }}, $created{{ $config->modelNames->name }});
			{{ $config->modelNames->name }}::where('id', $created{{ $config->modelNames->name }}['id'])->forceDelete();
		});
	}

	#[Test]
	public function test_read_{{ $config->modelNames->snake }}()
	{
		return $this->runOrExt(__FUNCTION__, function () {
			$this->actingAs(\{{ $config->namespaces->model }}\User::where('id', 1)->first(), 'api');
			${{ $config->modelNames->camel }} = {{ $config->modelNames->name }}::factory()->create();

			$db{{ $config->modelNames->name }} = $this->{{ $config->modelNames->camel }}Repo->find(${{ $config->modelNames->camel }}->{{ $config->primaryName }});

			$db{{ $config->modelNames->name }} = $db{{ $config->modelNames->name }}->toArray();
			$this->assertModelData(${{ $config->modelNames->camel }}->toArray(), $db{{ $config->modelNames->name }});
			{{ $config->modelNames->name }}::where('id', ${{ $config->modelNames->camel }}->id)->forceDelete();
		});
	}

	#[Test]
	public function test_update_{{ $config->modelNames->snake }}()
	{
		return $this->runOrExt(__FUNCTION__, function () {
			$this->actingAs(\{{ $config->namespaces->model }}\User::where('id', 1)->first(), 'api');
			${{ $config->modelNames->camel }} = {{ $config->modelNames->name }}::factory()->create();
			$updatedNew{{ $config->modelNames->name }} = {{ $config->modelNames->name }}::factory()->make()->toArray();

			$updated{{ $config->modelNames->name }} = $this->{{ $config->modelNames->camel }}Repo->update($updatedNew{{ $config->modelNames->name }}, ${{ $config->modelNames->camel }}->{{ $config->primaryName }});

			$this->assertModelData($updatedNew{{ $config->modelNames->name }}, $updated{{ $config->modelNames->name }}->toArray());
			$db{{ $config->modelNames->name }} = $this->{{ $config->modelNames->camel }}Repo->find(${{ $config->modelNames->camel }}->{{ $config->primaryName }});
			$this->assertModelData($updatedNew{{ $config->modelNames->name }}, $db{{ $config->modelNames->name }}->toArray());
			{{ $config->modelNames->name }}::where('id', ${{ $config->modelNames->camel }}->id)->forceDelete();
		});
	}

	#[Test]
	public function test_delete_{{ $config->modelNames->snake }}()
	{
		return $this->runOrExt(__FUNCTION__, function () {
			$this->actingAs(\{{ $config->namespaces->model }}\User::where('id', 1)->first(), 'api');
			${{ $config->modelNames->camel }} = {{ $config->modelNames->name }}::factory()->create();

			$resp = $this->{{ $config->modelNames->camel }}Repo->delete(${{ $config->modelNames->camel }}->{{ $config->primaryName }});

			$this->assertTrue($resp);
			$this->assertNull({{ $config->modelNames->name }}::find(${{ $config->modelNames->camel }}->{{ $config->primaryName }}), '{{ $config->modelNames->name }} should not exist in DB');
			{{ $config->modelNames->name }}::where('id', ${{ $config->modelNames->camel }}->id)->withTrashed()->forceDelete();
		});
	}
}
