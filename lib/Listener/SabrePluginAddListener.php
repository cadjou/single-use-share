<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Listener;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\SingleUseShare\Files\WatermarkDownloadPlugin;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Authenticated WebDAV (/remote.php/dav/...) goes through the normal
 * OC_Filesystem/preSetup hook, so the storage wrapper is already registered
 * by the time this fires (see Application::addStorageWrapper()) - this only
 * needs to add the Content-Length-fixing download plugin. Same bug, same
 * fix as the public share path: OCA\DAV\Connector\Sabre\Node::getSize() is
 * shared code between both entry points.
 *
 * @template-implements IEventListener<SabrePluginAddEvent>
 */
class SabrePluginAddListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof SabrePluginAddEvent) {
			return;
		}

		$server = $event->getServer();
		if ($server !== null) {
			$server->addPlugin(new WatermarkDownloadPlugin());
		}
	}
}
