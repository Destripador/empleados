<?php

declare(strict_types=1);

namespace OCA\Empleados\Tutorial;

/**
 * Allowed tutorial lesson identifiers (versioned).
 *
 * Persisted config keys use the form: tutorial.{lessonId}
 */
final class TutorialCatalog {

	public const LESSONS = [
		'bienvenida.v1',
		'vacaciones.crear.v1',
		'tiempos.reportar.v1',
		'equipos.consultar.v1',
	];

	public static function isValid(string $lessonId): bool {
		return in_array($lessonId, self::LESSONS, true);
	}

	public static function configKey(string $lessonId): string {
		return 'tutorial.' . $lessonId;
	}
}
