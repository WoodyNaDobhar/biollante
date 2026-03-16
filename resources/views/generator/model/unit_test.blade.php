@php
	echo '<?php'.PHP_EOL;
@endphp

namespace {{ $config->namespaces->unitTests }};

use {{ $config->namespaces->model }}\{{ $config->modelNames->name }};
use Tests\TestCase;

class {{ $config->modelNames->name }}UnitTest extends TestCase
{
	/**
	 * Placeholder test for models with no extensions.
	 * Remove this test and add meaningful ones when extensions are implemented.
	 */
	public function test_placeholder_for_no_extensions(): void
	{
		$extensionFile = dirname(__DIR__, 2) . '/app/Models/Extensions/{{ $config->modelNames->name }}Extension.php';

		if (file_exists($extensionFile)) {
			$extensionContents = file_get_contents($extensionFile);

			// Check if the file contains meaningful code (e.g., methods or properties)
			$hasContent = preg_match(
				'/(function\s+\w+|public\s+\$|protected\s+\$|private\s+\$|public\s+static\s+\$|protected\s+static\s+\$|private\s+static\s+\$)/',
				$extensionContents
			);

			$this->assertFalse($hasContent !== 0, 'Extensions are implemented for this model. Replace this placeholder with meaningful tests.');
		} else {
			$this->assertTrue(true, 'No extension file found for this model.');
		}
	}

	// Add tests for extensions here as they are added.
	// For example:
	// public function test_extension_method_example(): void
	// {
	//     $model = new {{ $config->modelNames->name }}();
	//     // Your test logic here
	// }
}
