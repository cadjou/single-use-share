<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Files;

use OC\Files\Storage\Wrapper\Wrapper;
use OCA\SingleUseShare\Db\WatermarkConfig;
use OCA\SingleUseShare\Db\WatermarkConfigMapper;
use OCA\SingleUseShare\Service\WatermarkService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\ISharedStorage;
use OCP\IRequest;
use OCP\Share\IShare;

/**
 * Storage wrapper that transparently substitutes a watermarked copy of a
 * file's content whenever it is read through a share that has watermarking
 * enabled. This runs underneath both file downloads and preview generation
 * (both ultimately read through fopen()), so a viewer never sees the file
 * without its watermark - only the copy on disk stays untouched.
 *
 * Locating the IShare behind the current storage uses
 * \OCP\Files\Storage\ISharedStorage::getShare() (since 30.0.0) - confirmed
 * against Nextcloud 34.0.3 source: implemented by
 * OCA\DAV\Storage\PublicShareWrapper for public/link downloads via DAV and
 * by OCA\Files_Sharing\SharedStorage for internal shares. The older
 * OCA\Files_Sharing\ISharedStorage is a deprecated empty shell as of 30.0.0
 * and must not be used.
 */
class WatermarkStorageWrapper extends Wrapper {
	/**
	 * Watermarked content depends on runtime values (visitor IP, timestamp),
	 * so it can't be cached across requests - but within a single request,
	 * Sabre reads the size via filesize()/stat() *before* reading the bytes
	 * via fopen(). Without this, the announced Content-Length is the
	 * original file's size while the actual stream is the (larger)
	 * watermarked one, truncating every download. Memoizing per path for
	 * the lifetime of this instance (one request) means the size check and
	 * the actual read reuse the same computed content instead of
	 * watermarking the file twice.
	 *
	 * @var array<string, string|false> false = not watermarked, passthrough
	 */
	private array $watermarkedContentCache = [];

	public function __construct(
		array $parameters,
		private WatermarkConfigMapper $configMapper,
		private WatermarkService $watermarkService,
		private IRequest $request,
	) {
		parent::__construct($parameters);
	}

	/**
	 * @return resource|false
	 */
	public function fopen(string $path, string $mode) {
		if (!str_contains($mode, 'r')) {
			return parent::fopen($path, $mode);
		}

		$watermarked = $this->getWatermarkedContent($path);
		if ($watermarked === false) {
			return parent::fopen($path, $mode);
		}

		$memoryStream = fopen('php://temp', 'r+b');
		if ($memoryStream === false) {
			return false;
		}
		fwrite($memoryStream, $watermarked);
		rewind($memoryStream);

		return $memoryStream;
	}

	/**
	 * The base Wrapper delegates file_get_contents() straight to the wrapped
	 * storage instead of going through fopen(), which would silently bypass
	 * watermarking for any caller using this method (some preview providers
	 * do).
	 */
	public function file_get_contents(string $path): string|false {
		$watermarked = $this->getWatermarkedContent($path);
		if ($watermarked !== false) {
			return $watermarked;
		}
		return parent::file_get_contents($path);
	}

	/**
	 * Reports the watermarked size instead of the original file's, so the
	 * HTTP Content-Length announced before the body is streamed matches
	 * what fopen()/file_get_contents() will actually return.
	 */
	public function filesize(string $path): int|float|false {
		$watermarked = $this->getWatermarkedContent($path);
		if ($watermarked !== false) {
			return strlen($watermarked);
		}
		return parent::filesize($path);
	}

	public function stat(string $path): array|false {
		$stat = parent::stat($path);
		if ($stat === false) {
			return $stat;
		}

		$watermarked = $this->getWatermarkedContent($path);
		if ($watermarked !== false) {
			$stat['size'] = strlen($watermarked);
		}

		return $stat;
	}

	/**
	 * @return string|false the watermarked bytes, or false if this path
	 *   isn't watermarked (unsupported format, or no enabled config)
	 */
	private function getWatermarkedContent(string $path): string|false {
		if (array_key_exists($path, $this->watermarkedContentCache)) {
			return $this->watermarkedContentCache[$path];
		}

		return $this->watermarkedContentCache[$path] = $this->computeWatermarkedContent($path);
	}

	private function computeWatermarkedContent(string $path): string|false {
		$extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
		if (!$this->watermarkService->isSupportedExtension($extension)) {
			return false;
		}

		$config = $this->resolveConfigForPath();
		if ($config === null || !$config->getEnabled()) {
			return false;
		}

		$stream = parent::fopen($path, 'r');
		if ($stream === false) {
			return false;
		}
		$content = stream_get_contents($stream);
		fclose($stream);
		if ($content === false) {
			return false;
		}

		return $this->watermarkService->applyWatermark($content, $extension, $config, [
			'timestamp' => time(),
			'ip' => $this->request->getRemoteAddress(),
			'filename' => basename($path),
		]);
	}

	private function resolveConfigForPath(): ?WatermarkConfig {
		$share = $this->findShare();
		if ($share === null) {
			return null;
		}

		try {
			return $this->configMapper->findByShareId((int)$share->getId());
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Walks down the storage wrapper chain looking for the sharing layer, so
	 * this works no matter where in the wrapper stack this app was inserted.
	 */
	private function findShare(): ?IShare {
		$storage = $this->getWrapperStorage();

		while (true) {
			if ($storage->instanceOfStorage(ISharedStorage::class)) {
				/** @var ISharedStorage $storage */
				return $storage->getShare();
			}

			if (!$storage instanceof Wrapper) {
				return null;
			}

			$storage = $storage->getWrapperStorage();
		}
	}
}
