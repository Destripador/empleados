<?php

declare(strict_types=1);

namespace OCA\Empleados\AppInfo;

use OCA\Empleados\Command\SeedFestivosOficiales;
use OCA\Empleados\Listener\MovimientoArchivoListener;
use OCP\IDBConnection;
use OCA\Empleados\Cron\RecordatorioReportesTiempo;
use OCA\Empleados\Cron\RecordatorioPrimaVacacional;
use OCA\Empleados\BackgroundJob\RecalcularVacacionesJob;
use OCA\Empleados\Service\AniversarioSyncService;
use OCA\Empleados\Db\historialvacacionesMapper;
use OCA\Empleados\BackgroundJob\RecalcularFestivosVariablesJob;
use OCA\Empleados\Dashboard\ReportesWidget;
use OCA\Empleados\Dashboard\SoporteEquipoWidget;
use OCA\Empleados\Notification\ComprasNotifier;
use OCA\Empleados\Notification\ReportesNotifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'empleados';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$autoloadPath = __DIR__ . '/../../vendor/autoload.php';

		if (is_file($autoloadPath)) {
			require_once $autoloadPath;
		}

		$context->registerEventListener(NodeCreatedEvent::class, MovimientoArchivoListener::class);
		$context->registerEventListener(NodeWrittenEvent::class, MovimientoArchivoListener::class);
		$context->registerEventListener(NodeRenamedEvent::class, MovimientoArchivoListener::class);
		$context->registerEventListener(NodeCopiedEvent::class, MovimientoArchivoListener::class);
		$context->registerEventListener(NodeDeletedEvent::class, MovimientoArchivoListener::class);

		$context->registerService(AniversarioSyncService::class, function($c) {
			return new AniversarioSyncService(
				$c->query(historialvacacionesMapper::class)
			);
		});

		$context->registerDashboardWidget(ReportesWidget::class);
		$context->registerDashboardWidget(SoporteEquipoWidget::class);
		$context->registerNotifierService(ReportesNotifier::class);
		$context->registerNotifierService(ComprasNotifier::class);
		$context->registerService(SeedFestivosOficiales::class, function($c) {
			return new SeedFestivosOficiales(
				$c->query(IDBConnection::class),
				$c->query(\OCA\Empleados\Db\festivosMapper::class)
			);
		});
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function(IJobList $jobList) {
			if (!$jobList->has(RecordatorioReportesTiempo::class, null)) {
				$jobList->add(RecordatorioReportesTiempo::class);
			}

			if (!$jobList->has(RecalcularVacacionesJob::class, null)) {
				$jobList->add(RecalcularVacacionesJob::class);
			}

			if (!$jobList->has(RecalcularFestivosVariablesJob::class, null)) {
				$jobList->add(RecalcularFestivosVariablesJob::class);
			}

			if (!$jobList->has(RecordatorioPrimaVacacional::class, null)) {
				$jobList->add(RecordatorioPrimaVacacional::class);
			}
		});
	}
}
