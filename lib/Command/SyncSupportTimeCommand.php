<?php

declare(strict_types=1);

namespace OCA\Empleados\Command;

use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\reportetiempoMapper;
use OCA\Empleados\Db\SoporteHistorialMapper;
use OCA\Empleados\Service\SoporteReporteTiempoService;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncSupportTimeCommand extends Command {
	protected static $defaultName = 'empleados:sync-support-time';

	public function __construct(
		private SoporteHistorialMapper $soportes,
		private InventarioComputoMapper $equipos,
		private reportetiempoMapper $reportes,
		private SoporteReporteTiempoService $integration,
		private IDBConnection $db,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setDescription('Diagnostica y repara la sincronización entre soporte TI y reportes de tiempo.')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Solo muestra inconsistencias.')
			->addOption('repair', null, InputOption::VALUE_NONE, 'Crea reportes faltantes cuando existe duración.')
			->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Máximo de soportes por ejecución.', '100');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$limit = max(1, min(1000, (int)$input->getOption('limit')));
		$repair = (bool)$input->getOption('repair') && !(bool)$input->getOption('dry-run');
		$pending = $this->soportes->findWithDurationWithoutReport($limit);
		$repaired = 0;
		$errors = 0;

		foreach ($pending as $support) {
			if (!$repair) continue;
			try {
				$this->db->beginTransaction();
				$device = $this->equipos->findById((int)$support['id_equipo']);
				if ($device === null) throw new \RuntimeException('Equipo inexistente.');
				$this->integration->crearDesdeSoporte($support, $device);
				$this->db->commit();
				$repaired++;
			} catch (\Throwable $e) {
				$this->db->rollBack();
				$errors++;
				$output->warning(sprintf('Soporte #%d: %s', $support['id_soporte'], $e->getMessage()));
			}
		}

		$output->writeln(sprintf('Con duración sin reporte: %d', count($pending)));
		$output->writeln(sprintf('Sin duración: %d', $this->soportes->countWithoutDuration()));
		$output->writeln(sprintf('Reportes huérfanos: %d', $this->reportes->countOrphanSupportReports()));
		$output->writeln(sprintf('Reparados: %d; errores: %d', $repaired, $errors));

		return $errors === 0 ? Command::SUCCESS : Command::FAILURE;
	}
}
