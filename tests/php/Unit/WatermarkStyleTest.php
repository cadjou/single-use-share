<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Tests\Unit;

use OCA\SingleUseShare\Service\WatermarkStyle;
use PHPUnit\Framework\TestCase;

class WatermarkStyleTest extends TestCase {
	public function testDefaultsToDiagonalTiledWhenUnspecified(): void {
		$style = WatermarkStyle::fromArray([]);
		$this->assertSame(WatermarkStyle::POSITION_DIAGONAL_TILED, $style->getPosition());
		$this->assertTrue($style->isDiagonal());
		$this->assertTrue($style->isTiled());
		$this->assertSame(30, $style->getOpacity());
	}

	public function testUnknownPositionFallsBackToDefault(): void {
		$style = WatermarkStyle::fromArray(['position' => 'not_a_real_position']);
		$this->assertSame(WatermarkStyle::DEFAULT_POSITION, $style->getPosition());
	}

	public function testOpacityIsClampedBetween1And100(): void {
		$this->assertSame(1, WatermarkStyle::fromArray(['opacity' => -5])->getOpacity());
		$this->assertSame(100, WatermarkStyle::fromArray(['opacity' => 500])->getOpacity());
	}

	public function testOpacityRatioMatchesPercentage(): void {
		$style = WatermarkStyle::fromArray(['opacity' => 50]);
		$this->assertSame(0.5, $style->getOpacityRatio());
	}

	public function testBannerPositionIsNotDiagonal(): void {
		$style = WatermarkStyle::fromArray(['position' => WatermarkStyle::POSITION_BANNER_TOP]);
		$this->assertFalse($style->isDiagonal());
	}
}
