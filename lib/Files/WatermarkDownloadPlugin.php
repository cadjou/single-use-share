<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Files;

use OCA\DAV\Connector\Sabre\Node as DavNode;
use OCP\Files\File;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * Short-circuits Sabre's default GET handling for watermarked files.
 *
 * Sabre\DAV\CorePlugin::httpGet() builds the Content-Length response header
 * from OCA\DAV\Connector\Sabre\Node::getSize(), which returns a FileInfo
 * snapshot hydrated from the oc_filecache DB row - a value fixed at
 * scan/write time, completely disconnected from IStorage::filesize(). Since
 * watermarked content varies in size per request (dynamic fields like
 * IP/timestamp), that announced size is always wrong for a watermarked
 * file, silently truncating every download to the original file's length.
 * Confirmed by tracing a real download against Nextcloud 34.0.3 (the
 * WatermarkStorageWrapper::filesize()/stat() overrides alone don't help:
 * this code path never calls them).
 *
 * Registered at priority 10 (CorePlugin's own 'method:GET' listener uses
 * sabre/event's default priority of 100 - lower runs first), this fully
 * takes over the GET response for a watermarked path: sets the correct
 * headers, writes the already-computed watermarked body, and returns
 * false. That return value both stops CorePlugin from running afterwards
 * *and* is the literal signal Sabre\DAV\Server::invokeMethod() needs to
 * know the request was handled - if every listener returns a truthy value,
 * Sabre throws a 501 Not Implemented.
 *
 * Since watermarked content varies between requests, a Range request could
 * stitch together bytes from two differently-watermarked responses - so
 * this always serves the full body and advertises no range support.
 *
 * Registered by both Listener\BeforeSabrePubliclyLoadedListener (public
 * share links) and Listener\SabrePluginAddListener (authenticated
 * /remote.php/dav/), since Node::getSize() has the same bug on both paths.
 */
class WatermarkDownloadPlugin extends ServerPlugin {
	private const PRIORITY = 10;

	private ?Server $server = null;

	public function initialize(Server $server): void {
		$this->server = $server;
		$server->on('method:GET', [$this, 'httpGet'], self::PRIORITY);
	}

	public function httpGet(RequestInterface $request, ResponseInterface $response): ?bool {
		if ($this->server === null) {
			return null;
		}

		try {
			$node = $this->server->tree->getNodeForPath($request->getPath());
		} catch (\Throwable $e) {
			return null;
		}

		if (!$node instanceof DavNode) {
			return null;
		}

		$fileNode = $node->getNode();
		if (!$fileNode instanceof File) {
			return null;
		}

		$wrapper = WatermarkStorageWrapper::findInstance($fileNode->getStorage());
		if ($wrapper === null) {
			return null;
		}

		$watermarked = $wrapper->getWatermarkedContentForDownload($fileNode->getInternalPath());
		if ($watermarked === false) {
			return null;
		}

		$response->setHeader('Content-Type', $fileNode->getMimeType());
		$response->setHeader('Content-Length', (string)strlen($watermarked));
		$response->setHeader('Accept-Ranges', 'none');
		$response->setStatus(200);
		$response->setBody($watermarked);

		return false;
	}
}
