<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use OCA\Empleados\Exception\PdfGenerationException;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;
use Throwable;

class PdfService {
	private ITempManager $tempManager;
	private LoggerInterface $logger;

	public function __construct(
		ITempManager $tempManager,
		LoggerInterface $logger
	) {
		$this->tempManager = $tempManager;
		$this->logger = $logger;
	}

	public function generate(
		string $html,
		string $format = 'A4',
		string $orientation = 'P'
	): string {
		$orientation = strtoupper($orientation);

		try {
			if (!in_array($orientation, ['P', 'L'], true)) {
				throw new PdfGenerationException('No fue posible generar el PDF: la orientación no es válida.');
			}

			$tempDir = $this->tempManager->getTemporaryFolder('empleados-mpdf');

			if ($tempDir === false || !is_dir($tempDir) || !is_writable($tempDir)) {
				throw new PdfGenerationException('No fue posible preparar el directorio temporal del PDF.');
			}

			@chmod($tempDir, 0700);

			$mpdf = new Mpdf([
				'mode' => 'utf-8',
				'format' => $format,
				'orientation' => $orientation,
				'tempDir' => $tempDir,
				'margin_top' => 15,
				'margin_right' => 15,
				'margin_bottom' => 15,
				'margin_left' => 15,
				'default_font' => 'dejavusans',
				'whitelistStreamWrappers' => ['file'],
				'curlAllowUnsafeSslRequests' => false,
			]);

			$mpdf->WriteHTML($html);

			return $mpdf->Output('', Destination::STRING_RETURN);
		} catch (Throwable $e) {
			$this->logger->error('No se pudo generar el PDF con mPDF.', [
				'app' => 'empleados',
				'exception_class' => $e::class,
				'exception_message' => $e->getMessage(),
				'format' => $format,
				'orientation' => $orientation,
			]);

			if ($e instanceof PdfGenerationException) {
				throw $e;
			}

			throw new PdfGenerationException(
				'No fue posible generar el PDF. Revisa el registro de Nextcloud para más información.',
				0,
				$e
			);
		}
	}
}
