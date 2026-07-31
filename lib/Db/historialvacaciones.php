<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class historialvacaciones extends Entity {

	protected string $id_empleado = '';
	protected string $numero_aniversario = '';
	protected string $periodo_inicio = '';
	protected string $periodo_fin = '';
	protected string $dias_derecho = '';
	protected string $dias_acumulados = '';
	protected string $dias_acumulados_restantes = '';
	protected string $fecha_ingreso_base = '';
	protected string $dias_periodo_usados = '';
	protected string $dias_acumulados_usados = '';
	protected string $dias_acumulados_vencidos = '';
	protected string $dias_excedentes = '';
	protected ?string $fecha_expiracion_acumulados = null;
	protected int $acumulado_calculado = 0;
	protected int $asignado_manualmente = 0;
	protected int $acumulado_asignado_manualmente = 0;
	protected int $vigente = 0;
	protected ?string $recalculado_at = null;
	protected string $created_at = '';
	protected string $updated_at = '';

	public function __construct() {
		$this->addType('id_empleado', 'integer');
		$this->addType('numero_aniversario', 'integer');
		$this->addType('periodo_inicio', 'string');
		$this->addType('periodo_fin', 'string');
		$this->addType('dias_derecho', 'decimal');
		$this->addType('dias_acumulados', 'decimal');
		$this->addType('dias_acumulados_restantes', 'decimal');
		$this->addType('fecha_ingreso_base', 'string');
		$this->addType('dias_periodo_usados', 'decimal');
		$this->addType('dias_acumulados_usados', 'decimal');
		$this->addType('dias_acumulados_vencidos', 'decimal');
		$this->addType('dias_excedentes', 'decimal');
		$this->addType('fecha_expiracion_acumulados', 'string');
		$this->addType('acumulado_calculado', 'integer');
		$this->addType('asignado_manualmente', 'integer');
		$this->addType('acumulado_asignado_manualmente', 'integer');
		$this->addType('vigente', 'integer');
		$this->addType('recalculado_at', 'string');
		$this->addType('created_at', 'string');
		$this->addType('updated_at', 'string');
	}

	public function read(): array {
		return [
			'id_empleado' => $this->id_empleado,
			'numero_aniversario' => $this->numero_aniversario,
			'periodo_inicio' => $this->periodo_inicio,
			'periodo_fin' => $this->periodo_fin,
			'dias_derecho' => $this->dias_derecho,
			'dias_acumulados' => $this->dias_acumulados,
			'dias_acumulados_restantes' => $this->dias_acumulados_restantes,
			'fecha_ingreso_base' => $this->fecha_ingreso_base,
			'dias_periodo_usados' => $this->dias_periodo_usados,
			'dias_acumulados_usados' => $this->dias_acumulados_usados,
			'dias_acumulados_vencidos' => $this->dias_acumulados_vencidos,
			'dias_excedentes' => $this->dias_excedentes,
			'fecha_expiracion_acumulados' => $this->fecha_expiracion_acumulados,
			'acumulado_calculado' => $this->acumulado_calculado,
			'asignado_manualmente' => $this->asignado_manualmente,
			'acumulado_asignado_manualmente' => $this->acumulado_asignado_manualmente,
			'vigente' => $this->vigente,
			'recalculado_at' => $this->recalculado_at,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}
