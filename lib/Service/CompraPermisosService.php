<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;

class CompraPermisosService {

	private IDBConnection $db;
	private IGroupManager $groupManager;

	public function __construct(
		IDBConnection $db,
		IGroupManager $groupManager
	) {
		$this->db = $db;
		$this->groupManager = $groupManager;
	}

	public function isNextcloudAdmin(string $userId): bool {
		return $this->groupManager->isInGroup($userId, 'admin');
	}

	public function isComprasAdmin(string $userId): bool {
		return $this->isNextcloudAdmin($userId)
			|| $this->isInConfiguredGroup($userId, 'compras_grupo_admin', 'compras_admin');
	}

	public function isComprasApprover(string $userId): bool {
		return $this->isInConfiguredGroup($userId, 'compras_grupo_autorizadores', 'compras_autorizadores');
	}

	public function isComprasAccounting(string $userId): bool {
		return $this->isInConfiguredGroup($userId, 'compras_grupo_contabilidad', 'compras_contabilidad');
	}

	public function isComprasRequester(string $userId): bool {
		return $this->isInConfiguredGroup($userId, 'compras_grupo_solicitantes', 'compras_solicitantes');
	}

	public function canAccessModule(string $userId): bool {
		return $this->isComprasAdmin($userId)
			|| $this->isComprasApprover($userId)
			|| $this->isComprasAccounting($userId)
			|| $this->isComprasRequester($userId);
	}

	public function canCreateSolicitud(string $userId): bool {
		return $this->isComprasAdmin($userId)
			|| $this->isComprasRequester($userId);
	}

	public function canViewAll(string $userId): bool {
		return $this->isComprasAdmin($userId)
			|| $this->isComprasApprover($userId)
			|| $this->isComprasAccounting($userId);
	}

	public function canApprove(string $userId): bool {
		return $this->isComprasAdmin($userId)
			|| $this->isComprasApprover($userId);
	}

	public function canProcessPurchase(string $userId): bool {
		return $this->isComprasAdmin($userId)
			|| $this->isComprasAccounting($userId);
	}

	public function canSelectRequester(string $userId): bool {
		return $this->isComprasAdmin($userId);
	}

	public function canViewSolicitud(string $userId, string $ownerUserId): bool {
		if ($userId === $ownerUserId) {
			return $this->canAccessModule($userId);
		}

		return $this->canViewAll($userId);
	}

	public function getApproverGroupIds(): array {
		return array_values(array_unique(array_filter([
			$this->getConfig('compras_grupo_admin', 'compras_admin'),
			$this->getConfig('compras_grupo_autorizadores', 'compras_autorizadores'),
		])));
	}

	private function isInConfiguredGroup(string $userId, string $configName, string $defaultGroup): bool {
		$group = $this->getConfig($configName, $defaultGroup);

		if ($group === '') {
			return false;
		}

		return $this->groupManager->isInGroup($userId, $group);
	}

	private function getConfig(string $nombre, string $default): string {
		$qb = $this->db->getQueryBuilder();

		$qb->select('Data')
			->from('empleados_conf')
			->where($qb->expr()->eq(
				'Nombre',
				$qb->createNamedParameter($nombre, IQueryBuilder::PARAM_STR)
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row || !isset($row['Data']) || $row['Data'] === null || trim((string)$row['Data']) === '') {
			return $default;
		}

		return (string)$row['Data'];
	}
}