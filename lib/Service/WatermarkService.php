<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

use OCA\SingleUseShare\Db\WatermarkConfig;
use Psr\Log\LoggerInterface;

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
		private LoggerInterface $logger,
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
			// custom_text is nullable with no SQL default - a share saved
			// with the text left blank never marks the field "updated"
			// (Entity's setter no-ops when the value matches the class
			// default) and ends up NULL in the DB (see the migration that
			// fixes the schema default; this stays as defence in depth).
			$config->getCustomText() ?? '',
			$config->getDynamicFieldsArray(),
			$context,
		);

		if (trim($text) === '') {
			return $content;
		}

		$style = WatermarkStyle::fromArray($config->getStyleArray());

		try {
			if (in_array($extension, self::PDF_EXTENSIONS, true)) {
				return $this->pdfWatermarker->watermark($content, $text, $style);
			}

			if (in_array($extension, $this->imageWatermarker->getSupportedExtensions(), true)) {
				return $this->imageWatermarker->watermark($content, $text, $style);
			}
		} catch (\Throwable $e) {
			// Some real-world files can't be watermarked - e.g. FPDI's free
			// parser rejects PDFs using compression techniques it doesn't
			// support (this is exactly what Nextcloud's own bundled sample
			// PDF, "Nextcloud Manual.pdf", triggers). Serving a corrupted
			// half-processed file instead of a clear original is far worse
			// than silently skipping the watermark, so fall back to the
			// untouched content and only log the failure server-side.
			$this->logger->warning('SingleUseShare: watermarking failed, serving the original file unwatermarked', [
				'app' => 'singleuseshare',
				'exception' => $e,
				'extension' => $extension,
			]);
		}

		return $content;
	}
}
