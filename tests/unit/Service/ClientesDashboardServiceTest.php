<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Db\clientesMapper;
use OCA\Empleados\Db\honorariosParcialidadesMapper;
use OCA\Empleados\Service\ClienteLogoService;
use OCA\Empleados\Service\ClientesDashboardService;
use PHPUnit\Framework\TestCase;

class ClientesDashboardServiceTest extends TestCase {
	public function testDetectaMimeRealDePngJpegYWebp(): void {
		$png = "\x89PNG\r\n\x1a\n" . 'not-a-full-image';
		$jpeg = "\xFF\xD8\xFF\xE0" . 'jpeg';
		$webp = 'RIFF' . pack('V', 12) . 'WEBP' . 'xxxx';

		$this->assertSame('image/png', ClienteLogoService::detectMime($png));
		$this->assertSame('image/jpeg', ClienteLogoService::detectMime($jpeg));
		$this->assertSame('image/webp', ClienteLogoService::detectMime($webp));
		$this->assertSame('', ClienteLogoService::detectMime('<svg></svg>'));
		$this->assertSame('', ClienteLogoService::detectMime(''));
	}

	public function testResumeHonorariosPendientesSinInventarVencimiento(): void {
		$service = $this->service();

		$summary = $service->buildSummary(
			[
				['id' => 1, 'nombre' => 'Acme', 'logo' => 'png', 'estado' => 1, 'especial' => 0, 'cliente_padre' => null, 'lider_proyecto' => 4],
				['id' => 2, 'nombre' => 'Beta', 'logo' => null, 'estado' => 1, 'especial' => 1, 'cliente_padre' => 1, 'lider_proyecto' => null],
				['id' => 3, 'nombre' => 'Inactivo', 'logo' => null, 'estado' => 0, 'especial' => 0, 'cliente_padre' => null, 'lider_proyecto' => null],
			],
			[
				['id_cliente' => 1, 'tipo_moneda' => 'MXN', 'total' => 1000, 'pagado' => 400, 'pendiente' => 600],
				['id_cliente' => 2, 'tipo_moneda' => 'MXN', 'total' => 200, 'pagado' => 200, 'pendiente' => 0],
			],
			[
				['mes' => '2026-01', 'importe' => 500],
				['mes' => '2026-02', 'importe' => 700],
			],
			[
				['mes' => '2026-01', 'importe' => 400],
			]
		);

		$this->assertSame(3, $summary['catalogo']['total']);
		$this->assertSame(2, $summary['catalogo']['activos']);
		$this->assertSame(1, $summary['catalogo']['grupos']);
		$this->assertSame(1, $summary['catalogo']['subempresas']);
		$this->assertFalse($summary['analisis']['cartera_vencida']);
		$this->assertFalse($summary['analisis']['antiguedad']);
		$this->assertTrue($summary['analisis']['honorarios_pendientes']);

		$this->assertSame('MXN', $summary['monedas'][0]['moneda']);
		$this->assertSame(1200.0, $summary['monedas'][0]['total']);
		$this->assertSame(600.0, $summary['monedas'][0]['pagado']);
		$this->assertSame(600.0, $summary['monedas'][0]['pendiente']);
		$this->assertSame(50.0, $summary['monedas'][0]['porcentaje_pendiente']);
		$this->assertSame(1, $summary['monedas'][0]['clientes_con_pendiente']);

		$this->assertSame('Acme', $summary['ranking_pendiente'][0]['nombre']);
		$this->assertSame(600.0, $summary['ranking_pendiente'][0]['pendiente']);
		$this->assertSame(100.0, $summary['concentracion']['top5']['porcentaje']);
		$this->assertSame('2026-01', $summary['evolucion'][0]['mes']);
		$this->assertSame(500.0, $summary['evolucion'][0]['generado']);
		$this->assertSame(400.0, $summary['evolucion'][0]['cobrado']);
	}

	public function testCuentaFacturadoComoCobradoYNoComoPendiente(): void {
		$service = $this->service();
		$summary = $service->buildSummary(
			[['id' => 8, 'nombre' => 'Gamma', 'estado' => 1, 'especial' => 0, 'cliente_padre' => null, 'lider_proyecto' => null]],
			[['id_cliente' => 8, 'tipo_moneda' => 'MXN', 'total' => 150, 'pagado' => 150, 'pendiente' => 0]]
		);

		$this->assertSame(0.0, $summary['monedas'][0]['pendiente']);
		$this->assertSame(150.0, $summary['monedas'][0]['pagado']);
		$this->assertCount(0, $summary['ranking_pendiente']);
	}

	public function testNormalizaFiltrosConocidosYDescartaRelacionesInexistentes(): void {
		$filters = $this->service()->normalizeFilters([
			'id_cliente' => '12',
			'cliente_padre' => 3,
			'lider_proyecto' => 0,
			'estado' => '1',
			'tipo_honorario' => 'iguala',
			'solo_pendientes' => 'true',
			'fecha_inicio' => '2026-01-15 10:00:00',
			'area' => 9,
			'socio' => 4,
		]);

		$this->assertSame(12, $filters['id_cliente']);
		$this->assertSame(3, $filters['cliente_padre']);
		$this->assertNull($filters['lider_proyecto']);
		$this->assertSame(1, $filters['estado']);
		$this->assertSame('iguala', $filters['tipo_honorario']);
		$this->assertTrue($filters['solo_pendientes']);
		$this->assertSame('2026-01-15', $filters['fecha_inicio']);
		$this->assertArrayNotHasKey('area', $filters);
		$this->assertArrayNotHasKey('socio', $filters);
	}

	private function service(): ClientesDashboardService {
		return new ClientesDashboardService(
			$this->createMock(clientesMapper::class),
			$this->createMock(honorariosParcialidadesMapper::class),
		);
	}
}
