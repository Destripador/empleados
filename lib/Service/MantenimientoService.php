<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\departamentosMapper;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\MantenimientoCambioMapper;
use OCA\Empleados\Db\MantenimientoChecklist;
use OCA\Empleados\Db\MantenimientoChecklistMapper;
use OCA\Empleados\Db\MantenimientoEquipo;
use OCA\Empleados\Db\MantenimientoEquipoMapper;
use OCA\Empleados\Db\MantenimientoGrupo;
use OCA\Empleados\Db\MantenimientoGrupoMapper;
use OCA\Empleados\Exception\MantenimientoConflictException;
use OCA\Empleados\Exception\MantenimientoNotFoundException;
use OCA\Empleados\Exception\MantenimientoStorageException;
use OCA\Empleados\Exception\MantenimientoTransitionException;
use OCA\Empleados\Exception\MantenimientoValidationException;
use OCA\Empleados\Mantenimiento\ChecklistPreventivoCatalogo;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class MantenimientoService {
	public const TYPE_PREVENTIVE = 'preventive';
	public const TYPE_CORRECTIVE = 'corrective';
	public const TYPE_SPECIAL = 'special';
	public const TYPES = [self::TYPE_PREVENTIVE, self::TYPE_CORRECTIVE, self::TYPE_SPECIAL];

	private const FINAL_STATES = [
		MantenimientoEquipo::ESTADO_COMPLETED,
		MantenimientoEquipo::ESTADO_CANCELLED,
		MantenimientoEquipo::ESTADO_NOT_APPLICABLE,
	];
	private const TRANSITIONS = [
		MantenimientoEquipo::ESTADO_PENDING => [
			MantenimientoEquipo::ESTADO_SCHEDULED,
			MantenimientoEquipo::ESTADO_IN_PROGRESS,
			MantenimientoEquipo::ESTADO_RESCHEDULED,
			MantenimientoEquipo::ESTADO_CANCELLED,
			MantenimientoEquipo::ESTADO_NOT_APPLICABLE,
		],
		MantenimientoEquipo::ESTADO_SCHEDULED => [
			MantenimientoEquipo::ESTADO_IN_PROGRESS,
			MantenimientoEquipo::ESTADO_RESCHEDULED,
			MantenimientoEquipo::ESTADO_CANCELLED,
			MantenimientoEquipo::ESTADO_NOT_APPLICABLE,
		],
		MantenimientoEquipo::ESTADO_IN_PROGRESS => [
			MantenimientoEquipo::ESTADO_COMPLETED,
			MantenimientoEquipo::ESTADO_RESCHEDULED,
			MantenimientoEquipo::ESTADO_CANCELLED,
		],
		MantenimientoEquipo::ESTADO_RESCHEDULED => [
			MantenimientoEquipo::ESTADO_SCHEDULED,
			MantenimientoEquipo::ESTADO_IN_PROGRESS,
			MantenimientoEquipo::ESTADO_CANCELLED,
			MantenimientoEquipo::ESTADO_NOT_APPLICABLE,
		],
	];
	private const EXCLUDED_EQUIPMENT_STATES = ['baja', 'inactivo', 'inactive', 'eliminado', 'deleted'];
	private const MAX_HIERARCHY_NODES = 1000;
	private const MAX_TEXT_LENGTH = 65535;
	private const MAX_OBSERVATION_LENGTH = 4000;
	private const MAX_CAMPAIGN_DAYS = 31;

	public function __construct(
		private IDBConnection $db,
		private MantenimientoGrupoMapper $groupMapper,
		private MantenimientoEquipoMapper $maintenanceMapper,
		private MantenimientoChecklistMapper $checklistMapper,
		private MantenimientoCambioMapper $changeMapper,
		private InventarioComputoMapper $inventoryMapper,
		private departamentosMapper $departmentMapper,
		private IUserManager $userManager,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	public function createGroup(
		array $groupData,
		array $equipmentIds,
		string $actorUid,
		string $actorName,
		bool $allowPotentialDuplicates = false,
	): array {
		return $this->transactional(function () use ($groupData, $equipmentIds, $actorUid, $actorName, $allowPotentialDuplicates): array {
			$validated = $this->validateGroupData($groupData);
			$requestedIds = $this->validateEquipmentIds($equipmentIds);
			$departmentIds = $validated['id_departamento'] === null
				? []
				: $this->resolveDepartmentIds($validated['id_departamento'], $validated['include_descendants']);
			$equipment = $this->resolveEquipment($requestedIds, $validated, $departmentIds);
			$warnings = $this->findDuplicateWarnings($equipment, $validated['tipo'], $validated['fecha_inicio'], $validated['fecha_fin']);
			if ($warnings !== [] && !$allowPotentialDuplicates) {
				throw new MantenimientoConflictException('Se encontraron mantenimientos activos posiblemente duplicados.', $warnings);
			}

			$now = $this->now();
			$group = $this->groupMapper->insertGroup([
				'titulo' => $validated['titulo'],
				'id_departamento' => $validated['id_departamento'],
				'departamento_nombre' => $validated['departamento_nombre'],
				'tipo' => $validated['tipo'],
				// Deprecated single-day compatibility field. New code uses the period.
				'fecha_programada' => $validated['fecha_inicio'],
				'fecha_inicio' => $validated['fecha_inicio'],
				'fecha_fin' => $validated['fecha_fin'],
				'hora_inicio' => $validated['hora_inicio'],
				'hora_fin' => $validated['hora_fin'],
				'tecnico_uid' => $validated['tecnico_uid'],
				'tecnico_nombre' => $validated['tecnico_nombre'],
				'estado_admin' => MantenimientoGrupo::ESTADO_ACTIVE,
				'descripcion' => $validated['descripcion'],
				'creado_por' => $actorUid,
				'fecha_creacion' => $now,
				'fecha_actualizacion' => $now,
			]);

			$maintenances = [];
			$warningEquipmentIds = array_flip(array_column($warnings, 'id_equipo'));
			foreach ($equipment as $row) {
				$maintenance = $this->maintenanceMapper->insertMaintenance($this->buildMaintenanceSnapshot(
					(int)$group->getId(),
					$row,
					$validated,
					$actorUid,
					$now,
				));
				if ($validated['tipo'] === self::TYPE_PREVENTIVE) {
					foreach (ChecklistPreventivoCatalogo::ITEMS as $item) {
						$this->checklistMapper->insertResponse([
							'id_mantenimiento' => (int)$maintenance->getId(),
							'clave' => $item['clave'],
							'etiqueta' => $item['etiqueta'],
							'orden' => $item['orden'],
							'resultado' => MantenimientoChecklist::RESULTADO_PENDING,
							'observacion' => null,
							'actualizado_por' => $actorUid,
							'fecha_actualizacion' => $now,
						]);
					}
				}
				$this->recordAudit(
					(int)$group->getId(),
					(int)$maintenance->getId(),
					'created',
					null,
					MantenimientoEquipo::ESTADO_PENDING,
					isset($warningEquipmentIds[(int)$row['id_equipo']]) ? 'Mantenimiento creado con advertencia de duplicado aceptada.' : 'Mantenimiento creado.',
					$actorUid,
					$actorName,
				);
				$maintenances[] = $maintenance->jsonSerialize();
			}

			$this->recordAudit(
				(int)$group->getId(),
				null,
				'created',
				null,
				json_encode([
					'period_start' => $validated['fecha_inicio'],
					'period_end' => $validated['fecha_fin'],
					'default_start_time' => $validated['hora_inicio'],
					'default_end_time' => $validated['hora_fin'],
				], JSON_UNESCAPED_SLASHES) ?: null,
				'Campaña creada con ' . count($maintenances) . ' equipos' . ($warnings === [] ? '.' : '; duplicados potenciales aceptados.'),
				$actorUid,
				$actorName,
			);

			return [
				'group' => $group->jsonSerialize(),
				'maintenances' => $maintenances,
				'progress' => $this->calculateProgress($group, $this->groupMapper->getProgress((int)$group->getId(), $this->today())),
				'warnings' => $warnings,
			];
		}, 'create_group');
	}

	public function getGroup(int $groupId, int $limit = 50, int $offset = 0): array {
		$group = $this->getGroupEntity($groupId);
		return [
			'group' => $group->jsonSerialize(),
			'progress' => $this->calculateProgress($group, $this->groupMapper->getProgress($groupId, $this->today())),
			'maintenances' => $this->listGroupMaintenances($groupId, $limit, $offset),
		];
	}

	public function listGroups(string $from, string $to, ?int $departmentId, ?string $technicianUid, ?string $type, ?string $status, ?string $search, int $limit, int $offset): array {
		if ($type !== null && trim($type) !== '') $this->assertType($type);
		$items = $this->groupMapper->findCalendarPage($from, $to, $departmentId, $technicianUid, $type, $status, $search, $limit, $offset);
		return [
			'items' => array_map(fn(MantenimientoGrupo $group): array => $group->jsonSerialize(), $items),
			'total' => $this->groupMapper->countCalendar($from, $to, $departmentId, $technicianUid, $type, $status, $search),
			'limit' => $limit,
			'offset' => $offset,
		];
	}

	public function getGroupSummary(int $groupId): array {
		$group = $this->getGroupEntity($groupId);
		return [
			'group' => $group->jsonSerialize(),
			'progress' => $this->calculateProgress($group, $this->groupMapper->getProgress($groupId, $this->today())),
		];
	}

	public function getGroupProgress(int $groupId): array {
		$group = $this->getGroupEntity($groupId);
		return $this->calculateProgress($group, $this->groupMapper->getProgress($groupId, $this->today()));
	}

	public function listGroupMaintenances(int $groupId, int $limit = 50, int $offset = 0): array {
		$this->getGroupEntity($groupId);
		return [
			'items' => array_map(fn(MantenimientoEquipo $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findPageByGroup($groupId, $limit, $offset)),
			'pagination' => ['total' => $this->maintenanceMapper->countByGroup($groupId), 'limit' => $limit, 'offset' => $offset],
		];
	}

	public function listGroupMaintenancesFiltered(int $groupId, ?string $status, ?string $technicianUid, ?string $search, int $limit, int $offset): array {
		$this->getGroupEntity($groupId);
		return [
			'items' => array_map(fn(MantenimientoEquipo $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findPageByGroupFiltered($groupId, $status, $technicianUid, $search, $limit, $offset)),
			'pagination' => [
				'total' => $this->maintenanceMapper->countByGroupFiltered($groupId, $status, $technicianUid, $search),
				'limit' => $limit,
				'offset' => $offset,
			],
		];
	}

	public function getMaintenance(int $maintenanceId, int $auditLimit = 50, int $auditOffset = 0): array {
		$maintenance = $this->getMaintenanceEntity($maintenanceId);
		$group = $this->getGroupEntity((int)$maintenance->getIdGrupo());
		return [
			'maintenance' => $maintenance->jsonSerialize(),
			'group' => $group->jsonSerialize(),
			'checklist' => array_map(fn(MantenimientoChecklist $item): array => $item->jsonSerialize(), $this->checklistMapper->listByMaintenance($maintenanceId)),
			'audit' => array_map(static fn($item): array => $item->jsonSerialize(), $this->changeMapper->findByMaintenance($maintenanceId, $auditLimit, $auditOffset)),
		];
	}

	public function getMaintenanceRecord(int $maintenanceId): array {
		return $this->getMaintenanceEntity($maintenanceId)->jsonSerialize();
	}

	public function technicianHasGroupAccess(int $groupId, string $technicianUid): bool {
		return $this->maintenanceMapper->technicianHasGroupAccess($groupId, $technicianUid);
	}

	public function technicianHasEquipmentAccess(int $equipmentId, string $technicianUid): bool {
		return $this->maintenanceMapper->technicianHasEquipmentAccess($equipmentId, $technicianUid);
	}

	public function getEquipmentHistory(int $equipmentId, int $limit = 50, int $offset = 0): array {
		return array_map(fn(MantenimientoEquipo $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findHistoryByEquipment($equipmentId, $limit, $offset));
	}

	public function getEquipmentHistoryFiltered(int $equipmentId, ?string $type, ?string $status, ?string $from, ?string $to, int $limit, int $offset): array {
		if ($type !== null && trim($type) !== '') $this->assertType($type);
		return [
			'items' => array_map(fn(MantenimientoEquipo $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findHistoryFiltered($equipmentId, $type, $status, $from, $to, $limit, $offset)),
			'pagination' => [
				'total' => $this->maintenanceMapper->countHistoryFiltered($equipmentId, $type, $status, $from, $to),
				'limit' => $limit,
				'offset' => $offset,
			],
		];
	}

	public function listPotentialDuplicates(int $equipmentId, string $type, string $from, string $to): array {
		$this->assertType($type);
		$this->assertDate($from, 'La fecha inicial');
		$this->assertDate($to, 'La fecha final');
		if ($to < $from) throw new MantenimientoValidationException('La fecha final debe ser igual o posterior a la inicial.');
		return array_map(function (MantenimientoEquipo $item): array {
			$group = $this->getGroupEntity((int)$item->getIdGrupo());
			return array_merge($item->jsonSerialize(), [
				'groupId' => (int)$item->getIdGrupo(),
				'equipmentId' => (int)$item->getIdEquipo(),
				'existingPeriodStart' => $this->groupPeriodStart($group, $item),
				'existingPeriodEnd' => $this->groupPeriodEnd($group, $item),
				'status' => (string)$item->getEstado(),
				'type' => (string)$item->getTipo(),
			]);
		}, $this->maintenanceMapper->findPossibleDuplicates($equipmentId, $type, $from, $to));
	}

	public function listOverdueMaintenances(int $limit = 100, int $offset = 0): array {
		return array_map(fn(MantenimientoEquipo $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findOverdue($this->today(), $limit, $offset));
	}

	public function listOverdueMaintenancesFiltered(?int $departmentId, ?string $technicianUid, ?string $type, int $limit, int $offset): array {
		if ($type !== null && trim($type) !== '') $this->assertType($type);
		$today = $this->today();
		return [
			'items' => array_map(fn(MantenimientoEquipo $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findOverdueFiltered($today, $departmentId, $technicianUid, $type, $limit, $offset)),
			'total' => $this->maintenanceMapper->countOverdueFiltered($today, $departmentId, $technicianUid, $type),
			'limit' => $limit,
			'offset' => $offset,
		];
	}

	public function listEligibleEquipmentByDepartment(int $departmentId, bool $includeDescendants, bool $includeInactive, ?string $search, int $limit, int $offset): array {
		$departmentIds = $this->resolveDepartmentIds($departmentId, $includeDescendants);
		return [
			'items' => $this->inventoryMapper->findByDepartamento($departmentId, $search, $includeInactive, $includeDescendants, $departmentIds, $limit, $offset),
			'total' => $this->inventoryMapper->countByDepartamento($departmentId, $search, $includeInactive, $includeDescendants, $departmentIds),
			'departmentIds' => $departmentIds,
		];
	}

	public function resolveDepartmentIds(int $departmentId, bool $includeDescendants): array {
		$rows = $this->departmentMapper->findHierarchy();
		$byId = [];
		$children = [];
		foreach ($rows as $row) {
			$id = (int)($row['Id_departamento'] ?? $row['id_departamento'] ?? 0);
			if ($id <= 0) continue;
			$byId[$id] = $row;
			$parent = (int)($row['Id_padre'] ?? $row['id_padre'] ?? 0);
			if ($parent > 0) $children[$parent][] = $id;
		}
		if (!isset($byId[$departmentId])) {
			throw new MantenimientoValidationException('El departamento seleccionado no existe.');
		}
		if (!$includeDescendants) return [$departmentId];

		$result = [];
		$visited = [];
		$queue = [$departmentId];
		while ($queue !== []) {
			$current = array_shift($queue);
			if (isset($visited[$current])) {
				$this->logger->warning('Ciclo detectado en jerarquía de departamentos de mantenimiento.', ['id_departamento' => $current]);
				continue;
			}
			$visited[$current] = true;
			$result[] = $current;
			if (count($result) >= self::MAX_HIERARCHY_NODES) {
				$this->logger->warning('Límite de jerarquía alcanzado al resolver departamentos de mantenimiento.', ['id_departamento' => $departmentId]);
				break;
			}
			foreach ($children[$current] ?? [] as $child) $queue[] = $child;
		}
		return $result;
	}

	public function startMaintenance(int $maintenanceId, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		if ((string)$maintenance->getEstado() === MantenimientoEquipo::ESTADO_IN_PROGRESS) {
			return $maintenance->jsonSerialize();
		}
		$this->assertTransitionAllowed((string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_IN_PROGRESS);
		return $this->transactional(function () use ($maintenance, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->updateStatusIfCurrent(
				(int)$maintenance->getId(), (string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_IN_PROGRESS, $actorUid, $this->now(), null,
			);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'started', (string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_IN_PROGRESS, null, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'start', (int)$maintenance->getIdGrupo(), $maintenanceId);
	}

	public function scheduleMaintenance(int $maintenanceId, string $date, ?string $startTime, ?string $endTime, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		$group = $this->getGroupEntity((int)$maintenance->getIdGrupo());
		$this->assertTransitionAllowed((string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_SCHEDULED);
		$this->assertDate($date, 'La fecha programada');
		$periodStart = $this->groupPeriodStart($group, $maintenance);
		$periodEnd = $this->groupPeriodEnd($group, $maintenance);
		if ($periodStart !== null && $periodEnd !== null && ($date < $periodStart || $date > $periodEnd)) {
			throw new MantenimientoValidationException('La fecha programada debe estar dentro del periodo de la campaña.');
		}
		[$startTime, $endTime] = $this->validateTimes($startTime, $endTime);
		$oldValue = $this->scheduleAuditValue($maintenance);
		$newValue = implode('|', [$date, $startTime ?? '', $endTime ?? '']);

		return $this->transactional(function () use ($maintenance, $date, $startTime, $endTime, $oldValue, $newValue, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->scheduleIfCurrent((int)$maintenance->getId(), (string)$maintenance->getEstado(), $date, $startTime, $endTime, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'scheduled', $oldValue, $newValue, null, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'schedule', (int)$maintenance->getIdGrupo(), $maintenanceId);
	}

	public function completeMaintenance(int $maintenanceId, array $completionData, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		$this->assertTransitionAllowed((string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_COMPLETED);
		if ($this->emptyToNull($maintenance->getTecnicoUid()) === null) {
			throw new MantenimientoValidationException('Debe asignarse un técnico antes de completar el mantenimiento.');
		}
		$data = $this->validateWorkDetails($completionData, true);
		$this->validateChecklistForCompletion($maintenance);
		$finishedAt = $this->now();
		$startedAt = $maintenance->getFechaInicioReal();
		if ($startedAt !== null && (string)$startedAt > $finishedAt) {
			throw new MantenimientoValidationException('La fecha final no puede ser anterior al inicio.');
		}

		return $this->transactional(function () use ($maintenance, $data, $finishedAt, $actorUid, $actorName): array {
			$saved = $this->maintenanceMapper->saveResultIfCurrent((int)$maintenance->getId(), (string)$maintenance->getEstado(), $data, $actorUid);
			$this->assertConcurrentUpdate($saved);
			$updated = $this->maintenanceMapper->updateStatusIfCurrent(
				(int)$maintenance->getId(), (string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_COMPLETED, $actorUid, null, $finishedAt,
			);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'completed', (string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_COMPLETED, null, $actorUid, $actorName);
			// Extension point: soporte_historial and inv_movimientos will be integrated in a later phase.
			return $updated->jsonSerialize();
		}, 'complete', (int)$maintenance->getIdGrupo(), $maintenanceId);
	}

	public function rescheduleMaintenance(int $maintenanceId, string $newDate, ?string $newStartTime, ?string $newEndTime, string $reason, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		$group = $this->getGroupEntity((int)$maintenance->getIdGrupo());
		$this->assertTransitionAllowed((string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_RESCHEDULED);
		$this->assertDate($newDate, 'La nueva fecha');
		$periodStart = $this->groupPeriodStart($group, $maintenance);
		$periodEnd = $this->groupPeriodEnd($group, $maintenance);
		if ($periodStart !== null && $periodEnd !== null && ($newDate < $periodStart || $newDate > $periodEnd)) {
			throw new MantenimientoValidationException('La fecha programada debe estar dentro del periodo de la campaña.');
		}
		[$newStartTime, $newEndTime] = $this->validateTimes($newStartTime, $newEndTime);
		$reason = $this->requiredText($reason, 'El motivo de reprogramación', self::MAX_OBSERVATION_LENGTH);
		$oldValue = $this->scheduleAuditValue($maintenance);
		$newValue = implode('|', [$newDate, $newStartTime ?? '', $newEndTime ?? '']);

		return $this->transactional(function () use ($maintenance, $newDate, $newStartTime, $newEndTime, $reason, $oldValue, $newValue, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->rescheduleIfCurrent((int)$maintenance->getId(), (string)$maintenance->getEstado(), $newDate, $newStartTime, $newEndTime, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'rescheduled', $oldValue, $newValue, $reason, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'reschedule', (int)$maintenance->getIdGrupo(), $maintenanceId);
	}

	public function cancelMaintenance(int $maintenanceId, string $reason, string $actorUid, string $actorName): array {
		return $this->finishWithoutExecution($maintenanceId, MantenimientoEquipo::ESTADO_CANCELLED, 'cancelled', $reason, $actorUid, $actorName);
	}

	public function markNotApplicable(int $maintenanceId, string $reason, string $actorUid, string $actorName): array {
		return $this->finishWithoutExecution($maintenanceId, MantenimientoEquipo::ESTADO_NOT_APPLICABLE, 'status_changed', $reason, $actorUid, $actorName);
	}

	public function cancelGroup(int $groupId, string $reason, string $actorUid, string $actorName): array {
		$group = $this->getGroupEntity($groupId);
		$reason = $this->requiredText($reason, 'El motivo de cancelación', self::MAX_OBSERVATION_LENGTH);
		if ((string)$group->getEstadoAdmin() === MantenimientoGrupo::ESTADO_CANCELLED) return $this->getGroup($groupId);

		return $this->transactional(function () use ($group, $groupId, $reason, $actorUid, $actorName): array {
			if (!$this->groupMapper->cancel($groupId)) {
				throw new MantenimientoConflictException('La campaña cambió mientras se intentaba cancelar.');
			}
			foreach ($this->maintenanceMapper->findByGroup($groupId) as $maintenance) {
				if (!in_array((string)$maintenance->getEstado(), MantenimientoEquipo::ESTADOS_ACTIVOS, true)) continue;
				$updated = $this->maintenanceMapper->updateStatusIfCurrent((int)$maintenance->getId(), (string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_CANCELLED, $actorUid);
				$this->assertConcurrentUpdate($updated);
				$this->recordStateAudit($updated, 'cancelled', (string)$maintenance->getEstado(), MantenimientoEquipo::ESTADO_CANCELLED, $reason, $actorUid, $actorName);
			}
			$this->recordAudit($groupId, null, 'cancelled', MantenimientoGrupo::ESTADO_ACTIVE, MantenimientoGrupo::ESTADO_CANCELLED, $reason, $actorUid, $actorName);
			return $this->getGroup($groupId);
		}, 'cancel_group', $groupId);
	}

	public function assignTechnician(int $maintenanceId, ?string $technicianUid, ?string $technicianName, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		[$technicianUid, $resolvedName] = $this->resolveTechnician($technicianUid, $technicianName);
		return $this->transactional(function () use ($maintenance, $technicianUid, $resolvedName, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->updateTechnicianIfCurrent((int)$maintenance->getId(), (string)$maintenance->getEstado(), $technicianUid, $resolvedName, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'technician_changed', $maintenance->getTecnicoUid(), $technicianUid, null, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'assign_technician', (int)$maintenance->getIdGrupo(), $maintenanceId);
	}

	public function assignGroupTechnician(int $groupId, ?string $technicianUid, ?string $technicianName, string $actorUid, string $actorName): array {
		$group = $this->getMutableGroup($groupId);
		[$technicianUid, $resolvedName] = $this->resolveTechnician($technicianUid, $technicianName);
		return $this->transactional(function () use ($group, $groupId, $technicianUid, $resolvedName, $actorUid, $actorName): array {
			$this->groupMapper->updateBasic($groupId, ['tecnico_uid' => $technicianUid, 'tecnico_nombre' => $resolvedName]);
			foreach ($this->maintenanceMapper->findByGroup($groupId) as $maintenance) {
				if (!in_array((string)$maintenance->getEstado(), MantenimientoEquipo::ESTADOS_ACTIVOS, true)) continue;
				$updated = $this->maintenanceMapper->updateTechnicianIfCurrent((int)$maintenance->getId(), (string)$maintenance->getEstado(), $technicianUid, $resolvedName, $actorUid);
				$this->assertConcurrentUpdate($updated);
				$this->recordStateAudit($updated, 'technician_changed', $maintenance->getTecnicoUid(), $technicianUid, null, $actorUid, $actorName);
			}
			$this->recordAudit($groupId, null, 'technician_changed', $group->getTecnicoUid(), $technicianUid, 'Técnico general actualizado.', $actorUid, $actorName);
			return $this->getGroup($groupId);
		}, 'assign_group_technician', $groupId);
	}

	public function updateChecklist(int $maintenanceId, array $items, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		if ((string)$maintenance->getEstado() !== MantenimientoEquipo::ESTADO_IN_PROGRESS) {
			throw new MantenimientoTransitionException('El checklist solo puede editarse durante el mantenimiento.');
		}
		if ($items === []) throw new MantenimientoValidationException('Debe indicar al menos un elemento del checklist.');
		$existing = $this->checklistMapper->listByMaintenance($maintenanceId);
		$byKey = [];
		foreach ($existing as $item) $byKey[(string)$item->getClave()] = $item;
		$normalized = [];
		foreach ($items as $item) {
			$key = trim((string)($item['clave'] ?? ''));
			if ($key === '' || !isset($byKey[$key])) throw new MantenimientoValidationException('El checklist contiene una clave inexistente.');
			$result = (string)($item['resultado'] ?? '');
			if (!in_array($result, MantenimientoChecklist::RESULTADOS_VALIDOS, true)) throw new MantenimientoValidationException('El checklist contiene un resultado inválido.');
			$observation = $this->nullableText($item['observacion'] ?? null, self::MAX_OBSERVATION_LENGTH, 'La observación del checklist');
			if ($result === MantenimientoChecklist::RESULTADO_ATTENTION && $observation === null) throw new MantenimientoValidationException('Los elementos con atención requieren observación.');
			$normalized[$key] = [$byKey[$key], $result, $observation];
		}

		return $this->transactional(function () use ($maintenanceId, $maintenance, $normalized, $actorUid, $actorName): array {
			foreach ($normalized as [$existing, $result, $observation]) {
				$this->checklistMapper->updateResponse((int)$existing->getId(), $result, $observation, $actorUid);
			}
			$this->recordStateAudit($maintenance, 'checklist_updated', null, (string)count($normalized), 'Checklist actualizado parcialmente.', $actorUid, $actorName);
			return array_map(fn(MantenimientoChecklist $item): array => $item->jsonSerialize(), $this->checklistMapper->listByMaintenance($maintenanceId));
		}, 'update_checklist', (int)$maintenance->getIdGrupo(), $maintenanceId);
	}

	public function updateWorkDetails(int $maintenanceId, array $details, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		if ((string)$maintenance->getEstado() !== MantenimientoEquipo::ESTADO_IN_PROGRESS) {
			throw new MantenimientoTransitionException('Los resultados preliminares solo pueden editarse durante el mantenimiento.');
		}
		$data = $this->validateWorkDetails($details, false);
		return $this->transactional(function () use ($maintenance, $data, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->saveResultIfCurrent((int)$maintenance->getId(), (string)$maintenance->getEstado(), $data, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'result_updated', null, implode(',', array_keys($data)), 'Resultados preliminares actualizados.', $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'update_work_details', (int)$maintenance->getIdGrupo(), $maintenanceId);
	}

	private function finishWithoutExecution(int $maintenanceId, string $targetStatus, string $auditType, string $reason, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		$this->assertTransitionAllowed((string)$maintenance->getEstado(), $targetStatus);
		$reason = $this->requiredText($reason, 'El motivo', self::MAX_OBSERVATION_LENGTH);
		return $this->transactional(function () use ($maintenance, $targetStatus, $auditType, $reason, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->updateStatusIfCurrent((int)$maintenance->getId(), (string)$maintenance->getEstado(), $targetStatus, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, $auditType, (string)$maintenance->getEstado(), $targetStatus, $reason, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, $auditType, (int)$maintenance->getIdGrupo(), $maintenanceId);
	}

	private function validateGroupData(array $data): array {
		$title = $this->requiredText((string)($data['titulo'] ?? ''), 'El título', 255);
		$type = trim((string)($data['tipo'] ?? ''));
		$this->assertType($type);
		$periodStart = trim((string)($data['fecha_inicio'] ?? $data['fecha_programada'] ?? ''));
		$periodEnd = trim((string)($data['fecha_fin'] ?? $data['fecha_programada'] ?? ''));
		$this->assertDate($periodStart, 'La fecha inicial del periodo');
		$this->assertDate($periodEnd, 'La fecha final del periodo');
		if ($periodEnd < $periodStart) throw new MantenimientoValidationException('La fecha final no puede ser anterior a la fecha inicial.');
		$startDate = new \DateTimeImmutable($periodStart);
		$endDate = new \DateTimeImmutable($periodEnd);
		if ((int)$startDate->diff($endDate)->format('%a') >= self::MAX_CAMPAIGN_DAYS) {
			throw new MantenimientoValidationException('El periodo de la campaña no puede exceder 31 días.');
		}
		[$start, $end] = $this->validateTimes($data['hora_inicio'] ?? null, $data['hora_fin'] ?? null);
		$description = $this->nullableText($data['descripcion'] ?? null, self::MAX_TEXT_LENGTH, 'La descripción');
		$departmentId = isset($data['id_departamento']) && $data['id_departamento'] !== '' ? (int)$data['id_departamento'] : null;
		if ($departmentId !== null && $departmentId <= 0) throw new MantenimientoValidationException('El departamento seleccionado no es válido.');
		if ($departmentId === null && $type !== self::TYPE_SPECIAL) throw new MantenimientoValidationException('El departamento es obligatorio salvo en campañas especiales.');
		$departmentName = null;
		if ($departmentId !== null) {
			$row = $this->departmentMapper->findDepartmentRow($departmentId);
			if ($row === null) throw new MantenimientoValidationException('El departamento seleccionado no existe.');
			$departmentName = (string)($row['Nombre'] ?? $row['nombre'] ?? '');
		}
		[$technicianUid, $technicianName] = $this->resolveTechnician($data['tecnico_uid'] ?? null, $data['tecnico_nombre'] ?? null);
		$adminStatus = (string)($data['estado_admin'] ?? MantenimientoGrupo::ESTADO_ACTIVE);
		if ($adminStatus !== MantenimientoGrupo::ESTADO_ACTIVE) throw new MantenimientoValidationException('La campaña debe crearse en estado activo.');
		return [
			'titulo' => $title, 'tipo' => $type, 'fecha_inicio' => $periodStart, 'fecha_fin' => $periodEnd,
			'hora_inicio' => $start, 'hora_fin' => $end, 'descripcion' => $description,
			'id_departamento' => $departmentId, 'departamento_nombre' => $departmentName,
			'include_descendants' => (bool)($data['include_descendants'] ?? false),
			'tecnico_uid' => $technicianUid, 'tecnico_nombre' => $technicianName,
		];
	}

	private function validateEquipmentIds(array $equipmentIds): array {
		if ($equipmentIds === []) throw new MantenimientoValidationException('Debe seleccionar al menos un equipo.');
		$normalized = array_map('intval', $equipmentIds);
		if (array_filter($normalized, static fn(int $id): bool => $id <= 0) !== []) throw new MantenimientoValidationException('La lista contiene un identificador de equipo inválido.');
		if (count($normalized) !== count(array_unique($normalized))) throw new MantenimientoValidationException('La petición contiene equipos repetidos.');
		return array_values($normalized);
	}

	private function resolveEquipment(array $requestedIds, array $group, array $departmentIds): array {
		$rows = $this->inventoryMapper->findCampaignEquipmentByIds($requestedIds);
		$byId = [];
		foreach ($rows as $row) $byId[(int)$row['id_equipo']] = $row;
		$missing = array_values(array_diff($requestedIds, array_keys($byId)));
		if ($missing !== []) throw new MantenimientoNotFoundException('Uno o más equipos seleccionados no existen.');
		$resolved = [];
		foreach ($requestedIds as $id) {
			$row = $byId[$id];
			if (in_array(strtolower((string)($row['estado'] ?? '')), self::EXCLUDED_EQUIPMENT_STATES, true)) {
				throw new MantenimientoValidationException('Uno de los equipos seleccionados está dado de baja o eliminado.');
			}
			$employeeId = $this->nullableInt($row['id_empleado'] ?? null);
			$currentDepartment = $this->nullableInt($row['id_departamento'] ?? null);
			if ($group['id_departamento'] !== null && ($currentDepartment === null || !in_array($currentDepartment, $departmentIds, true))) {
				throw new MantenimientoConflictException('Un equipo ya no pertenece al departamento seleccionado.', [['id_equipo' => $id]]);
			}
			if ($employeeId === null && $group['id_departamento'] !== null) {
				throw new MantenimientoConflictException('Un equipo sin custodio no puede incluirse en una campaña departamental.', [['id_equipo' => $id]]);
			}
			$resolved[] = $row;
		}
		return $resolved;
	}

	private function findDuplicateWarnings(array $equipment, string $type, string $periodStart, string $periodEnd): array {
		$warnings = [];
		foreach ($equipment as $row) {
			foreach ($this->maintenanceMapper->findPossibleDuplicates((int)$row['id_equipo'], $type, $periodStart, $periodEnd) as $duplicate) {
				$existingGroup = $this->getGroupEntity((int)$duplicate->getIdGrupo());
				$existingStart = $this->groupPeriodStart($existingGroup, $duplicate);
				$existingEnd = $this->groupPeriodEnd($existingGroup, $duplicate);
				$warnings[] = [
					'groupId' => (int)$duplicate->getIdGrupo(),
					'equipmentId' => (int)$duplicate->getIdEquipo(),
					'existingPeriodStart' => $existingStart,
					'existingPeriodEnd' => $existingEnd,
					'status' => (string)$duplicate->getEstado(),
					'type' => (string)$duplicate->getTipo(),
					'id_equipo' => (int)$duplicate->getIdEquipo(),
					'id_mantenimiento_existente' => (int)$duplicate->getId(),
					'id_grupo_existente' => (int)$duplicate->getIdGrupo(),
					'tipo' => (string)$duplicate->getTipo(),
					'fecha_programada' => $duplicate->getFechaProgramada(),
					'estado' => (string)$duplicate->getEstado(),
				];
			}
		}
		return $warnings;
	}

	private function buildMaintenanceSnapshot(int $groupId, array $equipment, array $group, string $actorUid, string $now): array {
		$name = trim((string)($equipment['nombre_dispositivo'] ?? ''));
		if ($name === '') $name = trim((string)($equipment['nombre_sistema'] ?? ''));
		if ($name === '') $name = 'Equipo #' . (int)$equipment['id_equipo'];
		$model = trim(implode(' ', array_filter([(string)($equipment['marca'] ?? ''), (string)($equipment['modelo'] ?? '')])));
		return [
			'id_grupo' => $groupId,
			'id_equipo' => (int)$equipment['id_equipo'],
			'equipo_nombre' => $name,
			'equipo_identificador' => $this->emptyToNull($equipment['nombre_sistema'] ?? null),
			'id_modelo' => $this->nullableInt($equipment['id_modelo'] ?? null),
			'modelo_nombre' => $model === '' ? null : $model,
			'numero_serie' => $this->emptyToNull($equipment['numero_serie'] ?? null),
			'id_empleado' => $this->nullableInt($equipment['id_empleado'] ?? null),
			'empleado_uid' => $this->emptyToNull($equipment['empleado_uid'] ?? null),
			'empleado_nombre' => $this->emptyToNull($equipment['empleado_nombre'] ?? null),
			'id_departamento' => $this->nullableInt($equipment['id_departamento'] ?? null),
			'departamento_nombre' => $this->emptyToNull($equipment['departamento_nombre'] ?? null),
			'tecnico_uid' => $group['tecnico_uid'], 'tecnico_nombre' => $group['tecnico_nombre'],
			'tipo' => $group['tipo'], 'fecha_programada' => null,
			'hora_inicio_programada' => null, 'hora_fin_programada' => null,
			'estado' => MantenimientoEquipo::ESTADO_PENDING,
			'creado_por' => $actorUid, 'actualizado_por' => $actorUid,
			'fecha_creacion' => $now, 'fecha_actualizacion' => $now,
		];
	}

	private function calculateProgress(MantenimientoGrupo $group, array $counts): array {
		$total = (int)$counts['total'];
		$attended = (int)$counts['completed'] + (int)$counts['cancelled'] + (int)$counts['not_applicable'];
		$active = (int)$counts['pending'] + (int)$counts['scheduled'] + (int)$counts['in_progress'] + (int)$counts['rescheduled'];
		if ((string)$group->getEstadoAdmin() === MantenimientoGrupo::ESTADO_CANCELLED || ($total > 0 && (int)$counts['cancelled'] === $total)) {
			$status = 'cancelled';
		} elseif ($total === 0) {
			$status = 'partial';
		} elseif ($active === 0 && (int)$counts['completed'] > 0) {
			$status = 'completed';
		} elseif ($active === 0) {
			$status = 'partial';
		} elseif ((int)$counts['in_progress'] > 0 || (int)$counts['completed'] > 0) {
			$status = 'in_progress';
		} else {
			$status = 'pending';
		}
		$counts['operational_status'] = $status;
		$counts['percentage'] = $total === 0 ? 0.0 : round(($attended / $total) * 100, 2);
		return $counts;
	}

	private function assertTransitionAllowed(string $from, string $to): void {
		if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
			throw new MantenimientoTransitionException('La transición de estado solicitada no está permitida.');
		}
	}

	private function validateChecklistForCompletion(MantenimientoEquipo $maintenance): void {
		if ((string)$maintenance->getTipo() !== self::TYPE_PREVENTIVE) return;
		$items = $this->checklistMapper->listByMaintenance((int)$maintenance->getId());
		if ($items === []) throw new MantenimientoValidationException('El mantenimiento preventivo no tiene checklist.');
		foreach ($items as $item) {
			if ((string)$item->getResultado() === MantenimientoChecklist::RESULTADO_PENDING) throw new MantenimientoValidationException('Debe atender todo el checklist antes de completar.');
			if ((string)$item->getResultado() === MantenimientoChecklist::RESULTADO_ATTENTION && trim((string)$item->getObservacion()) === '') throw new MantenimientoValidationException('Los elementos con atención requieren observación.');
		}
	}

	private function validateWorkDetails(array $data, bool $forCompletion): array {
		$allowed = ['resultado', 'acciones_realizadas', 'incidencias', 'repuestos', 'observaciones', 'proxima_fecha'];
		$unknown = array_diff(array_keys($data), $allowed);
		if ($unknown !== []) throw new MantenimientoValidationException('Se enviaron campos de resultado no permitidos.');
		$result = [];
		foreach (array_diff($allowed, ['proxima_fecha']) as $field) {
			if (array_key_exists($field, $data)) $result[$field] = $this->nullableText($data[$field], self::MAX_TEXT_LENGTH, 'El campo ' . $field);
		}
		if ($forCompletion) {
			$result['resultado'] = $this->requiredText((string)($data['resultado'] ?? ''), 'El resultado', self::MAX_TEXT_LENGTH);
			$result['acciones_realizadas'] = $this->requiredText((string)($data['acciones_realizadas'] ?? ''), 'Las acciones realizadas', self::MAX_TEXT_LENGTH);
		}
		if (array_key_exists('proxima_fecha', $data)) {
			$result['proxima_fecha'] = $this->emptyToNull($data['proxima_fecha']);
			if ($result['proxima_fecha'] !== null) $this->assertDate($result['proxima_fecha'], 'La próxima fecha');
		}
		if (!$forCompletion && $result === []) throw new MantenimientoValidationException('No se enviaron resultados para actualizar.');
		return $result;
	}

	private function getMutableMaintenance(int $id): MantenimientoEquipo {
		$maintenance = $this->getMaintenanceEntity($id);
		$this->getMutableGroup((int)$maintenance->getIdGrupo());
		if (in_array((string)$maintenance->getEstado(), self::FINAL_STATES, true)) throw new MantenimientoTransitionException('El mantenimiento se encuentra en un estado final.');
		return $maintenance;
	}

	private function getMutableGroup(int $id): MantenimientoGrupo {
		$group = $this->getGroupEntity($id);
		if ((string)$group->getEstadoAdmin() === MantenimientoGrupo::ESTADO_CANCELLED) throw new MantenimientoTransitionException('La campaña está cancelada.');
		return $group;
	}

	private function getMaintenanceEntity(int $id): MantenimientoEquipo {
		try {
			return $this->maintenanceMapper->findById($id);
		} catch (DoesNotExistException $e) {
			throw new MantenimientoNotFoundException('El mantenimiento solicitado no existe.', 0, $e);
		}
	}

	private function getGroupEntity(int $id): MantenimientoGrupo {
		try {
			return $this->groupMapper->findById($id);
		} catch (DoesNotExistException $e) {
			throw new MantenimientoNotFoundException('La campaña solicitada no existe.', 0, $e);
		}
	}

	private function assertConcurrentUpdate(?MantenimientoEquipo $maintenance): void {
		if ($maintenance === null) throw new MantenimientoConflictException('El mantenimiento cambió durante la operación.');
	}

	private function recordStateAudit(MantenimientoEquipo $maintenance, string $type, ?string $oldValue, ?string $newValue, ?string $comment, string $actorUid, string $actorName): void {
		$this->recordAudit((int)$maintenance->getIdGrupo(), (int)$maintenance->getId(), $type, $oldValue, $newValue, $comment, $actorUid, $actorName);
	}

	private function recordAudit(int $groupId, ?int $maintenanceId, string $type, ?string $oldValue, ?string $newValue, ?string $comment, string $actorUid, string $actorName): void {
		$this->changeMapper->recordChange([
			'id_grupo' => $groupId, 'id_mantenimiento' => $maintenanceId, 'tipo_cambio' => $type,
			'valor_anterior' => $oldValue, 'valor_nuevo' => $newValue, 'comentario' => $comment,
			'usuario_uid' => $actorUid, 'usuario_nombre' => trim($actorName) !== '' ? trim($actorName) : $actorUid,
			'fecha' => $this->now(),
		]);
	}

	private function resolveTechnician(mixed $uid, mixed $fallbackName): array {
		$uid = $this->emptyToNull($uid);
		if ($uid === null) return [null, null];
		$user = $this->userManager->get($uid);
		if ($user === null || !$user->isEnabled()) throw new MantenimientoValidationException('El técnico seleccionado no existe o está deshabilitado.');
		return [$uid, trim((string)$user->getDisplayName()) ?: ($this->emptyToNull($fallbackName) ?? $uid)];
	}

	private function assertType(string $type): void {
		if (!in_array($type, self::TYPES, true)) throw new MantenimientoValidationException('El tipo de mantenimiento no es válido.');
	}

	private function assertDate(string $date, string $label): void {
		$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
		$errors = \DateTimeImmutable::getLastErrors();
		if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d') !== $date) {
			throw new MantenimientoValidationException($label . ' no es válida.');
		}
	}

	private function validateTimes(mixed $start, mixed $end): array {
		$start = $this->normalizeTime($start);
		$end = $this->normalizeTime($end);
		if (($start === null) !== ($end === null)) throw new MantenimientoValidationException('Las horas inicial y final deben proporcionarse juntas.');
		if ($start !== null && $end <= $start) throw new MantenimientoValidationException('La hora final debe ser posterior a la inicial.');
		return [$start, $end];
	}

	private function normalizeTime(mixed $value): ?string {
		$value = $this->emptyToNull($value);
		if ($value === null) return null;
		if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) throw new MantenimientoValidationException('El horario no tiene un formato válido.');
		return strlen($value) === 5 ? $value . ':00' : $value;
	}

	private function requiredText(string $value, string $label, int $maxLength): string {
		$value = trim($value);
		if ($value === '') throw new MantenimientoValidationException($label . ' es obligatorio.');
		if (mb_strlen($value) > $maxLength) throw new MantenimientoValidationException($label . ' excede la longitud permitida.');
		return $value;
	}

	private function nullableText(mixed $value, int $maxLength, string $label): ?string {
		$value = $this->emptyToNull($value);
		if ($value !== null && mb_strlen($value) > $maxLength) throw new MantenimientoValidationException($label . ' excede la longitud permitida.');
		return $value;
	}

	private function emptyToNull(mixed $value): ?string {
		if ($value === null) return null;
		$value = trim((string)$value);
		return $value === '' ? null : $value;
	}

	private function nullableInt(mixed $value): ?int {
		return $value === null || $value === '' ? null : (int)$value;
	}

	private function scheduleAuditValue(MantenimientoEquipo $maintenance): string {
		return implode('|', [(string)$maintenance->getFechaProgramada(), (string)$maintenance->getHoraInicioProgramada(), (string)$maintenance->getHoraFinProgramada()]);
	}

	private function groupPeriodStart(MantenimientoGrupo $group, ?MantenimientoEquipo $maintenance = null): ?string {
		return $this->emptyToNull($group->getFechaInicio())
			?? $this->emptyToNull($group->getFechaProgramada())
			?? ($maintenance === null ? null : $this->emptyToNull($maintenance->getFechaProgramada()));
	}

	private function groupPeriodEnd(MantenimientoGrupo $group, ?MantenimientoEquipo $maintenance = null): ?string {
		return $this->emptyToNull($group->getFechaFin()) ?? $this->groupPeriodStart($group, $maintenance);
	}

	private function now(): string {
		return $this->timeFactory->now()->format('Y-m-d H:i:s');
	}

	private function today(): string {
		return $this->timeFactory->now()->format('Y-m-d');
	}

	private function transactional(callable $operation, string $operationName, ?int $groupId = null, ?int $maintenanceId = null): mixed {
		$this->db->beginTransaction();
		try {
			$result = $operation();
			$this->db->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			$previous = $e->getPrevious();
			$this->logger->error('Falló una operación de mantenimiento.', [
				'operacion' => $operationName,
				'id_grupo' => $groupId,
				'id_mantenimiento' => $maintenanceId,
				'exceptionClass' => $e::class,
				'exceptionMessage' => $e->getMessage(),
				'exceptionCode' => $e->getCode(),
				'exceptionFile' => $e->getFile(),
				'exceptionLine' => $e->getLine(),
				'previousExceptionClass' => $previous === null ? null : $previous::class,
				'previousExceptionMessage' => $previous?->getMessage(),
				'exception' => $e,
			]);
			if ($e instanceof MantenimientoValidationException || $e instanceof MantenimientoNotFoundException || $e instanceof MantenimientoConflictException) throw $e;
			$message = strtolower($e->getMessage());
			if (str_contains($message, 'unique') || str_contains($message, 'duplicate')) {
				throw new MantenimientoConflictException('La operación entra en conflicto con otro mantenimiento existente.', [], $e);
			}
			throw new MantenimientoStorageException('No fue posible guardar los cambios de mantenimiento.', 0, $e);
		}
	}
}
