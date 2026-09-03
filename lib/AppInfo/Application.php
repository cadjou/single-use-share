<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\AppInfo;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\SingleUseShare\Files\StorageWrapperRegistrar;
use OCA\SingleUseShare\Listener\BeforeSabrePubliclyLoadedListener;
use OCA\SingleUseShare\Listener\LoadAdditionalScriptsListener;
use OCA\SingleUseShare\Listener\SabrePluginAddListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BeforeSabrePubliclyLoadedEvent;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'singleuseshare';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Anonymous public share downloads/previews: core mounts the share
		// outside of the preSetup hook below, so this is the only way to
		// get our wrapper into that mount's storage stack in time. It also
		// registers WatermarkDownloadPlugin, needed because Sabre's GET
		// response size comes from a filecache snapshot that the storage
		// wrapper alone can't reach (see WatermarkDownloadPlugin).
		$context->registerEventListener(BeforeSabrePubliclyLoadedEvent::class, BeforeSabrePubliclyLoadedListener::class);
		// Authenticated /remote.php/dav/ (internal shares): the storage
		// wrapper is already covered by the preSetup hook, but the same
		// Content-Length bug applies here too, so it still needs the
		// download plugin.
		$context->registerEventListener(SabrePluginAddEvent::class, SabrePluginAddListener::class);
		// Without this, nothing injects our built JS into the Files app
		// page, so the "Filigrane" sidebar tab it registers never loads.
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
	}

	public function boot(IBootContext $context): void {
		// Storage wrappers must be registered from within the
		// 'OC_Filesystem'/'preSetup' hook, not directly in boot() - doing it
		// directly risks running after a user's filesystem is already set
		// up, in which case addStorageWrapper() silently skips storages that
		// are already mounted (see OCA\Files_Lock\AppInfo\Application for
		// the same pattern, confirmed against Nextcloud 34.0.3 core). This
		// covers authenticated/internal access and previews triggered from
		// a logged-in user's context; anonymous public access is covered by
		// the BeforeSabrePubliclyLoadedEvent listener registered above.
		Util::connectHook('OC_Filesystem', 'preSetup', $this, 'addStorageWrapper');
	}

	/** @internal only public because OC_Hook requires it to be callable */
	public function addStorageWrapper(): void {
		$this->getContainer()->get(StorageWrapperRegistrar::class)->register();
	}
}
