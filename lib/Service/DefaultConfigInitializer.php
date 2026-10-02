<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Config\DefaultConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;

final class DefaultConfigInitializer {
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	/**
	 * @return array{table: list<string>, app: list<string>}
	 */
	public function initialize(): array {
		return [
			'table' => $this->initializeTableDefaults(),
			'app' => $this->initializeAppDefaults(),
		];
	}

	/** @return list<string> */
	public function initializeTableDefaults(): array {
		$this->db->beginTransaction();

		try {
			$existing = $this->getExistingTableKeys();
			$inserted = [];

			foreach (DefaultConfig::TABLE_DEFAULTS as $key => $value) {
				if (isset($existing[$key])) {
					continue;
				}

				$this->insertTableDefault($key, $value);
				$existing[$key] = true;
				$inserted[] = $key;
			}

			$this->db->commit();
			return $inserted;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/** @return list<string> */
	public function initializeAppDefaults(): array {
		$existing = array_fill_keys($this->config->getAppKeys(DefaultConfig::APP_ID), true);
		$inserted = [];

		foreach (DefaultConfig::APP_DEFAULTS as $key => $value) {
			if (isset($existing[$key])) {
				continue;
			}

			$this->config->setAppValue(DefaultConfig::APP_ID, $key, $value);
			$existing[$key] = true;
			$inserted[] = $key;
		}

		return $inserted;
	}

	/** @return array<string, true> */
	private function getExistingTableKeys(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->selectAlias('Nombre', 'config_name')
			->from(DefaultConfig::TABLE)
			->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$keys = [];
		foreach ($rows as $row) {
			if (isset($row['config_name'])) {
				$keys[(string)$row['config_name']] = true;
			}
		}

		return $keys;
	}

	private function insertTableDefault(string $key, ?string $value): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(DefaultConfig::TABLE)
			->values([
				'Nombre' => $qb->createNamedParameter($key, IQueryBuilder::PARAM_STR),
				'Data' => $qb->createNamedParameter(
					$value,
					$value === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR,
				),
			])
			->executeStatement();
	}
}
