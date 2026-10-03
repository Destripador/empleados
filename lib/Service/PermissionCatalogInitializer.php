<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Config\PermissionCatalog;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PermissionCatalogInitializer {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * Inserta exclusivamente definiciones oficiales faltantes.
	 *
	 * @return list<array<string, mixed>> filas insertadas
	 */
	public function initialize(): array {
		$this->db->beginTransaction();

		try {
			$existing = $this->existingKeys();
			$inserted = [];

			foreach (PermissionCatalog::entries() as $entry) {
				$key = PermissionCatalog::key($entry['module'], $entry['permission'], $entry['group_id']);
				if (isset($existing[$key])) {
					continue;
				}

				$this->insert($entry);
				$existing[$key] = true;
				$inserted[] = $entry;
			}

			$this->db->commit();
			return $inserted;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/** @return array<string, true> */
	private function existingKeys(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('module', 'permission', 'group_id')
			->from('emp_perm_groups')
			->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$keys = [];
		foreach ($rows as $row) {
			$keys[PermissionCatalog::key(
				(string)$row['module'],
				(string)$row['permission'],
				(string)$row['group_id'],
			)] = true;
		}

		return $keys;
	}

	/** @param array<string, mixed> $entry */
	private function insert(array $entry): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('emp_perm_groups')
			->values([
				'module' => $qb->createNamedParameter($entry['module'], IQueryBuilder::PARAM_STR),
				'permission' => $qb->createNamedParameter($entry['permission'], IQueryBuilder::PARAM_STR),
				'group_id' => $qb->createNamedParameter($entry['group_id'], IQueryBuilder::PARAM_STR),
				'label' => $qb->createNamedParameter($entry['label'], IQueryBuilder::PARAM_STR),
				'description' => $qb->createNamedParameter($entry['description'], IQueryBuilder::PARAM_STR),
				'restricted' => $qb->createNamedParameter($entry['restricted'] ? 1 : 0, IQueryBuilder::PARAM_INT),
				'enabled' => $qb->createNamedParameter($entry['enabled'] ? 1 : 0, IQueryBuilder::PARAM_INT),
				'sort_order' => $qb->createNamedParameter($entry['sort_order'], IQueryBuilder::PARAM_INT),
			])
			->executeStatement();
	}
}
