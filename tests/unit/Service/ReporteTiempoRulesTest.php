<?php

declare(strict_types=1);

namespace OCA\Empleados\Tests\Unit\Service;

use OCA\Empleados\Db\actividades;
use OCA\Empleados\Db\reportetiempo;
use OCA\Empleados\Exception\ReporteTiempoRuleException;
use OCA\Empleados\Service\ReporteTiempoRules;
use OCP\AppFramework\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReporteTiempoRulesTest extends TestCase {
	public function testNormalizaReportesHeredadosSinTipoTrabajo(): void {
		$this->assertSame(reportetiempo::TIPO_AUSENCIA, ReporteTiempoRules::normalizeExistingType(['id_cliente' => 99999]));
		$this->assertSame(reportetiempo::TIPO_AUSENCIA, ReporteTiempoRules::normalizeExistingType(['id_actividad' => 99999]));
		$this->assertSame(reportetiempo::TIPO_INTERNO, ReporteTiempoRules::normalizeExistingType(['id_cliente' => null]));
		$this->assertSame(reportetiempo::TIPO_INTERNO, ReporteTiempoRules::normalizeExistingType(['id_cliente' => 12, 'origen' => 'soporte_ti']));
		$this->assertSame(reportetiempo::TIPO_CLIENTE, ReporteTiempoRules::normalizeExistingType(['id_cliente' => 12]));
	}

	public function testValidaTrabajoParaCliente(): void {
		$this->assertSame(
			[25, reportetiempo::ORIGEN_MANUAL],
			ReporteTiempoRules::validateManual(reportetiempo::TIPO_CLIENTE, 25, $this->clientActivity(), 4),
		);
	}

	public function testValidaTrabajoInternoGlobalYDeVariasAreas(): void {
		$this->assertSame(
			[null, reportetiempo::ORIGEN_MANUAL_INTERNO],
			ReporteTiempoRules::validateManual(reportetiempo::TIPO_INTERNO, null, $this->internalActivity(), 9),
		);
		$scoped = $this->internalActivity(['alcance' => actividades::ALCANCE_AREAS, 'area_ids' => [3, 9]]);
		$this->assertSame(
			[null, reportetiempo::ORIGEN_MANUAL_INTERNO],
			ReporteTiempoRules::validateManual(reportetiempo::TIPO_INTERNO, null, $scoped, 9),
		);
	}

	public function testRechazaActividadInternaDeOtraArea(): void {
		try {
			ReporteTiempoRules::validateManual(
				reportetiempo::TIPO_INTERNO,
				null,
				$this->internalActivity(['alcance' => actividades::ALCANCE_AREAS, 'area_ids' => [3]]),
				9,
			);
			$this->fail('Se aceptó una actividad interna fuera del área del empleado.');
		} catch (ReporteTiempoRuleException $e) {
			$this->assertSame(Http::STATUS_FORBIDDEN, $e->getHttpStatus());
		}
	}

	#[DataProvider('invalidCombinations')]
	public function testRechazaCombinacionesIncompatibles(string $type, mixed $clientId, array $activity, int $status): void {
		try {
			ReporteTiempoRules::validateManual($type, $clientId, $activity, 3);
			$this->fail('Se aceptó una combinación incompatible.');
		} catch (ReporteTiempoRuleException $e) {
			$this->assertSame($status, $e->getHttpStatus());
		}
	}

	public static function invalidCombinations(): array {
		return [
			'cliente sin id' => [reportetiempo::TIPO_CLIENTE, null, self::clientActivityStatic(), Http::STATUS_BAD_REQUEST],
			'cliente reservado' => [reportetiempo::TIPO_CLIENTE, 99999, self::clientActivityStatic(), Http::STATUS_CONFLICT],
			'interna con cliente' => [reportetiempo::TIPO_INTERNO, 25, self::internalActivityStatic(), Http::STATUS_CONFLICT],
			'tipo cliente con actividad interna' => [reportetiempo::TIPO_CLIENTE, 25, self::internalActivityStatic(), Http::STATUS_CONFLICT],
			'tipo interno con actividad cliente' => [reportetiempo::TIPO_INTERNO, null, self::clientActivityStatic(), Http::STATUS_CONFLICT],
			'interna marcada cargable' => [reportetiempo::TIPO_INTERNO, null, self::internalActivityStatic(['cargable' => 1]), Http::STATUS_CONFLICT],
			'actividad reservada para ausencia' => [reportetiempo::TIPO_CLIENTE, 25, self::clientActivityStatic(['id_actividad' => 99999]), Http::STATUS_CONFLICT],
			'actividad de sistema manual' => [reportetiempo::TIPO_INTERNO, null, self::internalActivityStatic(['clave_sistema' => 'soporte_ti']), Http::STATUS_CONFLICT],
			'tipo desconocido' => ['otro', null, self::internalActivityStatic(), Http::STATUS_BAD_REQUEST],
		];
	}

	private function clientActivity(array $overrides = []): array {
		return self::clientActivityStatic($overrides);
	}

	private static function clientActivityStatic(array $overrides = []): array {
		return array_merge([
			'id_actividad' => 8,
			'tipo_actividad' => actividades::TIPO_CLIENTE,
			'alcance' => actividades::ALCANCE_GLOBAL,
			'cargable' => 1,
			'clave_sistema' => null,
			'area_ids' => [],
		], $overrides);
	}

	private function internalActivity(array $overrides = []): array {
		return self::internalActivityStatic($overrides);
	}

	private static function internalActivityStatic(array $overrides = []): array {
		return array_merge([
			'id_actividad' => 14,
			'tipo_actividad' => actividades::TIPO_INTERNO,
			'alcance' => actividades::ALCANCE_GLOBAL,
			'cargable' => 0,
			'clave_sistema' => null,
			'area_ids' => [],
		], $overrides);
	}
}
