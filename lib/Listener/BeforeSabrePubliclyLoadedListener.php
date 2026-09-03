<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Listener;

use OCA\SingleUseShare\Files\StorageWrapperRegistrar;
use OCA\SingleUseShare\Files\WatermarkDownloadPlugin;
use OCP\BeforeSabrePubliclyLoadedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<BeforeSabrePubliclyLoadedEvent>
 */
class BeforeSabrePubliclyLoadedListener implements IEventListener {
	public function __construct(
		private StorageWrapperRegistrar $registrar,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeSabrePubliclyLoadedEvent) {
			return;
		}

		// Anonymous public access bypasses the OC_Filesystem/preSetup hook
		// entirely (see StorageWrapperRegistrar's own doc comment), so both
		// the storage wrapper and the Content-Length-fixing download
		// plugin need to be registered here.
		$this->registrar->register();

		$server = $event->getServer();
		if ($server !== null) {
			$server->addPlugin(new WatermarkDownloadPlugin());
		}
	}
}
