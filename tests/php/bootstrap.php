<?php

declare(strict_types=1);

// Standard Nextcloud app test bootstrap: reuse the server's own test
// bootstrap (autoloads OCP/OC classes) once this app is installed under
// nextcloud/apps/singleuseshare, then load our own composer autoloader.
$serverBootstrap = __DIR__ . '/../../../../tests/bootstrap.php';
if (is_file($serverBootstrap)) {
	require_once $serverBootstrap;
}

require_once __DIR__ . '/../../vendor/autoload.php';

\OC_App::loadApp('singleuseshare');
