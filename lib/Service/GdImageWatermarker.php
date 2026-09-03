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

		$stamp = $this->buildStamp($text, $width, $style);
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

	private function buildStamp(string $text, int $baseWidth, WatermarkStyle $style): \GdImage {
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
			return $flat;
		}

		$rotated = imagerotate($flat, 45, $transparent);
		imagesavealpha($rotated, true);
		imagedestroy($flat);

		return $rotated;
	}

	private function ttfTextWidth(string $text, string $fontPath, int $fontSize): int {
		$box = imagettfbbox($fontSize, 0, $fontPath, $text);
		return (int)abs($box[2] - $box[0]);
	}

	private function applyStamp(\GdImage $canvas, \GdImage $stamp, int $canvasWidth, int $canvasHeight, WatermarkStyle $style): void {
		$stampWidth = imagesx($stamp);
		$stampHeight = imagesy($stamp);

		if (!$style->isTiled() || !$style->isDiagonal()) {
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
		return match ($position) {
			WatermarkStyle::POSITION_BANNER_TOP => [(int)round(($canvasWidth - $stampWidth) / 2), 10],
			WatermarkStyle::POSITION_BANNER_BOTTOM => [(int)round(($canvasWidth - $stampWidth) / 2), $canvasHeight - $stampHeight - 10],
			default => [(int)round(($canvasWidth - $stampWidth) / 2), (int)round(($canvasHeight - $stampHeight) / 2)],
		};
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
