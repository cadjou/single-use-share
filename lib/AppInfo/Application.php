<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\AppInfo;

use OC\Files\Filesystem;
use OCA\SingleUseShare\Db\WatermarkConfigMapper;
use OCA\SingleUseShare\Files\WatermarkStorageWrapper;
use OCA\SingleUseShare\Service\WatermarkService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Storage\IStorage;
use OCP\IRequest;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'singleuseshare';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		// Storage wrappers must be registered from within the
		// 'OC_Filesystem'/'preSetup' hook, not directly in boot() - doing it
		// directly risks running after a user's filesystem is already set
		// up, in which case addStorageWrapper() silently skips storages that
		// are already mounted (see OCA\Files_Lock\AppInfo\Application for
		// the same pattern, confirmed against Nextcloud 34.0.3 core).
		Util::connectHook('OC_Filesystem', 'preSetup', $this, 'addStorageWrapper');
	}

	/** @internal only public because OC_Hook requires it to be callable */
	public function addStorageWrapper(): void {
		$container = $this->getContainer();

		Filesystem::addStorageWrapper(
			self::APP_ID,
			function (string $mountPoint, IStorage $storage) use ($container) {
				return new WatermarkStorageWrapper(
					['storage' => $storage],
					$container->get(WatermarkConfigMapper::class),
					$container->get(WatermarkService::class),
					$container->get(IRequest::class),
				);
			},
			-10,
		);
	}
}
