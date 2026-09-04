<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

/**
 * Watermarks images using Imagick, when the extension is available on the
 * server. Produces better-quality anti-aliased, rotated text than the GD
 * fallback and supports a wider range of formats.
 */
class ImagickImageWatermarker implements ImageWatermarkerInterface {
	private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif'];

	public function getSupportedExtensions(): array {
		return self::SUPPORTED_EXTENSIONS;
	}

	public function watermark(string $imageContent, string $text, WatermarkStyle $style): string {
		$image = new \Imagick();
		try {
			$image->readImageBlob($imageContent);
		} catch (\ImagickException $e) {
			throw new \RuntimeException('Unable to decode image for watermarking', 0, $e);
		}

		$format = $image->getImageFormat();
		$width = $image->getImageWidth();
		$height = $image->getImageHeight();

		$draw = new \ImagickDraw();
		$draw->setFillColor(new \ImagickPixel('red'));
		$draw->setFillAlpha($style->getOpacityRatio());
		$draw->setFontSize(max(14, (int)round($width / 22)));
		$draw->setTextAlignment(\Imagick::ALIGN_CENTER);

		if ($style->isTiled()) {
			$metrics = $image->queryFontMetrics($draw, $text, true);
			$angle = $style->isDiagonal() ? -45 : 0;
			$this->applyTiled($image, $draw, $text, $width, $height, (int)ceil($metrics['textWidth']), (int)ceil($metrics['textHeight']), $angle);
		} else {
			$this->applySingle($image, $draw, $text, $style);
		}

		$image->setImageFormat($format);
		$result = $image->getImageBlob();
		$image->clear();
		$image->destroy();

		return $result;
	}

	private function applySingle(\Imagick $image, \ImagickDraw $draw, string $text, WatermarkStyle $style): void {
		$draw->setGravity(match ($style->getPosition()) {
			WatermarkStyle::POSITION_BANNER_TOP => \Imagick::GRAVITY_NORTH,
			WatermarkStyle::POSITION_BANNER_BOTTOM => \Imagick::GRAVITY_SOUTH,
			default => \Imagick::GRAVITY_CENTER,
		});
		$image->annotateImage($draw, 0, 10, 0, $text);
	}

	/**
	 * "Repeat" always covers the whole image, whatever position was chosen
	 * - previously this only tiled for the diagonal position, silently
	 * ignoring the repeat setting for center/banner (a single stamp placed
	 * once looked like a misplaced or missing watermark depending on the
	 * combination of options picked).
	 */
	private function applyTiled(\Imagick $image, \ImagickDraw $draw, string $text, int $width, int $height, int $stampWidth, int $stampHeight, float $angle): void {
		$stepX = max(1, (int)round($stampWidth * 1.6));
		$stepY = max(1, (int)round($stampHeight * 3.5));
		$draw->setGravity(\Imagick::GRAVITY_NORTHWEST);

		for ($y = -$stampHeight; $y < $height + $stampHeight; $y += $stepY) {
			for ($x = -$stampWidth; $x < $width + $stampWidth; $x += $stepX) {
				$image->annotateImage($draw, $x, $y, $angle, $text);
			}
		}
	}
}
