<?php

declare(strict_types=1);

namespace OCA\Empleados\Settings;

use OCA\Empleados\Db\configuracionesMapper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class settingsAdmin implements ISettings {
	private IL10N $l;
	private IConfig $config;
	private configuracionesMapper $configuracionesMapper;

	public function __construct(
		IConfig $config,
		IL10N $l,
		configuracionesMapper $configuracionesMapper,
	) {
		$this->config = $config;
		$this->l = $l;
		$this->configuracionesMapper = $configuracionesMapper;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$rows = $this->configuracionesMapper->GetConfig();
		$params = is_array($rows) ? array_column($rows, 'Data', 'Nombre') : [];

		return new TemplateResponse('empleados', 'settings/admin', [
			'config' => $params,
		], '');
	}

	public function getSection(): string {
		return 'empleados'; // Nombre de la sección creada
	}

	public function getPriority(): int {
		return 1;
	}
}
