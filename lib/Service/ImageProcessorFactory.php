<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

/**
 * Detects which image processing backend is available on the current server
 * so the app can transparently use the best one (Imagick) and fall back to
 * the one that's always present in PHP (GD) otherwise.
 */
class ImageProcessorFactory {
	public const BACKEND_IMAGICK = 'imagick';
	public const BACKEND_GD = 'gd';

	public function isImagickAvailable(): bool {
		return extension_loaded('imagick') && class_exists(\Imagick::class);
	}

	public function isGdAvailable(): bool {
		return extension_loaded('gd') && function_exists('imagecreatefromstring');
	}

	/**
	 * @return self::BACKEND_* the best backend available on this server
	 */
	public function getBestAvailableBackend(): string {
		if ($this->isImagickAvailable()) {
			return self::BACKEND_IMAGICK;
		}

		return self::BACKEND_GD;
	}
}
