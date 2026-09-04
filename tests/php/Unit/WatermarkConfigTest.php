<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Tests\Unit;

use OCA\SingleUseShare\Db\WatermarkConfig;
use PHPUnit\Framework\TestCase;

class WatermarkConfigTest extends TestCase {
	/**
	 * dynamic_fields/style are nullable DB columns: a row hydrated straight
	 * from the database (bypassing Entity's setter, which is where the '[]'
	 * / '{}' PHP defaults normally live) can carry a real NULL, exactly as
	 * happened in production for a share saved with no dynamic field
	 * checked. This must not crash json_decode().
	 */
	public function testGetDynamicFieldsArrayHandlesNullColumn(): void {
		$config = new WatermarkConfig();
		$this->setRawProperty($config, 'dynamicFields', null);

		$this->assertSame([], $config->getDynamicFieldsArray());
	}

	public function testGetStyleArrayHandlesNullColumn(): void {
		$config = new WatermarkConfig();
		$this->setRawProperty($config, 'style', null);

		$this->assertSame([], $config->getStyleArray());
	}

	private function setRawProperty(WatermarkConfig $config, string $property, mixed $value): void {
		$reflection = new \ReflectionProperty($config, $property);
		$reflection->setAccessible(true);
		$reflection->setValue($config, $value);
	}
}
