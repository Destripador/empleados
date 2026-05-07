<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use Exception;
use OCA\Empleados\Service\CompraSolicitudService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUserSession;

class CompraDocumentoController extends Controller {

	private CompraSolicitudService $service;
	private IUserSession $userSession;

	public function __construct(
		string $appName,
		IRequest $request,
		CompraSolicitudService $service,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);

		$this->service = $service;
		$this->userSession = $userSession;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function documento(int $id): Response {
		$response = new Response();

		try {
			$user = $this->userSession->getUser();

			if ($user === null) {
				$response->setStatus(Http::STATUS_FORBIDDEN);
				$response->setContent('Usuario no autenticado.');
				return $response;
			}

			$data = $this->service->obtenerDetalle($id, $user->getUID());

			$html = $this->renderDocumento($data);

			$csp = new ContentSecurityPolicy();
			$csp->allowInlineStyle(true);
			$response->setContentSecurityPolicy($csp);

			$response->addHeader('Content-Type', 'text/html; charset=utf-8');
			$response->setContent($html);

			return $response;
		} catch (Exception $e) {
			$response->setStatus(Http::STATUS_BAD_REQUEST);
			$response->setContent($e->getMessage());
			return $response;
		}
	}

	private function renderDocumento(array $data): string {
		$solicitud = $data['solicitud']->jsonSerialize();
		$detalles = array_map(static function ($detalle): array {
			return $detalle->jsonSerialize();
		}, $data['detalles']);

		$first = $detalles[0] ?? [];

		$totalExclIva = $this->money($solicitud['total_excl_iva'] ?? $solicitud['monto_estimado'] ?? 0);
		$iva = $this->money($solicitud['iva'] ?? 0);
		$totalInclIva = $this->money($solicitud['total_incl_iva'] ?? $solicitud['monto_final'] ?? 0);

		$garantia = ((int)($solicitud['garantia'] ?? 0)) === 1 ? 'SI' : 'NO';

		$rows = '';

		foreach ($detalles as $detalle) {
			$rows .= '
				<tr>
					<td>' . $this->e($detalle['descripcion'] ?? '') . '</td>
					<td>' . $this->e((string)($detalle['cantidad'] ?? '')) . '</td>
					<td>' . $this->e($detalle['unidad'] ?? '') . '</td>
					<td>' . $this->money($detalle['precio_estimado'] ?? 0) . '</td>
					<td>' . $this->money($detalle['iva'] ?? 0) . '</td>
					<td>' . $this->money($detalle['total'] ?? (($detalle['subtotal'] ?? 0) + ($detalle['iva'] ?? 0))) . '</td>
				</tr>';
		}

		if ($rows === '') {
			$rows = '<tr><td colspan="6">Sin conceptos registrados.</td></tr>';
		}

		return '<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>Solicitud de compra ' . $this->e((string)($solicitud['id_solicitud'] ?? '')) . '</title>
	<style>
		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			padding: 24px;
			background: #e9eef3;
			color: #111;
			font-family: Arial, Helvetica, sans-serif;
			font-size: 12px;
		}

		.document {
			width: 8.5in;
			min-height: 11in;
			margin: 0 auto;
			padding: 22px;
			background: #fff;
			border: 1px solid #c9c9c9;
			box-shadow: 0 8px 28px rgba(0, 0, 0, .15);
		}

		.header {
			display: grid;
			grid-template-columns: 1fr auto;
			gap: 16px;
			align-items: start;
			padding-bottom: 12px;
			border-bottom: 3px solid #111;
		}

		.title {
			margin: 0;
			font-size: 22px;
			font-weight: 800;
			letter-spacing: .03em;
			text-transform: uppercase;
		}

		.subtitle {
			margin-top: 5px;
			font-size: 13px;
			font-weight: 700;
		}

		.meta {
			text-align: right;
			font-size: 12px;
			line-height: 1.6;
		}

		.section {
			margin-top: 14px;
			border: 1px solid #111;
		}

		.section-title {
			padding: 6px 8px;
			background: #111;
			color: #fff;
			font-size: 12px;
			font-weight: 700;
			text-transform: uppercase;
		}

		.grid {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			border-top: 1px solid #111;
		}

		.grid.two {
			grid-template-columns: repeat(2, 1fr);
		}

		.field {
			min-height: 42px;
			padding: 7px 8px;
			border-right: 1px solid #111;
			border-bottom: 1px solid #111;
		}

		.field:nth-child(4n) {
			border-right: 0;
		}

		.grid.two .field:nth-child(2n) {
			border-right: 0;
		}

		.label {
			display: block;
			margin-bottom: 4px;
			color: #555;
			font-size: 10px;
			font-weight: 700;
			text-transform: uppercase;
		}

		.value {
			display: block;
			font-size: 12px;
			font-weight: 700;
			white-space: pre-wrap;
			word-break: break-word;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		th,
		td {
			padding: 7px 8px;
			border: 1px solid #111;
			text-align: left;
			vertical-align: top;
		}

		th {
			background: #e5e5e5;
			font-size: 10px;
			font-weight: 800;
			text-transform: uppercase;
		}

		.total-box {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			margin-top: 14px;
			border: 1px solid #111;
		}

		.total-item {
			padding: 8px;
			border-right: 1px solid #111;
			text-align: center;
		}

		.total-item:last-child {
			border-right: 0;
		}

		.total-item span {
			display: block;
			font-size: 10px;
			font-weight: 700;
			text-transform: uppercase;
		}

		.total-item strong {
			display: block;
			margin-top: 4px;
			font-size: 16px;
		}

		.signatures {
			display: grid;
			grid-template-columns: repeat(5, 1fr);
			gap: 10px;
			margin-top: 28px;
		}

		.signature {
			min-height: 72px;
			text-align: center;
			font-size: 11px;
		}

		.line {
			height: 42px;
			border-bottom: 1px solid #111;
			margin-bottom: 6px;
		}

		.comments {
			min-height: 70px;
			padding: 8px;
			border: 1px solid #111;
			white-space: pre-wrap;
		}

		.actions {
			width: 8.5in;
			margin: 16px auto;
			display: flex;
			justify-content: flex-end;
			gap: 8px;
		}

		.actions button {
			padding: 8px 12px;
			border: 1px solid #111;
			background: #fff;
			cursor: pointer;
			font-weight: 700;
		}

		@media print {
			body {
				padding: 0;
				background: #fff;
			}

			.actions {
				display: none;
			}

			.document {
				width: 100%;
				min-height: auto;
				border: 0;
				box-shadow: none;
				page-break-after: always;
			}
		}
	</style>
</head>
<body>
	<div class="actions">
		<button onclick="window.print()">Imprimir / Guardar PDF</button>
	</div>

	<div class="document">
		<div class="header">
			<div>
				<h1 class="title">Solicitud de compra</h1>
				<div class="subtitle">' . $this->e((string)($solicitud['folio'] ?? '')) . '</div>
			</div>

			<div class="meta">
				<strong>No.</strong> ' . $this->e((string)($solicitud['id_solicitud'] ?? '')) . '<br>
				<strong>Fecha:</strong> ' . $this->e((string)($solicitud['created_at'] ?? '')) . '<br>
				<strong>Estado:</strong> ' . $this->e((string)($solicitud['estado'] ?? '')) . '
			</div>
		</div>

		<div class="section">
			<div class="section-title">Datos del solicitante</div>
			<div class="grid">
				<div class="field">
					<span class="label">Nombre</span>
					<span class="value">' . $this->e($solicitud['solicitante_nombre'] ?? $solicitud['id_user'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Dpto</span>
					<span class="value">' . $this->e($solicitud['solicitante_depto'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Cargo</span>
					<span class="value">' . $this->e($solicitud['solicitante_cargo'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Jefe directo</span>
					<span class="value">' . $this->e($solicitud['jefe_directo_nombre'] ?? '') . '</span>
				</div>
			</div>
		</div>

		<div class="section">
			<div class="section-title">Datos de compra</div>
			<div class="grid">
				<div class="field">
					<span class="label">Tipo de compra</span>
					<span class="value">' . $this->e($solicitud['tipo_compra'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Garantía</span>
					<span class="value">' . $garantia . '</span>
				</div>
				<div class="field">
					<span class="label">Uso de compra</span>
					<span class="value">' . $this->e($solicitud['uso_compra'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Moneda</span>
					<span class="value">' . $this->e($solicitud['moneda'] ?? 'MXN') . '</span>
				</div>
			</div>

			<div class="grid two">
				<div class="field">
					<span class="label">Información</span>
					<span class="value">' . $this->e($solicitud['informacion'] ?? $solicitud['descripcion'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Motivo</span>
					<span class="value">' . $this->e($solicitud['motivo'] ?? $solicitud['justificacion'] ?? '') . '</span>
				</div>
			</div>
		</div>

		<div class="section">
			<div class="section-title">Requisición</div>
			<div class="grid">
				<div class="field">
					<span class="label">Proveedor</span>
					<span class="value">' . $this->e($solicitud['proveedor_nombre'] ?? $first['proveedor_nombre'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Atención</span>
					<span class="value">' . $this->e($solicitud['atencion'] ?? $first['atencion'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Entrega</span>
					<span class="value">' . $this->e($solicitud['entrega'] ?? $first['entrega'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Marca / Modelo</span>
					<span class="value">' . $this->e($solicitud['marca_modelo'] ?? $first['marca_modelo'] ?? '') . '</span>
				</div>
			</div>
			<div class="grid two">
				<div class="field">
					<span class="label">Especificaciones</span>
					<span class="value">' . $this->e($solicitud['especificaciones'] ?? $first['especificaciones'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Comentarios</span>
					<span class="value">' . $this->e($solicitud['comentarios_req'] ?? '') . '</span>
				</div>
			</div>
		</div>

		<div class="section">
			<div class="section-title">Productos / servicios solicitados</div>
			<table>
				<thead>
					<tr>
						<th>Descripción</th>
						<th>Cant.</th>
						<th>Unidad</th>
						<th>Precio</th>
						<th>IVA</th>
						<th>Total</th>
					</tr>
				</thead>
				<tbody>' . $rows . '</tbody>
			</table>
		</div>

		<div class="section">
			<div class="section-title">Administración</div>
			<div class="grid">
				<div class="field">
					<span class="label">Oficina %</span>
					<span class="value">' . $this->e((string)($solicitud['oficina_pct'] ?? '')) . '</span>
				</div>
				<div class="field">
					<span class="label">Empleado %</span>
					<span class="value">' . $this->e((string)($solicitud['empleado_pct'] ?? '')) . '</span>
				</div>
				<div class="field">
					<span class="label">Tipo de pago</span>
					<span class="value">' . $this->e($solicitud['tipo_pago'] ?? '') . '</span>
				</div>
				<div class="field">
					<span class="label">Quincenas</span>
					<span class="value">' . $this->e((string)($solicitud['quincenas'] ?? '')) . '</span>
				</div>
			</div>
		</div>

		<div class="total-box">
			<div class="total-item">
				<span>Total excl. IVA</span>
				<strong>' . $totalExclIva . '</strong>
			</div>
			<div class="total-item">
				<span>IVA</span>
				<strong>' . $iva . '</strong>
			</div>
			<div class="total-item">
				<span>Total</span>
				<strong>' . $totalInclIva . '</strong>
			</div>
		</div>

		<div class="signatures">
			<div class="signature">
				<div class="line"></div>
				Colaborador
			</div>
			<div class="signature">
				<div class="line"></div>
				Gerente
			</div>
			<div class="signature">
				<div class="line"></div>
				Sistemas
			</div>
			<div class="signature">
				<div class="line"></div>
				Socio
			</div>
			<div class="signature">
				<div class="line"></div>
				Administración
			</div>
		</div>

		<div class="section">
			<div class="section-title">Comentarios administración</div>
			<div class="comments">' . $this->e($solicitud['comentarios_admin'] ?? '') . '</div>
		</div>
	</div>
</body>
</html>';
	}

	private function e(?string $value): string {
		return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function money($value): string {
		return '$' . number_format((float)$value, 2);
	}
}
