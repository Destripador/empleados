<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class HistorialAusencias extends Entity {

	protected string $id_ausencias = '';
	protected ?string $id_aniversario = null;
	protected string $id_tipo_ausencia = '';
	protected string $fecha_de = '';
	protected string $fecha_hasta = '';
	protected ?bool $prima_vacacional = false;
	protected ?string $archivo = null;
	protected string $timestamp = '';
	protected ?bool $a_socio = false;
	protected ?bool $a_gerente = false;
	protected ?bool $a_capital_humano = false;
	protected string $notas = '';
	protected float $dias_solicitados = 0.0;
	protected float $dias_de_acumulado = 0.0;
	protected float $dias_de_periodo = 0.0;
	protected ?string $solicitud_grupo = null;
	protected ?string $idempotency_key = null;

	public function __construct() {
		$this->addType('id_historial_ausencias', 'string');
		$this->addType('id_ausencias', 'string');
		$this->addType('id_aniversario', 'string');
		$this->addType('id_tipo_ausencia', 'string');
		$this->addType('fecha_de', 'string');
		$this->addType('fecha_hasta', 'string');
		$this->addType('prima_vacacional', 'bool');
		$this->addType('archivo', 'string');
		$this->addType('timestamp', 'string');
		$this->addType('a_socio', 'bool');
		$this->addType('a_gerente', 'bool');
		$this->addType('a_capital_humano', 'bool');
		$this->addType('notas', 'string');
		$this->addType('dias_solicitados', 'decimal');
		$this->addType('dias_de_acumulado', 'decimal');
		$this->addType('dias_de_periodo', 'decimal');
		$this->addType('solicitud_grupo', 'string');
		$this->addType('idempotency_key', 'string');
	}

	public function read(): array {
		return [
			'id_historial_ausencias' => $this->id_historial_ausencias,
			'id_ausencias' => $this->id_ausencias,
			'id_aniversario' => $this->id_aniversario,
			'id_tipo_ausencia' => $this->id_tipo_ausencia,
			'fecha_de' => $this->fecha_de,
			'fecha_hasta' => $this->fecha_hasta,
			'prima_vacacional' => $this->prima_vacacional,
			'archivo' => $this->archivo,
			'timestamp' => $this->timestamp,
			'a_socio' => $this->a_socio,
			'a_gerente' => $this->a_gerente,
			'a_capital_humano' => $this->a_capital_humano,
			'notas' => $this->notas,
			'dias_solicitados' => $this->dias_solicitados,
			'dias_de_acumulado' => $this->dias_de_acumulado,
			'dias_de_periodo' => $this->dias_de_periodo,
			'solicitud_grupo' => $this->solicitud_grupo,
			'idempotency_key' => $this->idempotency_key,
		];
	}
}
