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
	protected ?string $fecha_expiracion_acumulados = null;
	protected int $acumulado_calculado = 0;
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
		$this->addType('fecha_expiracion_acumulados', 'string');
		$this->addType('acumulado_calculado', 'integer');
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
			'fecha_expiracion_acumulados' => $this->fecha_expiracion_acumulados,
			'acumulado_calculado' => $this->acumulado_calculado,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		];
	}
}