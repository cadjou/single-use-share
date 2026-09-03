<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

/**
 * Picks the best image watermarking backend available on the server
 * (Imagick if present, GD otherwise) and delegates to it.
 */
class ImageWatermarker implements ImageWatermarkerInterface {
	private ImageWatermarkerInterface $backend;

	public function __construct(ImageProcessorFactory $processorFactory) {
		$this->backend = $processorFactory->isImagickAvailable()
			? new ImagickImageWatermarker()
			: new GdImageWatermarker();
	}

	public function watermark(string $imageContent, string $text, WatermarkStyle $style): string {
		return $this->backend->watermark($imageContent, $text, $style);
	}

	public function getSupportedExtensions(): array {
		return $this->backend->getSupportedExtensions();
	}
}
