<?php

declare(strict_types=1);

namespace OCA\Empleados\Dashboard;

use OCA\Empleados\AppInfo\Application;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IWidget;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

class ReportesWidget implements IWidget, IIconWidget {

	public function __construct(
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
	) {
	}

	public function getId(): string {
		return 'empleados_reportes';
	}

	public function getTitle(): string {
		return $this->l10n->t('Report time');
	}

	public function getOrder(): int {
		return 50;
	}

	public function getIconClass(): string {
		return 'icon-timezone';
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')
		);
	}

	public function getUrl(): ?string {
		return null;
	}

	public function load(): void {
		Util::addTranslations(Application::APP_ID);
		Util::addScript(Application::APP_ID, 'empleados-dashboard-reportes');
	}
}
