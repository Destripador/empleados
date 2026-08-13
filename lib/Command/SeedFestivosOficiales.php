<?php

declare(strict_types=1);

namespace OCA\Empleados\Command;

use OCA\Empleados\Db\festivosMapper;
use OCA\Empleados\Service\FestivosCalculator;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedFestivosOficiales extends Command {

	private IDBConnection $db;
	private festivosMapper $festivosMapper;

	private const OFICIALES = [
		['nombre' => 'Año Nuevo',                  'tipo' => 'fijo',     'fecha' => '01-01'],
		['nombre' => 'Día de la Constitución',      'tipo' => 'variable', 'mes' => 2,  'semana' => 1, 'dia' => 1],
		['nombre' => 'Natalicio de Benito Juárez',  'tipo' => 'variable', 'mes' => 3,  'semana' => 3, 'dia' => 1],
		['nombre' => 'Día del Trabajo',              'tipo' => 'fijo',     'fecha' => '05-01'],
		['nombre' => 'Independencia de México',      'tipo' => 'fijo',     'fecha' => '09-16'],
		['nombre' => 'Revolución Mexicana',          'tipo' => 'variable', 'mes' => 11, 'semana' => 3, 'dia' => 1],
		['nombre' => 'Navidad',                      'tipo' => 'fijo',     'fecha' => '12-25'],
	];

	public function __construct(IDBConnection $db, festivosMapper $festivosMapper) {
		parent::__construct();
		$this->db = $db;
		$this->festivosMapper = $festivosMapper;
	}

	protected function configure(): void {
		$this->setName('empleados:seed-festivos')
			->setDescription('Crea los festivos oficiales que falten en la tabla empleados_festivos (idempotente, verifica por nombre).');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$anio = (int)date('Y');
		$creados = 0;
		$saltados = 0;

		foreach (self::OFICIALES as $f) {
			$qb = $this->db->getQueryBuilder();
			$existe = $qb->select($qb->createFunction('COUNT(*)'))
				->from('empleados_festivos')
				->where($qb->expr()->eq('nombre', $qb->createNamedParameter($f['nombre'])))
				->executeQuery()->fetchOne();

			if ((int)$existe > 0) {
				$output->writeln("Ya existe: {$f['nombre']} — se omite.");
				$saltados++;
				continue;
			}

			$fecha = $f['tipo'] === 'fijo'
				? $f['fecha']
				: FestivosCalculator::nthWeekday($anio, $f['mes'], $f['dia'], $f['semana'])->format('m-d');

			$this->festivosMapper->createFestivo(
				$f['nombre'],
				$fecha,
				$f['tipo'],
				1, // oficial
				$f['mes'] ?? null,
				$f['semana'] ?? null,
				$f['dia'] ?? null,
				$f['tipo'] === 'variable' ? $anio : null
			);

			$output->writeln("Creado: {$f['nombre']} ({$fecha})");
			$creados++;
		}

		$output->writeln("Listo. Creados: {$creados}, ya existían: {$saltados}.");
		return 0;
	}
}