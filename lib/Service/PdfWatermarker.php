<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Watermarks PDF documents. Tries FPDI + TCPDF first (pure PHP, no system
 * dependency, preserves the original vector content and file size) - but
 * FPDI's free parser rejects some real-world PDFs (notably ones using
 * compression techniques it doesn't support; Nextcloud's own bundled sample
 * PDF triggers this). When that happens and Imagick is available with a PDF
 * delegate (Ghostscript - confirmed present wherever this was tested), fall
 * back to rasterizing each page through Imagick/Ghostscript, stamping it
 * with the same image-watermarking logic used for JPG/PNG shares, and
 * reassembling a new PDF from the watermarked pages. That fallback loses
 * text selectability (the page becomes an image), but guarantees the
 * watermark is actually applied instead of silently shipping the original.
 */
class PdfWatermarker {
	private const SUPPORTED_EXTENSIONS = ['pdf'];

	/** Resolution used when a page has to be rasterized (fallback path only). */
	private const FALLBACK_DPI = 150;

	public function __construct(
		private ImageProcessorFactory $processorFactory,
	) {
	}

	public function getSupportedExtensions(): array {
		return self::SUPPORTED_EXTENSIONS;
	}

	public function watermark(string $pdfContent, string $text, WatermarkStyle $style): string {
		try {
			return $this->watermarkWithFpdi($pdfContent, $text, $style);
		} catch (\Throwable $e) {
			if (!$this->processorFactory->isImagickAvailable()) {
				throw $e;
			}
			return $this->watermarkByRasterizing($pdfContent, $text, $style);
		}
	}

	private function watermarkWithFpdi(string $pdfContent, string $text, WatermarkStyle $style): string {
		$tmpFile = tempnam(sys_get_temp_dir(), 'sus_pdf_');
		if ($tmpFile === false) {
			throw new \RuntimeException('Unable to create a temporary file for PDF watermarking');
		}
		file_put_contents($tmpFile, $pdfContent);

		try {
			$pdf = new Fpdi();
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
			// The diagonal tiling loop below deliberately draws text at
			// coordinates outside the page (negative, or beyond its height)
			// so a rotated stamp still covers the corners. TCPDF's default
			// SetAutoPageBreak(true) treats any such out-of-bounds Text()
			// call as "start a new page", turning a 1-page source PDF into
			// dozens of blank pages. Watermarking never needs page breaks.
			$pdf->SetAutoPageBreak(false, 0);

			$pageCount = $pdf->setSourceFile($tmpFile);

			for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
				$templateId = $pdf->importPage($pageNo);
				$size = $pdf->getTemplateSize($templateId);

				$orientation = $size['width'] > $size['height'] ? 'L' : 'P';
				$pdf->AddPage($orientation, [$size['width'], $size['height']]);
				$pdf->useTemplate($templateId);

				$this->drawWatermark($pdf, $text, $style, (float)$size['width'], (float)$size['height']);
			}

			$output = $pdf->Output('', 'S');
			return is_string($output) ? $output : '';
		} finally {
			unlink($tmpFile);
		}
	}

	private function watermarkByRasterizing(string $pdfContent, string $text, WatermarkStyle $style): string {
		$source = new \Imagick();
		try {
			$source->setResolution(self::FALLBACK_DPI, self::FALLBACK_DPI);
			$source->readImageBlob($pdfContent);

			$imageWatermarker = new ImagickImageWatermarker();
			$result = new \Imagick();

			foreach ($source as $page) {
				$page->setImageFormat('png');
				$watermarkedBlob = $imageWatermarker->watermark($page->getImageBlob(), $text, $style);

				$watermarkedPage = new \Imagick();
				$watermarkedPage->readImageBlob($watermarkedBlob);
				$result->addImage($watermarkedPage);
				$watermarkedPage->clear();
			}

			$result->setImageFormat('pdf');
			$output = $result->getImagesBlob();
			$result->clear();

			return $output;
		} finally {
			$source->clear();
		}
	}

	private function drawWatermark(Fpdi $pdf, string $text, WatermarkStyle $style, float $pageWidth, float $pageHeight): void {
		$pdf->SetTextColor(200, 0, 0);
		$pdf->SetFont('helvetica', 'B', max(10, (int)round($pageWidth / 6)));
		$pdf->SetAlpha($style->getOpacityRatio());

		$lines = explode("\n", $text);
		$blockWidth = max(1.0, $pageWidth / 3);

		if ($style->isDiagonal() && $style->isTiled()) {
			$stepX = $blockWidth * 1.4;
			$stepY = $blockWidth * 0.9;
			for ($y = -$pageHeight; $y < $pageHeight * 2; $y += $stepY) {
				for ($x = -$pageWidth; $x < $pageWidth * 2; $x += $stepX) {
					$this->drawBlock($pdf, $lines, $x, $y, 45);
				}
			}
		} else {
			$angle = 0;
			$y = match ($style->getPosition()) {
				WatermarkStyle::POSITION_BANNER_TOP => 10,
				WatermarkStyle::POSITION_BANNER_BOTTOM => $pageHeight - 20,
				default => $pageHeight / 2 - 10,
			};
			$this->drawBlock($pdf, $lines, ($pageWidth - $blockWidth) / 2, $y, $angle);
		}

		$pdf->SetAlpha(1);
	}

	/** @param string[] $lines */
	private function drawBlock(Fpdi $pdf, array $lines, float $x, float $y, float $angle): void {
		$pdf->StartTransform();
		$pdf->Rotate($angle, $x, $y);
		$lineHeight = 6;
		foreach ($lines as $index => $line) {
			$pdf->Text($x, $y + ($index * $lineHeight), $line);
		}
		$pdf->StopTransform();
	}
}
