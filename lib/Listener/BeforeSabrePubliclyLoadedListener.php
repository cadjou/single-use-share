<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Listener;

use OCA\SingleUseShare\Files\StorageWrapperRegistrar;
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

		$this->registrar->register();
	}
}
