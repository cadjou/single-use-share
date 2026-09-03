<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

/**
 * Visual configuration of a watermark, chosen by the person creating the share.
 */
class WatermarkStyle {
	public const POSITION_CENTER = 'center';
	public const POSITION_DIAGONAL_TILED = 'diagonal_tiled';
	public const POSITION_BANNER_TOP = 'banner_top';
	public const POSITION_BANNER_BOTTOM = 'banner_bottom';

	public const DEFAULT_POSITION = self::POSITION_DIAGONAL_TILED;
	public const DEFAULT_OPACITY = 30;

	private string $position;
	private int $opacity;
	private bool $tiled;

	public function __construct(
		string $position = self::DEFAULT_POSITION,
		int $opacity = self::DEFAULT_OPACITY,
		bool $tiled = true,
	) {
		$this->position = self::isKnownPosition($position) ? $position : self::DEFAULT_POSITION;
		$this->opacity = max(1, min(100, $opacity));
		$this->tiled = $tiled;
	}

	public static function isKnownPosition(string $position): bool {
		return in_array($position, [
			self::POSITION_CENTER,
			self::POSITION_DIAGONAL_TILED,
			self::POSITION_BANNER_TOP,
			self::POSITION_BANNER_BOTTOM,
		], true);
	}

	/**
	 * @param array{position?: string, opacity?: int, tiled?: bool} $data
	 */
	public static function fromArray(array $data): self {
		return new self(
			$data['position'] ?? self::DEFAULT_POSITION,
			(int)($data['opacity'] ?? self::DEFAULT_OPACITY),
			(bool)($data['tiled'] ?? true),
		);
	}

	public function getPosition(): string {
		return $this->position;
	}

	/** Opacity as a percentage, 1-100 */
	public function getOpacity(): int {
		return $this->opacity;
	}

	/** Opacity as a 0.0-1.0 ratio, handy for GD/Imagick alpha APIs */
	public function getOpacityRatio(): float {
		return $this->opacity / 100;
	}

	public function isTiled(): bool {
		return $this->tiled;
	}

	public function isDiagonal(): bool {
		return $this->position === self::POSITION_DIAGONAL_TILED;
	}
}
