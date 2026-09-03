<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\SingleUseShare\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * Without this, the app stays fully inert on the frontend: nothing tells
 * Nextcloud to inject our built js/singleuseshare-main.js into the Files
 * app page, so the "Filigrane" sidebar tab (registered by that script,
 * getSidebar().registerTab()) never gets a chance to run.
 *
 * @template-implements IEventListener<LoadAdditionalScriptsEvent>
 */
class LoadAdditionalScriptsListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		Util::addScript(Application::APP_ID, 'singleuseshare-main');
	}
}
