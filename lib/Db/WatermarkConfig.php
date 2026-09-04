<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getShareId()
 * @method void setShareId(int $shareId)
 * @method bool getEnabled()
 * @method void setEnabled(bool $enabled)
 * @method string getCustomText()
 * @method void setCustomText(string $customText)
 * @method string getDynamicFields()
 * @method void setDynamicFields(string $dynamicFields)
 * @method string getStyle()
 * @method void setStyle(string $style)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */
class WatermarkConfig extends Entity {
	protected $shareId;
	protected $enabled = false;
	protected $customText = '';
	protected $dynamicFields = '[]';
	protected $style = '{}';
	protected $createdAt;

	public function __construct() {
		$this->addType('shareId', 'integer');
		$this->addType('enabled', 'boolean');
		$this->addType('customText', 'string');
		$this->addType('dynamicFields', 'string');
		$this->addType('style', 'string');
		$this->addType('createdAt', 'integer');
	}

	/**
	 * @return string[] enabled dynamic field keys, e.g. ['date_heure', 'ip']
	 */
	public function getDynamicFieldsArray(): array {
		// The DB column can legitimately be NULL (nullable, no SQL-level
		// default) even though the PHP property defaults to '[]' - Entity's
		// setter is a no-op when the new value equals the current one, so
		// setDynamicFields('[]') on a fresh entity never marks the field as
		// updated and it's simply omitted from the INSERT. Hydrating from a
		// DB row bypasses that optimization and sets the real (null) value.
		$decoded = json_decode($this->getDynamicFields() ?? '[]', true);
		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @return array{position?: string, opacity?: int, tiled?: bool}
	 */
	public function getStyleArray(): array {
		$decoded = json_decode($this->getStyle() ?? '{}', true);
		return is_array($decoded) ? $decoded : [];
	}
}
