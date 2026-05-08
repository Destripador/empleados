<?php

declare(strict_types=1);

namespace OCA\Empleados\AppInfo;

use OCA\Empleados\Cron\RecordatorioReportesTiempo;
use OCA\Empleados\Dashboard\ReportesWidget;
use OCA\Empleados\Helper\MailHelper;
use OCA\Empleados\Notification\ComprasNotifier;
use OCA\Empleados\Notification\ReportesNotifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;

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

		$context->registerNotifierService(ReportesNotifier::class);
		$context->registerNotifierService(ComprasNotifier::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function(IJobList $jobList) {
			if (!$jobList->has(RecordatorioReportesTiempo::class, null)) {
				$jobList->add(RecordatorioReportesTiempo::class);
			}
		});
	}
}