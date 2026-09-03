<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Files;

use OC\Files\Filesystem;
use OCA\SingleUseShare\AppInfo\Application;
use OCA\SingleUseShare\Db\WatermarkConfigMapper;
use OCA\SingleUseShare\Service\WatermarkService;
use OCP\Files\Storage\IStorage;
use OCP\IRequest;

/**
 * Registers the watermarking storage wrapper. This needs to run from two
 * different entry points, because Nextcloud core itself doesn't set up an
 * anonymous public share's mount through the usual OC_Filesystem/preSetup
 * hook - confirmed against Nextcloud 34.0.3 source
 * (apps/dav/appinfo/v2/publicremote.php), which carries the exact same
 * "FIXME: should not add storage wrappers outside of preSetup" as the
 * anti-pattern this app used to have in its own bootstrap:
 *
 * - the preSetup hook (see Application::addStorageWrapper()), for
 *   authenticated/internal access and previews triggered from a logged-in
 *   user's own filesystem setup
 * - OCP\BeforeSabrePubliclyLoadedEvent (see
 *   Listener\BeforeSabrePubliclyLoadedListener), for anonymous public link
 *   downloads/previews, whose mount core sets up outside of preSetup
 */
class StorageWrapperRegistrar {
	public function __construct(
		private WatermarkConfigMapper $configMapper,
		private WatermarkService $watermarkService,
		private IRequest $request,
	) {
	}

	public function register(): void {
		Filesystem::addStorageWrapper(
			Application::APP_ID,
			function (string $mountPoint, IStorage $storage) {
				return new WatermarkStorageWrapper(
					['storage' => $storage],
					$this->configMapper,
					$this->watermarkService,
					$this->request,
				);
			},
			-10,
		);
	}
}
