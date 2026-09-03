<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<WatermarkConfig>
 */
class WatermarkConfigMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'singleuseshare_config', WatermarkConfig::class);
	}

	/**
	 * @throws DoesNotExistException when no config exists for this share
	 */
	public function findByShareId(int $shareId): WatermarkConfig {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('share_id', $qb->createNamedParameter($shareId, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function existsForShareId(int $shareId): bool {
		try {
			$this->findByShareId($shareId);
			return true;
		} catch (DoesNotExistException $e) {
			return false;
		}
	}

	/**
	 * Insert or update the watermark config for a given share.
	 */
	public function upsert(WatermarkConfig $config): WatermarkConfig {
		if ($config->getId() !== null) {
			return $this->update($config);
		}

		try {
			$existing = $this->findByShareId($config->getShareId());
			$config->setId($existing->getId());
			return $this->update($config);
		} catch (DoesNotExistException $e) {
			return $this->insert($config);
		}
	}
}
