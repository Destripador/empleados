<?php
declare(strict_types=1);

namespace OCA\Empleados\BackgroundJob;

use OCA\Empleados\Db\festivosMapper;
use OCA\Empleados\Service\FestivosCalculator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class RecalcularFestivosVariablesJob extends TimedJob {

	private festivosMapper $mapper;

	public function __construct(ITimeFactory $time, festivosMapper $mapper) {
		parent::__construct($time);
		$this->setInterval(24 * 60 * 60);
		$this->mapper = $mapper;
	}

	protected function run($argument): void {
		$anioActual = (int)date('Y');

		foreach ($this->mapper->findVariables() as $festivo) {
			if ((int)($festivo['anio_calculado'] ?? 0) === $anioActual) {
				continue;
			}

			$fecha = FestivosCalculator::nthWeekday(
				$anioActual,
				(int)$festivo['regla_mes'],
				(int)$festivo['regla_dia_semana'],
				(int)$festivo['regla_semana']
			);

			$this->mapper->actualizarFechaCalculada(
				(int)$festivo['id_festivo'],
				$fecha->format('m-d'),
				$anioActual
			);
		}
	}
}