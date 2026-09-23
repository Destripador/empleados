<?php

declare(strict_types=1);

namespace OCA\Empleados\Command;

use OCA\Empleados\Db\honorariosMapper;
use OCA\Empleados\Db\honorariosParcialidadesMapper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RellenarTiposCambioCommand extends Command {

	public function __construct(
		private honorariosParcialidadesMapper $parcialidadesMapper,
		private honorariosMapper $honorariosMapper
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('empleados:rellenar-tipos-cambio')
			->setDescription('Rellena tipos de cambio faltantes en parcialidades facturadas o pagadas');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$resultado = $this->parcialidadesMapper->rellenarTiposCambioFaltantes();

		$output->writeln(sprintf(
			'Factura actualizadas: %d | Pago actualizadas: %d',
			$resultado['factura_actualizadas'],
			$resultado['pago_actualizadas']
		));

		foreach ($resultado['honorarios_factura_completos'] as $idHonorario) {
			$this->honorariosMapper->registrarCambioMonedaFacturaTotal((int)$idHonorario);
		}

		foreach ($resultado['honorarios_pago_completos'] as $idHonorario) {
			$this->honorariosMapper->registrarCambioMonedaTotal((int)$idHonorario);
		}

		if (!empty($resultado['factura_sin_tipo_cambio'])) {
			$output->writeln('<comment>Sin tipo de cambio de factura (parcialidades): '
				. implode(', ', $resultado['factura_sin_tipo_cambio']) . '</comment>');
		}

		if (!empty($resultado['pago_sin_tipo_cambio'])) {
			$output->writeln('<comment>Sin tipo de cambio de pago (parcialidades): '
				. implode(', ', $resultado['pago_sin_tipo_cambio']) . '</comment>');
		}

		return Command::SUCCESS;
	}
}