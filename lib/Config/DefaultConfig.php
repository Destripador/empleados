<?php

declare(strict_types=1);

namespace OCA\Empleados\Config;

final class DefaultConfig {
	public const APP_ID = 'empleados';
	public const TABLE = 'empleados_conf';

	/** @var array<string, string|null> */
	public const TABLE_DEFAULTS = [
		'usuario_almacenamiento' => null,
		'automatic_save_note' => 'false',
		'acumular_vacaciones' => 'false',
		'modulo_ahorro' => 'false',
		'modulo_ausencias' => 'false',
		'ausencias_readonly' => 'false',
		'modulo_clientes' => 'false',
		'modulo_reporte_tiempos' => 'false',
		'modulo_inventario' => 'false',
		'modulo_soporte' => 'false',
		'modulo_compras' => 'false',
	];

	/** @var array<string, string> */
	public const APP_DEFAULTS = [
		'reportes_recordatorios_enabled' => 'true',
		'reportes_recordatorios_grupo' => 'empleados',
		'reportes_recordatorios_hora' => '17',
		'reportes_recordatorios_zona_horaria' => 'America/Mexico_City',
		'reportes_recordatorios_email' => 'true',
		'reportes_horas_minimas' => '0',
		'reportes_horas_esperadas_jornada' => '8',
		'reportes_admin_reports_group' => 'recursos_humanos',
	];

	private function __construct() {
	}
}
