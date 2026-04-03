<?php

namespace Biollante\Testing;

trait ApiTestTrait
{
	private $response;

	public function assertApiResponse(array $actualData)
	{
		$this->assertApiSuccess();

		$response = json_decode($this->response->getContent(), true);
		$responseData = $response['data'];

		$this->assertNotEmpty($responseData['id']);

		// Compare only the keys provided in the actualData
		$this->assertModelData($actualData, $responseData);
	}

	public function assertApiSuccess()
	{
		$this->response->assertStatus(200);
		$this->response->assertJson(['success' => true]);
	}

	public function assertModelData(array $actualData, array $expectedData)
	{
		foreach (array_keys($actualData) as $key) {
			if (!array_key_exists($key, $expectedData)) {
				// Skip keys that aren't present in the response
				continue;
			}
	
			if (in_array($key, ['created_at', 'updated_at', 'deleted_at'])) {
				if(in_array($key, ['created_at', 'updated_at'])){
					$this->assertNotEmpty($expectedData[$key], "Expected timestamp field '{$key}' to have a value.");
					$this->assertMatchesRegularExpression(
						'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
						$expectedData[$key],
						"Expected timestamp field '{$key}' to match datetime format."
					);
				}else{
					$this->assertEmpty($expectedData[$key], "Expected timestamp field '{$key}' to be empty.");
				}
				continue;
			}
	
			$this->assertEquals($actualData[$key], $expectedData[$key], "Mismatch for field '{$key}'");
		}
	}
}
