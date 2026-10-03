<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getTipoMoneda()
 * @method void setTipoMoneda(string $tipoMoneda)
 * @method string getSerie()
 * @method void setSerie(string $serie)
 */
class Moneda extends Entity {
	protected $tipoMoneda;
	protected $serie;

	public function __construct() {
		$this->addType('id', 'integer');
	}
}