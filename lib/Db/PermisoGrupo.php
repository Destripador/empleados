<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\Entity;

class PermisoGrupo extends Entity {

	protected int $id = 0;
	protected string $module = '';
	protected string $permission = '';
	protected string $groupId = '';
	protected string $label = '';
	protected ?string $description = null;
	protected int $restricted = 0;
	protected int $enabled = 1;
	protected int $sortOrder = 0;
	protected string $createdAt = '';
	protected ?string $updatedAt = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('module', 'string');
		$this->addType('permission', 'string');
		$this->addType('group_id', 'string');
		$this->addType('label', 'string');
		$this->addType('description', 'string');
		$this->addType('restricted', 'integer');
		$this->addType('enabled', 'integer');
		$this->addType('sort_order', 'integer');
		$this->addType('created_at', 'string');
		$this->addType('updated_at', 'string');
	}

	public function read(): array {
		return [
			'id' => $this->id,
			'module' => $this->module,
			'permission' => $this->permission,
			'group_id' => $this->groupId,
			'label' => $this->label,
			'description' => $this->description,
			'restricted' => $this->restricted,
			'enabled' => $this->enabled,
			'sort_order' => $this->sortOrder,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}