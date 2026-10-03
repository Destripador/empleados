<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\MantenimientoEquipo;
use OCA\Empleados\Db\MantenimientoGrupo;
use OCA\Empleados\Exception\MantenimientoConflictException;
use OCA\Empleados\Exception\MantenimientoNotFoundException;
use OCA\Empleados\Exception\MantenimientoStorageException;
use OCA\Empleados\Exception\MantenimientoTransitionException;
use OCA\Empleados\Exception\MantenimientoValidationException;
use OCA\Empleados\Service\MantenimientoService;
use OCA\Empleados\Service\PermisosService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class MantenimientoController extends BaseController {
	private const DEFAULT_LIMIT = 50;
	private const MAX_LIMIT = 200;
	private const MAX_EQUIPMENT_IDS = 1000;
	private const MAX_CALENDAR_DAYS = 366;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		private MantenimientoService $maintenanceService,
		private PermisosService $permissions,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function technicians(): DataResponse {
		return $this->execute(function (array $actor): array {
			$this->requireReadRole($actor['uid']);
			$uids = array_unique(array_merge(
				$this->permissions->getUsersWithPermission('inventario.technician'),
				$this->permissions->getUsersWithPermission('inventario.admin'),
			));
			$items = [];
			foreach ($uids as $uid) {
				$uid = trim((string)$uid);
				if ($uid === '') continue;
				$user = $this->userManager->get($uid);
				if ($user === null || !$user->isEnabled()) continue;
				$displayName = trim((string)$user->getDisplayName());
				$items[] = ['uid' => $uid, 'displayName' => $displayName !== '' ? $displayName : $uid];
			}
			usort($items, static fn(array $left, array $right): int => strnatcasecmp($left['displayName'], $right['displayName']) ?: strnatcasecmp($left['uid'], $right['uid']));
			return ['items' => $items];
		}, 'list_technicians');
	}

	#[UseSession]
	#[NoAdminRequired]
	public function groups(mixed $start = null, mixed $end = null, mixed $departmentId = null, mixed $technicianUid = null, mixed $type = null, mixed $status = null, mixed $search = null, mixed $limit = self::DEFAULT_LIMIT, mixed $offset = 0): DataResponse {
		return $this->execute(function (array $actor) use ($start, $end, $departmentId, $technicianUid, $type, $status, $search, $limit, $offset): array {
			$role = $this->requireReadRole($actor['uid']);
			[$from, $to] = $this->normalizeDateRange($start, $end, true);
			$technician = $role === 'technician' ? $actor['uid'] : $this->normalizeNullableString($technicianUid, 255);
			$result = $this->maintenanceService->listGroups(
				$from, $to, $this->normalizeNullableId($departmentId), $technician,
				$this->normalizeNullableString($type, 40), $this->normalizeGroupStatus($status),
				$this->normalizeNullableString($search, 255), $this->normalizeLimit($limit), $this->normalizeOffset($offset),
			);
			$result['items'] = array_map(fn(array $group): array => $this->sanitizeGroupSummary($group), $result['items']);
			return $result;
		}, 'list_groups');
	}

	#[UseSession]
	#[NoAdminRequired]
	public function createGroup(mixed $title = null, mixed $departmentId = null, mixed $includeDescendants = false, mixed $type = null, mixed $periodStart = null, mixed $periodEnd = null, mixed $startTime = null, mixed $endTime = null, mixed $technicianUid = null, mixed $description = null, mixed $equipmentIds = [], mixed $allowPotentialDuplicates = false, mixed $scheduledDate = null): DataResponse {
		return $this->execute(function (array $actor) use ($title, $departmentId, $includeDescendants, $type, $periodStart, $periodEnd, $startTime, $endTime, $technicianUid, $description, $equipmentIds, $allowPotentialDuplicates, $scheduledDate): array {
			$this->requireAdmin($actor['uid']);
			$technicianUid = $this->normalizeNullableString($technicianUid, 255);
			$this->requireEligibleTechnician($technicianUid);
			$ids = $this->normalizeIdArray($equipmentIds);
			// Temporary compatibility: the former scheduledDate represents a one-day period.
			$normalizedPeriodStart = $periodStart ?? $scheduledDate;
			$normalizedPeriodEnd = $periodEnd ?? $scheduledDate;
			return $this->maintenanceService->createGroup([
				'titulo' => $this->normalizeRequiredString($title, 255, 'El título'),
				'id_departamento' => $this->normalizeNullableId($departmentId),
				'include_descendants' => $this->normalizeBoolean($includeDescendants),
				'tipo' => $this->normalizeRequiredString($type, 40, 'El tipo'),
				'fecha_inicio' => $this->normalizeDate($normalizedPeriodStart, 'La fecha inicial del periodo'),
				'fecha_fin' => $this->normalizeDate($normalizedPeriodEnd, 'La fecha final del periodo'),
				'hora_inicio' => $this->normalizeNullableTime($startTime),
				'hora_fin' => $this->normalizeNullableTime($endTime),
				'tecnico_uid' => $technicianUid,
				'descripcion' => $this->normalizeNullableString($description, 65535),
			], $ids, $actor['uid'], $actor['displayName'], $this->normalizeBoolean($allowPotentialDuplicates));
		}, 'create_group', Http::STATUS_CREATED);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function group(mixed $id): DataResponse {
		return $this->execute(function (array $actor) use ($id): array {
			$groupId = $this->normalizePositiveId($id, 'grupo');
			$this->requireGroupReadAccess($groupId, $actor['uid']);
			$result = $this->maintenanceService->getGroupSummary($groupId);
			$result['group'] = $this->sanitizeGroupSummary($result['group']);
			return $result;
		}, 'get_group', Http::STATUS_OK, ['groupId' => $id]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function groupMaintenances(mixed $id, mixed $status = null, mixed $technicianUid = null, mixed $search = null, mixed $limit = self::DEFAULT_LIMIT, mixed $offset = 0): DataResponse {
		return $this->execute(function (array $actor) use ($id, $status, $technicianUid, $search, $limit, $offset): array {
			$groupId = $this->normalizePositiveId($id, 'grupo');
			$role = $this->requireGroupReadAccess($groupId, $actor['uid']);
			$technician = $role === 'technician' ? $actor['uid'] : $this->normalizeNullableString($technicianUid, 255);
			$result = $this->maintenanceService->listGroupMaintenancesFiltered(
				$groupId, $this->normalizeMaintenanceStatus($status), $technician,
				$this->normalizeNullableString($search, 255), $this->normalizeLimit($limit), $this->normalizeOffset($offset),
			);
			$result['items'] = array_map(fn(array $item): array => $this->sanitizeMaintenanceSummary($item), $result['items']);
			return $result;
		}, 'list_group_maintenances', Http::STATUS_OK, ['groupId' => $id]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function cancelGroup(mixed $id, mixed $reason = null): DataResponse {
		return $this->execute(function (array $actor) use ($id, $reason): array {
			$this->requireAdmin($actor['uid']);
			return $this->maintenanceService->cancelGroup(
				$this->normalizePositiveId($id, 'grupo'), $this->normalizeRequiredString($reason, 4000, 'El motivo'),
				$actor['uid'], $actor['displayName'],
			);
		}, 'cancel_group', Http::STATUS_OK, ['groupId' => $id]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function assignGroupTechnician(mixed $id, mixed $technicianUid = null): DataResponse {
		return $this->execute(function (array $actor) use ($id, $technicianUid): array {
			$this->requireAdmin($actor['uid']);
			$technicianUid = $this->normalizeNullableString($technicianUid, 255);
			$this->requireEligibleTechnician($technicianUid);
			return $this->maintenanceService->assignGroupTechnician(
				$this->normalizePositiveId($id, 'grupo'), $technicianUid, null,
				$actor['uid'], $actor['displayName'],
			);
		}, 'assign_group_technician', Http::STATUS_OK, ['groupId' => $id]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function eligibleEquipment(mixed $id, mixed $includeDescendants = false, mixed $includeInactive = false, mixed $search = null, mixed $limit = self::DEFAULT_LIMIT, mixed $offset = 0): DataResponse {
		return $this->execute(function (array $actor) use ($id, $includeDescendants, $includeInactive, $search, $limit, $offset): array {
			$this->requireAdmin($actor['uid']);
			$result = $this->maintenanceService->listEligibleEquipmentByDepartment(
				$this->normalizePositiveId($id, 'departamento'), $this->normalizeBoolean($includeDescendants),
				$this->normalizeBoolean($includeInactive), $this->normalizeNullableString($search, 255),
				$this->normalizeLimit($limit), $this->normalizeOffset($offset),
			);
			$result['items'] = array_map(fn(array $row): array => $this->normalizeEligibleEquipment($row), $result['items']);
			return $result;
		}, 'eligible_equipment');
	}

	#[UseSession]
	#[NoAdminRequired]
	public function maintenance(mixed $id, mixed $auditLimit = self::DEFAULT_LIMIT, mixed $auditOffset = 0): DataResponse {
		return $this->execute(function (array $actor) use ($id, $auditLimit, $auditOffset): array {
			$maintenanceId = $this->normalizePositiveId($id, 'mantenimiento');
			$record = $this->maintenanceService->getMaintenanceRecord($maintenanceId);
			$role = $this->requireMaintenanceReadAccess($record, $actor['uid']);
			$result = $this->maintenanceService->getMaintenance($maintenanceId, $this->normalizeLimit($auditLimit), $this->normalizeOffset($auditOffset));
			if ($role === 'view') $result = $this->sanitizeReadOnlyDetail($result);
			return $result;
		}, 'get_maintenance', Http::STATUS_OK, ['maintenanceId' => $id]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function start(mixed $id): DataResponse {
		return $this->maintenanceWrite($id, 'start', fn(int $maintenanceId, array $actor): array => $this->maintenanceService->startMaintenance($maintenanceId, $actor['uid'], $actor['displayName']));
	}

	#[UseSession]
	#[NoAdminRequired]
	public function schedule(mixed $id, mixed $scheduledDate = null, mixed $startTime = null, mixed $endTime = null): DataResponse {
		return $this->maintenanceWrite($id, 'schedule', fn(int $maintenanceId, array $actor): array => $this->maintenanceService->scheduleMaintenance(
			$maintenanceId, $this->normalizeDate($scheduledDate, 'La fecha programada'),
			$this->normalizeNullableTime($startTime), $this->normalizeNullableTime($endTime),
			$actor['uid'], $actor['displayName'],
		));
	}

	#[UseSession]
	#[NoAdminRequired]
	public function complete(mixed $id, mixed $resultado = null, mixed $accionesRealizadas = null, mixed $incidencias = null, mixed $repuestos = null, mixed $observaciones = null, mixed $proximaFecha = null): DataResponse {
		return $this->maintenanceWrite($id, 'complete', function (int $maintenanceId, array $actor) use ($resultado, $accionesRealizadas, $incidencias, $repuestos, $observaciones, $proximaFecha): array {
			return $this->maintenanceService->completeMaintenance($maintenanceId, $this->normalizeWorkDetails([
				'resultado' => $resultado, 'acciones_realizadas' => $accionesRealizadas,
				'incidencias' => $incidencias, 'repuestos' => $repuestos,
				'observaciones' => $observaciones, 'proxima_fecha' => $proximaFecha,
			], true), $actor['uid'], $actor['displayName']);
		});
	}

	#[UseSession]
	#[NoAdminRequired]
	public function reschedule(mixed $id, mixed $scheduledDate = null, mixed $startTime = null, mixed $endTime = null, mixed $reason = null): DataResponse {
		return $this->maintenanceWrite($id, 'reschedule', fn(int $maintenanceId, array $actor): array => $this->maintenanceService->rescheduleMaintenance(
			$maintenanceId, $this->normalizeDate($scheduledDate, 'La fecha programada'),
			$this->normalizeNullableTime($startTime), $this->normalizeNullableTime($endTime),
			$this->normalizeRequiredString($reason, 4000, 'El motivo'), $actor['uid'], $actor['displayName'],
		));
	}

	#[UseSession]
	#[NoAdminRequired]
	public function cancel(mixed $id, mixed $reason = null): DataResponse {
		return $this->execute(function (array $actor) use ($id, $reason): array {
			$this->requireAdmin($actor['uid']);
			$maintenance = $this->maintenanceService->cancelMaintenance(
				$this->normalizePositiveId($id, 'mantenimiento'), $this->normalizeRequiredString($reason, 4000, 'El motivo'),
				$actor['uid'], $actor['displayName'],
			);
			return $this->withProgress($maintenance);
		}, 'cancel_maintenance', Http::STATUS_OK, ['maintenanceId' => $id]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function notApplicable(mixed $id, mixed $reason = null): DataResponse {
		return $this->maintenanceWrite($id, 'not_applicable', fn(int $maintenanceId, array $actor): array => $this->maintenanceService->markNotApplicable(
			$maintenanceId, $this->normalizeRequiredString($reason, 4000, 'El motivo'), $actor['uid'], $actor['displayName'],
		));
	}

	#[UseSession]
	#[NoAdminRequired]
	public function assignTechnician(mixed $id, mixed $technicianUid = null): DataResponse {
		return $this->execute(function (array $actor) use ($id, $technicianUid): array {
			$this->requireAdmin($actor['uid']);
			$technicianUid = $this->normalizeNullableString($technicianUid, 255);
			$this->requireEligibleTechnician($technicianUid);
			$maintenance = $this->maintenanceService->assignTechnician(
				$this->normalizePositiveId($id, 'mantenimiento'), $technicianUid, null,
				$actor['uid'], $actor['displayName'],
			);
			return $this->withProgress($maintenance);
		}, 'assign_technician', Http::STATUS_OK, ['maintenanceId' => $id]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function updateChecklist(mixed $id, mixed $items = []): DataResponse {
		return $this->execute(function (array $actor) use ($id, $items): array {
			$maintenanceId = $this->normalizePositiveId($id, 'mantenimiento');
			$record = $this->maintenanceService->getMaintenanceRecord($maintenanceId);
			$this->requireMaintenanceWriteAccess($record, $actor['uid']);
			if (!is_array($items)) throw new MantenimientoValidationException('El checklist debe ser una lista.');
			$normalized = [];
			foreach ($items as $item) {
				if (!is_array($item)) throw new MantenimientoValidationException('Cada elemento del checklist debe ser un objeto.');
				$normalized[] = [
					'clave' => $this->normalizeRequiredString($item['key'] ?? null, 80, 'La clave'),
					'resultado' => $this->normalizeRequiredString($item['result'] ?? null, 24, 'El resultado'),
					'observacion' => $this->normalizeNullableString($item['observation'] ?? null, 4000),
				];
			}
			return ['checklist' => $this->maintenanceService->updateChecklist($maintenanceId, $normalized, $actor['uid'], $actor['displayName'])];
		}, 'update_checklist', Http::STATUS_OK, ['maintenanceId' => $id]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function updateWork(mixed $id, mixed $resultado = null, mixed $accionesRealizadas = null, mixed $incidencias = null, mixed $repuestos = null, mixed $observaciones = null, mixed $proximaFecha = null): DataResponse {
		return $this->maintenanceWrite($id, 'update_work', function (int $maintenanceId, array $actor) use ($resultado, $accionesRealizadas, $incidencias, $repuestos, $observaciones, $proximaFecha): array {
			return $this->maintenanceService->updateWorkDetails($maintenanceId, $this->normalizeWorkDetails([
				'resultado' => $resultado, 'acciones_realizadas' => $accionesRealizadas,
				'incidencias' => $incidencias, 'repuestos' => $repuestos,
				'observaciones' => $observaciones, 'proxima_fecha' => $proximaFecha,
			], false), $actor['uid'], $actor['displayName']);
		});
	}

	#[UseSession]
	#[NoAdminRequired]
	public function equipmentHistory(mixed $id, mixed $limit = self::DEFAULT_LIMIT, mixed $offset = 0, mixed $type = null, mixed $status = null, mixed $start = null, mixed $end = null): DataResponse {
		return $this->execute(function (array $actor) use ($id, $limit, $offset, $type, $status, $start, $end): array {
			$equipmentId = $this->normalizePositiveId($id, 'equipo');
			$role = $this->requireReadRole($actor['uid']);
			if ($role === 'technician' && !$this->maintenanceService->technicianHasEquipmentAccess($equipmentId, $actor['uid'])) $this->deny('No tienes acceso al historial de este equipo.');
			[$from, $to] = $this->normalizeOptionalDateRange($start, $end);
			$result = $this->maintenanceService->getEquipmentHistoryFiltered(
				$equipmentId, $this->normalizeNullableString($type, 40), $this->normalizeMaintenanceStatus($status),
				$from, $to, $this->normalizeLimit($limit), $this->normalizeOffset($offset),
			);
			$result['items'] = array_map(fn(array $item): array => $this->sanitizeMaintenanceSummary($item), $result['items']);
			return $result;
		}, 'equipment_history');
	}

	#[UseSession]
	#[NoAdminRequired]
	public function overdue(mixed $departmentId = null, mixed $technicianUid = null, mixed $type = null, mixed $limit = self::DEFAULT_LIMIT, mixed $offset = 0): DataResponse {
		return $this->execute(function (array $actor) use ($departmentId, $technicianUid, $type, $limit, $offset): array {
			$role = $this->requireReadRole($actor['uid']);
			$technician = $role === 'technician' ? $actor['uid'] : $this->normalizeNullableString($technicianUid, 255);
			$result = $this->maintenanceService->listOverdueMaintenancesFiltered(
				$this->normalizeNullableId($departmentId), $technician, $this->normalizeNullableString($type, 40),
				$this->normalizeLimit($limit), $this->normalizeOffset($offset),
			);
			$result['items'] = array_map(fn(array $item): array => $this->sanitizeMaintenanceSummary($item), $result['items']);
			return $result;
		}, 'overdue');
	}

	#[UseSession]
	#[NoAdminRequired]
	public function duplicates(mixed $equipmentIds = [], mixed $type = null, mixed $periodStart = null, mixed $periodEnd = null, mixed $scheduledDate = null): DataResponse {
		return $this->execute(function (array $actor) use ($equipmentIds, $type, $periodStart, $periodEnd, $scheduledDate): array {
			$this->requireAdmin($actor['uid']);
			$ids = $this->normalizeIdArray($equipmentIds);
			$type = $this->normalizeRequiredString($type, 40, 'El tipo');
			$from = $this->normalizeDate($periodStart ?? $scheduledDate, 'La fecha inicial del periodo');
			$to = $this->normalizeDate($periodEnd ?? $scheduledDate, 'La fecha final del periodo');
			$items = [];
			foreach ($ids as $equipmentId) {
				$items = array_merge($items, $this->maintenanceService->listPotentialDuplicates($equipmentId, $type, $from, $to));
			}
			return ['items' => $items, 'total' => count($items)];
		}, 'duplicates');
	}

	private function maintenanceWrite(mixed $id, string $operation, callable $write): DataResponse {
		return $this->execute(function (array $actor) use ($id, $write): array {
			$maintenanceId = $this->normalizePositiveId($id, 'mantenimiento');
			$record = $this->maintenanceService->getMaintenanceRecord($maintenanceId);
			$this->requireMaintenanceWriteAccess($record, $actor['uid']);
			return $this->withProgress($write($maintenanceId, $actor));
		}, $operation, Http::STATUS_OK, ['maintenanceId' => $id]);
	}

	private function execute(callable $operation, string $operationName, int $successStatus = Http::STATUS_OK, array $context = []): DataResponse {
		$actor = null;
		try {
			$actor = $this->requireActor();
			$this->requireInventoryModule();
			return new DataResponse(['success' => true, 'data' => $operation($actor)], $successStatus);
		} catch (MantenimientoTransitionException $e) {
			return $this->error('maintenance_transition_error', $e->getMessage(), Http::STATUS_CONFLICT);
		} catch (MantenimientoConflictException $e) {
			$isDuplicate = false;
			foreach ($e->getConflicts() as $conflict) {
				if (isset($conflict['id_mantenimiento_existente'])) {
					$isDuplicate = true;
					break;
				}
			}
			$code = $isDuplicate ? 'maintenance_duplicate_conflict' : 'maintenance_conflict';
			return $this->error($code, $e->getMessage(), Http::STATUS_CONFLICT, $e->getConflicts());
		} catch (MantenimientoValidationException $e) {
			return $this->error('maintenance_validation_error', $e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (MantenimientoNotFoundException $e) {
			return $this->error('maintenance_not_found', $e->getMessage(), Http::STATUS_NOT_FOUND);
		} catch (MantenimientoStorageException $e) {
			$this->logInternal($operationName, $context, $actor, $e);
			return $this->error('maintenance_storage_error', 'No fue posible completar la operación.', Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (\DomainException $e) {
			$status = in_array($e->getCode(), [Http::STATUS_UNAUTHORIZED, Http::STATUS_FORBIDDEN], true) ? $e->getCode() : Http::STATUS_FORBIDDEN;
			return $this->error($status === Http::STATUS_UNAUTHORIZED ? 'authentication_required' : 'maintenance_access_denied', $e->getMessage(), $status);
		} catch (\Throwable $e) {
			$this->logInternal($operationName, $context, $actor, $e);
			return $this->error('maintenance_internal_error', 'Ocurrió un error interno al procesar la solicitud.', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function requireActor(): array {
		$user = $this->userSession->getUser();
		if ($user === null) throw new \DomainException('Debes iniciar sesión para continuar.', Http::STATUS_UNAUTHORIZED);
		return ['uid' => $user->getUID(), 'displayName' => $user->getDisplayName() ?: $user->getUID()];
	}

	private function requireInventoryModule(): void {
		if (!$this->permissions->isModuleEnabled('inventario')) throw new \DomainException('El módulo de inventario no está disponible.', Http::STATUS_FORBIDDEN);
	}

	private function role(string $uid): string {
		if ($this->permissions->canManageInventory($uid)) return 'admin';
		if ($this->permissions->canSee('inventario.technician', $uid)) return 'technician';
		if ($this->permissions->canSee('inventario.view', $uid)) return 'view';
		return 'none';
	}

	private function requireReadRole(string $uid): string {
		$role = $this->role($uid);
		if ($role === 'none') $this->deny();
		return $role;
	}

	private function requireAdmin(string $uid): void {
		if ($this->role($uid) !== 'admin') $this->deny();
	}

	private function requireEligibleTechnician(?string $uid): void {
		if ($uid === null) return;
		if (!$this->permissions->canManageInventory($uid) && !$this->permissions->canSee('inventario.technician', $uid)) {
			throw new MantenimientoValidationException('El usuario seleccionado no es un técnico de inventario autorizado.');
		}
	}

	private function requireGroupReadAccess(int $groupId, string $uid): string {
		$role = $this->requireReadRole($uid);
		if ($role === 'technician' && !$this->maintenanceService->technicianHasGroupAccess($groupId, $uid)) $this->deny('No tienes acceso a esta campaña.');
		return $role;
	}

	private function requireMaintenanceReadAccess(array $maintenance, string $uid): string {
		$role = $this->requireReadRole($uid);
		if ($role === 'technician' && (string)($maintenance['tecnico_uid'] ?? '') !== $uid) $this->deny('No tienes acceso a este mantenimiento.');
		return $role;
	}

	private function requireMaintenanceWriteAccess(array $maintenance, string $uid): void {
		$role = $this->role($uid);
		if ($role === 'admin') return;
		if ($role !== 'technician' || (string)($maintenance['tecnico_uid'] ?? '') !== $uid) $this->deny('No tienes permiso para modificar este mantenimiento.');
	}

	private function deny(string $message = 'No tienes permiso para realizar esta operación.'): void {
		throw new \DomainException($message, Http::STATUS_FORBIDDEN);
	}

	private function withProgress(array $maintenance): array {
		return ['maintenance' => $maintenance, 'groupProgress' => $this->maintenanceService->getGroupProgress((int)$maintenance['id_grupo'])];
	}

	private function error(string $code, string $message, int $status, array $conflicts = []): DataResponse {
		$error = ['code' => $code, 'message' => $message];
		if ($conflicts !== []) $error['conflicts'] = $conflicts;
		return new DataResponse(['success' => false, 'error' => $error], $status);
	}

	private function logInternal(string $operation, array $context, ?array $actor, \Throwable $e): void {
		$previous = $e->getPrevious();
		$this->logger->error('Falló un endpoint de mantenimiento.', [
			'operation' => $operation,
			'groupId' => isset($context['groupId']) && is_numeric($context['groupId']) ? (int)$context['groupId'] : null,
			'maintenanceId' => isset($context['maintenanceId']) && is_numeric($context['maintenanceId']) ? (int)$context['maintenanceId'] : null,
			'actorUid' => $actor['uid'] ?? null,
			'exceptionClass' => $e::class,
			'exceptionMessage' => $e->getMessage(),
			'previousExceptionClass' => $previous === null ? null : $previous::class,
			'previousExceptionMessage' => $previous?->getMessage(),
			'exception' => $e,
		]);
	}

	private function normalizePositiveId(mixed $value, string $label): int {
		if (is_int($value)) $id = $value;
		elseif (is_string($value) && preg_match('/^[1-9]\d*$/', $value)) $id = (int)$value;
		else throw new MantenimientoValidationException('El identificador de ' . $label . ' no es válido.');
		if ($id <= 0) throw new MantenimientoValidationException('El identificador de ' . $label . ' no es válido.');
		return $id;
	}

	private function normalizeNullableId(mixed $value): ?int {
		return $value === null || $value === '' ? null : $this->normalizePositiveId($value, 'filtro');
	}

	private function normalizeBoolean(mixed $value): bool {
		if (is_bool($value)) return $value;
		if ($value === 1 || $value === '1' || $value === 'true') return true;
		if ($value === 0 || $value === '0' || $value === 'false' || $value === null || $value === '') return false;
		throw new MantenimientoValidationException('El valor booleano no es válido.');
	}

	private function normalizeLimit(mixed $value): int {
		if ($value === null || $value === '') return self::DEFAULT_LIMIT;
		$limit = $this->normalizePositiveId($value, 'límite');
		if ($limit > self::MAX_LIMIT) throw new MantenimientoValidationException('El límite máximo es ' . self::MAX_LIMIT . '.');
		return $limit;
	}

	private function normalizeOffset(mixed $value): int {
		if ($value === 0 || $value === '0' || $value === null || $value === '') return 0;
		return $this->normalizePositiveId($value, 'desplazamiento');
	}

	private function normalizeIdArray(mixed $values): array {
		if (!is_array($values) || $values === []) throw new MantenimientoValidationException('Debe seleccionar al menos un equipo.');
		if (count($values) > self::MAX_EQUIPMENT_IDS) throw new MantenimientoValidationException('No se permiten más de ' . self::MAX_EQUIPMENT_IDS . ' equipos por campaña.');
		return array_map(fn(mixed $value): int => $this->normalizePositiveId($value, 'equipo'), $values);
	}

	private function normalizeDate(mixed $value, string $label): string {
		if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) throw new MantenimientoValidationException($label . ' no es válida.');
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		$errors = \DateTimeImmutable::getLastErrors();
		if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) throw new MantenimientoValidationException($label . ' no es válida.');
		return $value;
	}

	private function normalizeDateRange(mixed $start, mixed $end, bool $required): array {
		if (!$required && ($start === null || $start === '') && ($end === null || $end === '')) return [null, null];
		if ($start === null || $start === '' || $end === null || $end === '') throw new MantenimientoValidationException('Las fechas inicial y final son obligatorias.');
		$from = $this->normalizeDate($start, 'La fecha inicial');
		$to = $this->normalizeDate($end, 'La fecha final');
		if ($to < $from) throw new MantenimientoValidationException('La fecha final debe ser igual o posterior a la inicial.');
		$days = (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days;
		if ($days > self::MAX_CALENDAR_DAYS) throw new MantenimientoValidationException('El rango no puede exceder ' . self::MAX_CALENDAR_DAYS . ' días.');
		return [$from, $to];
	}

	private function normalizeOptionalDateRange(mixed $start, mixed $end): array {
		return $this->normalizeDateRange($start, $end, false);
	}

	private function normalizeNullableTime(mixed $value): ?string {
		$value = $this->normalizeNullableString($value, 5);
		if ($value !== null && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) throw new MantenimientoValidationException('La hora no tiene formato HH:MM válido.');
		return $value;
	}

	private function normalizeGroupStatus(mixed $value): ?string {
		$status = $this->normalizeNullableString($value, 20);
		if ($status !== null && !in_array($status, MantenimientoGrupo::ESTADOS_VALIDOS, true)) throw new MantenimientoValidationException('El estado de campaña no es válido.');
		return $status;
	}

	private function normalizeMaintenanceStatus(mixed $value): ?string {
		$status = $this->normalizeNullableString($value, 24);
		if ($status !== null && !in_array($status, MantenimientoEquipo::ESTADOS_VALIDOS, true)) throw new MantenimientoValidationException('El estado de mantenimiento no es válido.');
		return $status;
	}

	private function normalizeRequiredString(mixed $value, int $maxLength, string $label): string {
		$value = $this->normalizeNullableString($value, $maxLength);
		if ($value === null) throw new MantenimientoValidationException($label . ' es obligatorio.');
		return $value;
	}

	private function normalizeNullableString(mixed $value, int $maxLength): ?string {
		if ($value === null) return null;
		if (!is_scalar($value)) throw new MantenimientoValidationException('Se recibió un texto no válido.');
		$value = trim((string)$value);
		if ($value === '') return null;
		if (mb_strlen($value) > $maxLength) throw new MantenimientoValidationException('Un texto excede la longitud permitida.');
		return $value;
	}

	private function normalizeWorkDetails(array $values, bool $required): array {
		$result = [];
		foreach ($values as $key => $value) {
			if ($key === 'proxima_fecha') {
				$normalized = $this->normalizeNullableString($value, 10);
				if ($normalized !== null) $normalized = $this->normalizeDate($normalized, 'La próxima fecha');
			} else {
				$normalized = $this->normalizeNullableString($value, 65535);
			}
			if ($normalized !== null || ($required && in_array($key, ['resultado', 'acciones_realizadas'], true))) $result[$key] = $normalized;
		}
		return $result;
	}

	private function sanitizeGroupSummary(array $group): array {
		unset($group['descripcion'], $group['creado_por']);
		return $group;
	}

	private function sanitizeMaintenanceSummary(array $maintenance): array {
		foreach (['resultado', 'acciones_realizadas', 'incidencias', 'repuestos', 'observaciones', 'empleado_nombre'] as $field) unset($maintenance[$field]);
		return $maintenance;
	}

	private function sanitizeReadOnlyDetail(array $detail): array {
		$detail['maintenance'] = $this->sanitizeMaintenanceSummary($detail['maintenance']);
		foreach (['empleado_uid', 'tecnico_uid', 'tecnico_nombre', 'numero_serie', 'actualizado_por', 'creado_por'] as $field) unset($detail['maintenance'][$field]);
		$detail['audit'] = [];
		foreach ($detail['checklist'] as &$item) unset($item['observacion'], $item['actualizado_por']);
		unset($item);
		return $detail;
	}

	private function normalizeEligibleEquipment(array $row): array {
		return [
			'id' => (int)$row['id_equipo'],
			'identifier' => $row['nombre_sistema'] ?: $row['nombre_dispositivo'],
			'name' => $row['nombre_dispositivo'],
			'model' => $row['modelo'], 'brand' => $row['marca'], 'serialNumber' => $row['numero_serie'], 'status' => $row['estado'],
			'employee' => $row['id_empleado'] === null ? null : ['id' => (int)$row['id_empleado'], 'uid' => $row['empleado_uid'], 'name' => $row['empleado_nombre']],
			'department' => ['id' => (int)$row['id_departamento'], 'name' => $row['departamento_nombre']],
			'lastMaintenance' => $row['ultimo_mantenimiento'], 'nextActiveMaintenance' => $row['proximo_mantenimiento_activo'],
		];
	}
}
