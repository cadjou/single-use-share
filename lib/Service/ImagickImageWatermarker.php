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

	/** Longest watermark line must fit within this fraction of the image width. */
	private const MAX_TEXT_WIDTH_RATIO = 0.85;
	private const MIN_FONT_SIZE = 8;

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
		$draw->setTextAlignment(\Imagick::ALIGN_CENTER);

		$fontSize = max(14, (int)round($width / 22));
		$draw->setFontSize($fontSize);
		$metrics = $this->fitFontSize($image, $draw, $text, $width, $fontSize);

		if ($style->isTiled()) {
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

	/**
	 * A multi-line watermark (custom text plus several dynamic fields) can
	 * easily be wider than a small/narrow image at the default font size,
	 * overflowing the canvas - single-placement styles then get clipped
	 * instead of visibly centered/banner-placed. Shrink the font once to
	 * keep the longest line within a safe fraction of the image width.
	 *
	 * @return array the (possibly re-measured, at the reduced font size) font metrics
	 */
	private function fitFontSize(\Imagick $image, \ImagickDraw $draw, string $text, int $width, int $fontSize): array {
		$metrics = $image->queryFontMetrics($draw, $text, true);
		$maxWidth = $width * self::MAX_TEXT_WIDTH_RATIO;

		if ($metrics['textWidth'] <= $maxWidth || $metrics['textWidth'] <= 0) {
			return $metrics;
		}

		$scale = $maxWidth / $metrics['textWidth'];
		$fittedFontSize = max(self::MIN_FONT_SIZE, (int)floor($fontSize * $scale));
		$draw->setFontSize($fittedFontSize);

		return $image->queryFontMetrics($draw, $text, true);
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
