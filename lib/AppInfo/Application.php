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

class Application extends App implements IBootstrap {
	public const APP_ID = 'singleuseshare';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		Filesystem::addStorageWrapper(
			self::APP_ID,
			function (string $mountPoint, IStorage $storage) use ($context) {
				$server = $context->getServerContainer();
				return new WatermarkStorageWrapper(
					['storage' => $storage],
					$server->get(WatermarkConfigMapper::class),
					$server->get(WatermarkService::class),
					$server->get(IRequest::class),
				);
			},
			-10,
		);
	}
}
