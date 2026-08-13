<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class contactoemergenciaMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'emp_cont_emer', contactoemergencia::class);
	}

	public function findByEmpleado(int $idEmpleado): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->orderBy('es_principal', 'DESC')->addOrderBy('orden', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function findForEmpleado(int $id, int $idEmpleado): contactoemergencia {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function saveContact(contactoemergencia $contact): contactoemergencia {
		$this->db->beginTransaction();
		try {
			if ($contact->getEsPrincipal()) {
				$this->clearPrincipal($contact->getIdEmpleado());
			}
			$contact->setPrincipalEmpleado($contact->getEsPrincipal() ? $contact->getIdEmpleado() : null);
			$saved = $contact->getId() ? $this->update($contact) : $this->insert($contact);
			$this->db->commit();
			return $saved;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function setPrincipal(int $id, int $idEmpleado): contactoemergencia {
		$contact = $this->findForEmpleado($id, $idEmpleado);
		$contact->setEsPrincipal(1);
		$contact->setUpdatedAt(date('Y-m-d H:i:s'));
		return $this->saveContact($contact);
	}

	public function deleteByEmpleado(int $idEmpleado): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function clearPrincipal(int $idEmpleado): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('es_principal', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->set('principal_empleado', $qb->createNamedParameter(null))
			->where($qb->expr()->eq('id_empleado', $qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
