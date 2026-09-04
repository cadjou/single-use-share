<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

/**
 * Watermarks images using the GD extension, which ships with virtually every
 * PHP install. Text is rendered once onto a small transparent "stamp" (using
 * a TTF font when one can be found on the server, a GD bitmap font
 * otherwise), the stamp is rotated for the diagonal style, then tiled or
 * placed across the source image.
 */
class GdImageWatermarker implements ImageWatermarkerInterface {
	private const SUPPORTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

	/** The stamp (all lines, tiled repeat unit) must fit within this fraction of the image's smaller side. */
	private const MAX_STAMP_RATIO = 0.85;

	/** Common paths for a usable TTF font across the Linux distros Nextcloud typically runs on. */
	private const CANDIDATE_FONT_PATHS = [
		'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
		'/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/liberation/LiberationSans-Bold.ttf',
		'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
	];

	public function getSupportedExtensions(): array {
		return self::SUPPORTED_EXTENSIONS;
	}

	public function watermark(string $imageContent, string $text, WatermarkStyle $style): string {
		$source = @imagecreatefromstring($imageContent);
		if ($source === false) {
			throw new \RuntimeException('Unable to decode image for watermarking');
		}

		$mime = $this->detectMime($imageContent);
		$width = imagesx($source);
		$height = imagesy($source);

		$stamp = $this->buildStamp($text, $width, $height, $style);
		imagealphablending($source, true);
		$this->applyStamp($source, $stamp, $width, $height, $style);

		$result = $this->encode($source, $mime);

		imagedestroy($source);
		imagedestroy($stamp);

		return $result;
	}

	private function findFontPath(): ?string {
		if (!function_exists('imagettftext')) {
			return null;
		}
		foreach (self::CANDIDATE_FONT_PATHS as $path) {
			if (is_readable($path)) {
				return $path;
			}
		}
		return null;
	}

	private function buildStamp(string $text, int $baseWidth, int $baseHeight, WatermarkStyle $style): \GdImage {
		$lines = explode("\n", $text);
		$fontPath = $this->findFontPath();
		$fontSize = max(10, (int)round($baseWidth / 22));
		$lineHeight = $fontPath !== null ? (int)round($fontSize * 1.4) : 18;

		$maxLineWidth = 0;
		foreach ($lines as $line) {
			$lineWidth = $fontPath !== null
				? $this->ttfTextWidth($line, $fontPath, $fontSize)
				: imagefontwidth(5) * strlen($line);
			$maxLineWidth = max($maxLineWidth, $lineWidth);
		}

		$flatWidth = max(1, $maxLineWidth + 20);
		$flatHeight = max(1, $lineHeight * count($lines) + 10);

		$flat = imagecreatetruecolor($flatWidth, $flatHeight);
		imagesavealpha($flat, true);
		$transparent = imagecolorallocatealpha($flat, 0, 0, 0, 127);
		imagefill($flat, 0, 0, $transparent);

		$alpha = (int)round(127 - ($style->getOpacityRatio() * 127));
		$textColor = imagecolorallocatealpha($flat, 255, 0, 0, $alpha);

		$y = 5;
		foreach ($lines as $line) {
			if ($fontPath !== null) {
				imagettftext($flat, $fontSize, 0, 5, $y + $fontSize, $textColor, $fontPath, $line);
			} else {
				imagestring($flat, 5, 5, $y, $line, $textColor);
			}
			$y += $lineHeight;
		}

		if (!$style->isDiagonal()) {
			return $this->constrainToCanvas($flat, $baseWidth, $baseHeight);
		}

		$rotated = imagerotate($flat, 45, $transparent);
		imagesavealpha($rotated, true);
		imagedestroy($flat);

		return $this->constrainToCanvas($rotated, $baseWidth, $baseHeight);
	}

	/**
	 * A multi-line watermark (custom text plus several dynamic fields) can
	 * easily be wider or taller than a small image at the default font
	 * size, overflowing the canvas - single-placement styles then get
	 * clipped instead of visibly centered/banner-placed. Downscale the
	 * whole rendered stamp (works the same way regardless of whether it
	 * was drawn with a TTF font or GD's fixed-size bitmap font, which can't
	 * be shrunk by changing a font-size parameter) to keep it within a safe
	 * fraction of the image.
	 */
	private function constrainToCanvas(\GdImage $stamp, int $canvasWidth, int $canvasHeight): \GdImage {
		$width = imagesx($stamp);
		$height = imagesy($stamp);
		$maxWidth = max(1, (int)round($canvasWidth * self::MAX_STAMP_RATIO));
		$maxHeight = max(1, (int)round($canvasHeight * self::MAX_STAMP_RATIO));

		$scale = min(1.0, $maxWidth / $width, $maxHeight / $height);
		if ($scale >= 1.0) {
			return $stamp;
		}

		$newWidth = max(1, (int)round($width * $scale));
		$newHeight = max(1, (int)round($height * $scale));

		$resized = imagecreatetruecolor($newWidth, $newHeight);
		imagesavealpha($resized, true);
		imagealphablending($resized, false);
		imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
		imagecopyresampled($resized, $stamp, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
		imagedestroy($stamp);

		return $resized;
	}

	private function ttfTextWidth(string $text, string $fontPath, int $fontSize): int {
		$box = imagettfbbox($fontSize, 0, $fontPath, $text);
		return (int)abs($box[2] - $box[0]);
	}

	private function applyStamp(\GdImage $canvas, \GdImage $stamp, int $canvasWidth, int $canvasHeight, WatermarkStyle $style): void {
		$stampWidth = imagesx($stamp);
		$stampHeight = imagesy($stamp);

		// "Repeat" covers the whole image regardless of the chosen
		// position - previously this only tiled for the diagonal position,
		// silently ignoring the repeat setting for center/banner, which
		// looked like a single misplaced stamp instead of the requested
		// tiled coverage.
		if (!$style->isTiled()) {
			[$x, $y] = $this->positionFor($style->getPosition(), $canvasWidth, $canvasHeight, $stampWidth, $stampHeight);
			imagecopy($canvas, $stamp, $x, $y, 0, 0, $stampWidth, $stampHeight);
			return;
		}

		$stepX = max(1, (int)round($stampWidth * 1.3));
		$stepY = max(1, (int)round($stampHeight * 1.3));
		for ($y = -$stampHeight; $y < $canvasHeight + $stampHeight; $y += $stepY) {
			for ($x = -$stampWidth; $x < $canvasWidth + $stampWidth; $x += $stepX) {
				imagecopy($canvas, $stamp, $x, $y, 0, 0, $stampWidth, $stampHeight);
			}
		}
	}

	/** @return array{0: int, 1: int} */
	private function positionFor(string $position, int $canvasWidth, int $canvasHeight, int $stampWidth, int $stampHeight): array {
		[$x, $y] = match ($position) {
			WatermarkStyle::POSITION_BANNER_TOP => [(int)round(($canvasWidth - $stampWidth) / 2), 10],
			WatermarkStyle::POSITION_BANNER_BOTTOM => [(int)round(($canvasWidth - $stampWidth) / 2), $canvasHeight - $stampHeight - 10],
			default => [(int)round(($canvasWidth - $stampWidth) / 2), (int)round(($canvasHeight - $stampHeight) / 2)],
		};

		// Belt-and-suspenders: constrainToCanvas() should already keep the
		// stamp within bounds, but never let it start off-canvas (which
		// looked like a truncated, oddly-placed watermark).
		return [
			max(0, min($x, max(0, $canvasWidth - $stampWidth))),
			max(0, min($y, max(0, $canvasHeight - $stampHeight))),
		];
	}

	private function detectMime(string $content): string {
		$info = @getimagesizefromstring($content);
		return is_array($info) ? ($info['mime'] ?? 'image/png') : 'image/png';
	}

	private function encode(\GdImage $image, string $mime): string {
		ob_start();
		switch ($mime) {
			case 'image/jpeg':
				imagejpeg($image, null, 90);
				break;
			case 'image/gif':
				imagegif($image);
				break;
			case 'image/webp':
				if (function_exists('imagewebp')) {
					imagewebp($image, null, 90);
				} else {
					imagepng($image);
				}
				break;
			case 'image/bmp':
				if (function_exists('imagebmp')) {
					imagebmp($image);
				} else {
					imagepng($image);
				}
				break;
			case 'image/png':
			default:
				imagepng($image);
				break;
		}
		return (string)ob_get_clean();
	}
}
