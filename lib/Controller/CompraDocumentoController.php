<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\Service\CompraSolicitudService;
use OCA\Empleados\Service\PdfService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

use OCA\Empleados\Db\CompraSolicitudMapper;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

use OCA\Empleados\Db\CompraHistorialMapper;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;

use OCA\Empleados\Service\CompraPermisosService;
use Exception;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
/**
 * Controlador para generar el PDF de solicitud de compra.
 */
class CompraDocumentoController extends Controller {
	private const MAX_SIGNED_FILE_SIZE = 10485760;

	private CompraSolicitudService $service;
	private IUserSession $userSession;
	private IRootFolder $rootFolder;
	private CompraSolicitudMapper $solicitudMapper;
	private CompraHistorialMapper $historialMapper;
	private IURLGenerator $urlGenerator;
	private IClientService $clientService;
	private CompraPermisosService $permisosService;
	private IAppData $appData;
	private PdfService $pdfService;

	public function __construct(
		string $appName,
		IRequest $request,
		CompraSolicitudService $service,
		IUserSession $userSession,
		IRootFolder $rootFolder,
		CompraSolicitudMapper $solicitudMapper,
		CompraHistorialMapper $historialMapper,
		CompraPermisosService $permisosService,
		IURLGenerator $urlGenerator,
		IClientService $clientService,
		IAppData $appData,
		PdfService $pdfService
	) {
		parent::__construct($appName, $request);

		$this->service = $service;
		$this->userSession = $userSession;
		$this->rootFolder = $rootFolder;
		$this->solicitudMapper = $solicitudMapper;
		$this->historialMapper = $historialMapper;
		$this->permisosService = $permisosService;
		$this->urlGenerator = $urlGenerator;
		$this->clientService = $clientService;
		$this->appData = $appData;
		$this->pdfService = $pdfService;
	}

	/**
	* @NoAdminRequired
	* @NoCSRFRequired
	*/
	public function documento(int $id): DataDisplayResponse {
		try {
			$user = $this->userSession->getUser();

			if ($user === null) {
				return new DataDisplayResponse(
					'Usuario no autenticado.',
					Http::STATUS_FORBIDDEN,
					['Content-Type' => 'text/plain; charset=utf-8']
				);
			}

			$generado = $this->generarPdfSolicitud($id, $user->getUID());

			return new DataDisplayResponse(
				$generado['pdf'],
				Http::STATUS_OK,
				[
					'Content-Type' => 'application/pdf',
					'Content-Disposition' => 'inline; filename="' . $generado['fileName'] . '"',
					'Cache-Control' => 'no-store, no-cache, must-revalidate',
					'Pragma' => 'no-cache',
				]
			);
		} catch (\Throwable $e) {
			return new DataDisplayResponse(
				'No se pudo generar el PDF: ' . $e->getMessage(),
				Http::STATUS_BAD_REQUEST,
				['Content-Type' => 'text/plain; charset=utf-8']
			);
		}
	}

	private function renderDocumentoHtml(array $data): string {
		$solicitud = $data['solicitud']->jsonSerialize();

		$detalles = array_map(static function ($detalle): array {
			return $detalle->jsonSerialize();
		}, $data['detalles'] ?? []);

		$historial = array_map(static function ($evento): array {
			return method_exists($evento, 'jsonSerialize') ? $evento->jsonSerialize() : [];
		}, $data['historial'] ?? []);

		$idSolicitud = (string)($solicitud['id_solicitud'] ?? '');
		$folio = (string)($solicitud['folio'] ?? ('Solicitud #' . $idSolicitud));
		$estado = (string)($solicitud['estado'] ?? '');
		$garantia = ((int)($solicitud['garantia'] ?? 0)) === 1 ? 'Sí' : 'No';

		$first = $detalles[0] ?? [];

		$proveedor = $solicitud['proveedor_nombre'] ?? $first['proveedor_nombre'] ?? '';
		$atencion = $solicitud['atencion'] ?? $first['atencion'] ?? '';
		$entrega = $solicitud['entrega'] ?? $first['entrega'] ?? '';
		$marcaModelo = $solicitud['marca_modelo'] ?? $first['marca_modelo'] ?? '';
		$especificaciones = $solicitud['especificaciones'] ?? $first['especificaciones'] ?? '';

		$totalExclIva = $this->money($solicitud['total_excl_iva'] ?? $solicitud['monto_estimado'] ?? 0);
		$iva = $this->money($solicitud['iva'] ?? 0);
		$totalInclIva = $this->money($solicitud['total_incl_iva'] ?? $solicitud['monto_final'] ?? 0);

		$conceptRows = $this->renderConceptRows($detalles);
		$historyRows = $this->renderHistoryRows($historial);

		$logoDataUri = $this->getInstanceLogoDataUri();

		$logoHtml = $logoDataUri !== ''
			? '<td class="logo-cell"><img class="instance-logo" src="' . $this->e($logoDataUri) . '" alt="Logo"></td>'
			: '';

		return '<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>' . $this->e($folio) . '</title>
	<style>
		@page { margin: 15mm; }
		* { box-sizing: border-box; }
		body { margin: 0; color: #111827; background: #fff; font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; line-height: 1.25; }
		.header { width: 100%; border-bottom: 3px solid #111827; padding-bottom: 10px; margin-bottom: 12px; }
		.header-table { width: 100%; border-collapse: collapse; }
		.header-table td { border: 0; padding: 0; vertical-align: top; }
		.company { font-size: 10pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #374151; }
		h1 { margin: 4px 0 2px 0; font-size: 22pt; line-height: 1; text-transform: uppercase; }
		.folio { font-size: 12pt; font-weight: bold; color: #374151; }
		.meta { width: 210px; border: 1px solid #111827; }
		.meta-title { padding: 5px 7px; background: #111827; color: #fff; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; }
		.meta-row { width: 100%; border-collapse: collapse; }
		.meta-row td { border-top: 1px solid #111827; padding: 4px 6px; font-size: 9pt; }
		.meta-row td:first-child { width: 72px; background: #f3f4f6; color: #4b5563; font-weight: bold; text-transform: uppercase; }
		.section { margin-top: 10px; border: 1px solid #111827; }
		.section-title { padding: 5px 7px; background: #111827; color: #fff; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
		.grid { width: 100%; border-collapse: collapse; }
		.grid td { width: 25%; padding: 6px 7px; border-top: 1px solid #111827; border-right: 1px solid #111827; vertical-align: top; }
		.grid td:last-child { border-right: 0; }
		.grid.two td { width: 50%; }
		.label { display: block; margin-bottom: 3px; color: #4b5563; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
		.value { display: block; font-size: 9.5pt; font-weight: bold; white-space: pre-wrap; word-break: break-word; }
		table.items { width: 100%; border-collapse: collapse; page-break-inside: auto; }
		table.items thead { display: table-header-group; }
		table.items tbody { display: table-row-group; }
		table.items tr { page-break-inside: auto; }
		table.items th, table.items td { padding: 5px 6px; border-top: 1px solid #111827; border-right: 1px solid #111827; vertical-align: top; text-align: left; }
		table.items th:last-child, table.items td:last-child { border-right: 0; }
		table.items th { background: #f3f4f6; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
		.text-right { text-align: right !important; }
		.text-center { text-align: center !important; }
		.muted { display: block; margin-top: 3px; color: #6b7280; font-size: 8pt; font-weight: normal; white-space: pre-wrap; }
		.totals { width: 100%; margin-top: 10px; border-collapse: collapse; page-break-inside: avoid; }
		.totals td { width: 33.333%; padding: 8px; border: 1px solid #111827; text-align: center; }
		.totals span { display: block; color: #4b5563; font-size: 8pt; font-weight: bold; text-transform: uppercase; }
		.totals strong { display: block; margin-top: 3px; font-size: 15pt; }
		.signatures { width: 100%; margin-top: 22px; border-collapse: collapse; page-break-inside: avoid; }
		.signatures td { width: 25%; padding: 0 6px; border: 0; text-align: center; vertical-align: bottom; font-size: 8.5pt; }
		.signature-line { height: 40px; border-bottom: 1px solid #111827; margin-bottom: 5px; }
		.footer-note { margin-top: 12px; padding-top: 7px; border-top: 1px solid #d1d5db; color: #6b7280; font-size: 7.5pt; text-align: center; }
		.brand-table {width: 100%;
			border-collapse: collapse;
		}
		.brand-table td {
			border: 0;
			padding: 0;
			vertical-align: middle;
		}
		.logo-cell {
			width: 72px;
			padding-right: 12px !important;
		}
		.instance-logo {
			display: block;
			width: 64px;
			height: auto;
		}
	</style>
</head>
<body>
	<div class="header">
		<table class="header-table">
			<tr>
				<td>
					<table class="brand-table">
						<tr>
							' . $logoHtml . '
							<td>
								<div class="company">Módulo de empleados</div>
								<h1>Solicitud de compra</h1>
								<div class="folio">' . $this->e($folio) . '</div>
							</td>
						</tr>
					</table>
				</td>
				<td style="width: 220px;">
					<div class="meta">
						<div class="meta-title">Control del documento</div>
						<table class="meta-row">
							<tr><td>No.</td><td>' . $this->e($idSolicitud) . '</td></tr>
							<tr><td>Fecha</td><td>' . $this->e($this->formatDate($solicitud['created_at'] ?? '')) . '</td></tr>
							<tr><td>Estado</td><td>' . $this->e($this->estadoLabel($estado)) . '</td></tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</div>

	<div class="section">
		<div class="section-title">Datos del solicitante</div>
		<table class="grid"><tr>
			<td><span class="label">Nombre</span><span class="value">' . $this->e($solicitud['solicitante_nombre'] ?? $solicitud['id_user'] ?? '') . '</span></td>
			<td><span class="label">Departamento</span><span class="value">' . $this->e($solicitud['solicitante_depto'] ?? '') . '</span></td>
			<td><span class="label">Puesto</span><span class="value">' . $this->e($solicitud['solicitante_cargo'] ?? '') . '</span></td>
			<td><span class="label">Jefe directo</span><span class="value">' . $this->e($solicitud['jefe_directo_nombre'] ?? '') . '</span></td>
		</tr></table>
	</div>

	<div class="section">
		<div class="section-title">Datos generales de la compra</div>
		<table class="grid"><tr>
			<td><span class="label">Título</span><span class="value">' . $this->e($solicitud['titulo'] ?? '') . '</span></td>
			<td><span class="label">Tipo</span><span class="value">' . $this->e($this->humanLabel($solicitud['tipo_compra'] ?? '')) . '</span></td>
			<td><span class="label">Uso</span><span class="value">' . $this->e($this->humanLabel($solicitud['uso_compra'] ?? '')) . '</span></td>
			<td><span class="label">Garantía</span><span class="value">' . $this->e($garantia) . '</span></td>
		</tr></table>
		<table class="grid"><tr>
			<td><span class="label">Fecha requerida</span><span class="value">' . $this->e($this->formatDate($solicitud['fecha_requerida'] ?? '')) . '</span></td>
			<td><span class="label">Fecha envío</span><span class="value">' . $this->e($this->formatDate($solicitud['fecha_envio'] ?? '')) . '</span></td>
			<td><span class="label">Fecha autorización</span><span class="value">' . $this->e($this->formatDate($solicitud['fecha_autorizacion'] ?? '')) . '</span></td>
			<td><span class="label">Moneda</span><span class="value">' . $this->e($solicitud['moneda'] ?? 'MXN') . '</span></td>
		</tr></table>
		<table class="grid two"><tr>
			<td><span class="label">Información</span><span class="value">' . $this->e($solicitud['informacion'] ?? $solicitud['descripcion'] ?? '') . '</span></td>
			<td><span class="label">Motivo / justificación</span><span class="value">' . $this->e($solicitud['motivo'] ?? $solicitud['justificacion'] ?? '') . '</span></td>
		</tr></table>
	</div>

	<div class="section">
		<div class="section-title">Proveedor / requisición</div>
		<table class="grid"><tr>
			<td><span class="label">Proveedor</span><span class="value">' . $this->e($proveedor) . '</span></td>
			<td><span class="label">Atención</span><span class="value">' . $this->e($atencion) . '</span></td>
			<td><span class="label">Entrega</span><span class="value">' . $this->e($entrega) . '</span></td>
			<td><span class="label">Marca / modelo</span><span class="value">' . $this->e($marcaModelo) . '</span></td>
		</tr></table>
		<table class="grid two"><tr>
			<td><span class="label">Especificaciones</span><span class="value">' . $this->e($especificaciones) . '</span></td>
			<td><span class="label">Comentarios requisición</span><span class="value">' . $this->e($solicitud['comentarios_req'] ?? '') . '</span></td>
		</tr></table>
	</div>

	<div class="section">
		<div class="section-title">Productos / servicios solicitados</div>
		<table class="items">
			<thead><tr>
				<th style="width:34%;">Descripción</th><th style="width:14%;">Proveedor</th><th style="width:8%;" class="text-center">Cant.</th><th style="width:9%;" class="text-center">Unidad</th><th style="width:12%;" class="text-right">Precio</th><th style="width:11%;" class="text-right">IVA</th><th style="width:12%;" class="text-right">Total</th>
			</tr></thead>
			<tbody>' . $conceptRows . '</tbody>
		</table>
	</div>

	<table class="totals"><tr>
		<td><span>Subtotal</span><strong>' . $totalExclIva . '</strong></td>
		<td><span>IVA</span><strong>' . $iva . '</strong></td>
		<td><span>Total</span><strong>' . $totalInclIva . '</strong></td>
	</tr></table>

	<div class="section">
		<div class="section-title">Administración</div>
		<table class="grid"><tr>
			<td><span class="label">Oficina %</span><span class="value">' . $this->e($solicitud['oficina_pct'] ?? '') . '</span></td>
			<td><span class="label">Empleado %</span><span class="value">' . $this->e($solicitud['empleado_pct'] ?? '') . '</span></td>
			<td><span class="label">Tipo de pago</span><span class="value">' . $this->e($this->humanLabel($solicitud['tipo_pago'] ?? '')) . '</span></td>
			<td><span class="label">Quincenas</span><span class="value">' . $this->e($solicitud['quincenas'] ?? '') . '</span></td>
		</tr></table>
		<table class="grid two"><tr>
			<td><span class="label">Comentarios administración</span><span class="value">' . $this->e($solicitud['comentarios_admin'] ?? '') . '</span></td>
			<td><span class="label">Observaciones</span><span class="value">Este documento debe conservarse firmado y digitalizado como evidencia de autorización.</span></td>
		</tr></table>
	</div>

	<table class="signatures"><tr>
		<td><div class="signature-line"></div><strong>Solicitó</strong><br>' . $this->e($solicitud['solicitante_nombre'] ?? $solicitud['id_user'] ?? '') . '</td>
		<td><div class="signature-line"></div><strong>Jefe directo</strong><br>' . $this->e($solicitud['jefe_directo_nombre'] ?? '') . '</td>
		<td><div class="signature-line"></div><strong>Compras / Sistemas</strong><br>Validación técnica / administrativa</td>
		<td><div class="signature-line"></div><strong>Administración</strong><br>Recepción y archivo</td>
	</tr></table>

	<div class="footer-note">Documento generado por el módulo de Empleados. La autorización final deberá conservarse con firma física o digitalización adjunta.</div>

	<div class="section">
		<div class="section-title">Historial de autorización</div>
		<table class="items"><thead><tr><th>Acción</th><th>Estado anterior</th><th>Estado nuevo</th><th>Usuario</th><th>Fecha</th><th>Comentario</th></tr></thead><tbody>' . $historyRows . '</tbody></table>
	</div>
</body>
</html>';
	}

	private function renderConceptRows(array $detalles): string {
		if (count($detalles) === 0) {
			return '<tr><td colspan="7" class="text-center">Sin conceptos registrados.</td></tr>';
		}

		$rows = '';

		foreach ($detalles as $detalle) {
			$extra = [];

			if (!empty($detalle['marca_modelo'])) {
				$extra[] = 'Marca / modelo: ' . (string)$detalle['marca_modelo'];
			}

			if (!empty($detalle['especificaciones'])) {
				$extra[] = (string)$detalle['especificaciones'];
			}

			if (!empty($detalle['notas'])) {
				$extra[] = 'Notas: ' . (string)$detalle['notas'];
			}

			$extraHtml = count($extra) > 0
				? '<span class="muted">' . $this->e(implode("\n", $extra)) . '</span>'
				: '';

			$total = $detalle['total'] ?? (($detalle['subtotal'] ?? 0) + ($detalle['iva'] ?? 0));

			$rows .= '<tr>'
				. '<td><strong>' . $this->e($detalle['descripcion'] ?? '') . '</strong>' . $extraHtml . '</td>'
				. '<td>' . $this->e($detalle['proveedor_nombre'] ?? '') . '</td>'
				. '<td class="text-center">' . $this->e($detalle['cantidad'] ?? '') . '</td>'
				. '<td class="text-center">' . $this->e($detalle['unidad'] ?? '') . '</td>'
				. '<td class="text-right">' . $this->money($detalle['precio_estimado'] ?? 0) . '</td>'
				. '<td class="text-right">' . $this->money($detalle['iva'] ?? 0) . '</td>'
				. '<td class="text-right"><strong>' . $this->money($total) . '</strong></td>'
				. '</tr>';
		}

		return $rows;
	}

	private function renderHistoryRows(array $historial): string {
		if (count($historial) === 0) {
			return '<tr><td colspan="6" class="text-center">Sin historial registrado.</td></tr>';
		}

		$rows = '';

		foreach ($historial as $evento) {
			$rows .= '<tr>'
				. '<td>' . $this->e($this->humanLabel($evento['accion'] ?? '')) . '</td>'
				. '<td>' . $this->e($this->estadoLabel((string)($evento['estado_anterior'] ?? ''))) . '</td>'
				. '<td>' . $this->e($this->estadoLabel((string)($evento['estado_nuevo'] ?? ''))) . '</td>'
				. '<td>' . $this->e($evento['created_by'] ?? '') . '</td>'
				. '<td>' . $this->e($this->formatDate($evento['created_at'] ?? '')) . '</td>'
				. '<td>' . $this->e($evento['comentario'] ?? '') . '</td>'
				. '</tr>';
		}

		return $rows;
	}

	private function e($value): string {
		return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function money($value): string {
		return '$' . number_format((float)$value, 2, '.', ',');
	}

	private function formatDate($value): string {
		if ($value === null || $value === '') {
			return '-';
		}

		try {
			$date = new \DateTime((string)$value);
			return $date->format('d/m/Y H:i');
		} catch (Throwable $e) {
			return (string)$value;
		}
	}

	private function estadoLabel(string $estado): string {
		$map = [
			'borrador' => 'Borrador',
			'pendiente_autorizacion' => 'Pendiente de autorización',
			'autorizada' => 'Autorizada',
			'rechazada' => 'Rechazada',
			'cancelada' => 'Cancelada',
		];

		return $map[$estado] ?? $this->humanLabel($estado);
	}

	private function humanLabel($value): string {
		$value = trim((string)$value);

		if ($value === '') {
			return '-';
		}

		$value = str_replace('_', ' ', $value);

		if (function_exists('mb_convert_case')) {
			return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
		}

		return ucwords($value);
	}

	private function safeFilename(string $value): string {
		$value = preg_replace('/[^0-9a-zA-ZáéíóúÁÉÍÓÚñÑ._ -]/u', '_', $value) ?? 'documento.pdf';
		$value = trim($value);

		return $value !== '' ? $value : 'documento.pdf';
	}
	/**
	* @NoAdminRequired
	*/
	public function guardarDocumento(int $id): DataResponse {
		try {
			$user = $this->userSession->getUser();

			if ($user === null) {
				return new DataResponse([
					'success' => false,
					'message' => 'Usuario no autenticado.',
				], Http::STATUS_FORBIDDEN);
			}

			$userId = $user->getUID();
			$data = $this->service->obtenerDetalle($id, $userId);
			$solicitud = $data['solicitud']->jsonSerialize();
			$this->assertCanManageOfficialDocuments($solicitud, $userId);

			$generado = $this->generarPdfSolicitud($id, $userId, $data);

			$file = $this->guardarPdfEnArchivos(
				$userId,
				$generado['folio'],
				$generado['fileName'],
				$generado['pdf']
			);

			$this->solicitudMapper->updateSolicitud($id, [
				'pdf_file_id' => $file->getId(),
				'pdf_nombre' => $generado['fileName'],
				'pdf_generado_at' => date('Y-m-d H:i:s'),
				'updated_by' => $userId,
			]);

			$this->historialMapper->insertHistorial(
				$id,
				'pdf_generado',
				$generado['estado'] ?? null,
				$generado['estado'] ?? null,
				'PDF generado y guardado: ' . $generado['fileName'],
				[
					'file_id' => $file->getId(),
					'file_name' => $generado['fileName'],
					'path' => $generado['path'] ?? null,
				],
				$userId
			);

			return new DataResponse([
				'success' => true,
				'message' => 'PDF guardado correctamente.',
				'data' => [
					'file_id' => $file->getId(),
					'file_name' => $generado['fileName'],
					'path' => $generado['path'],
				],
			]);
		} catch (\Throwable $e) {
			return new DataResponse([
				'success' => false,
				'message' => 'No se pudo guardar el PDF: ' . $e->getMessage(),
			], $this->errorStatus($e));
		}
	}

	private function generarPdfSolicitud(int $id, string $userId, ?array $detail = null): array {
		$data = $detail ?? $this->service->obtenerDetalle($id, $userId);
		$solicitud = $data['solicitud']->jsonSerialize();

		$folio = (string)($solicitud['folio'] ?? ('Solicitud-' . $id));
		$fileName = $this->safeFilename('Solicitud-compra-' . $folio . '.pdf');

		$html = $this->renderDocumentoHtml($data);
		$pdf = $this->pdfService->generate($html, 'A4', 'P');

		return [
			'pdf' => $pdf,
			'folio' => $folio,
			'fileName' => $fileName,
			'path' => 'Compras/Solicitudes/' . $this->safePathSegment($folio) . '/' . $fileName,
			'estado' => (string)($solicitud['estado'] ?? ''),
		];
	}
	private function guardarPdfEnArchivos(
		string $userId,
		string $folio,
		string $fileName,
		string $pdf
	): File {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$folder = $this->ensureFolder(
			$userFolder,
			'Compras/Solicitudes/' . $this->safePathSegment($folio)
		);

		if ($folder->nodeExists($fileName)) {
			$node = $folder->get($fileName);

			if (!$node instanceof File) {
				throw new Exception('Ya existe una carpeta con el nombre del PDF.');
			}

			$file = $node;
		} else {
			$file = $folder->newFile($fileName);
		}

		$file->putContent($pdf);

		return $file;
	}

	private function ensureFolder(Folder $baseFolder, string $path): Folder {
		$folder = $baseFolder;
		$parts = array_filter(explode('/', trim($path, '/')));

		foreach ($parts as $part) {
			$part = $this->safePathSegment($part);

			if ($part === '') {
				continue;
			}

			if ($folder->nodeExists($part)) {
				$node = $folder->get($part);

				if (!$node instanceof Folder) {
					throw new Exception('Existe un archivo donde se esperaba una carpeta: ' . $part);
				}

				$folder = $node;
				continue;
			}

			$folder = $folder->newFolder($part);
		}

		return $folder;
	}

	private function safePathSegment(string $value): string {
		$value = trim($value);
		$value = preg_replace('/[\/\\\\:*?"<>|]/u', '_', $value) ?? '';
		$value = preg_replace('/\s+/u', ' ', $value) ?? '';

		return trim($value);
	}
	/**
	* @NoAdminRequired
	*/
	public function subirFirmado(int $id): DataResponse {
		try {
			$user = $this->userSession->getUser();

			if ($user === null) {
				return new DataResponse([
					'success' => false,
					'message' => 'Usuario no autenticado.',
				], Http::STATUS_FORBIDDEN);
			}

			$userId = $user->getUID();

			$data = $this->service->obtenerDetalle($id, $userId);
			$solicitud = $data['solicitud']->jsonSerialize();
			$this->assertCanManageOfficialDocuments($solicitud, $userId);

			$uploadedFile = $this->request->getUploadedFile('archivo');

			if (!is_array($uploadedFile) || empty($uploadedFile['tmp_name'])) {
				throw new Exception('No se recibió ningún archivo.');
			}

			if ((int)($uploadedFile['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
				throw new Exception('Error al subir el archivo.');
			}

			$originalName = (string)($uploadedFile['name'] ?? 'documento-firmado.pdf');
			$tmpName = (string)$uploadedFile['tmp_name'];

			$content = file_get_contents($tmpName);

			if ($content === false || $content === '') {
				throw new Exception('No se pudo leer el archivo subido.');
			}

			$validated = $this->validarArchivoFirmado(
				$originalName,
				(string)($uploadedFile['type'] ?? ''),
				$content,
				(int)($uploadedFile['size'] ?? 0)
			);
			$mime = $validated['mime'];
			$extension = $validated['extension'];
			$folio = (string)($solicitud['folio'] ?? ('Solicitud-' . $id));
			$fileName = $this->safeFilename('Solicitud-compra-' . $folio . '-firmado.' . $extension);

			$file = $this->guardarArchivoFirmado(
				$userId,
				$folio,
				$fileName,
				$content
			);

			$this->solicitudMapper->updateSolicitud($id, [
				'firmado_file_id' => $file->getId(),
				'firmado_nombre' => $fileName,
				'firmado_mime' => $mime,
				'firmado_subido_at' => date('Y-m-d H:i:s'),
				'firmado_subido_by' => $userId,
				'updated_by' => $userId,
			]);

			$this->historialMapper->insertHistorial(
				$id,
				'documento_firmado_subido',
				(string)($solicitud['estado'] ?? ''),
				(string)($solicitud['estado'] ?? ''),
				'Documento firmado cargado: ' . $fileName,
				[
					'file_id' => $file->getId(),
					'file_name' => $fileName,
					'mime' => $mime,
					'tamano' => strlen($content),
				],
				$userId
			);

			return new DataResponse([
				'success' => true,
				'message' => 'Documento firmado guardado correctamente.',
				'data' => [
					'file_id' => $file->getId(),
					'file_name' => $fileName,
				],
			]);
		} catch (\Throwable $e) {
			return new DataResponse([
				'success' => false,
				'message' => 'No se pudo guardar el documento firmado: ' . $e->getMessage(),
			], $this->errorStatus($e));
		}
	}

	/**
	* @NoAdminRequired
	* @NoCSRFRequired
	*/
	public function verFirmado(int $id): DataDisplayResponse {
		try {
			$user = $this->userSession->getUser();

			if ($user === null) {
				return new DataDisplayResponse(
					'Usuario no autenticado.',
					Http::STATUS_FORBIDDEN,
					['Content-Type' => 'text/plain; charset=utf-8']
				);
			}

			$data = $this->service->obtenerDetalle($id, $user->getUID());
			$solicitud = $data['solicitud']->jsonSerialize();

			$fileId = (int)($solicitud['firmado_file_id'] ?? 0);

			if ($fileId <= 0) {
				throw new Exception('La solicitud no tiene documento firmado.');
			}

			$nodes = $this->rootFolder->getById($fileId);
			$file = null;

			foreach ($nodes as $node) {
				if ($node instanceof File) {
					$file = $node;
					break;
				}
			}

			if (!$file instanceof File) {
				throw new Exception('No se encontró el archivo firmado.');
			}

			$fileName = (string)($solicitud['firmado_nombre'] ?? $file->getName());
			$mime = (string)($solicitud['firmado_mime'] ?? $file->getMimetype());

			return new DataDisplayResponse(
				$file->getContent(),
				Http::STATUS_OK,
				[
					'Content-Type' => $mime ?: 'application/octet-stream',
					'Content-Disposition' => 'inline; filename="' . $this->safeFilename($fileName) . '"',
					'Cache-Control' => 'no-store, no-cache, must-revalidate',
					'Pragma' => 'no-cache',
				]
			);
		} catch (\Throwable $e) {
			return new DataDisplayResponse(
				'No se pudo abrir el documento firmado: ' . $e->getMessage(),
				Http::STATUS_BAD_REQUEST,
				['Content-Type' => 'text/plain; charset=utf-8']
			);
		}
	}
	private function guardarArchivoFirmado(
		string $userId,
		string $folio,
		string $fileName,
		string $content
	): File {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		$folder = $this->ensureFolder(
			$userFolder,
			'Compras/Solicitudes/' . $this->safePathSegment($folio) . '/Firmado'
		);

		if ($folder->nodeExists($fileName)) {
			$node = $folder->get($fileName);

			if (!$node instanceof File) {
				throw new Exception('Ya existe una carpeta con el nombre del documento firmado.');
			}

			$file = $node;
		} else {
			$file = $folder->newFile($fileName);
		}

		$file->putContent($content);

		return $file;
	}

	private function validarArchivoFirmado(
		string $fileName,
		string $declaredMime,
		string $content,
		int $reportedSize
	): array {
		$normalizedName = basename(str_replace('\\', '/', trim($fileName)));
		if ($normalizedName === '' || strlen($normalizedName) > 255 || str_contains($fileName, "\0")) {
			throw new Exception('El nombre del archivo no es válido.');
		}

		$size = strlen($content);
		if ($size <= 0 || $reportedSize > self::MAX_SIGNED_FILE_SIZE || $size > self::MAX_SIGNED_FILE_SIZE) {
			throw new Exception('El archivo firmado debe ser menor o igual a 10 MB.');
		}

		$extension = strtolower(pathinfo($normalizedName, PATHINFO_EXTENSION));

		$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
		$allowedMimes = [
			'application/pdf',
			'image/jpeg',
			'image/png',
		];

		if (!in_array($extension, $allowedExtensions, true)) {
			throw new Exception('Solo se permiten archivos PDF, JPG o PNG.');
		}

		if ($declaredMime !== '' && !in_array($declaredMime, $allowedMimes, true)) {
			throw new Exception('El tipo de archivo no es válido.');
		}

		$mime = $this->detectDocumentMime($content);
		if (!in_array($mime, $allowedMimes, true)) {
			throw new Exception('El contenido del archivo no corresponde a un tipo permitido.');
		}

		$expectedMime = $this->mimeFromExtension($extension);
		if ($mime !== $expectedMime) {
			throw new Exception('La extensión no coincide con el contenido del archivo.');
		}

		return [
			'name' => $normalizedName,
			'extension' => $extension,
			'mime' => $mime,
			'size' => $size,
		];
	}

	private function assertCanManageOfficialDocuments(array $solicitud, string $userId): void {
		if (!$this->permisosService->canProcessPurchase($userId)) {
			throw new Exception('No tienes permisos para administrar documentos de esta solicitud.');
		}

		if ((string)($solicitud['estado'] ?? '') !== 'autorizada') {
			throw new Exception('Los documentos oficiales solo pueden modificarse en solicitudes autorizadas.');
		}
	}

	private function detectDocumentMime(string $content): string {
		if (strncmp($content, '%PDF-', 5) === 0) {
			return 'application/pdf';
		}
		if (strncmp($content, "\x89PNG", 4) === 0) {
			return 'image/png';
		}
		if (strncmp($content, "\xFF\xD8\xFF", 3) === 0) {
			return 'image/jpeg';
		}

		if (function_exists('finfo_buffer')) {
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			return (string)$finfo->buffer($content);
		}

		return '';
	}

	private function errorStatus(\Throwable $e): int {
		return stripos($e->getMessage(), 'permis') !== false
			? Http::STATUS_FORBIDDEN
			: Http::STATUS_BAD_REQUEST;
	}

	private function mimeFromExtension(string $extension): string {
		$map = [
			'pdf' => 'application/pdf',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
		];

		return $map[strtolower($extension)] ?? 'application/octet-stream';
	}
	private function getInstanceLogoDataUri(): string {
		$logo = $this->getUploadedLogoDataUri();

		if ($logo !== '') {
			return $logo;
		}

		return $this->getFallbackLogoDataUri();
	}

	private function getUploadedLogoDataUri(): string {
		try {
			$folder = $this->appData->getFolder('compras');
		} catch (NotFoundException $e) {
			return '';
		}

		foreach (['logo-documento.png', 'logo-documento.jpg'] as $fileName) {
			if (!$folder->fileExists($fileName)) {
				continue;
			}

			$file = $folder->getFile($fileName);
			$content = $file->getContent();

			if ($content === '') {
				continue;
			}

			$mime = $this->detectImageMime($content);

			if ($mime === '') {
				continue;
			}

			return 'data:' . $mime . ';base64,' . base64_encode($content);
		}

		return '';
	}

	private function getFallbackLogoDataUri(): string {
		$paths = [
			__DIR__ . '/../../img/logo-documento.png',
			__DIR__ . '/../../img/logo-documento.jpg',
			__DIR__ . '/../../img/logo-documento.svg',
			__DIR__ . '/../../img/app.svg',
		];

		foreach ($paths as $path) {
			if (!is_readable($path)) {
				continue;
			}

			$content = file_get_contents($path);

			if ($content === false || $content === '') {
				continue;
			}

			return $this->imageContentToDataUri($content);
		}

		return '';
	}

	private function imageContentToDataUri(string $content): string {
		$mime = $this->detectImageMime($content);

		if ($mime === '') {
			return '';
		}

		return 'data:' . $mime . ';base64,' . base64_encode($content);
	}

	private function detectImageMime(string $content): string {
		if (strncmp($content, "\x89PNG", 4) === 0) {
			return 'image/png';
		}

		if (strncmp($content, "\xFF\xD8\xFF", 3) === 0) {
			return 'image/jpeg';
		}

		if (function_exists('finfo_buffer')) {
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			$mime = (string)$finfo->buffer($content);

			if (in_array($mime, [
				'image/png',
				'image/jpeg',
			], true)) {
				return $mime;
			}
		}

		return '';
	}
}
