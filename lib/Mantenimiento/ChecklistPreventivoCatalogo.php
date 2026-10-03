<?php

declare(strict_types=1);

namespace OCA\Empleados\Mantenimiento;

final class ChecklistPreventivoCatalogo {
	/** Labels are source strings; the next phase will translate them before storing each snapshot. */
	public const ITEMS = [
		['clave' => 'external_cleaning', 'etiqueta' => 'External cleaning', 'orden' => 10],
		['clave' => 'internal_cleaning', 'etiqueta' => 'Internal cleaning', 'orden' => 20],
		['clave' => 'fans_review', 'etiqueta' => 'Fan review', 'orden' => 30],
		['clave' => 'temperature_review', 'etiqueta' => 'Temperature review', 'orden' => 40],
		['clave' => 'storage_review', 'etiqueta' => 'Storage review', 'orden' => 50],
		['clave' => 'memory_review', 'etiqueta' => 'Memory review', 'orden' => 60],
		['clave' => 'system_updates', 'etiqueta' => 'System updates', 'orden' => 70],
		['clave' => 'application_updates', 'etiqueta' => 'Application updates', 'orden' => 80],
		['clave' => 'security_status', 'etiqueta' => 'Security status', 'orden' => 90],
		['clave' => 'peripherals_review', 'etiqueta' => 'Peripheral review', 'orden' => 100],
		['clave' => 'cabling_review', 'etiqueta' => 'Cabling review', 'orden' => 110],
		['clave' => 'functional_test', 'etiqueta' => 'General functional test', 'orden' => 120],
		['clave' => 'backup_validation', 'etiqueta' => 'Backup or file validation', 'orden' => 130],
	];

	private function __construct() {
	}
}
