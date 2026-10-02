<?php

declare(strict_types=1);

namespace OCA\Empleados\Config;

/**
 * Catálogo oficial de permisos requeridos por la aplicación.
 *
 * Las filas adicionales creadas por administradores siguen siendo válidas;
 * esta clase sólo define el mínimo que una instalación debe contener.
 */
final class PermissionCatalog {
	/** @return list<array{module: string, permission: string, group_id: string, label: string, description: string, restricted: bool, enabled: bool, sort_order: int}> */
	public static function entries(): array {
		return [
			self::entry('compras', 'request', 'compras_solicitantes', 'Compras - Solicitantes', 'Puede crear y dar seguimiento a sus propias solicitudes de compra.', false, 10),
			self::entry('compras', 'approve', 'compras_autorizadores', 'Compras - Autorizadores', 'Puede revisar, aprobar o rechazar solicitudes de compra.', false, 20),
			self::entry('compras', 'admin', 'compras_admin', 'Compras - Administradores', 'Control total del módulo de compras.', true, 30),
			self::entry('compras', 'accounting', 'compras_contabilidad', 'Compras - Contabilidad', 'Puede revisar solicitudes para seguimiento contable.', false, 40),
			self::entry('empleados', 'hr', 'recursos_humanos', 'Recursos Humanos', 'Puede administrar empleados, áreas, puestos, equipos, ahorro y ausencias.', true, 50),
			self::entry('clientes', 'admin', 'clientes_admin', 'Clientes - Administradores', 'Puede administrar clientes, grupos empresariales y actividades.', true, 60),
			self::entry('clientes', 'view', 'clientes_view', 'Clientes - Consulta', 'Puede consultar y ver clientes sin administrar el catálogo.', false, 61),
			self::entry('inventario', 'admin', 'ti_admin', 'TI - Administradores', 'Puede administrar inventario, equipos y solicitudes de soporte.', true, 70),
			self::entry('inventario', 'technician', 'ti_tecnicos', 'TI - Técnicos', 'Puede atender y actualizar los mantenimientos que tenga asignados.', false, 71),
			self::entry('inventario', 'view', 'ti_consulta', 'TI - Consulta', 'Puede consultar inventario, calendario y avance de mantenimientos.', false, 72),
			self::entry('soporte', 'view', 'soporte_view', 'Soporte - Consulta', 'Puede consultar el historial de soporte asociado a los equipos.', false, 75),
			self::entry('reporte_tiempos', 'admin', 'reportes_admin', 'Reportes - Administradores', 'Puede consultar reportes administrativos y seguimiento de cumplimiento.', true, 80),
			self::entry('reporte_tiempos', 'view', 'reportes_view', 'Reportes - Consulta', 'Puede consultar reportes administrativos según el alcance de su equipo.', false, 81),
			self::entry('ahorro', 'admin', 'ahorro_admin', 'Ahorro - Administradores', 'Puede administrar solicitudes y panel del fondo de ahorro.', true, 90),
			self::entry('ausencias', 'admin', 'ausencias_admin', 'Ausencias - Administradores', 'Puede administrar ausencias, vacaciones y calendario laboral.', true, 100),
			self::entry('empleados', 'admin', 'empleados_admin', 'Empleados - Administradores', 'Puede administrar empleados, áreas, puestos y equipos sin requerir acceso total de Recursos Humanos.', true, 110),
		];
	}

	/** @return list<array{id: string, label: string, description: string}> */
	public static function baseGroups(): array {
		return [
			[
				'id' => 'empleados',
				'label' => 'Empleados',
				'description' => 'Grupo base usado por flujos generales y reportes de tiempo de los empleados.',
			],
			[
				'id' => 'recursos_humanos',
				'label' => 'Recursos Humanos',
				'description' => 'Grupo base histórico y grupo oficial del permiso empleados.hr.',
			],
		];
	}

	public static function key(string $module, string $permission, string $groupId): string {
		return $module . '::' . $permission . '::' . $groupId;
	}

	/** @return array{module: string, permission: string, group_id: string, label: string, description: string, restricted: bool, enabled: bool, sort_order: int} */
	private static function entry(
		string $module,
		string $permission,
		string $groupId,
		string $label,
		string $description,
		bool $restricted,
		int $sortOrder,
	): array {
		return [
			'module' => $module,
			'permission' => $permission,
			'group_id' => $groupId,
			'label' => $label,
			'description' => $description,
			'restricted' => $restricted,
			'enabled' => true,
			'sort_order' => $sortOrder,
		];
	}

	private function __construct() {
	}
}
