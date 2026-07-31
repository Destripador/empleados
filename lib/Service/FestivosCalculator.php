<?php
declare(strict_types=1);

namespace OCA\Empleados\Service;

class FestivosCalculator {

	/**
	 * @param int $anio
	 * @param int $mes 1-12
	 * @param int $diaSemana ISO-8601: 1=lunes ... 7=domingo
	 * @param int $semana 1,2,3,4 = esa ocurrencia; -1 = última del mes
	 */
	public static function nthWeekday(int $anio, int $mes, int $diaSemana, int $semana): \DateTime {
		if ($semana > 0) {
			$fecha = new \DateTime(sprintf('%04d-%02d-01', $anio, $mes));
			$primerDiaSemana = (int)$fecha->format('N');
			$offset = ($diaSemana - $primerDiaSemana + 7) % 7;
			$dia = 1 + $offset + ($semana - 1) * 7;
			return new \DateTime(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
		}

		$fecha = new \DateTime(sprintf('%04d-%02d-01', $anio, $mes));
		$fecha->modify('last day of this month');
		$ultimoDiaSemana = (int)$fecha->format('N');
		$offset = ($ultimoDiaSemana - $diaSemana + 7) % 7;
		$fecha->modify("-{$offset} days");
		return $fecha;
	}
}