<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * dynamic_fields/style/custom_text had no SQL-level default, only a
 * PHP-side one on WatermarkConfig. Entity's magic setter is a no-op when
 * the new value equals the property's current (default) value, so it never
 * gets marked "updated" and the column is omitted from the INSERT entirely
 * whenever a share is saved with that field left at its default (e.g. no
 * dynamic field checked, or the text left blank) - the DB then falls back
 * to NULL (nullable, no default) instead of '[]'/'{}'/''` , which crashed
 * json_decode()/DynamicFieldResolver on the next read (the PHP-side
 * getters/call sites now also guard against this directly, but the schema
 * should reflect the intended default regardless). `enabled` was never
 * affected since it already had an explicit SQL default matching its PHP
 * one.
 */
class Version000002Date20260904120000 extends SimpleMigrationStep {
	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('singleuseshare_config')) {
			$table = $schema->getTable('singleuseshare_config');

			if ($table->hasColumn('dynamic_fields')) {
				$table->getColumn('dynamic_fields')->setDefault('[]');
			}
			if ($table->hasColumn('style')) {
				$table->getColumn('style')->setDefault('{}');
			}
			if ($table->hasColumn('custom_text')) {
				$table->getColumn('custom_text')->setDefault('');
			}
		}

		return $schema;
	}
}
