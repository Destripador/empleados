<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Db\departamentosMapper;
use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\MantenimientoCambio;
use OCA\Empleados\Db\MantenimientoCambioMapper;
use OCA\Empleados\Db\MantenimientoChecklist;
use OCA\Empleados\Db\MantenimientoChecklistMapper;
use OCA\Empleados\Db\MantenimientoEquipo;
use OCA\Empleados\Db\MantenimientoEquipoMapper;
use OCA\Empleados\Db\MantenimientoGrupo;
use OCA\Empleados\Db\MantenimientoGrupoMapper;
use OCA\Empleados\Exception\MantenimientoConflictException;
use OCA\Empleados\Exception\MantenimientoStorageException;
use OCA\Empleados\Exception\MantenimientoTransitionException;
use OCA\Empleados\Exception\MantenimientoValidationException;
use OCA\Empleados\Mantenimiento\ChecklistPreventivoCatalogo;
use OCA\Empleados\Service\MantenimientoService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MantenimientoServiceTest extends TestCase {
	public function testCreatePreventiveGroupBuildsSnapshotsChecklistAndAuditInOneTransaction(): void {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([$this->equipmentRow()]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn([]);
		$d['groups']->method('insertGroup')->willReturn($this->group());
		$d['maintenances']->expects($this->once())->method('insertMaintenance')
			->with($this->callback(function (array $data): bool {
				return $data['id_equipo'] === 8
					&& $data['id_empleado'] === 3
					&& $data['empleado_uid'] === 'ana'
					&& $data['id_departamento'] === 4
					&& $data['departamento_nombre'] === 'TI'
					&& $data['modelo_nombre'] === 'Dell Latitude'
					&& $data['fecha_programada'] === null
					&& $data['hora_inicio_programada'] === null
					&& $data['hora_fin_programada'] === null;
			}))
			->willReturn($this->maintenance());
		$d['checks']->expects($this->exactly(count(ChecklistPreventivoCatalogo::ITEMS)))
			->method('insertResponse')->willReturnCallback(fn(array $data): MantenimientoChecklist => $this->check($data['clave'], $data['resultado']));
		$d['changes']->expects($this->exactly(2))->method('recordChange')
			->with($this->callback(function (array $data): bool {
				$this->assertSame('admin', $data['usuario_uid']);
				return $data['tipo_cambio'] === 'created';
			}))
			->willReturn(new MantenimientoCambio());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 1, pending: 1));
		$d['db']->expects($this->once())->method('beginTransaction');
		$d['db']->expects($this->once())->method('commit');

		$result = $d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin');

		$this->assertCount(1, $result['maintenances']);
		$this->assertSame('pending', $result['progress']['operational_status']);
		$this->assertSame([], $result['warnings']);
	}

	public function testCreateGroupWithSeveralEquipmentCreatesOneMaintenancePerSelection(): void {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([
			$this->equipmentRow(8), $this->equipmentRow(9), $this->equipmentRow(10),
		]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn([]);
		$d['groups']->method('insertGroup')->willReturn($this->group());
		$next = 10;
		$d['maintenances']->expects($this->exactly(3))->method('insertMaintenance')
			->willReturnCallback(fn(): MantenimientoEquipo => $this->maintenance(++$next));
		$d['checks']->method('insertResponse')->willReturn(new MantenimientoChecklist());
		$d['changes']->method('recordChange')->willReturn(new MantenimientoCambio());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 3, pending: 3));

		$result = $d['service']->createGroup($this->groupData(), [8, 9, 10], 'admin', 'Admin');

		$this->assertCount(3, $result['maintenances']);
	}

	public function testCreateGroupRejectsInvertedAndExcessivePeriodsAndInvalidTimes(): void {
		foreach ([
			['fecha_inicio' => '2026-08-20', 'fecha_fin' => '2026-08-19'],
			['fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-09-01'],
			['hora_inicio' => '18:00', 'hora_fin' => '09:00'],
		] as $change) {
			$d = $this->dependencies();
			$this->expectExceptionForIteration(MantenimientoValidationException::class);
			try {
				$d['service']->createGroup(array_merge($this->groupData(), $change), [8], 'admin', 'Admin');
				$this->fail('El periodo u horario inválido debió rechazarse.');
			} catch (MantenimientoValidationException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testCreateGroupRollsBackWhenChecklistFails(): void {
		$d = $this->readyForCreation();
		$d['checks']->method('insertResponse')->willThrowException(new \RuntimeException('database failure'));
		$d['db']->expects($this->once())->method('rollBack');
		$d['db']->expects($this->never())->method('commit');

		$this->expectException(MantenimientoStorageException::class);
		$d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin');
	}

	public function testCreateGroupRollsBackWhenMaintenanceInsertFails(): void {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([$this->equipmentRow()]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn([]);
		$d['groups']->method('insertGroup')->willReturn($this->group());
		$d['maintenances']->method('insertMaintenance')->willThrowException(new \RuntimeException('insert failed'));
		$d['checks']->expects($this->never())->method('insertResponse');
		$d['db']->expects($this->once())->method('rollBack');

		$this->expectException(MantenimientoStorageException::class);
		$d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin');
	}

	public function testCreateGroupRejectsEmptyAndRepeatedIds(): void {
		$d = $this->dependencies();
		$d['db']->expects($this->exactly(2))->method('rollBack');
		foreach ([[], [8, 8]] as $ids) {
			try {
				$d['service']->createGroup($this->groupData(), $ids, 'admin', 'Admin');
				$this->fail('La validación debió fallar.');
			} catch (MantenimientoValidationException) {
			}
		}
	}

	public function testCreateGroupRejectsMissingInactiveAndWrongDepartmentEquipment(): void {
		foreach ([
			[],
			[$this->equipmentRow(8, 'baja')],
			[$this->equipmentRow(8, 'activo', 99)],
		] as $rows) {
			$d = $this->dependencies();
			$d['inventory']->method('findCampaignEquipmentByIds')->willReturn($rows);
			$this->expectExceptionForIteration($rows === [] ? \OCA\Empleados\Exception\MantenimientoNotFoundException::class : ($rows[0]['estado'] === 'baja' ? MantenimientoValidationException::class : MantenimientoConflictException::class));
			try {
				$d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin');
				$this->fail('La selección inválida debió rechazarse.');
			} catch (\Throwable $e) {
				$this->assertInstanceOf($rows === [] ? \OCA\Empleados\Exception\MantenimientoNotFoundException::class : ($rows[0]['estado'] === 'baja' ? MantenimientoValidationException::class : MantenimientoConflictException::class), $e);
			}
		}
	}

	public function testSpecialCampaignAllowsExplicitEquipmentWithoutCustodian(): void {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([$this->equipmentRow(8, 'activo', null, null)]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn([]);
		$d['groups']->method('insertGroup')->willReturn($this->group(type: MantenimientoService::TYPE_SPECIAL, departmentId: null));
		$d['maintenances']->expects($this->once())->method('insertMaintenance')->with($this->callback(
			fn(array $data): bool => $data['id_empleado'] === null && $data['id_departamento'] === null,
		))->willReturn($this->maintenance(type: MantenimientoService::TYPE_SPECIAL));
		$d['changes']->method('recordChange')->willReturn(new MantenimientoCambio());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 1, pending: 1));

		$data = $this->groupData();
		$data['tipo'] = MantenimientoService::TYPE_SPECIAL;
		$data['id_departamento'] = null;
		$result = $d['service']->createGroup($data, [8], 'admin', 'Admin');

		$this->assertCount(1, $result['maintenances']);
	}

	public function testPotentialDuplicateRejectsOrReturnsStructuredWarningWhenAllowed(): void {
		foreach ([false, true] as $allow) {
			$d = $this->readyForCreation([$this->maintenance(44)]);
			if (!$allow) {
				try {
					$d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin', false);
					$this->fail('Debió rechazar el conflicto.');
				} catch (MantenimientoConflictException $e) {
					$this->assertSame(44, $e->getConflicts()[0]['id_mantenimiento_existente']);
				}
			} else {
				$result = $d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin', true);
				$this->assertSame(44, $result['warnings'][0]['id_mantenimiento_existente']);
			}
		}
	}

	public function testStartPendingAndScheduledButRejectsCompleted(): void {
		foreach ([MantenimientoEquipo::ESTADO_PENDING, MantenimientoEquipo::ESTADO_SCHEDULED] as $status) {
			$d = $this->dependencies();
			$current = $this->maintenance(status: $status);
			$d['maintenances']->method('findById')->willReturn($current);
			$d['groups']->method('findById')->willReturn($this->group());
			$d['maintenances']->method('updateStatusIfCurrent')->willReturn($this->maintenance(status: MantenimientoEquipo::ESTADO_IN_PROGRESS));
			$d['changes']->method('recordChange')->willReturn(new MantenimientoCambio());
			$this->assertSame('in_progress', $d['service']->startMaintenance(11, 'tech', 'Tech')['estado']);
		}

		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MantenimientoEquipo::ESTADO_COMPLETED));
		$d['groups']->method('findById')->willReturn($this->group());
		$this->expectException(MantenimientoTransitionException::class);
		$d['service']->startMaintenance(11, 'tech', 'Tech');
	}

	public function testScheduleMaintenanceValidatesCampaignPeriodAndUsesScheduledTransition(): void {
		$d = $this->dependencies();
		$current = $this->maintenance(status: MantenimientoEquipo::ESTADO_PENDING);
		$d['maintenances']->method('findById')->willReturn($current);
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->once())->method('scheduleIfCurrent')
			->with(11, 'pending', '2026-08-12', '10:00:00', '11:00:00', 'tech')
			->willReturn($this->maintenance(status: MantenimientoEquipo::ESTADO_SCHEDULED));
		$d['changes']->expects($this->once())->method('recordChange')->willReturn(new MantenimientoCambio());

		$result = $d['service']->scheduleMaintenance(11, '2026-08-12', '10:00', '11:00', 'tech', 'Tech');

		$this->assertSame(MantenimientoEquipo::ESTADO_SCHEDULED, $result['estado']);

		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MantenimientoEquipo::ESTADO_PENDING));
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->never())->method('scheduleIfCurrent');
		$this->expectException(MantenimientoValidationException::class);
		$d['service']->scheduleMaintenance(11, '2026-08-20', null, null, 'tech', 'Tech');
	}

	public function testCompleteInProgressValidatesChecklistAndRecordsCompletion(): void {
		$d = $this->dependencies();
		$current = $this->maintenance(status: MantenimientoEquipo::ESTADO_IN_PROGRESS, technicianUid: 'tech');
		$d['maintenances']->method('findById')->willReturn($current);
		$d['groups']->method('findById')->willReturn($this->group());
		$d['checks']->method('listByMaintenance')->willReturn([$this->check('external_cleaning', 'ok')]);
		$d['maintenances']->method('saveResultIfCurrent')->willReturn($current);
		$d['maintenances']->method('updateStatusIfCurrent')->willReturn($this->maintenance(status: MantenimientoEquipo::ESTADO_COMPLETED, technicianUid: 'tech'));
		$d['changes']->expects($this->once())->method('recordChange')->with($this->callback(
			fn(array $data): bool => $data['tipo_cambio'] === 'completed' && $data['valor_anterior'] === 'in_progress',
		))->willReturn(new MantenimientoCambio());

		$result = $d['service']->completeMaintenance(11, ['resultado' => 'Correcto', 'acciones_realizadas' => 'Limpieza'], 'tech', 'Tech');

		$this->assertSame('completed', $result['estado']);
	}

	public function testCompletePendingIsRejectedBeforeWritingResults(): void {
		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MantenimientoEquipo::ESTADO_PENDING, technicianUid: 'tech'));
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->never())->method('saveResultIfCurrent');

		$this->expectException(MantenimientoTransitionException::class);
		$d['service']->completeMaintenance(11, ['resultado' => 'Correcto', 'acciones_realizadas' => 'Limpieza'], 'tech', 'Tech');
	}

	public function testRescheduleCancelAndNotApplicableUseCentralTransitions(): void {
		foreach (['reschedule', 'cancel', 'not_applicable'] as $operation) {
			$d = $this->dependencies();
			$current = $this->maintenance(status: MantenimientoEquipo::ESTADO_SCHEDULED);
			$d['maintenances']->method('findById')->willReturn($current);
			$d['groups']->method('findById')->willReturn($this->group());
			$d['changes']->method('recordChange')->willReturn(new MantenimientoCambio());
			if ($operation === 'reschedule') {
				$d['maintenances']->method('rescheduleIfCurrent')->willReturn($this->maintenance(status: MantenimientoEquipo::ESTADO_RESCHEDULED));
				$result = $d['service']->rescheduleMaintenance(11, '2026-08-12', '10:00', '11:00', 'Cambio de agenda', 'tech', 'Tech');
				$this->assertSame('rescheduled', $result['estado']);
			} else {
				$target = $operation === 'cancel' ? MantenimientoEquipo::ESTADO_CANCELLED : MantenimientoEquipo::ESTADO_NOT_APPLICABLE;
				$d['maintenances']->method('updateStatusIfCurrent')->willReturn($this->maintenance(status: $target));
				$result = $operation === 'cancel'
					? $d['service']->cancelMaintenance(11, 'No disponible', 'tech', 'Tech')
					: $d['service']->markNotApplicable(11, 'Equipo retirado', 'tech', 'Tech');
				$this->assertSame($target, $result['estado']);
			}
		}
	}

	public function testRescheduleRejectsDateOutsideCampaignPeriod(): void {
		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MantenimientoEquipo::ESTADO_SCHEDULED));
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->never())->method('rescheduleIfCurrent');

		$this->expectException(MantenimientoValidationException::class);
		$d['service']->rescheduleMaintenance(11, '2026-09-01', '10:00', '11:00', 'Fuera del periodo', 'tech', 'Tech');
	}

	public function testCancelGroupOnlyChangesActiveMaintenances(): void {
		$d = $this->dependencies();
		$d['groups']->method('findById')->willReturnOnConsecutiveCalls($this->group(), $this->group(status: MantenimientoGrupo::ESTADO_CANCELLED), $this->group(status: MantenimientoGrupo::ESTADO_CANCELLED));
		$d['groups']->method('cancel')->willReturn(true);
		$d['maintenances']->method('findByGroup')->willReturn([
			$this->maintenance(11, MantenimientoEquipo::ESTADO_PENDING),
			$this->maintenance(12, MantenimientoEquipo::ESTADO_COMPLETED),
		]);
		$d['maintenances']->expects($this->once())->method('updateStatusIfCurrent')->willReturn($this->maintenance(11, MantenimientoEquipo::ESTADO_CANCELLED));
		$d['changes']->method('recordChange')->willReturn(new MantenimientoCambio());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 2, completed: 1, cancelled: 1));
		$d['maintenances']->method('findPageByGroup')->willReturn([]);
		$d['maintenances']->method('countByGroup')->willReturn(2);

		$result = $d['service']->cancelGroup(7, 'Campaña suspendida', 'admin', 'Admin');

		$this->assertSame('cancelled', $result['progress']['operational_status']);
	}

	public function testChecklistRejectsUnknownInvalidAttentionAndFinalState(): void {
		foreach (['unknown', 'invalid', 'attention', 'pending', 'final'] as $case) {
			$d = $this->dependencies();
			$status = $case === 'final' ? MantenimientoEquipo::ESTADO_COMPLETED : ($case === 'pending' ? MantenimientoEquipo::ESTADO_PENDING : MantenimientoEquipo::ESTADO_IN_PROGRESS);
			$d['maintenances']->method('findById')->willReturn($this->maintenance(status: $status));
			$d['groups']->method('findById')->willReturn($this->group());
			$d['checks']->method('listByMaintenance')->willReturn([$this->check('external_cleaning', 'pending')]);
			$item = match ($case) {
				'unknown' => ['clave' => 'other', 'resultado' => 'ok'],
				'invalid' => ['clave' => 'external_cleaning', 'resultado' => 'bad'],
				'attention' => ['clave' => 'external_cleaning', 'resultado' => 'attention'],
				default => ['clave' => 'external_cleaning', 'resultado' => 'ok'],
			};
			try {
				$d['service']->updateChecklist(11, [$item], 'tech', 'Tech');
				$this->fail('La actualización debió rechazarse.');
			} catch (MantenimientoValidationException|MantenimientoTransitionException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testChecklistPartialUpdateAndWorkDraftAreAuditedWithoutCompleting(): void {
		$d = $this->dependencies();
		$maintenance = $this->maintenance(status: MantenimientoEquipo::ESTADO_IN_PROGRESS);
		$existing = $this->check('external_cleaning', 'pending');
		$updatedCheck = $this->check('external_cleaning', 'attention', 'Requiere limpieza adicional');
		$d['maintenances']->method('findById')->willReturn($maintenance);
		$d['groups']->method('findById')->willReturn($this->group());
		$d['checks']->method('listByMaintenance')->willReturnOnConsecutiveCalls([$existing], [$updatedCheck]);
		$d['checks']->expects($this->once())->method('updateResponse')->with(20, 'attention', 'Requiere limpieza adicional', 'tech')->willReturn($updatedCheck);
		$d['maintenances']->expects($this->once())->method('saveResultIfCurrent')
			->with(11, 'in_progress', ['resultado' => 'Avance'], 'tech')->willReturn($maintenance);
		$d['maintenances']->expects($this->never())->method('updateStatusIfCurrent');
		$d['changes']->expects($this->exactly(2))->method('recordChange')->willReturn(new MantenimientoCambio());

		$items = $d['service']->updateChecklist(11, [['clave' => 'external_cleaning', 'resultado' => 'attention', 'observacion' => 'Requiere limpieza adicional']], 'tech', 'Tech');
		$draft = $d['service']->updateWorkDetails(11, ['resultado' => 'Avance'], 'tech', 'Tech');

		$this->assertSame('attention', $items[0]['resultado']);
		$this->assertSame('in_progress', $draft['estado']);
	}

	public function testAssignTechnicianUsesOfficialUserSnapshotAndAuditsActor(): void {
		$d = $this->dependencies();
		$current = $this->maintenance(status: MantenimientoEquipo::ESTADO_PENDING);
		$updated = $this->maintenance(status: MantenimientoEquipo::ESTADO_PENDING, technicianUid: 'tech');
		$user = $this->createMock(IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$user->method('getDisplayName')->willReturn('Técnico Oficial');
		$d['users']->method('get')->with('tech')->willReturn($user);
		$d['maintenances']->method('findById')->willReturn($current);
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->once())->method('updateTechnicianIfCurrent')
			->with(11, 'pending', 'tech', 'Técnico Oficial', 'admin')->willReturn($updated);
		$d['changes']->expects($this->once())->method('recordChange')->with($this->callback(
			fn(array $data): bool => $data['tipo_cambio'] === 'technician_changed' && $data['usuario_uid'] === 'admin' && $data['usuario_nombre'] === 'Administrador',
		))->willReturn(new MantenimientoCambio());

		$result = $d['service']->assignTechnician(11, 'tech', 'Nombre no confiable', 'admin', 'Administrador');

		$this->assertSame('tech', $result['tecnico_uid']);
	}

	public function testConcurrentStateChangeRollsBackAsDomainConflict(): void {
		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MantenimientoEquipo::ESTADO_PENDING));
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->method('updateStatusIfCurrent')->willReturn(null);
		$d['changes']->expects($this->never())->method('recordChange');
		$d['db']->expects($this->once())->method('rollBack');

		$this->expectException(MantenimientoConflictException::class);
		$d['service']->startMaintenance(11, 'tech', 'Tech');
	}

	public function testOverdueUsesInjectedClockDate(): void {
		$d = $this->dependencies();
		$d['maintenances']->expects($this->once())->method('findOverdue')->with('2026-08-04', 25, 5)->willReturn([$this->maintenance()]);

		$result = $d['service']->listOverdueMaintenances(25, 5);

		$this->assertCount(1, $result);
	}

	public function testProgressCoversEmptyPendingInProgressCompletedCancelledAndPartial(): void {
		$cases = [
			[$this->group(), $this->counts(), 'partial', 0.0],
			[$this->group(), $this->counts(total: 2, pending: 2), 'pending', 0.0],
			[$this->group(), $this->counts(total: 2, pending: 1, completed: 1), 'in_progress', 50.0],
			[$this->group(), $this->counts(total: 2, completed: 1, cancelled: 1), 'completed', 100.0],
			[$this->group(status: MantenimientoGrupo::ESTADO_CANCELLED), $this->counts(total: 2, pending: 2), 'cancelled', 0.0],
			[$this->group(), $this->counts(total: 2, cancelled: 1, notApplicable: 1), 'partial', 100.0],
		];
		foreach ($cases as [$group, $counts, $status, $percentage]) {
			$d = $this->dependencies();
			$d['groups']->method('findById')->willReturn($group);
			$d['groups']->method('getProgress')->willReturn($counts);
			$result = $d['service']->getGroupProgress(7);
			$this->assertSame($status, $result['operational_status']);
			$this->assertSame($percentage, $result['percentage']);
		}
	}

	public function testDepartmentResolutionHonorsFlagAndDetectsCycle(): void {
		$rows = [
			['Id_departamento' => 1, 'Id_padre' => 3, 'Nombre' => 'A'],
			['Id_departamento' => 2, 'Id_padre' => 1, 'Nombre' => 'B'],
			['Id_departamento' => 3, 'Id_padre' => 2, 'Nombre' => 'C'],
		];
		$d = $this->dependencies($rows);
		$d['logger']->expects($this->once())->method('warning');

		$this->assertSame([1], $d['service']->resolveDepartmentIds(1, false));
		$this->assertSame([1, 2, 3], $d['service']->resolveDepartmentIds(1, true));
	}

	private function readyForCreation(array $duplicates = []): array {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([$this->equipmentRow()]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn($duplicates);
		$d['groups']->method('insertGroup')->willReturn($this->group());
		$d['maintenances']->method('insertMaintenance')->willReturn($this->maintenance());
		$d['changes']->method('recordChange')->willReturn(new MantenimientoCambio());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 1, pending: 1));
		return $d;
	}

	private function dependencies(?array $hierarchy = null): array {
		$db = $this->createMock(IDBConnection::class);
		$groups = $this->createMock(MantenimientoGrupoMapper::class);
		$maintenances = $this->createMock(MantenimientoEquipoMapper::class);
		$checks = $this->createMock(MantenimientoChecklistMapper::class);
		$changes = $this->createMock(MantenimientoCambioMapper::class);
		$inventory = $this->createMock(InventarioComputoMapper::class);
		$departments = $this->createMock(departamentosMapper::class);
		$departments->method('findDepartmentRow')->willReturn(['Id_departamento' => 4, 'Id_padre' => null, 'Nombre' => 'TI']);
		$departments->method('findHierarchy')->willReturn($hierarchy ?? [['Id_departamento' => 4, 'Id_padre' => null, 'Nombre' => 'TI']]);
		$users = $this->createMock(IUserManager::class);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-04 12:00:00', new \DateTimeZone('UTC')));
		$logger = $this->createMock(LoggerInterface::class);
		return [
			'service' => new MantenimientoService($db, $groups, $maintenances, $checks, $changes, $inventory, $departments, $users, $clock, $logger),
			'db' => $db, 'groups' => $groups, 'maintenances' => $maintenances,
			'checks' => $checks, 'changes' => $changes, 'inventory' => $inventory,
			'departments' => $departments, 'users' => $users, 'clock' => $clock, 'logger' => $logger,
		];
	}

	private function groupData(): array {
		return ['titulo' => 'Preventivo agosto', 'tipo' => 'preventive', 'fecha_inicio' => '2026-08-10', 'fecha_fin' => '2026-08-14', 'hora_inicio' => '09:00', 'hora_fin' => '10:00', 'id_departamento' => 4];
	}

	private function equipmentRow(int $id = 8, string $status = 'activo', ?int $departmentId = 4, ?int $employeeId = 3): array {
		return [
			'id_equipo' => $id, 'nombre_dispositivo' => 'LAP-' . $id, 'nombre_sistema' => 'host-' . $id,
			'id_modelo' => 2, 'modelo' => 'Latitude', 'marca' => 'Dell', 'numero_serie' => 'SERIE',
			'estado' => $status, 'id_empleado' => $employeeId, 'empleado_uid' => $employeeId === null ? null : 'ana',
			'empleado_nombre' => $employeeId === null ? null : 'Ana', 'id_departamento' => $departmentId,
			'departamento_nombre' => $departmentId === null ? null : 'TI',
		];
	}

	private function group(int $id = 7, string $status = MantenimientoGrupo::ESTADO_ACTIVE, string $type = MantenimientoService::TYPE_PREVENTIVE, ?int $departmentId = 4): MantenimientoGrupo {
		$group = new MantenimientoGrupo();
		$group->setId($id); $group->setEstadoAdmin($status); $group->setTipo($type); $group->setIdDepartamento($departmentId); $group->setFechaInicio('2026-08-10'); $group->setFechaFin('2026-08-14');
		return $group;
	}

	private function maintenance(int $id = 11, string $status = MantenimientoEquipo::ESTADO_PENDING, string $type = MantenimientoService::TYPE_PREVENTIVE, ?string $technicianUid = null): MantenimientoEquipo {
		$item = new MantenimientoEquipo();
		$item->setId($id); $item->setIdGrupo(7); $item->setIdEquipo(8); $item->setEstado($status); $item->setTipo($type);
		$item->setFechaProgramada('2026-08-10'); $item->setHoraInicioProgramada('09:00:00'); $item->setHoraFinProgramada('10:00:00');
		$item->setTecnicoUid($technicianUid); $item->setFechaInicioReal('2026-08-04 11:00:00');
		return $item;
	}

	private function check(string $key, string $result, ?string $observation = null): MantenimientoChecklist {
		$item = new MantenimientoChecklist();
		$item->setId(20); $item->setClave($key); $item->setResultado($result); $item->setObservacion($observation);
		return $item;
	}

	private function counts(int $total = 0, int $pending = 0, int $scheduled = 0, int $inProgress = 0, int $completed = 0, int $rescheduled = 0, int $cancelled = 0, int $notApplicable = 0, int $overdue = 0): array {
		return ['total' => $total, 'pending' => $pending, 'scheduled' => $scheduled, 'in_progress' => $inProgress, 'completed' => $completed, 'rescheduled' => $rescheduled, 'cancelled' => $cancelled, 'not_applicable' => $notApplicable, 'overdue' => $overdue];
	}

	private function expectExceptionForIteration(string $class): void {
		// Keeps the expected class visible in each table-driven branch without stopping the loop.
		$this->assertTrue(is_a($class, \Throwable::class, true));
	}
}
