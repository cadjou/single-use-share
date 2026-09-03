<?php

declare(strict_types=1);

namespace OCA\SingleUseShare\Service;

/**
 * Resolves the dynamic field tokens (date/time, IP, filename, ...) that can be
 * toggled independently in a watermark's configuration, and builds the final
 * watermark text by combining them with the user's free-text.
 *
 * Adding a new dynamic field only requires registering a new entry in
 * self::FIELDS - no other part of the app needs to change.
 */
class DynamicFieldResolver {
	public const FIELD_DATE_HEURE = 'date_heure';
	public const FIELD_IP = 'ip';
	public const FIELD_NOM_FICHIER = 'nom_fichier';

	/**
	 * @var array<string, array{label: string, resolve: callable(array): string}>
	 */
	private const FIELDS = [
		self::FIELD_DATE_HEURE => [
			'label' => 'Date et heure',
		],
		self::FIELD_IP => [
			'label' => 'Adresse IP',
		],
		self::FIELD_NOM_FICHIER => [
			'label' => 'Nom du fichier',
		],
	];

	/**
	 * @return array<string, string> map of field key => human label, for the settings UI
	 */
	public static function availableFields(): array {
		return array_map(static fn (array $field): string => $field['label'], self::FIELDS);
	}

	public static function isKnownField(string $fieldKey): bool {
		return isset(self::FIELDS[$fieldKey]);
	}

	private function resolveField(string $fieldKey, array $context): ?string {
		switch ($fieldKey) {
			case self::FIELD_DATE_HEURE:
				$timestamp = $context['timestamp'] ?? time();
				return date('d/m/Y H:i', $timestamp);
			case self::FIELD_IP:
				return $context['ip'] ?? null;
			case self::FIELD_NOM_FICHIER:
				return $context['filename'] ?? null;
			default:
				return null;
		}
	}

	/**
	 * @param string $customText free text entered by the person who created the share
	 * @param string[] $enabledFields dynamic field keys toggled on, e.g. ['date_heure', 'ip']
	 * @param array{timestamp?: int, ip?: string, filename?: string} $context values used to resolve dynamic fields
	 */
	public function buildWatermarkText(string $customText, array $enabledFields, array $context): string {
		$lines = [];

		$customText = trim($customText);
		if ($customText !== '') {
			$lines[] = $customText;
		}

		foreach ($enabledFields as $fieldKey) {
			if (!self::isKnownField($fieldKey)) {
				continue;
			}
			$value = $this->resolveField($fieldKey, $context);
			if ($value !== null && $value !== '') {
				$lines[] = $value;
			}
		}

		return implode("\n", $lines);
	}
}
