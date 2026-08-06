<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\actividades;
use OCA\Empleados\Db\reportetiempo;
use OCA\Empleados\Exception\ReporteTiempoRuleException;
use OCP\AppFramework\Http;

final class ReporteTiempoRules {
	public const RESERVED_ABSENCE_ID = 99999;

	public static function normalizeExistingType(array $report): string {
		$type = trim((string)($report['tipo_trabajo'] ?? ''));
		if (in_array($type, reportetiempo::TIPOS_TRABAJO_VALIDOS, true)) return $type;
		if ((int)($report['id_cliente'] ?? 0) === self::RESERVED_ABSENCE_ID || (int)($report['id_actividad'] ?? 0) === self::RESERVED_ABSENCE_ID) {
			return reportetiempo::TIPO_AUSENCIA;
		}
		return ($report['id_cliente'] ?? null) === null || ($report['origen'] ?? null) === 'soporte_ti'
			? reportetiempo::TIPO_INTERNO
			: reportetiempo::TIPO_CLIENTE;
	}

	public static function validateManual(
		string $workType,
		mixed $clientId,
		array $activity,
		?int $employeeDepartmentId,
	): array {
		$workType = strtolower(trim($workType));
		if (!in_array($workType, [reportetiempo::TIPO_CLIENTE, reportetiempo::TIPO_INTERNO], true)) {
			throw new ReporteTiempoRuleException('El tipo de trabajo no es válido.', Http::STATUS_BAD_REQUEST);
		}
		if ((int)($activity['id_actividad'] ?? 0) === self::RESERVED_ABSENCE_ID) {
			throw new ReporteTiempoRuleException('La actividad reservada para ausencias no puede utilizarse manualmente.', Http::STATUS_CONFLICT);
		}
		if (($activity['clave_sistema'] ?? null) !== null) {
			throw new ReporteTiempoRuleException('La actividad del sistema no puede seleccionarse manualmente.', Http::STATUS_CONFLICT);
		}

		$activityType = (string)($activity['tipo_actividad'] ?? actividades::TIPO_CLIENTE);
		if ($workType === reportetiempo::TIPO_CLIENTE) {
			if (!is_numeric($clientId) || (int)$clientId <= 0) {
				throw new ReporteTiempoRuleException('Selecciona un cliente válido.', Http::STATUS_BAD_REQUEST);
			}
			if ((int)$clientId === self::RESERVED_ABSENCE_ID) {
				throw new ReporteTiempoRuleException('El cliente reservado para ausencias no puede utilizarse en reportes normales.', Http::STATUS_CONFLICT);
			}
			if ($activityType !== actividades::TIPO_CLIENTE) {
				throw new ReporteTiempoRuleException('La actividad seleccionada no corresponde al tipo de trabajo.', Http::STATUS_CONFLICT);
			}
			return [(int)$clientId, reportetiempo::ORIGEN_MANUAL];
		}

		if ($clientId !== null && $clientId !== '') {
			throw new ReporteTiempoRuleException('El trabajo interno no puede asociarse a un cliente.', Http::STATUS_CONFLICT);
		}
		if ($activityType !== actividades::TIPO_INTERNO || (int)($activity['cargable'] ?? 0) !== 0) {
			throw new ReporteTiempoRuleException('La actividad seleccionada no corresponde al tipo de trabajo.', Http::STATUS_CONFLICT);
		}
		$scope = (string)($activity['alcance'] ?? actividades::ALCANCE_GLOBAL);
		if ($scope === actividades::ALCANCE_AREAS) {
			$areaIds = array_map('intval', $activity['area_ids'] ?? []);
			if ($employeeDepartmentId === null || !in_array($employeeDepartmentId, $areaIds, true)) {
				throw new ReporteTiempoRuleException('Esta actividad no está disponible para tu área.', Http::STATUS_FORBIDDEN);
			}
		}
		return [null, reportetiempo::ORIGEN_MANUAL_INTERNO];
	}

	public static function isAutomatic(array $report): bool {
		$origin = trim((string)($report['origen'] ?? ''));
		return $origin !== '' && !in_array($origin, [reportetiempo::ORIGEN_MANUAL, reportetiempo::ORIGEN_MANUAL_INTERNO], true);
	}
}
