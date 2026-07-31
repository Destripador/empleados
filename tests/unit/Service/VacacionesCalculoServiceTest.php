<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use DateTimeImmutable;
use OCA\Empleados\Db\aniversarioMapper;
use OCA\Empleados\Db\ausenciasMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\historialausenciasMapper;
use OCA\Empleados\Db\historialvacacionesMapper;
use OCA\Empleados\Service\VacacionesCalculoService;
use OCA\Empleados\Service\VacationPeriodCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class VacacionesCalculoServiceTest extends TestCase {
	private empleadosMapper&MockObject $empleados;
	private ausenciasMapper&MockObject $ausencias;
	private historialvacacionesMapper&MockObject $historialVacaciones;
	private historialausenciasMapper&MockObject $historialAusencias;
	private aniversarioMapper&MockObject $aniversarios;
	private VacacionesCalculoService $service;
	private string $ingreso = '2024-01-01';
	/** @var array<int, array<string, mixed>> */
	private array $solicitudes = [];
	/** @var array<int, array<string, mixed>> */
	private array $periodosPersistidos = [];

	protected function setUp(): void {
		parent::setUp();

		$this->empleados = $this->createMock(empleadosMapper::class);
		$this->ausencias = $this->createMock(ausenciasMapper::class);
		$this->historialVacaciones = $this->createMock(historialvacacionesMapper::class);
		$this->historialAusencias = $this->createMock(historialausenciasMapper::class);
		$this->aniversarios = $this->createMock(aniversarioMapper::class);

		$this->empleados->method('GetMyEmployeeInfoByIdEmpleado')
			->willReturnCallback(fn(): array => [[
				'Id_empleados' => 10,
				'Ingreso' => $this->ingreso,
			]]);
		$this->ausencias->method('GetAusenciasById')->willReturn([[
			'id_ausencias' => 20,
			'id_empleado' => 10,
		]]);
		$this->historialAusencias->method('getSolicitudesParaCalculo')
			->willReturnCallback(fn(): array => $this->solicitudes);
		$this->historialVacaciones->method('getByEmpleado')->willReturn([]);
		$this->historialVacaciones->method('upsertCalculado')
			->willReturnCallback(function(array $periodo): void {
				$this->periodosPersistidos[] = $periodo;
			});
		$this->aniversarios->method('GetAniversarios')->willReturn([
			['numero_aniversario' => 0, 'dias' => 0],
			['numero_aniversario' => 1, 'dias' => 12],
			['numero_aniversario' => 2, 'dias' => 14],
			['numero_aniversario' => 3, 'dias' => 16],
			['numero_aniversario' => 4, 'dias' => 18],
			['numero_aniversario' => 5, 'dias' => 20],
			['numero_aniversario' => 6, 'dias' => 22],
		]);

		$this->service = new VacacionesCalculoService(
			$this->empleados,
			$this->ausencias,
			$this->historialVacaciones,
			$this->historialAusencias,
			$this->aniversarios,
			new VacationPeriodCalculator(),
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testSecondAnniversaryCarriesLeftoversAndConsumesThemFirst(): void {
		$this->solicitudes = [
			$this->solicitud(1, '2025-03-03', '2025-03-04', 2.0),
		];

		$resultado = $this->service->evaluarSolicitud(
			10,
			20,
			'2026-01-12',
			'2026-01-14',
			false,
			true,
			false,
			[],
			new DateTimeImmutable('2026-01-10'),
		);

		self::assertSame(3.0, $resultado['dias_solicitados']);
		self::assertSame(2, $resultado['id_aniversario']);
		self::assertSame(3.0, $resultado['dias_de_acumulado']);
		self::assertSame(0.0, $resultado['dias_de_periodo']);
	}

	public function testRequestCrossingCarryExpiryUsesCurrentPeriodAfterTheCutoff(): void {
		$this->solicitudes = [];

		$resultado = $this->service->evaluarSolicitud(
			10,
			20,
			'2026-06-30',
			'2026-07-02',
			false,
			true,
			false,
			[],
			new DateTimeImmutable('2026-01-10'),
		);

		self::assertSame(3.0, $resultado['dias_solicitados']);
		self::assertSame(2.0, $resultado['dias_de_acumulado']);
		self::assertSame(1.0, $resultado['dias_de_periodo']);
	}

	public function testExpiredCarryIsNotAllocated(): void {
		$resultado = $this->service->evaluarSolicitud(
			10,
			20,
			'2026-07-02',
			'2026-07-03',
			false,
			true,
			false,
			[],
			new DateTimeImmutable('2026-01-10'),
		);

		self::assertSame(0.0, $resultado['dias_de_acumulado']);
		self::assertSame(2.0, $resultado['dias_de_periodo']);
	}

	public function testRejectedAndCancelledRequestsDoNotConsumeBalance(): void {
		$rechazada = $this->solicitud(1, '2026-01-05', '2026-01-09', 5.0);
		$rechazada['a_gerente'] = 2;
		$cancelada = $this->solicitud(2, '2026-01-12', '2026-01-16', 5.0);
		$cancelada['a_socio'] = 3;
		$this->solicitudes = [$rechazada, $cancelada];

		$resultado = $this->service->evaluarSolicitud(
			10,
			20,
			'2026-01-19',
			'2026-01-23',
			false,
			true,
			false,
			[],
			new DateTimeImmutable('2026-01-10'),
		);

		self::assertSame(5.0, $resultado['dias_de_acumulado']);
		self::assertSame(0.0, $resultado['dias_de_periodo']);
		self::assertSame(0.0, $resultado['dias_excedentes']);
	}

	public function testEditedRequestReplacesOriginalReservationInsteadOfDiscountingTwice(): void {
		$this->solicitudes = [
			$this->solicitud(99, '2026-01-05', '2026-01-09', 5.0),
		];

		$resultado = $this->service->evaluarSolicitud(
			10,
			20,
			'2026-01-05',
			'2026-01-08',
			false,
			true,
			false,
			[99],
			new DateTimeImmutable('2026-01-10'),
		);

		self::assertSame(4.0, $resultado['dias_solicitados']);
		self::assertSame(4.0, $resultado['dias_de_acumulado']);
		self::assertSame(0.0, $resultado['dias_excedentes']);
	}

	public function testHalfDayAndManipulatedClientDayCountAreCalculatedFromDates(): void {
		$medioDia = $this->service->evaluarSolicitud(
			10,
			20,
			'2026-01-12',
			'2026-01-12',
			true,
			true,
			false,
			[],
			new DateTimeImmutable('2026-01-10'),
		);
		self::assertSame(0.5, $medioDia['dias_solicitados']);

		// La API del servicio no recibe un supuesto total del navegador.
		self::assertSame(
			3.0,
			$this->service->calcularDiasSolicitados(
				'2026-01-12',
				'2026-01-14',
				false,
			),
		);
	}

	public function testChangingHireDateBothWaysRebuildsOnlyValidPeriods(): void {
		$this->ingreso = '2025-01-01';
		$haciaFuturo = $this->service->recalcularEmpleado(
			10,
			20,
			new DateTimeImmutable('2026-07-29'),
		);
		self::assertCount(2, $haciaFuturo['periodos']);

		$this->ingreso = '2020-01-01';
		$haciaPasado = $this->service->recalcularEmpleado(
			10,
			20,
			new DateTimeImmutable('2026-07-29'),
		);
		self::assertCount(7, $haciaPasado['periodos']);
		self::assertSame(6, $haciaPasado['actual']['numero_aniversario']);
	}

	public function testRepeatedRecalculationProducesTheSameProjection(): void {
		$this->solicitudes = [
			$this->solicitud(1, '2025-03-03', '2025-03-04', 2.0),
		];

		$primera = $this->service->recalcularEmpleado(
			10,
			20,
			new DateTimeImmutable('2026-01-10'),
		);
		$segunda = $this->service->recalcularEmpleado(
			10,
			20,
			new DateTimeImmutable('2026-01-10'),
		);

		self::assertSame($primera['actual'], $segunda['actual']);
		self::assertSame($primera['periodos'], $segunda['periodos']);
	}

	public function testHistoricalRequestOutsideNewCalendarIsPreservedAsExcess(): void {
		$this->ingreso = '2025-01-01';
		$this->solicitudes = [
			$this->solicitud(1, '2024-12-02', '2024-12-03', 2.0),
		];

		$resultado = $this->service->recalcularEmpleado(
			10,
			20,
			new DateTimeImmutable('2026-01-10'),
		);

		self::assertSame(2.0, $resultado['solicitudes_fuera_de_calendario']);
		self::assertSame(2.0, $resultado['actual']['dias_excedentes']);
		self::assertGreaterThanOrEqual(0.0, $resultado['actual']['dias_totales_disponibles']);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function solicitud(
		int $id,
		string $fechaDe,
		string $fechaHasta,
		float $dias,
	): array {
		return [
			'id_historial_ausencias' => $id,
			'id_ausencias' => 20,
			'id_aniversario' => null,
			'fecha_de' => $fechaDe,
			'fecha_hasta' => $fechaHasta,
			'dias_solicitados' => $dias,
			'dias_de_acumulado' => 0,
			'dias_de_periodo' => 0,
			'solicitar_prima_vacacional' => 1,
			'privado' => 0,
			'a_gerente' => 0,
			'a_socio' => 0,
			'a_capital_humano' => 0,
			'timestamp' => $fechaDe . ' 00:00:00',
		];
	}
}
