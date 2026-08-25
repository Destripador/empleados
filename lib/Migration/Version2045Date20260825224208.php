<?php

declare(strict_types=1);

namespace OCA\Empleados\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\IDBConnection;

/**
 * Corrige el estado 'pagado' de las parcialidades
 */
class Version2045Date20260825224208 extends SimpleMigrationStep {

	private IDBConnection $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		// No hay cambios de esquema, solo datos.
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_parcialidad', 'pagado', 'fecha_factura', 'fecha_pago')
			->from('empleados_honorarios_p');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$actualizados = 0;

		foreach ($rows as $row) {
			$tieneFactura = !empty($row['fecha_factura']);
			$tienePago = !empty($row['fecha_pago']);

			// 0 = PENDIENTE, 1 = FACTURADA, 2 = PAGADA
			$pagadoCorrecto = 0;
			if ($tieneFactura && $tienePago) {
				$pagadoCorrecto = 2;
			} elseif ($tieneFactura) {
				$pagadoCorrecto = 1;
			}

			if ((int)$row['pagado'] === $pagadoCorrecto) {
				continue;
			}

			$update = $this->db->getQueryBuilder();
			$update->update('empleados_honorarios_p')
				->set('pagado', $update->createNamedParameter($pagadoCorrecto, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				->where(
					$update->expr()->eq(
						'id_parcialidad',
						$update->createNamedParameter($row['id_parcialidad'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)
					)
				);
			$update->executeStatement();

			$actualizados++;
		}

		$output->info("Parcialidades corregidas: {$actualizados}");
	}
}