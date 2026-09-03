<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

use OCA\SingleUseShare\Db\WatermarkConfig;

/**
 * Entry point used by the download/preview listeners: decides whether a file
 * can be watermarked and, if so, produces the watermarked binary content.
 * The original file on disk is never touched.
 */
class WatermarkService {
	private const PDF_EXTENSIONS = ['pdf'];

	public function __construct(
		private ImageWatermarker $imageWatermarker,
		private PdfWatermarker $pdfWatermarker,
		private DynamicFieldResolver $dynamicFieldResolver,
	) {
	}

	public function isSupportedExtension(string $extension): bool {
		$extension = strtolower(ltrim($extension, '.'));
		return in_array($extension, self::PDF_EXTENSIONS, true)
			|| in_array($extension, $this->imageWatermarker->getSupportedExtensions(), true);
	}

	/**
	 * @param array{timestamp?: int, ip?: string, filename?: string} $context values used to resolve dynamic fields
	 */
	public function applyWatermark(string $content, string $extension, WatermarkConfig $config, array $context): string {
		$extension = strtolower(ltrim($extension, '.'));

		$text = $this->dynamicFieldResolver->buildWatermarkText(
			$config->getCustomText(),
			$config->getDynamicFieldsArray(),
			$context,
		);

		if (trim($text) === '') {
			return $content;
		}

		$style = WatermarkStyle::fromArray($config->getStyleArray());

		if (in_array($extension, self::PDF_EXTENSIONS, true)) {
			return $this->pdfWatermarker->watermark($content, $text, $style);
		}

		if (in_array($extension, $this->imageWatermarker->getSupportedExtensions(), true)) {
			return $this->imageWatermarker->watermark($content, $text, $style);
		}

		return $content;
	}
}
