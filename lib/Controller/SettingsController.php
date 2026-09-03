<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Controller;

use OCA\SingleUseShare\Db\WatermarkConfig;
use OCA\SingleUseShare\Db\WatermarkConfigMapper;
use OCA\SingleUseShare\Service\DynamicFieldResolver;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * Reads and writes the watermark configuration attached to a share. Exposed
 * to the "Filigrane" Files sidebar tab (src/views/SharingWatermarkTab.vue).
 */
class SettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IManager $shareManager,
		private WatermarkConfigMapper $configMapper,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function get(int $shareId): DataResponse {
		if (!$this->canManageShare($shareId)) {
			return new DataResponse(['message' => 'Not found'], 404);
		}

		try {
			$config = $this->configMapper->findByShareId($shareId);
			return new DataResponse($this->serialize($config));
		} catch (DoesNotExistException $e) {
			return new DataResponse([
				'shareId' => $shareId,
				'enabled' => false,
				'customText' => '',
				'dynamicFields' => [],
				'style' => [],
				'availableDynamicFields' => DynamicFieldResolver::availableFields(),
			]);
		}
	}

	/**
	 * @param string[] $dynamicFields
	 * @param array{position?: string, opacity?: int, tiled?: bool} $style
	 */
	#[NoAdminRequired]
	public function save(int $shareId, bool $enabled, string $customText = '', array $dynamicFields = [], array $style = []): DataResponse {
		if (!$this->canManageShare($shareId)) {
			return new DataResponse(['message' => 'Not found'], 404);
		}

		$dynamicFields = array_values(array_filter($dynamicFields, [DynamicFieldResolver::class, 'isKnownField']));

		try {
			$config = $this->configMapper->findByShareId($shareId);
		} catch (DoesNotExistException $e) {
			$config = new WatermarkConfig();
			$config->setShareId($shareId);
			$config->setCreatedAt(time());
		}

		$config->setEnabled($enabled);
		$config->setCustomText($customText);
		$config->setDynamicFields(json_encode($dynamicFields));
		$config->setStyle(json_encode($style));

		$config = $this->configMapper->upsert($config);

		return new DataResponse($this->serialize($config));
	}

	/**
	 * getShareById() needs a provider-prefixed id ("ocinternal:5", not just
	 * "5") - the frontend only ever knows the bare numeric id, so like
	 * files_sharing's own ShareAPIController::getShareById() we try each
	 * provider prefix in turn. Passing $userId as the recipient also makes
	 * the provider itself enforce that this user has access to the share.
	 */
	private const SHARE_PROVIDER_PREFIXES = [
		'ocinternal',
		'ocMailShare',
		'ocRoomShare',
		'ocCircleShare',
		'ocFederatedSharing',
	];

	/**
	 * The share must belong to (or have been created by) the current user -
	 * anyone else, including the recipient, must not be able to read or
	 * change its watermark configuration.
	 */
	private function canManageShare(int $shareId): bool {
		if ($this->userId === null) {
			return false;
		}

		$share = $this->findShareAsCurrentUser($shareId);
		if ($share === null) {
			return false;
		}

		return $share->getSharedBy() === $this->userId || $share->getShareOwner() === $this->userId;
	}

	private function findShareAsCurrentUser(int $shareId): ?IShare {
		foreach (self::SHARE_PROVIDER_PREFIXES as $prefix) {
			try {
				return $this->shareManager->getShareById($prefix . ':' . $shareId, $this->userId);
			} catch (ShareNotFound $e) {
				continue;
			}
		}
		return null;
	}

	private function serialize(WatermarkConfig $config): array {
		return [
			'shareId' => $config->getShareId(),
			'enabled' => $config->getEnabled(),
			'customText' => $config->getCustomText(),
			'dynamicFields' => $config->getDynamicFieldsArray(),
			'style' => $config->getStyleArray(),
			'availableDynamicFields' => DynamicFieldResolver::availableFields(),
		];
	}
}
