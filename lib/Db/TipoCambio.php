<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getIdMoneda()
 * @method void setIdMoneda(int $idMoneda)
 * @method string getFecha()
 * @method void setFecha(string $fecha)
 * @method float getValor()
 * @method void setValor(float $valor)
 */
class TipoCambio extends Entity {
	protected $idMoneda;
	protected $fecha;
	protected $valor;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('idMoneda', 'integer');
		$this->addType('valor', 'float');
	}
}