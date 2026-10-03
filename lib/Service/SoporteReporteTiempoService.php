<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\actividadesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\reportetiempoMapper;
use OCP\IConfig;

class SoporteReporteTiempoService {
	public const ORIGEN = 'soporte_ti';
	public const ACTIVIDAD_CLAVE = 'soporte_ti';
	public const MAX_DURACION_MINUTOS = 1440;

	public function __construct(
		private reportetiempoMapper $reportesMapper,
		private actividadesMapper $actividadesMapper,
		private empleadosMapper $empleadosMapper,
		private IConfig $config,
	) {
	}

	public function crearDesdeSoporte(array $soporte, array $equipo): int {
		$idSoporte = (int)($soporte['id_soporte'] ?? 0);
		$existente = $this->obtenerPorSoporte($idSoporte);
		if ($existente !== null) {
			return (int)$existente['id_reporte'];
		}

		$data = $this->construirReporte($soporte, $equipo);
		return $this->reportesMapper->createIntegrated($data);
	}

	public function actualizarDesdeSoporte(array $soporte, array $equipo): int {
		$idSoporte = (int)($soporte['id_soporte'] ?? 0);
		$existente = $this->obtenerPorSoporte($idSoporte);
		if ($existente === null) {
			return $this->crearDesdeSoporte($soporte, $equipo);
		}

		$data = $this->construirReporte($soporte, $equipo);
		$this->reportesMapper->updateIntegrated(self::ORIGEN, $idSoporte, $data);
		return (int)$existente['id_reporte'];
	}

	public function eliminarDesdeSoporte(int $idSoporte): void {
		$this->reportesMapper->deleteByOrigin(self::ORIGEN, $idSoporte);
	}

	public function obtenerPorSoporte(int $idSoporte): ?array {
		return $this->reportesMapper->findByOrigin(self::ORIGEN, $idSoporte);
	}

	public function asegurarActividadSoporte(): int {
		return $this->actividadesMapper->ensureSystemActivity(
			self::ACTIVIDAD_CLAVE,
			'Soporte TI',
			'Actividad interna para tiempo generado desde soporte de Inventario TI.'
		);
	}

	public function validarDuracion(mixed $value): int {
		if (is_int($value)) {
			$duration = $value;
		} elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
			$duration = (int)$value;
		} else {
			throw new \InvalidArgumentException('La duración debe ser un número entero de minutos.');
		}

		if ($duration <= 0 || $duration > self::MAX_DURACION_MINUTOS) {
			throw new \InvalidArgumentException('La duración debe estar entre 1 y 1440 minutos.');
		}

		return $duration;
	}

	public function normalizarFecha(?string $value, string $userId): string {
		$timezone = $this->getTimezone($userId);
		if ($value === null || trim($value) === '') {
			return (new \DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
		}

		$text = trim($value);
		$date = false;
		foreach (['!Y-m-d\TH:i', '!Y-m-d H:i:s', '!Y-m-d'] as $format) {
			$candidate = \DateTimeImmutable::createFromFormat($format, $text, $timezone);
			$errors = \DateTimeImmutable::getLastErrors();
			if ($candidate !== false && (!is_array($errors) || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
				$date = $candidate;
				break;
			}
		}
		if ($date === false) throw new \InvalidArgumentException('La fecha del soporte no es válida.');

		return $date->setTimezone($timezone)->format('Y-m-d H:i:s');
	}

	private function construirReporte(array $soporte, array $equipo): array {
		$idSoporte = (int)($soporte['id_soporte'] ?? 0);
		if ($idSoporte <= 0) {
			throw new \InvalidArgumentException('Identificador de soporte inválido.');
		}

		$uid = trim((string)($soporte['usuario_soporte'] ?? ''));
		$employeeRows = $uid !== '' ? $this->empleadosMapper->GetMyEmployeeInfo($uid) : [];
		$idEmpleado = (int)($employeeRows[0]['Id_empleados'] ?? 0);
		if ($idEmpleado <= 0) {
			throw new \RuntimeException('El técnico autenticado no tiene un empleado asociado.');
		}

		$duration = $this->validarDuracion($soporte['duracion_minutos'] ?? null);
		$date = $this->normalizarFecha((string)($soporte['fecha'] ?? ''), $uid);
		$device = trim((string)($equipo['nombre_dispositivo'] ?? $equipo['nombre_sistema'] ?? ('Equipo ' . ($equipo['id_equipo'] ?? ''))));
		$category = trim(explode('·', (string)($soporte['accion'] ?? 'soporte'))[0]);
		$description = sprintf(
			"Soporte TI — %s — %s\n%s\nSoporte #%d",
			$device,
			$category,
			trim((string)($soporte['detalles'] ?? '')),
			$idSoporte
		);

		return [
			'id_empleado' => $idEmpleado,
			'id_actividad' => $this->asegurarActividadSoporte(),
			'descripcion' => $description,
			'tiempo_registrado' => $duration,
			'fecha_registro' => substr($date, 0, 10),
			'origen' => self::ORIGEN,
			'origen_id' => $idSoporte,
		];
	}

	private function getTimezone(string $userId): \DateTimeZone {
		$name = $this->config->getUserValue($userId, 'core', 'timezone', '');
		if ($name === '') {
			$name = $this->config->getSystemValueString('logtimezone', 'UTC');
		}

		try {
			return new \DateTimeZone($name ?: 'UTC');
		} catch (\Throwable) {
			return new \DateTimeZone('UTC');
		}
	}
}
