<?php

declare(strict_types=1);

namespace OCA\Empleados\Cron;

use OCP\BackgroundJob\Job;
use Carbon\Carbon;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use OCA\Empleados\Db\configuracionesMapper;

# require_once __DIR__ . '/../../vendor/autoload.php';

class ActualizarAniversarios extends Job {
	protected function run($argument): void {
		/** @var IDBConnection $connection */
		$connection = \OC::$server->get(IDBConnection::class);

		/** @var LoggerInterface $logger */
		$logger = \OC::$server->get(LoggerInterface::class);

		/** @var configuracionesMapper $configuracionesMapper */
		$configuracionesMapper = \OC::$server->query(configuracionesMapper::class);

		$configMap = array_column($configuracionesMapper->GetConfig(), 'Data', 'Nombre');
        $acumularVacaciones = strtolower(trim($configMap['acumular_vacaciones'] ?? 'false')) === 'true';


		$hoy = Carbon::now();
		$fechaHoy = $hoy->format('m-d');
		$fechaHoyStr = $hoy->format('Y-m-d');

		$qb = $connection->getQueryBuilder();
		$qb->select(
				'e.Id_empleados',
				'e.Ingreso',
				'a.id_ausencias',
				'a.id_aniversario',
				'a.dias_disponibles',
				'a.timestamp' // Asegúrate de tener este campo en la tabla `ausencias`
			)
			->from('empleados', 'e')
			->join('e', 'ausencias', 'a', 'a.id_empleado = e.Id_empleados')
			->where($qb->expr()->eq(
				$qb->createFunction("DATE_FORMAT(`Ingreso`, '%m-%d')"),
				$qb->createNamedParameter($fechaHoy)
			));

		$rows = $qb->execute()->fetchAll();

		foreach ($rows as $row) {
			if (empty($row['Ingreso'])) {
				continue;
			}

			try {
				$ingreso = new Carbon($row['Ingreso']);
				$anios = $ingreso->diffInYears($hoy);

				// Verifica si ya se ejecutó hoy
				$ultimaActualizacion = isset($row['timestamp']) ? new Carbon($row['timestamp']) : Carbon::create(1970);
				if (
					$anios > (int)$row['id_aniversario'] &&
					$ultimaActualizacion->format('Y-m-d') !== $fechaHoyStr
				) {
					$qbDias = $connection->getQueryBuilder();
					$qbDias->select('dias')
						->from('aniversarios')
						->where($qbDias->expr()->eq('numero_aniversario', $qbDias->createNamedParameter($anios)));
					$diasRow = $qbDias->execute()->fetch();

					if ($diasRow && isset($diasRow['dias'])) {
						$diasAsignados = (float)$diasRow['dias'];
						$nuevoTotalDias = $acumularVacaciones
							? ((float)$row['dias_disponibles']) + $diasAsignados
							: $diasAsignados;

						$update = $connection->getQueryBuilder();
						$update->update('ausencias')
							->set('id_aniversario', $update->createNamedParameter($anios))
							->set('dias_disponibles', $update->createNamedParameter($nuevoTotalDias))
							->set('prima_vacacional', $update->createNamedParameter(0))
							->set('timestamp', $update->createNamedParameter($hoy->format('Y-m-d H:i:s')))
							->where($update->expr()->eq('id_ausencias', $update->createNamedParameter($row['id_ausencias'])))
							->executeStatement();

						$logger->info("🎉 Aniversario actualizado: empleado {$row['Id_empleados']} → {$anios} años, {$nuevoTotalDias} días disponibles.");
					} else {
						$logger->warning("⚠️ Sin días definidos para el aniversario {$anios} (empleado {$row['Id_empleados']}).");
					}
				}
			} catch (\Throwable $e) {
				$logger->error("❌ Error con empleado {$row['Id_empleados']}: " . $e->getMessage());
			}
		}
	}
}
