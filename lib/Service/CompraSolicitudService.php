<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use DateTime;
use Exception;
use OCA\Empleados\Db\CompraDetalleMapper;
use OCA\Empleados\Db\CompraHistorialMapper;
use OCA\Empleados\Db\CompraSolicitud;
use OCA\Empleados\Db\CompraSolicitudMapper;

class CompraSolicitudService {

	public const ESTADO_BORRADOR = 'borrador';
	public const ESTADO_PENDIENTE_AUTORIZACION = 'pendiente_autorizacion';
	public const ESTADO_AUTORIZADA = 'autorizada';
	public const ESTADO_RECHAZADA = 'rechazada';
	public const ESTADO_CANCELADA = 'cancelada';

	private CompraSolicitudMapper $solicitudMapper;
	private CompraDetalleMapper $detalleMapper;
	private CompraHistorialMapper $historialMapper;
	private CompraFolioService $folioService;
	private CompraPermisosService $permisosService;

	public function __construct(
		CompraSolicitudMapper $solicitudMapper,
		CompraDetalleMapper $detalleMapper,
		CompraHistorialMapper $historialMapper,
		CompraFolioService $folioService,
		CompraPermisosService $permisosService
	) {
		$this->solicitudMapper = $solicitudMapper;
		$this->detalleMapper = $detalleMapper;
		$this->historialMapper = $historialMapper;
		$this->folioService = $folioService;
		$this->permisosService = $permisosService;
	}

	public function listar(string $userId, bool $todas = false, int $limit = 50, int $offset = 0): array {
		if ($todas && $this->permisosService->canViewAll($userId)) {
			return $this->solicitudMapper->findAll($limit, $offset);
		}

		return $this->solicitudMapper->findByUser($userId, $limit, $offset);
	}

	public function listarPendientesAutorizacion(string $userId, int $limit = 100, int $offset = 0): array {
		if (!$this->permisosService->canApprove($userId)) {
			throw new Exception('No tienes permisos para ver solicitudes pendientes de autorización.');
		}

		return $this->solicitudMapper->findByEstado(self::ESTADO_PENDIENTE_AUTORIZACION, $limit, $offset);
	}

	public function obtenerDetalle(int $idSolicitud, string $userId): array {
		$solicitud = $this->solicitudMapper->find($idSolicitud);

		if (!$this->permisosService->canViewSolicitud($userId, (string)$solicitud->getIdUser())) {
			throw new Exception('No tienes permisos para ver esta solicitud.');
		}

		return [
			'solicitud' => $solicitud,
			'detalles' => $this->detalleMapper->findBySolicitud($idSolicitud),
			'historial' => $this->historialMapper->findBySolicitud($idSolicitud),
		];
	}

	public function crear(array $data, string $userId): array {
		if (!$this->permisosService->canCreateSolicitud($userId)) {
			throw new Exception('No tienes permisos para crear solicitudes de compra.');
		}

		$titulo = trim((string)($data['titulo'] ?? ''));

		if ($titulo === '') {
			throw new Exception('El título de la solicitud es obligatorio.');
		}

		$detalles = $data['detalles'] ?? [];

		if (!is_array($detalles) || count($detalles) === 0) {
			throw new Exception('La solicitud debe incluir al menos un concepto.');
		}

		$now = date('Y-m-d H:i:s');
		$montoEstimado = $this->calcularMontoEstimado($detalles);

		$solicitud = $this->solicitudMapper->insertSolicitud([
			'folio' => $this->folioService->generarFolio(),
			'id_user' => $userId,
			'id_empleado' => $data['id_empleado'] ?? null,
			'id_departamento' => $data['id_departamento'] ?? null,
			'id_equipo' => $data['id_equipo'] ?? null,
			'id_cliente' => $data['id_cliente'] ?? null,
			'titulo' => $titulo,
			'descripcion' => $data['descripcion'] ?? null,
			'justificacion' => $data['justificacion'] ?? null,
			'monto_estimado' => $montoEstimado,
			'monto_final' => null,
			'moneda' => $data['moneda'] ?? 'MXN',
			'prioridad' => $data['prioridad'] ?? 'normal',
			'estado' => self::ESTADO_BORRADOR,
			'fecha_requerida' => $data['fecha_requerida'] ?? null,
			'fecha_envio' => null,
			'fecha_autorizacion' => null,
			'fecha_cierre' => null,
			'proveedor_seleccionado' => null,
			'created_by' => $userId,
			'updated_by' => $userId,
		]);

		$idSolicitud = (int)$solicitud->getIdSolicitud();

		$this->detalleMapper->replaceBySolicitud(
			$idSolicitud,
			$this->normalizarDetalles($detalles)
		);

		$this->historialMapper->insertHistorial(
			$idSolicitud,
			'creada',
			null,
			self::ESTADO_BORRADOR,
			'Solicitud creada.',
			[
				'monto_estimado' => $montoEstimado,
				'created_at' => $now,
			],
			$userId
		);

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function actualizar(int $idSolicitud, array $data, string $userId): array {
		$solicitud = $this->solicitudMapper->find($idSolicitud);

		if ((string)$solicitud->getIdUser() !== $userId && !$this->permisosService->canViewAll($userId)) {
			throw new Exception('No tienes permisos para editar esta solicitud.');
		}

		if ((string)$solicitud->getEstado() !== self::ESTADO_BORRADOR) {
			throw new Exception('Solo se pueden editar solicitudes en borrador.');
		}

		$detalles = $data['detalles'] ?? null;
		$montoEstimado = null;

		if (is_array($detalles)) {
			if (count($detalles) === 0) {
				throw new Exception('La solicitud debe incluir al menos un concepto.');
			}

			$montoEstimado = $this->calcularMontoEstimado($detalles);
		}

		$updateData = [
			'id_departamento' => $data['id_departamento'] ?? $solicitud->getIdDepartamento(),
			'id_equipo' => $data['id_equipo'] ?? $solicitud->getIdEquipo(),
			'id_cliente' => $data['id_cliente'] ?? $solicitud->getIdCliente(),
			'titulo' => isset($data['titulo']) ? trim((string)$data['titulo']) : $solicitud->getTitulo(),
			'descripcion' => $data['descripcion'] ?? $solicitud->getDescripcion(),
			'justificacion' => $data['justificacion'] ?? $solicitud->getJustificacion(),
			'monto_estimado' => $montoEstimado ?? $solicitud->getMontoEstimado(),
			'moneda' => $data['moneda'] ?? $solicitud->getMoneda(),
			'prioridad' => $data['prioridad'] ?? $solicitud->getPrioridad(),
			'fecha_requerida' => $data['fecha_requerida'] ?? $solicitud->getFechaRequerida(),
			'updated_by' => $userId,
		];

		if ($updateData['titulo'] === '') {
			throw new Exception('El título de la solicitud es obligatorio.');
		}

		$this->solicitudMapper->updateSolicitud($idSolicitud, $updateData);

		if (is_array($detalles)) {
			$this->detalleMapper->replaceBySolicitud(
				$idSolicitud,
				$this->normalizarDetalles($detalles)
			);
		}

		$this->historialMapper->insertHistorial(
			$idSolicitud,
			'editada',
			self::ESTADO_BORRADOR,
			self::ESTADO_BORRADOR,
			'Solicitud editada.',
			null,
			$userId
		);

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function enviarAutorizacion(int $idSolicitud, string $userId): array {
		$solicitud = $this->solicitudMapper->find($idSolicitud);

		if ((string)$solicitud->getIdUser() !== $userId && !$this->permisosService->canViewAll($userId)) {
			throw new Exception('No tienes permisos para enviar esta solicitud.');
		}

		if ((string)$solicitud->getEstado() !== self::ESTADO_BORRADOR) {
			throw new Exception('Solo se pueden enviar solicitudes en borrador.');
		}

		$this->solicitudMapper->cambiarEstado(
			$idSolicitud,
			self::ESTADO_PENDIENTE_AUTORIZACION,
			$userId,
			'fecha_envio'
		);

		$this->historialMapper->insertHistorial(
			$idSolicitud,
			'enviada_autorizacion',
			self::ESTADO_BORRADOR,
			self::ESTADO_PENDIENTE_AUTORIZACION,
			'Solicitud enviada a autorización.',
			null,
			$userId
		);

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function autorizar(int $idSolicitud, string $userId, ?string $comentario = null): array {
		if (!$this->permisosService->canApprove($userId)) {
			throw new Exception('No tienes permisos para autorizar solicitudes.');
		}

		$solicitud = $this->solicitudMapper->find($idSolicitud);

		if ((string)$solicitud->getEstado() !== self::ESTADO_PENDIENTE_AUTORIZACION) {
			throw new Exception('Solo se pueden autorizar solicitudes pendientes de autorización.');
		}

		$this->solicitudMapper->cambiarEstado(
			$idSolicitud,
			self::ESTADO_AUTORIZADA,
			$userId,
			'fecha_autorizacion'
		);

		$this->historialMapper->insertHistorial(
			$idSolicitud,
			'autorizada',
			self::ESTADO_PENDIENTE_AUTORIZACION,
			self::ESTADO_AUTORIZADA,
			$comentario ?: 'Solicitud autorizada.',
			null,
			$userId
		);

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function rechazar(int $idSolicitud, string $userId, ?string $comentario = null): array {
		if (!$this->permisosService->canApprove($userId)) {
			throw new Exception('No tienes permisos para rechazar solicitudes.');
		}

		$solicitud = $this->solicitudMapper->find($idSolicitud);

		if ((string)$solicitud->getEstado() !== self::ESTADO_PENDIENTE_AUTORIZACION) {
			throw new Exception('Solo se pueden rechazar solicitudes pendientes de autorización.');
		}

		$this->solicitudMapper->cambiarEstado(
			$idSolicitud,
			self::ESTADO_RECHAZADA,
			$userId,
			null
		);

		$this->historialMapper->insertHistorial(
			$idSolicitud,
			'rechazada',
			self::ESTADO_PENDIENTE_AUTORIZACION,
			self::ESTADO_RECHAZADA,
			$comentario ?: 'Solicitud rechazada.',
			null,
			$userId
		);

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	private function calcularMontoEstimado(array $detalles): float {
		$total = 0.0;

		foreach ($detalles as $detalle) {
			$cantidad = (float)($detalle['cantidad'] ?? 1);
			$precio = (float)($detalle['precio_estimado'] ?? 0);

			$total += $cantidad * $precio;
		}

		return round($total, 2);
	}

	private function normalizarDetalles(array $detalles): array {
		$normalizados = [];

		foreach ($detalles as $detalle) {
			$descripcion = trim((string)($detalle['descripcion'] ?? ''));

			if ($descripcion === '') {
				throw new Exception('Cada concepto debe tener descripción.');
			}

			$cantidad = (float)($detalle['cantidad'] ?? 1);
			$precio = (float)($detalle['precio_estimado'] ?? 0);
			$subtotal = round($cantidad * $precio, 2);

			$normalizados[] = [
				'descripcion' => $descripcion,
				'cantidad' => $cantidad,
				'unidad' => $detalle['unidad'] ?? null,
				'precio_estimado' => $precio,
				'subtotal' => $subtotal,
				'notas' => $detalle['notas'] ?? null,
			];
		}

		return $normalizados;
	}
}
