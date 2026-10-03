<?php

declare(strict_types=1);

namespace OCA\Empleados\Tutorial;

/**
 * Allowed tutorial lesson identifiers (versioned).
 *
 * Legacy config keys use tutorial.{lessonId}; versioned lessons declare their key here.
 */
final class TutorialCatalog {
	public const OFFICE_SIMULATION_INTRO = 'office_simulation_intro';
	public const OFFICE_SIMULATION_INTRO_VERSION = 1;

	public const LESSONS = [
		'bienvenida.v1',
		'vacaciones.crear.v1',
		'tiempos.reportar.v1',
		'equipos.consultar.v1',
	];

	public const VERSIONED_LESSONS = [
		self::OFFICE_SIMULATION_INTRO => [
			'configKey' => 'office_simulation_intro_version',
			'requiredVersion' => self::OFFICE_SIMULATION_INTRO_VERSION,
		],
	];

	public static function isValid(string $lessonId): bool {
		return in_array($lessonId, self::LESSONS, true);
	}

	public static function configKey(string $lessonId): string {
		return 'tutorial.' . $lessonId;
	}

	public static function isVersioned(string $lessonId): bool {
		return isset(self::VERSIONED_LESSONS[$lessonId]);
	}

	public static function versionConfigKey(string $lessonId): string {
		return (string)(self::VERSIONED_LESSONS[$lessonId]['configKey'] ?? '');
	}

	public static function requiredVersion(string $lessonId): int {
		return (int)(self::VERSIONED_LESSONS[$lessonId]['requiredVersion'] ?? 0);
	}
}
