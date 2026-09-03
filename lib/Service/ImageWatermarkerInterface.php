<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

interface ImageWatermarkerInterface {
	/**
	 * @param string $imageContent raw binary content of the source image
	 * @return string raw binary content of the watermarked image, same format as the input
	 * @throws \RuntimeException when the image content can't be decoded
	 */
	public function watermark(string $imageContent, string $text, WatermarkStyle $style): string;

	/**
	 * @return string[] lowercase file extensions this backend can handle
	 */
	public function getSupportedExtensions(): array;
}
