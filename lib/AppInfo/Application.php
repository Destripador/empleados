<?php

namespace OCA\Empleados\AppInfo;

use OCA\Empleados\Activity\ActivityProvider;
# use OCA\Empleados\Cron\ActualizarAniversarios;
use OCA\Empleados\Helper\MailHelper;
use OCA\Empleados\Dashboard\ReportesWidget;

use OCP\Activity\IManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCP\Mail\IMailer;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\BackgroundJob\IJobList;

class Application extends App implements IBootstrap {
	public const APP_ID = 'empleados';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerService(MailHelper::class, function($c) {
			return new MailHelper(
				$c->query(IMailer::class),
				$c->query(IL10N::class),
				'servicios.torreon@mail.com',
				$c->query(IURLGenerator::class)
			);
		});

		$context->registerDashboardWidget(ReportesWidget::class);
	}

	public function boot(IBootContext $context): void {
	}
}