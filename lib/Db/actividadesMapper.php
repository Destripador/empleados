<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class actividadesMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'empleados_actividades', actividades::class);
    }

	    public function findById(int $id): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('id_actividad', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        $result = $qb->executeQuery();
        $data = $result->fetchAll();
        $result->closeCursor();

	        return $this->attachAreas($data);
    }

    public function findAll(?int $limit = null, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('id_actividad', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $result = $qb->executeQuery();
        $data = $result->fetchAll();
        $result->closeCursor();

	        return $this->attachAreas($data);
	    }

	public function findManualAvailable(?int $departmentId): array {
		return array_values(array_filter($this->findAll(), static function (array $activity) use ($departmentId): bool {
			if ((int)($activity['id_actividad'] ?? 0) === 99999) return false;
			if (($activity['clave_sistema'] ?? null) !== null) return false;
			$type = (string)($activity['tipo_actividad'] ?? actividades::TIPO_CLIENTE);
			if ($type === actividades::TIPO_CLIENTE) return true;
			if ((string)($activity['alcance'] ?? actividades::ALCANCE_GLOBAL) === actividades::ALCANCE_GLOBAL) return true;
			return $departmentId !== null && in_array($departmentId, $activity['area_ids'] ?? [], true);
		}));
	}

	    public function deleteById(int $id): void {
			$activity = $this->findById($id);
			if ($id === 99999 || ($activity[0]['clave_sistema'] ?? null) !== null) {
				throw new \RuntimeException('Las actividades internas del sistema no se pueden eliminar.');
			}
			$this->db->beginTransaction();
			try {
				$this->deleteAreas($id);
				$qb = $this->db->getQueryBuilder();
				$qb->delete($this->getTableName())
					->where($qb->expr()->eq('id_actividad', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
					->executeStatement();
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
	    }

		public function ensureSystemActivity(
			string $key,
			string $name,
			string $details,
			string $scope = actividades::ALCANCE_GLOBAL,
			array $areaIds = [],
		): int {
			$this->assertScope($scope);
			$areaIds = $this->normalizeAreaIds($areaIds);
			if ($scope === actividades::ALCANCE_AREAS) $this->assertAreasExist($areaIds);
			$qb = $this->db->getQueryBuilder();
		$qb->select('id_actividad')->from($this->getTableName())
			->where($qb->expr()->eq('clave_sistema', $qb->createNamedParameter($key)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();

			if ($id !== false) {
				$update = $this->db->getQueryBuilder();
				$update->update($this->getTableName())
					->set('cargable', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))
					->set('tipo_actividad', $update->createNamedParameter(actividades::TIPO_INTERNO))
					->set('alcance', $update->createNamedParameter($scope))
					->where($update->expr()->eq('id_actividad', $update->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
					->executeStatement();
				$this->replaceAreas((int)$id, $scope === actividades::ALCANCE_AREAS ? $areaIds : []);
				return (int)$id;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())->values([
			'nombre' => $insert->createNamedParameter($name),
			'detalles' => $insert->createNamedParameter($details),
			'tiempo_estimado' => $insert->createNamedParameter(0),
				'cargable' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'clave_sistema' => $insert->createNamedParameter($key),
				'tipo_actividad' => $insert->createNamedParameter(actividades::TIPO_INTERNO),
				'alcance' => $insert->createNamedParameter($scope),
			])->executeStatement();

			$id = (int)$this->db->lastInsertId($this->getTableName());
			$this->replaceAreas($id, $scope === actividades::ALCANCE_AREAS ? $areaIds : []);
			return $id;
		}

	public function createActivity(
		string $name,
		?string $details,
		float $estimatedTime,
		bool $billable,
		string $activityType,
		string $scope,
		array $areaIds,
	): int {
		[$activityType, $scope, $billable, $areaIds] = $this->validateConfiguration($activityType, $scope, $billable, $areaIds);
		$this->db->beginTransaction();
		try {
			$insert = $this->db->getQueryBuilder();
			$insert->insert($this->getTableName())->values([
				'nombre' => $insert->createNamedParameter($name),
				'detalles' => $insert->createNamedParameter($details),
				'tiempo_estimado' => $insert->createNamedParameter($estimatedTime),
				'cargable' => $insert->createNamedParameter((int)$billable, IQueryBuilder::PARAM_INT),
				'clave_sistema' => $insert->createNamedParameter(null),
				'tipo_actividad' => $insert->createNamedParameter($activityType),
				'alcance' => $insert->createNamedParameter($scope),
			])->executeStatement();
			$id = (int)$this->db->lastInsertId($this->getTableName());
			$this->replaceAreas($id, $areaIds);
			$this->db->commit();
			return $id;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

    public function updateActividad(
        ?int $id_actividad,
        string $nombre,
        ?string $detalles,
        float $tiempoestimado,
	        bool $cargable,
			string $tipoActividad = actividades::TIPO_CLIENTE,
			string $alcance = actividades::ALCANCE_GLOBAL,
			array $areaIds = [],
	    ): void {
			$actual = $id_actividad !== null ? $this->findById($id_actividad) : [];
			if ($actual === []) throw new \InvalidArgumentException('La actividad seleccionada no existe.');
			if (($actual[0]['clave_sistema'] ?? null) !== null) {
				$tipoActividad = actividades::TIPO_INTERNO;
			}
			[$tipoActividad, $alcance, $cargable, $areaIds] = $this->validateConfiguration($tipoActividad, $alcance, $cargable, $areaIds);
			$this->db->beginTransaction();
			try {
	        $query = $this->db->getQueryBuilder();
	        $query->update($this->getTableName())
            ->set('nombre', $query->createNamedParameter($nombre))
            ->set('detalles', $query->createNamedParameter($detalles))
            ->set('tiempo_estimado', $query->createNamedParameter($tiempoestimado))
	            ->set('cargable', $query->createNamedParameter((int)$cargable, IQueryBuilder::PARAM_INT))
				->set('tipo_actividad', $query->createNamedParameter($tipoActividad))
				->set('alcance', $query->createNamedParameter($alcance))
            ->where(
                $query->expr()->eq('id_actividad', $query->createNamedParameter($id_actividad, IQueryBuilder::PARAM_INT))
            );

	        $query->executeStatement();
				$this->replaceAreas((int)$id_actividad, $areaIds);
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
	    }

    /**
     * Asegura que exista la actividad con id 99999, usada para reportes de tiempo generados automáticamente 
     * por ausencias. Si ya existe, no hace nada. Si no existe, la crea con cargable = 0.
     */
    public function ensureActividadAusencia(): void {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id_actividad')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('id_actividad', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT))
            );

        $result = $qb->executeQuery();
        $existe = $result->fetch();
        $result->closeCursor();

        if ($existe) {
            return;
        }

        $insert = $this->db->getQueryBuilder();
        $insert->insert($this->getTableName())
            ->values([
                'id_actividad'    => $insert->createNamedParameter(99999, IQueryBuilder::PARAM_INT),
                'nombre'          => $insert->createNamedParameter('Ausencia'),
                'detalles'        => $insert->createNamedParameter('Actividad para reportes generados por ausencias.'),
                'tiempo_estimado' => $insert->createNamedParameter(0),
	                'cargable'        => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
					'tipo_actividad'  => $insert->createNamedParameter(actividades::TIPO_CLIENTE),
					'alcance'         => $insert->createNamedParameter(actividades::ALCANCE_GLOBAL),
	            ]);

	        $insert->executeStatement();
	    }

	private function attachAreas(array $activities): array {
		if ($activities === []) return [];
		$ids = array_values(array_unique(array_map('intval', array_column($activities, 'id_actividad'))));
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('aa.id_actividad', 'aa.id_departamento')
			->selectAlias('d.Nombre', 'departamento_nombre')
			->from('empleados_actividad_areas', 'aa')
			->leftJoin('aa', 'departamentos', 'd', 'd.Id_departamento = aa.id_departamento')
			->where($qb->expr()->in('aa.id_actividad', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('aa.id_departamento', 'ASC')
			->executeQuery();
		$byActivity = [];
		foreach ($result->fetchAll() as $row) {
			$byActivity[(int)$row['id_actividad']][] = [
				'id_departamento' => (int)$row['id_departamento'],
				'nombre' => (string)($row['departamento_nombre'] ?? ''),
			];
		}
		$result->closeCursor();
		foreach ($activities as &$activity) {
			$activity['tipo_actividad'] ??= actividades::TIPO_CLIENTE;
			$activity['alcance'] ??= actividades::ALCANCE_GLOBAL;
			$activity['areas'] = $byActivity[(int)$activity['id_actividad']] ?? [];
			$activity['area_ids'] = array_column($activity['areas'], 'id_departamento');
		}
		unset($activity);
		return $activities;
	}

	private function validateConfiguration(string $type, string $scope, bool $billable, array $areaIds): array {
		$type = strtolower(trim($type));
		$scope = strtolower(trim($scope));
		if (!in_array($type, actividades::TIPOS_VALIDOS, true)) throw new \InvalidArgumentException('Tipo de actividad inválido.');
		$this->assertScope($scope);
		$areaIds = $this->normalizeAreaIds($areaIds);
		if ($type === actividades::TIPO_INTERNO) $billable = false;
		if ($type === actividades::TIPO_CLIENTE) {
			$scope = actividades::ALCANCE_GLOBAL;
			$areaIds = [];
		} elseif ($scope === actividades::ALCANCE_AREAS) {
			if ($areaIds === []) throw new \InvalidArgumentException('Selecciona al menos un área para la actividad interna.');
			$this->assertAreasExist($areaIds);
		} else {
			$areaIds = [];
		}
		return [$type, $scope, $billable, $areaIds];
	}

	private function assertScope(string $scope): void {
		if (!in_array($scope, actividades::ALCANCES_VALIDOS, true)) throw new \InvalidArgumentException('Alcance de actividad inválido.');
	}

	private function normalizeAreaIds(array $areaIds): array {
		$areaIds = array_values(array_unique(array_map('intval', $areaIds)));
		if (array_filter($areaIds, static fn(int $id): bool => $id <= 0) !== []) throw new \InvalidArgumentException('La lista de áreas contiene identificadores inválidos.');
		return $areaIds;
	}

	private function assertAreasExist(array $areaIds): void {
		if ($areaIds === []) return;
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select($qb->createFunction('COUNT(DISTINCT Id_departamento)'))->from('departamentos')
			->where($qb->expr()->in('Id_departamento', $qb->createNamedParameter($areaIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		if ($count !== count($areaIds)) throw new \InvalidArgumentException('Una o más áreas seleccionadas no existen.');
	}

	private function replaceAreas(int $activityId, array $areaIds): void {
		$this->deleteAreas($activityId);
		$now = date('Y-m-d H:i:s');
		foreach ($areaIds as $departmentId) {
			$insert = $this->db->getQueryBuilder();
			$insert->insert('empleados_actividad_areas')->values([
				'id_actividad' => $insert->createNamedParameter($activityId, IQueryBuilder::PARAM_INT),
				'id_departamento' => $insert->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT),
				'created_at' => $insert->createNamedParameter($now),
			])->executeStatement();
		}
	}

	private function deleteAreas(int $activityId): void {
		$delete = $this->db->getQueryBuilder();
		$delete->delete('empleados_actividad_areas')
			->where($delete->expr()->eq('id_actividad', $delete->createNamedParameter($activityId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
