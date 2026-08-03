<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Db\actividadesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\reportetiempoMapper;
use OCA\Empleados\Service\SoporteReporteTiempoService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class SoporteReporteTiempoServiceTest extends TestCase {
	public function testDuracionSeValidaComoEnteroPositivo(): void {
		$service = $this->service();
		$this->assertSame(90, $service->validarDuracion('90'));
		foreach ([0, -1, 1441, 1.5, '1:30'] as $invalid) {
			try {
				$service->validarDuracion($invalid);
				$this->fail('La duración inválida fue aceptada.');
			} catch (\InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testUsuarioSinEmpleadoNoCreaReporte(): void {
		$reports = $this->createMock(reportetiempoMapper::class);
		$reports->expects($this->never())->method('createIntegrated');
		$employees = $this->createMock(empleadosMapper::class);
		$employees->method('GetMyEmployeeInfo')->willReturn([]);
		$service = $this->service($reports, $employees);

		$this->expectException(\RuntimeException::class);
		$service->crearDesdeSoporte([
			'id_soporte' => 1,
			'usuario_soporte' => 'sin-empleado',
			'duracion_minutos' => 30,
			'fecha' => '2026-08-01 10:00:00',
		], ['id_equipo' => 8]);
	}

	private function service(?reportetiempoMapper $reports = null, ?empleadosMapper $employees = null): SoporteReporteTiempoService {
		$reports ??= $this->createMock(reportetiempoMapper::class);
		$activities = $this->createMock(actividadesMapper::class);
		$employees ??= $this->createMock(empleadosMapper::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->willReturn('UTC');
		return new SoporteReporteTiempoService($reports, $activities, $employees, $config);
	}
}
