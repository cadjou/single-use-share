<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Tests\Unit;

use OCA\SingleUseShare\Service\DynamicFieldResolver;
use PHPUnit\Framework\TestCase;

class DynamicFieldResolverTest extends TestCase {
	private DynamicFieldResolver $resolver;

	protected function setUp(): void {
		parent::setUp();
		$this->resolver = new DynamicFieldResolver();
	}

	public function testCustomTextOnlyWhenNoFieldEnabled(): void {
		$text = $this->resolver->buildWatermarkText('Confidentiel', [], ['ip' => '10.0.0.1']);
		$this->assertSame('Confidentiel', $text);
	}

	public function testCombinesCustomTextAndDynamicFields(): void {
		$text = $this->resolver->buildWatermarkText(
			'Confidentiel',
			[DynamicFieldResolver::FIELD_IP, DynamicFieldResolver::FIELD_NOM_FICHIER],
			['ip' => '10.0.0.1', 'filename' => 'contrat.pdf'],
		);

		$this->assertSame("Confidentiel\n10.0.0.1\ncontrat.pdf", $text);
	}

	public function testDateHeureIsFormatted(): void {
		$timestamp = mktime(14, 32, 0, 9, 3, 2026);
		$text = $this->resolver->buildWatermarkText('', [DynamicFieldResolver::FIELD_DATE_HEURE], ['timestamp' => $timestamp]);

		$this->assertSame('03/09/2026 14:32', $text);
	}

	public function testUnknownFieldKeyIsIgnored(): void {
		$text = $this->resolver->buildWatermarkText('Confidentiel', ['not_a_real_field'], []);
		$this->assertSame('Confidentiel', $text);
	}

	public function testMissingContextValueIsSkippedNotEmptyLine(): void {
		$text = $this->resolver->buildWatermarkText('Confidentiel', [DynamicFieldResolver::FIELD_IP], []);
		$this->assertSame('Confidentiel', $text);
	}

	public function testEmptyEverythingProducesEmptyString(): void {
		$text = $this->resolver->buildWatermarkText('   ', [], []);
		$this->assertSame('', $text);
	}

	public function testAvailableFieldsExposesHumanLabels(): void {
		$fields = DynamicFieldResolver::availableFields();
		$this->assertArrayHasKey(DynamicFieldResolver::FIELD_IP, $fields);
		$this->assertSame('Adresse IP', $fields[DynamicFieldResolver::FIELD_IP]);
	}
}
