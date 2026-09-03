<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Tests\Unit;

use OCA\SingleUseShare\Db\WatermarkConfig;
use OCA\SingleUseShare\Service\DynamicFieldResolver;
use OCA\SingleUseShare\Service\ImageWatermarker;
use OCA\SingleUseShare\Service\PdfWatermarker;
use OCA\SingleUseShare\Service\WatermarkService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WatermarkServiceTest extends TestCase {
	private ImageWatermarker&MockObject $imageWatermarker;
	private PdfWatermarker&MockObject $pdfWatermarker;
	private WatermarkService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->imageWatermarker = $this->createMock(ImageWatermarker::class);
		$this->imageWatermarker->method('getSupportedExtensions')->willReturn(['jpg', 'jpeg', 'png']);
		$this->pdfWatermarker = $this->createMock(PdfWatermarker::class);

		$this->service = new WatermarkService(
			$this->imageWatermarker,
			$this->pdfWatermarker,
			new DynamicFieldResolver(),
		);
	}

	private function configWithText(string $text): WatermarkConfig {
		$config = new WatermarkConfig();
		$config->setShareId(1);
		$config->setEnabled(true);
		$config->setCustomText($text);
		$config->setDynamicFields('[]');
		$config->setStyle('{}');
		return $config;
	}

	public function testSupportsPdfAndDelegatedImageExtensions(): void {
		$this->assertTrue($this->service->isSupportedExtension('pdf'));
		$this->assertTrue($this->service->isSupportedExtension('PNG'));
		$this->assertFalse($this->service->isSupportedExtension('docx'));
	}

	public function testPdfContentIsRoutedToPdfWatermarker(): void {
		$this->pdfWatermarker->expects($this->once())
			->method('watermark')
			->willReturn('watermarked-pdf-bytes');
		$this->imageWatermarker->expects($this->never())->method('watermark');

		$result = $this->service->applyWatermark('original-bytes', 'pdf', $this->configWithText('Confidentiel'), []);

		$this->assertSame('watermarked-pdf-bytes', $result);
	}

	public function testImageContentIsRoutedToImageWatermarker(): void {
		$this->imageWatermarker->expects($this->once())
			->method('watermark')
			->willReturn('watermarked-image-bytes');
		$this->pdfWatermarker->expects($this->never())->method('watermark');

		$result = $this->service->applyWatermark('original-bytes', 'png', $this->configWithText('Confidentiel'), []);

		$this->assertSame('watermarked-image-bytes', $result);
	}

	public function testEmptyResolvedTextReturnsContentUnchanged(): void {
		$this->pdfWatermarker->expects($this->never())->method('watermark');

		$result = $this->service->applyWatermark('original-bytes', 'pdf', $this->configWithText(''), []);

		$this->assertSame('original-bytes', $result);
	}

	public function testUnsupportedExtensionReturnsContentUnchanged(): void {
		$this->pdfWatermarker->expects($this->never())->method('watermark');
		$this->imageWatermarker->expects($this->never())->method('watermark');

		$result = $this->service->applyWatermark('original-bytes', 'docx', $this->configWithText('Confidentiel'), []);

		$this->assertSame('original-bytes', $result);
	}
}
