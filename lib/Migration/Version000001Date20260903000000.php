<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20260903000000 extends SimpleMigrationStep {
	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('singleuseshare_config')) {
			$table = $schema->createTable('singleuseshare_config');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('share_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('enabled', Types::BOOLEAN, [
				'notnull' => true,
				'default' => false,
			]);
			$table->addColumn('custom_text', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('dynamic_fields', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('style', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['share_id'], 'sus_config_share_id_uniq');
		}

		return $schema;
	}
}
