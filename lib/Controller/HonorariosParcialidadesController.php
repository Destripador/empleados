<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\honorariosMapper;
use OCA\Empleados\Db\honorariosParcialidadesMapper;
use OCA\Empleados\Db\clientesMapper;
use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\BitacoraService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class HonorariosParcialidadesController extends BaseController {

	protected honorariosMapper $honorariosMapper;
	protected honorariosParcialidadesMapper $honorariosParcialidadesMapper;
	protected clientesMapper $clientesMapper;
	private PermisosService $permisosService;
	private BitacoraService $bitacoraService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		honorariosMapper $honorariosMapper,
		honorariosParcialidadesMapper $honorariosParcialidadesMapper,
		clientesMapper $clientesMapper,
		PermisosService $permisosService,
		BitacoraService $bitacoraService
	) {
		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$empleadosMapper,
			$configuracionesMapper
		);

		$this->honorariosMapper = $honorariosMapper;
		$this->honorariosParcialidadesMapper = $honorariosParcialidadesMapper;
		$this->clientesMapper = $clientesMapper;
		$this->permisosService = $permisosService;
		$this->bitacoraService = $bitacoraService;
	}

	private function requireClientesAccess(): void {
		$this->permisosService->requireCanSee('clientes');
	}

	private function requireClientesAdminAccess(): void {
		$this->permisosService->requireCanSee('clientes.admin');
	}

	/**
	 * Registra un movimiento del módulo "honorarios_parcialidades" en la bitácora general.
	 */
	private function registrarMovimiento(
		?string $uidActor,
		?int $idReferencia,
		?string $nombreAfectado,
		string $tipo,
		string $mensaje
	): void {
		$this->bitacoraService->registrar('honorarios_parcialidades', $uidActor, null, $nombreAfectado, $tipo, $mensaje, $idReferencia);
	}

	/**
	 * Devuelve [uidActor, nombreActor] del usuario en sesión, con fallback a "Sistema".
	 */
	private function getActorInfo(): array {
		$actor = $this->userSession->getUser();
		$uidActor = $actor ? $actor->getUID() : null;
		$nombreActor = $actor ? $actor->getDisplayName() : 'Sistema';

		return [$uidActor, $nombreActor];
	}

	/**
	 * Arma el contexto (cliente, número de parcialidad, monto) que se usa
	 * para dar detalle en los mensajes de bitácora. Recibe la parcialidad
	 * ya cargada (antes de modificarla) para no perder su estado previo.
	 *
	 * @return array{nombreCliente:string, idCliente:?int, etiqueta:string, montoTxt:string}
	 */
	private function getContextoParcialidad(array $parcialidad): array {
		$idHonorario = (int)($parcialidad['id_honorario'] ?? 0);
		$honorario = $idHonorario > 0 ? $this->honorariosMapper->findById($idHonorario) : null;

		$idCliente = $honorario ? (int)($honorario['id_cliente'] ?? 0) : null;
		$nombreCliente = 'Cliente desconocido';

		if ($idCliente) {
			$cliente = $this->clientesMapper->findById($idCliente);
			$nombreCliente = $cliente['nombre'] ?? $nombreCliente;
		}

		$numeroParcialidad = $parcialidad['numero_parcialidad'] ?? null;
		$etiqueta = $numeroParcialidad !== null
			? sprintf('parcialidad #%s', $numeroParcialidad)
			: 'la parcialidad';

		$monto = $parcialidad['monto'] ?? $parcialidad['importe'] ?? null;
		$montoTxt = $monto !== null ? number_format((float)$monto, 2) : null;

		return [
			'nombreCliente' => $nombreCliente,
			'idCliente' => $idCliente,
			'etiqueta' => $etiqueta,
			'montoTxt' => $montoTxt,
		];
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(int $id_parcialidad): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->honorariosParcialidadesMapper->findById($id_parcialidad),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByHonorario(int $id_honorario): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->honorariosParcialidadesMapper->findByHonorario($id_honorario),
			Http::STATUS_OK
		);
	}

	/**
	 * Marca parcialidad como pagada. Si el honorario queda completado, lo desactiva.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function marcarPagada(int $id_parcialidad, string $fecha_pago): DataResponse {
		$this->requireClientesAdminAccess();

		$parcialidad = $this->honorariosParcialidadesMapper->findById($id_parcialidad);

		$idHonorarioFinalizado = $this->honorariosParcialidadesMapper
			->marcarPagada($id_parcialidad, $fecha_pago);

		if ($idHonorarioFinalizado !== null) {
			$this->honorariosMapper->desactivarHonorario($idHonorarioFinalizado);
		}

		// --- Movimiento (bitácora) ---
		if ($parcialidad) {
			$ctx = $this->getContextoParcialidad($parcialidad);
			[$uidActor, $nombreActor] = $this->getActorInfo();

			$mensaje = sprintf(
				'%s marcó la %s del cliente "%s" como **pagada** el %s%s.',
				$nombreActor,
				$ctx['etiqueta'],
				$ctx['nombreCliente'],
				$fecha_pago,
				$ctx['montoTxt'] !== null ? sprintf(' (monto: %s)', $ctx['montoTxt']) : ''
			);

			$this->registrarMovimiento($uidActor, $id_parcialidad, $ctx['nombreCliente'], 'pago', $mensaje);

			if ($idHonorarioFinalizado !== null) {
				$mensajeFin = sprintf(
					'%s completó el pago de todas las parcialidades del honorario del cliente "%s"; el honorario quedó finalizado automáticamente.',
					$nombreActor,
					$ctx['nombreCliente']
				);

				$this->registrarMovimiento($uidActor, $idHonorarioFinalizado, $ctx['nombreCliente'], 'honorario_completado', $mensajeFin);
			}
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findPagadasPorCliente(int $id_cliente): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->honorariosParcialidadesMapper->findPagadasPorCliente($id_cliente),
			Http::STATUS_OK
		);
	}

	/**
	 * Marcar parcialidad como facturada
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function marcarFacturada(
		int $id_parcialidad,
		string $fecha_factura,
		?int $id_cliente_pagador = null
	): DataResponse {
		$this->requireClientesAdminAccess();

		$parcialidad = $this->honorariosParcialidadesMapper->findById($id_parcialidad);

		$this->honorariosParcialidadesMapper
			->marcarFacturada($id_parcialidad, $fecha_factura, $id_cliente_pagador);

		// --- Movimiento (bitácora) ---
		if ($parcialidad) {
			$ctx = $this->getContextoParcialidad($parcialidad);
			[$uidActor, $nombreActor] = $this->getActorInfo();

			$nombrePagador = null;
			if ($id_cliente_pagador !== null && $id_cliente_pagador !== $ctx['idCliente']) {
				$clientePagador = $this->clientesMapper->findById($id_cliente_pagador);
				$nombrePagador = $clientePagador['nombre'] ?? null;
			}

			$mensaje = sprintf(
				'%s marcó la %s del cliente "%s" como **facturada** el %s%s.',
				$nombreActor,
				$ctx['etiqueta'],
				$ctx['nombreCliente'],
				$fecha_factura,
				$nombrePagador ? sprintf(' (facturada a "%s")', $nombrePagador) : ''
			);

			$this->registrarMovimiento($uidActor, $id_parcialidad, $ctx['nombreCliente'], 'facturacion', $mensaje);
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	/**
	 * Revierte pagada -> facturada. La fecha de factura se conserva intacta.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function cancelarPago(int $id_parcialidad): DataResponse {
		$this->requireClientesAdminAccess();

		$parcialidad = $this->honorariosParcialidadesMapper->findById($id_parcialidad);
		$fechaPagoAnterior = $parcialidad['fecha_pago'] ?? null;

		$idHonorario = $this->honorariosParcialidadesMapper->cancelarPago($id_parcialidad);

		if ($idHonorario !== null) {
			$this->honorariosMapper->reactivarHonorario($idHonorario);
		}

		// --- Movimiento (bitácora) ---
		if ($parcialidad) {
			$ctx = $this->getContextoParcialidad($parcialidad);
			[$uidActor, $nombreActor] = $this->getActorInfo();

			$mensaje = sprintf(
				'%s canceló el pago de la %s del cliente "%s"%s, regresándola a estado facturada.',
				$nombreActor,
				$ctx['etiqueta'],
				$ctx['nombreCliente'],
				$fechaPagoAnterior ? sprintf(' (pago registrado el %s)', $fechaPagoAnterior) : ''
			);

			$this->registrarMovimiento($uidActor, $id_parcialidad, $ctx['nombreCliente'], 'cancelacion_pago', $mensaje);

			if ($idHonorario !== null) {
				$mensajeReact = sprintf(
					'%s reactivó automáticamente el honorario del cliente "%s" al cancelar un pago.',
					$nombreActor,
					$ctx['nombreCliente']
				);

				$this->registrarMovimiento($uidActor, $idHonorario, $ctx['nombreCliente'], 'honorario_reactivado', $mensajeReact);
			}
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	/**
	 * Revierte facturada -> pendiente.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function cancelarFactura(int $id_parcialidad): DataResponse {
		$this->requireClientesAdminAccess();

		$parcialidad = $this->honorariosParcialidadesMapper->findById($id_parcialidad);
		$fechaFacturaAnterior = $parcialidad['fecha_factura'] ?? null;

		$this->honorariosParcialidadesMapper->cancelarFactura($id_parcialidad);

		// --- Movimiento (bitácora) ---
		if ($parcialidad) {
			$ctx = $this->getContextoParcialidad($parcialidad);
			[$uidActor, $nombreActor] = $this->getActorInfo();

			$mensaje = sprintf(
				'%s canceló la factura de la %s del cliente "%s"%s, regresándola a estado pendiente.',
				$nombreActor,
				$ctx['etiqueta'],
				$ctx['nombreCliente'],
				$fechaFacturaAnterior ? sprintf(' (facturada el %s)', $fechaFacturaAnterior) : ''
			);

			$this->registrarMovimiento($uidActor, $id_parcialidad, $ctx['nombreCliente'], 'cancelacion_factura', $mensaje);
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function agregarParcialidadIguala(int $id_honorario): DataResponse {
		$this->requireClientesAdminAccess();

		$this->honorariosParcialidadesMapper
			->agregarParcialidadIguala($id_honorario);

		// --- Movimiento (bitácora) ---
		$honorario = $this->honorariosMapper->findById($id_honorario);

		if ($honorario) {
			$idCliente = (int)($honorario['id_cliente'] ?? 0);
			$cliente = $idCliente > 0 ? $this->clientesMapper->findById($idCliente) : null;
			$nombreCliente = $cliente['nombre'] ?? 'Cliente desconocido';

			[$uidActor, $nombreActor] = $this->getActorInfo();

			$mensaje = sprintf(
				'%s agregó una nueva parcialidad de iguala al honorario del cliente "%s".',
				$nombreActor,
				$nombreCliente
			);

			$this->registrarMovimiento($uidActor, $id_honorario, $nombreCliente, 'creacion', $mensaje);
		}

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}
}