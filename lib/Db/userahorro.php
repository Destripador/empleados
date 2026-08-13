<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;


class userahorro extends Entity {
    
    protected string $id_ahorro = '';
    protected string $id_user = '';
    protected string $id_permision = '';
    protected string $state = '';
    protected string $last_modified = '';

	public function __construct() {
        $this->addType('id_ahorro', 'string');
        $this->addType('id_user', 'string');
		$this->addType('id_permision', 'string');
		$this->addType('state', 'string');
		$this->addType('last_modified', 'integer');
	}

	public function read(): array {
		return [
			'id_ahorro' => $this->id_ahorro,
			'id_user' => $this->id_user,
			'id_permision' => $this->id_permision,
			'state' => $this->state,
			'last_modified' => (int) $this->last_modified,
		];
	}
}