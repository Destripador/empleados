<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class festivos extends Entity {
	protected ?int $id_festivo = null;
	protected string $nombre = '';
	protected string $fecha = '';
	protected string $tipo = 'fijo';
	protected int $oficial = 0;
	protected ?int $reglaMes = null;
	protected ?int $reglaSemana = null;
	protected ?int $reglaDiaSemana = null;
	protected ?int $anioCalculado = null;

	public function __construct() {
		$this->addType('id_festivo', 'integer');
		$this->addType('nombre', 'string');
		$this->addType('fecha', 'string');
		$this->addType('tipo', 'string');
		$this->addType('oficial', 'integer');
		$this->addType('reglaMes', 'integer');
		$this->addType('reglaSemana', 'integer');
		$this->addType('reglaDiaSemana', 'integer');
		$this->addType('anioCalculado', 'integer');
	}

	public function read(): array {
		return [
			'id_festivo' => $this->id_festivo,
			'nombre' => $this->nombre,
			'fecha' => $this->fecha,
			'tipo' => $this->tipo,
			'oficial' => $this->oficial,
			'regla_mes' => $this->reglaMes,
			'regla_semana' => $this->reglaSemana,
			'regla_dia_semana' => $this->reglaDiaSemana,
			'anio_calculado' => $this->anioCalculado,
		];
	}
}