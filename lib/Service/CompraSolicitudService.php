<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use Exception;
use OCA\Empleados\Db\CompraAutorizacion;
use OCA\Empleados\Db\CompraAutorizacionMapper;
use OCA\Empleados\Db\CompraDetalleMapper;
use OCA\Empleados\Db\CompraHistorialMapper;
use OCA\Empleados\Db\CompraSolicitudMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCP\IDBConnection;
use OCP\IUserManager;
use Throwable;

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
	private empleadosMapper $empleadosMapper;
	private CompraNotificacionService $notificacionService;
	private CompraAutorizacionMapper $autorizacionMapper;
	private IDBConnection $db;
	private IUserManager $userManager;

	public function __construct(
		CompraSolicitudMapper $solicitudMapper,
		CompraDetalleMapper $detalleMapper,
		CompraHistorialMapper $historialMapper,
		CompraFolioService $folioService,
		CompraPermisosService $permisosService,
		empleadosMapper $empleadosMapper,
		CompraNotificacionService $notificacionService,
		CompraAutorizacionMapper $autorizacionMapper,
		IDBConnection $db,
		IUserManager $userManager
	) {
		$this->solicitudMapper = $solicitudMapper;
		$this->detalleMapper = $detalleMapper;
		$this->historialMapper = $historialMapper;
		$this->folioService = $folioService;
		$this->permisosService = $permisosService;
		$this->empleadosMapper = $empleadosMapper;
		$this->notificacionService = $notificacionService;
		$this->autorizacionMapper = $autorizacionMapper;
		$this->db = $db;
		$this->userManager = $userManager;
	}

	public function listar(
		string $userId,
		bool $todas = false,
		int $limit = 50,
		int $offset = 0,
		?string $estado = null
	): array {
		$estados = [
			self::ESTADO_BORRADOR,
			self::ESTADO_PENDIENTE_AUTORIZACION,
			self::ESTADO_AUTORIZADA,
			self::ESTADO_RECHAZADA,
			self::ESTADO_CANCELADA,
		];

		if ($estado !== null && !in_array($estado, $estados, true)) {
			throw new Exception('El estado solicitado no es válido.');
		}

		$idUser = $todas && $this->permisosService->canViewAll($userId)
			? null
			: $userId;
		$summary = $this->solicitudMapper->getListSummary($idUser, $estado);

		return [
			'items' => $this->solicitudMapper->findPage($idUser, $estado, $limit, $offset),
			'pagination' => [
				'total' => $summary['total'],
				'limit' => $limit,
				'offset' => $offset,
			],
			'summary' => $summary,
		];
	}

	public function listarPendientesAutorizacion(string $userId, int $limit = 100, int $offset = 0): array {
		if (!$this->permisosService->canApprove($userId)) {
			throw new Exception('No tienes permisos para ver solicitudes pendientes de autorización.');
		}

		return $this->solicitudMapper->findByEstado(self::ESTADO_PENDIENTE_AUTORIZACION, $limit, $offset);
	}

	public function obtenerDetalle(int $idSolicitud, string $userId, bool $includeHistory = true): array {
		$solicitud = $this->solicitudMapper->find($idSolicitud);
		$resolved = $this->resolveApprovalStages($solicitud);
		$isPending = (string)$solicitud->getEstado() === self::ESTADO_PENDIENTE_AUTORIZACION;
		$isHierarchyApprover = $isPending
			&& in_array($userId, array_column($resolved['stages'], 'uid'), true);
		if (
			$isHierarchyApprover
			&& !$this->autorizacionMapper->hasAssignments($idSolicitud)
		) {
			$this->transactional(fn(): array => $this->ensureApprovalFlow($solicitud));
		}

		if (
			!$this->permisosService->canViewSolicitud($userId, (string)$solicitud->getIdUser())
			&& !$isHierarchyApprover
			&& !$this->autorizacionMapper->isAssigned($idSolicitud, $userId)
		) {
			throw new Exception('No tienes permisos para ver esta solicitud.');
		}

		return [
			'solicitud' => $solicitud,
			'detalles' => $this->detalleMapper->findBySolicitud($idSolicitud),
			'historial' => $includeHistory ? $this->historialMapper->findBySolicitud($idSolicitud) : [],
		];
	}

	public function obtenerHistorial(
		int $idSolicitud,
		string $userId,
		int $limit = 10,
		int $offset = 0
	): array {
		$this->obtenerDetalle($idSolicitud, $userId, false);

		return [
			'items' => $this->historialMapper->findPageBySolicitud($idSolicitud, $limit, $offset),
			'pagination' => [
				'total' => $this->historialMapper->countBySolicitud($idSolicitud),
				'limit' => $limit,
				'offset' => $offset,
			],
		];
	}

	public function crear(array $data, string $userId): array {
		$data = $this->applyRequesterRules($data, $userId);

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

		$detallesNormalizados = $this->normalizarDetalles($detalles);
		$totales = $this->calcularTotales($detallesNormalizados, $data);

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
			'monto_estimado' => $totales['total_excl_iva'],
			'monto_final' => $totales['total_incl_iva'],
			'moneda' => $data['moneda'] ?? 'MXN',
			'prioridad' => $data['prioridad'] ?? 'normal',
			'estado' => self::ESTADO_BORRADOR,
			'fecha_requerida' => $this->emptyToNull($data['fecha_requerida'] ?? null),
			'fecha_envio' => null,
			'fecha_autorizacion' => null,
			'fecha_cierre' => null,
			'proveedor_seleccionado' => null,
			'created_by' => $userId,
			'updated_by' => $userId,

			// Datos para PDF
			'solicitante_nombre' => $this->emptyToNull($data['solicitante_nombre'] ?? null),
			'solicitante_depto' => $this->emptyToNull($data['solicitante_depto'] ?? null),
			'solicitante_cargo' => $this->emptyToNull($data['solicitante_cargo'] ?? null),
			'jefe_directo_nombre' => $this->emptyToNull($data['jefe_directo_nombre'] ?? null),

			'tipo_compra' => $this->emptyToNull($data['tipo_compra'] ?? null),
			'garantia' => $this->toBoolInt($data['garantia'] ?? 0),
			'uso_compra' => $this->emptyToNull($data['uso_compra'] ?? 'empresa'),
			'informacion' => $this->emptyToNull($data['informacion'] ?? $data['descripcion'] ?? null),
			'motivo' => $this->emptyToNull($data['motivo'] ?? $data['justificacion'] ?? null),

			'proveedor_nombre' => $this->emptyToNull($data['proveedor_nombre'] ?? $detallesNormalizados[0]['proveedor_nombre'] ?? null),
			'atencion' => $this->emptyToNull($data['atencion'] ?? $detallesNormalizados[0]['atencion'] ?? null),
			'entrega' => $this->emptyToNull($data['entrega'] ?? $detallesNormalizados[0]['entrega'] ?? null),
			'marca_modelo' => $this->emptyToNull($data['marca_modelo'] ?? $detallesNormalizados[0]['marca_modelo'] ?? null),
			'especificaciones' => $this->emptyToNull($data['especificaciones'] ?? $detallesNormalizados[0]['especificaciones'] ?? null),
			'comentarios_req' => $this->emptyToNull($data['comentarios_req'] ?? null),

			'oficina_pct' => $this->toNullableFloat($data['oficina_pct'] ?? null),
			'empleado_pct' => $this->toNullableFloat($data['empleado_pct'] ?? null),
			'tipo_pago' => $this->emptyToNull($data['tipo_pago'] ?? null),
			'quincenas' => $this->toNullableInt($data['quincenas'] ?? null),

			'total_excl_iva' => $totales['total_excl_iva'],
			'iva' => $totales['iva'],
			'total_incl_iva' => $totales['total_incl_iva'],
			'comentarios_admin' => $this->emptyToNull($data['comentarios_admin'] ?? null),
		]);

		$idSolicitud = (int)$solicitud->getIdSolicitud();

		$this->detalleMapper->replaceBySolicitud($idSolicitud, $detallesNormalizados);

		$this->registrarHistorial(
			$idSolicitud,
			'creada',
			null,
			self::ESTADO_BORRADOR,
			'Solicitud creada.',
			$userId,
			[
				'total_excl_iva' => $totales['total_excl_iva'],
				'iva' => $totales['iva'],
				'total_incl_iva' => $totales['total_incl_iva'],
			]
		);

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function actualizar(int $idSolicitud, array $data, string $userId): array {
		$data = $this->applyRequesterRules($data, $userId);
		$solicitud = $this->solicitudMapper->find($idSolicitud);

		if (!$this->canModifyDraft($solicitud, $userId)) {
			throw new Exception('No tienes permisos para editar esta solicitud.');
		}

		if ((string)$solicitud->getEstado() !== self::ESTADO_BORRADOR) {
			throw new Exception('Solo se pueden editar solicitudes en borrador.');
		}

		$detalles = $data['detalles'] ?? null;
		$detallesNormalizados = null;

		if (is_array($detalles)) {
			if (count($detalles) === 0) {
				throw new Exception('La solicitud debe incluir al menos un concepto.');
			}

			$detallesNormalizados = $this->normalizarDetalles($detalles);
		} else {
			$detallesActuales = $this->detalleMapper->findBySolicitud($idSolicitud);
			$detallesNormalizados = array_map(static function ($detalle): array {
				return $detalle->jsonSerialize();
			}, $detallesActuales);
		}

		$totales = $this->calcularTotales($detallesNormalizados, $data, [
			'total_excl_iva' => $solicitud->getTotalExclIva(),
			'iva' => $solicitud->getIva(),
			'total_incl_iva' => $solicitud->getTotalInclIva(),
		]);

		$titulo = array_key_exists('titulo', $data)
			? trim((string)$data['titulo'])
			: (string)$solicitud->getTitulo();

		if ($titulo === '') {
			throw new Exception('El título de la solicitud es obligatorio.');
		}

		$this->solicitudMapper->updateSolicitud($idSolicitud, [
			'id_departamento' => $data['id_departamento'] ?? $solicitud->getIdDepartamento(),
			'id_equipo' => $data['id_equipo'] ?? $solicitud->getIdEquipo(),
			'id_cliente' => $data['id_cliente'] ?? $solicitud->getIdCliente(),

			'titulo' => $titulo,
			'descripcion' => $data['descripcion'] ?? $solicitud->getDescripcion(),
			'justificacion' => $data['justificacion'] ?? $solicitud->getJustificacion(),
			'monto_estimado' => $totales['total_excl_iva'],
			'monto_final' => $totales['total_incl_iva'],
			'moneda' => $data['moneda'] ?? $solicitud->getMoneda(),
			'prioridad' => $data['prioridad'] ?? $solicitud->getPrioridad(),
			'fecha_requerida' => array_key_exists('fecha_requerida', $data)
				? $this->emptyToNull($data['fecha_requerida'])
				: $solicitud->getFechaRequerida(),
			'updated_by' => $userId,

			'solicitante_nombre' => $data['solicitante_nombre'] ?? $solicitud->getSolicitanteNombre(),
			'solicitante_depto' => $data['solicitante_depto'] ?? $solicitud->getSolicitanteDepto(),
			'solicitante_cargo' => $data['solicitante_cargo'] ?? $solicitud->getSolicitanteCargo(),
			'jefe_directo_nombre' => $data['jefe_directo_nombre'] ?? $solicitud->getJefeDirectoNombre(),

			'tipo_compra' => $data['tipo_compra'] ?? $solicitud->getTipoCompra(),
			'garantia' => array_key_exists('garantia', $data) ? $this->toBoolInt($data['garantia']) : $solicitud->getGarantia(),
			'uso_compra' => $data['uso_compra'] ?? $solicitud->getUsoCompra(),
			'informacion' => $data['informacion'] ?? $solicitud->getInformacion(),
			'motivo' => $data['motivo'] ?? $solicitud->getMotivo(),

			'proveedor_nombre' => $data['proveedor_nombre'] ?? $solicitud->getProveedorNombre(),
			'atencion' => $data['atencion'] ?? $solicitud->getAtencion(),
			'entrega' => $data['entrega'] ?? $solicitud->getEntrega(),
			'marca_modelo' => $data['marca_modelo'] ?? $solicitud->getMarcaModelo(),
			'especificaciones' => $data['especificaciones'] ?? $solicitud->getEspecificaciones(),
			'comentarios_req' => $data['comentarios_req'] ?? $solicitud->getComentariosReq(),

			'oficina_pct' => array_key_exists('oficina_pct', $data) ? $this->toNullableFloat($data['oficina_pct']) : $solicitud->getOficinaPct(),
			'empleado_pct' => array_key_exists('empleado_pct', $data) ? $this->toNullableFloat($data['empleado_pct']) : $solicitud->getEmpleadoPct(),
			'tipo_pago' => $data['tipo_pago'] ?? $solicitud->getTipoPago(),
			'quincenas' => array_key_exists('quincenas', $data) ? $this->toNullableInt($data['quincenas']) : $solicitud->getQuincenas(),

			'total_excl_iva' => $totales['total_excl_iva'],
			'iva' => $totales['iva'],
			'total_incl_iva' => $totales['total_incl_iva'],
			'comentarios_admin' => $data['comentarios_admin'] ?? $solicitud->getComentariosAdmin(),
		]);

		if (is_array($detalles)) {
			$this->detalleMapper->replaceBySolicitud($idSolicitud, $detallesNormalizados);
		}

		$this->registrarHistorial(
			$idSolicitud,
			'editada',
			self::ESTADO_BORRADOR,
			self::ESTADO_BORRADOR,
			'Solicitud editada.',
			$userId
		);

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function enviarAutorizacion(int $idSolicitud, string $userId): array {
		$solicitud = $this->solicitudMapper->find($idSolicitud);

		if (!$this->canSendSolicitud($solicitud, $userId)) {
			throw new Exception('No tienes permisos para enviar esta solicitud.');
		}

		if ((string)$solicitud->getEstado() !== self::ESTADO_BORRADOR) {
			throw new Exception('Solo se pueden enviar solicitudes en borrador.');
		}

		$current = $this->transactional(function () use ($solicitud, $idSolicitud, $userId): ?CompraAutorizacion {
			if (!$this->solicitudMapper->cambiarEstadoSiActual(
				$idSolicitud,
				self::ESTADO_BORRADOR,
				self::ESTADO_PENDIENTE_AUTORIZACION,
				$userId,
				'fecha_envio'
			)) {
				throw new Exception('La solicitud cambió de estado mientras se enviaba.');
			}

			$flow = $this->ensureApprovalFlow($solicitud);
			$this->registrarHistorial(
				$idSolicitud,
				'enviada_autorizacion',
				self::ESTADO_BORRADOR,
				self::ESTADO_PENDIENTE_AUTORIZACION,
				'Solicitud enviada a autorización.',
				$userId,
				[
					'flujo' => $flow['stages'],
					'requiere_revision' => $flow['requires_review'],
					'incidencias' => $flow['issues'],
				]
			);

			return $this->autorizacionMapper->findCurrent($idSolicitud);
		});

		if ($current !== null) {
			$this->notificacionService->notificarAprobadorActual(
				$solicitud,
				(string)$current->getIdAutorizador(),
				(string)$current->getRol()
			);
		}

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function autorizar(int $idSolicitud, string $userId, ?string $comentario = null): array {
		$solicitud = $this->solicitudMapper->find($idSolicitud);
		$outcome = $this->transactional(function () use ($solicitud, $idSolicitud, $userId, $comentario): array {
			if ((string)$solicitud->getEstado() !== self::ESTADO_PENDIENTE_AUTORIZACION) {
				throw new Exception('Solo se pueden autorizar solicitudes pendientes de autorización.');
			}

			$flow = $this->ensureApprovalFlow($solicitud);
			$current = $this->autorizacionMapper->findCurrent($idSolicitud);
			if ($current === null) {
				throw new Exception($flow['requires_review']
					? 'La solicitud requiere revisión de su estructura de aprobación.'
					: 'La solicitud ya no tiene una etapa pendiente.');
			}
			if ((string)$current->getIdAutorizador() !== $userId) {
				throw new Exception('No tienes permiso: solamente el aprobador actual puede autorizar esta etapa.');
			}
			if (!$this->autorizacionMapper->resolvePending(
				(int)$current->getIdAutorizacion(),
				'aprobada',
				$comentario
			)) {
				throw new Exception('La etapa ya fue procesada por otra petición.');
			}

			$next = $this->autorizacionMapper->findCurrent($idSolicitud);
			$final = $next === null && !$flow['requires_review'];
			$estadoNuevo = $final ? self::ESTADO_AUTORIZADA : self::ESTADO_PENDIENTE_AUTORIZACION;
			if ($final && !$this->solicitudMapper->cambiarEstadoSiActual(
				$idSolicitud,
				self::ESTADO_PENDIENTE_AUTORIZACION,
				self::ESTADO_AUTORIZADA,
				$userId,
				'fecha_autorizacion'
			)) {
				throw new Exception('La solicitud cambió de estado durante la autorización.');
			}

			$this->registrarHistorial(
				$idSolicitud,
				$final ? 'autorizada' : 'etapa_autorizada',
				self::ESTADO_PENDIENTE_AUTORIZACION,
				$estadoNuevo,
				$comentario ?: ($final ? 'Solicitud autorizada.' : 'Etapa de autorización completada.'),
				$userId,
				$this->stageMetadata($current)
			);

			return ['final' => $final, 'next' => $next];
		});

		if ($outcome['final']) {
			$this->notificacionService->notificarSolicitudAutorizada($solicitud, $userId, $comentario);
		} elseif ($outcome['next'] instanceof CompraAutorizacion) {
			$this->notificacionService->notificarAprobadorActual(
				$solicitud,
				(string)$outcome['next']->getIdAutorizador(),
				(string)$outcome['next']->getRol()
			);
		}

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function rechazar(int $idSolicitud, string $userId, ?string $comentario = null): array {
		$comentario = trim((string)$comentario);
		if ($comentario === '') {
			throw new Exception('El comentario es obligatorio para rechazar la solicitud.');
		}

		$solicitud = $this->solicitudMapper->find($idSolicitud);
		$this->transactional(function () use ($solicitud, $idSolicitud, $userId, $comentario): void {
			if ((string)$solicitud->getEstado() !== self::ESTADO_PENDIENTE_AUTORIZACION) {
				throw new Exception('Solo se pueden rechazar solicitudes pendientes de autorización.');
			}

			$flow = $this->ensureApprovalFlow($solicitud);
			$current = $this->autorizacionMapper->findCurrent($idSolicitud);
			if ($current === null) {
				throw new Exception($flow['requires_review']
					? 'La solicitud requiere revisión de su estructura de aprobación.'
					: 'La solicitud ya no tiene una etapa pendiente.');
			}
			if ((string)$current->getIdAutorizador() !== $userId) {
				throw new Exception('No tienes permiso: solamente el aprobador actual puede rechazar esta etapa.');
			}
			if (!$this->autorizacionMapper->resolvePending(
				(int)$current->getIdAutorizacion(),
				'rechazada',
				$comentario
			)) {
				throw new Exception('La etapa ya fue procesada por otra petición.');
			}
			if (!$this->solicitudMapper->cambiarEstadoSiActual(
				$idSolicitud,
				self::ESTADO_PENDIENTE_AUTORIZACION,
				self::ESTADO_RECHAZADA,
				$userId
			)) {
				throw new Exception('La solicitud cambió de estado durante el rechazo.');
			}

			$this->autorizacionMapper->cancelPendingBySolicitud($idSolicitud);
			$this->registrarHistorial(
				$idSolicitud,
				'rechazada',
				self::ESTADO_PENDIENTE_AUTORIZACION,
				self::ESTADO_RECHAZADA,
				$comentario ?: 'Solicitud rechazada.',
				$userId,
				$this->stageMetadata($current)
			);
		});

		$this->notificacionService->notificarSolicitudRechazada($solicitud, $userId, $comentario);

		return $this->obtenerDetalle($idSolicitud, $userId);
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
			$iva = array_key_exists('iva', $detalle)
				? round((float)$detalle['iva'], 2)
				: 0.0;
			$total = array_key_exists('total', $detalle)
				? round((float)$detalle['total'], 2)
				: round($subtotal + $iva, 2);

			$normalizados[] = [
				'descripcion' => $descripcion,
				'cantidad' => $cantidad,
				'unidad' => $this->emptyToNull($detalle['unidad'] ?? null),
				'precio_estimado' => $precio,
				'subtotal' => $subtotal,
				'notas' => $this->emptyToNull($detalle['notas'] ?? null),

				'marca_modelo' => $this->emptyToNull($detalle['marca_modelo'] ?? null),
				'especificaciones' => $this->emptyToNull($detalle['especificaciones'] ?? null),
				'iva' => $iva,
				'total' => $total,
				'proveedor_nombre' => $this->emptyToNull($detalle['proveedor_nombre'] ?? null),
				'entrega' => $this->emptyToNull($detalle['entrega'] ?? null),
				'atencion' => $this->emptyToNull($detalle['atencion'] ?? null),
			];
		}

		return $normalizados;
	}

	private function calcularTotales(array $detalles, array $data, ?array $fallback = null): array {
		$totalExclIva = array_reduce($detalles, static function ($total, $detalle) {
			return $total + (float)($detalle['subtotal'] ?? 0);
		}, 0.0);

		$iva = array_reduce($detalles, static function ($total, $detalle) {
			return $total + (float)($detalle['iva'] ?? 0);
		}, 0.0);

		// Los totales son derivados de conceptos normalizados en backend. Nunca se
		// aceptan los montos calculados que envía el navegador.
		if ($detalles === [] && $fallback !== null && $fallback['total_excl_iva'] !== null) {
			$totalExclIva = (float)$fallback['total_excl_iva'];
		}

		if ($detalles === [] && $fallback !== null && $fallback['iva'] !== null) {
			$iva = (float)$fallback['iva'];
		}

		$totalInclIva = $totalExclIva + $iva;

		if ($detalles === [] && $fallback !== null && $fallback['total_incl_iva'] !== null) {
			$totalInclIva = (float)$fallback['total_incl_iva'];
		}

		return [
			'total_excl_iva' => round($totalExclIva, 2),
			'iva' => round($iva, 2),
			'total_incl_iva' => round($totalInclIva, 2),
		];
	}

	private function emptyToNull($value): ?string {
		if ($value === null) {
			return null;
		}

		$value = trim((string)$value);

		return $value === '' ? null : $value;
	}

	private function toNullableFloat($value): ?float {
		if ($value === null || $value === '') {
			return null;
		}

		return round((float)$value, 2);
	}

	private function toNullableInt($value): ?int {
		if ($value === null || $value === '') {
			return null;
		}

		return (int)$value;
	}

	private function toBoolInt($value): int {
		if (is_bool($value)) {
			return $value ? 1 : 0;
		}

		if (is_numeric($value)) {
			return ((int)$value) === 1 ? 1 : 0;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'si', 'sí', 'yes'], true) ? 1 : 0;
	}

	public function cancelar(int $idSolicitud, string $userId, ?string $comentario = null): array {
		$solicitud = $this->solicitudMapper->find($idSolicitud);

		if (!$this->canCancelSolicitud($solicitud, $userId)) {
			throw new Exception('No tienes permisos para cancelar esta solicitud.');
		}

		if (in_array((string)$solicitud->getEstado(), [
			self::ESTADO_AUTORIZADA,
			self::ESTADO_RECHAZADA,
			self::ESTADO_CANCELADA,
		], true)) {
			throw new Exception('Esta solicitud ya no se puede cancelar.');
		}

		$estadoAnterior = (string)$solicitud->getEstado();

		$this->transactional(function () use ($idSolicitud, $estadoAnterior, $userId, $comentario): void {
			if (!$this->solicitudMapper->cambiarEstadoSiActual(
				$idSolicitud,
				$estadoAnterior,
				self::ESTADO_CANCELADA,
				$userId,
				'fecha_cierre'
			)) {
				throw new Exception('La solicitud cambió de estado mientras se cancelaba.');
			}
			$this->autorizacionMapper->cancelPendingBySolicitud($idSolicitud);
			$this->registrarHistorial(
				$idSolicitud,
				'cancelada',
				$estadoAnterior,
				self::ESTADO_CANCELADA,
				$comentario ?: 'Solicitud cancelada.',
				$userId
			);
		});

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function resolverAprobadorActual(int $idSolicitud): ?array {
		$current = $this->autorizacionMapper->findCurrent($idSolicitud);
		return $current?->jsonSerialize();
	}

	public function obtenerFlujo(int $idSolicitud, string $userId): array {
		$solicitud = $this->solicitudMapper->find($idSolicitud);
		$resolved = $this->resolveApprovalStages($solicitud);
		$isPendingHierarchyApprover = (string)$solicitud->getEstado() === self::ESTADO_PENDIENTE_AUTORIZACION
			&& in_array($userId, array_column($resolved['stages'], 'uid'), true);
		$allowed = $this->permisosService->canViewSolicitud($userId, (string)$solicitud->getIdUser())
			|| $isPendingHierarchyApprover
			|| $this->autorizacionMapper->isAssigned($idSolicitud, $userId);
		if (!$allowed) {
			throw new Exception('No tienes permisos para consultar el flujo de esta solicitud.');
		}

		if (
			(string)$solicitud->getEstado() === self::ESTADO_PENDIENTE_AUTORIZACION
			&& !$this->autorizacionMapper->hasAssignments($idSolicitud)
		) {
			$this->transactional(fn(): array => $this->ensureApprovalFlow($solicitud));
		}

		$stages = array_map(
			static fn(CompraAutorizacion $stage): array => $stage->jsonSerialize(),
			$this->autorizacionMapper->findBySolicitud($idSolicitud)
		);
		$current = $this->resolverAprobadorActual($idSolicitud);
		$canResolve = (string)$solicitud->getEstado() === self::ESTADO_PENDIENTE_AUTORIZACION
			&& $current !== null
			&& (string)$current['id_autorizador'] === $userId;

		return [
			'id_solicitud' => $idSolicitud,
			'estado' => (string)$solicitud->getEstado(),
			'aprobador_actual' => $current,
			'etapas' => $stages,
			'can_approve' => $canResolve,
			'can_reject' => $canResolve,
			'requiere_revision' => $resolved['requires_review'],
			'incidencias' => $resolved['issues'],
		];
	}

	public function contexto(string $userId): array {
		return [
			'uid' => $userId,

			// Compatibilidad con el frontend anterior.
			'can_select_requester' => $this->permisosService->canSelectRequester($userId),

			'permissions' => [
				'can_create' => $this->permisosService->canCreateSolicitud($userId),
				'can_view_all' => $this->permisosService->canViewAll($userId),
				'can_approve' => $this->permisosService->canApprove($userId),
				'can_process_purchase' => $this->permisosService->canProcessPurchase($userId),
				'can_select_requester' => $this->permisosService->canSelectRequester($userId),
			],

			'requester' => $this->getRequesterData($userId),
		];
	}

	private function ensureApprovalFlow($solicitud): array {
		$idSolicitud = (int)$solicitud->getIdSolicitud();
		$resolved = $this->resolveApprovalStages($solicitud);
		if (!$this->autorizacionMapper->hasAssignments($idSolicitud)) {
			foreach ($resolved['stages'] as $stage) {
				$this->autorizacionMapper->insertStage($idSolicitud, $stage);
			}
		}

		return $resolved;
	}

	private function resolveApprovalStages($solicitud): array {
		$idEmpleado = trim((string)$solicitud->getIdEmpleado());
		$employeeRows = $idEmpleado !== ''
			? $this->empleadosMapper->GetMyEmployeeInfoByIdEmpleado($idEmpleado)
			: $this->empleadosMapper->GetMyEmployeeInfo((string)$solicitud->getIdUser());
		$employee = $employeeRows[0] ?? null;
		if ($employee === null) {
			return [
				'stages' => [],
				'issues' => ['No se encontró la estructura laboral del solicitante.'],
				'requires_review' => true,
			];
		}

		$roles = [
			'gerente' => trim((string)($employee['Id_gerente'] ?? $employee['id_gerente'] ?? '')),
			'socio' => trim((string)($employee['Id_socio'] ?? $employee['id_socio'] ?? '')),
		];
		$stages = [];
		$issues = [];
		$stageByUid = [];

		foreach ($roles as $role => $uid) {
			if ($uid === '') {
				$issues[] = sprintf('El solicitante no tiene %s asignado.', $role);
				continue;
			}

			$user = $this->userManager->get($uid);
			if ($user === null || !$user->isEnabled()) {
				$issues[] = sprintf('El %s asignado (%s) no existe o está deshabilitado.', $role, $uid);
				continue;
			}

			if (isset($stageByUid[$uid])) {
				$index = $stageByUid[$uid];
				$stages[$index]['rol'] = 'gerente_socio';
				continue;
			}

			$approverRows = $this->empleadosMapper->GetMyEmployeeInfo($uid);
			$approver = $approverRows[0] ?? [];
			$stageByUid[$uid] = count($stages);
			$stages[] = [
				'uid' => $uid,
				'id_empleado' => $approver['Id_empleados'] ?? $approver['id_empleados'] ?? null,
				'nombre' => $user->getDisplayName() ?: $uid,
				'rol' => $role,
				'nivel' => count($stages) + 1,
			];
		}

		return [
			'stages' => $stages,
			'issues' => $issues,
			'requires_review' => $issues !== [] || $stages === [],
		];
	}

	private function registrarHistorial(
		int $idSolicitud,
		string $accion,
		?string $estadoAnterior,
		?string $estadoNuevo,
		?string $comentario,
		string $actorUid,
		array $metadata = []
	): void {
		$user = $this->userManager->get($actorUid);
		$metadata = array_merge($metadata, [
			'actor_uid' => $actorUid,
			'actor_nombre' => $user?->getDisplayName() ?: $actorUid,
		]);
		$this->historialMapper->insertHistorial(
			$idSolicitud,
			$accion,
			$estadoAnterior,
			$estadoNuevo,
			$comentario,
			$metadata,
			$actorUid
		);
	}

	private function stageMetadata(CompraAutorizacion $stage): array {
		return [
			'aprobador_uid' => (string)$stage->getIdAutorizador(),
			'aprobador_nombre' => (string)$stage->getAutorizadorNombre(),
			'rol' => (string)$stage->getRol(),
			'nivel' => (int)$stage->getNivel(),
		];
	}

	private function transactional(callable $operation) {
		$this->db->beginTransaction();
		try {
			$result = $operation();
			$this->db->commit();
			return $result;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function getRequesterData(string $userId): array {
		$rows = $this->empleadosMapper->GetMyEmployeeInfo($userId);
		$empleado = $rows[0] ?? [];

		$managerUid = (string)($empleado['Id_gerente'] ?? $empleado['id_gerente'] ?? '');
		$managerName = $managerUid;

		if ($managerUid !== '') {
			$managerRows = $this->empleadosMapper->GetMyEmployeeInfo($managerUid);
			$manager = $managerRows[0] ?? [];

			$managerName = (string)(
				$manager['displayname']
				?? $manager['DisplayName']
				?? $manager['nombre_completo']
				?? $managerUid
			);
		}

		return [
			'uid' => $userId,
			'id_empleado' => $empleado['Id_empleados'] ?? $empleado['id_empleados'] ?? null,
			'id_departamento' => $empleado['Id_departamento'] ?? $empleado['id_departamento'] ?? null,
			'id_puesto' => $empleado['Id_puesto'] ?? $empleado['id_puesto'] ?? null,
			'solicitante_nombre' => (string)(
				$empleado['displayname']
				?? $empleado['DisplayName']
				?? $empleado['Nombre']
				?? $userId
			),
			'solicitante_depto' => (string)($empleado['Id_departamento'] ?? $empleado['id_departamento'] ?? ''),
			'solicitante_cargo' => (string)($empleado['Id_puesto'] ?? $empleado['id_puesto'] ?? ''),
			'jefe_directo_uid' => $managerUid,
			'jefe_directo_nombre' => $managerName,
			'raw' => $this->sanitizeEmployeeRaw($empleado),
		];
	}

	private function sanitizeEmployeeRaw(array $raw): array {
		unset($raw['password']);
		unset($raw['token']);
		unset($raw['session']);
		unset($raw['salt']);

		return $raw;
	}

	private function isSolicitudOwner($solicitud, string $userId): bool {
		return (string)$solicitud->getIdUser() === $userId;
	}

	private function canManageSolicitud(string $userId): bool {
		/*
		 * En nuestro flujo, canSelectRequester() solo lo tiene compras_admin/admin.
		 * Lo usamos como permiso fuerte de administración de compras.
		 */
		return $this->permisosService->canSelectRequester($userId);
	}

	private function canModifyDraft($solicitud, string $userId): bool {
		return $this->isSolicitudOwner($solicitud, $userId)
			|| $this->canManageSolicitud($userId);
	}

	private function canSendSolicitud($solicitud, string $userId): bool {
		return $this->isSolicitudOwner($solicitud, $userId)
			|| $this->canManageSolicitud($userId);
	}

	private function canCancelSolicitud($solicitud, string $userId): bool {
		return $this->isSolicitudOwner($solicitud, $userId)
			|| $this->canManageSolicitud($userId);
	}

	private function applyRequesterRules(array $data, string $userId): array {
		if ($this->permisosService->canSelectRequester($userId)) {
			return $data;
		}

		$requester = $this->getRequesterData($userId);

		$data['id_empleado'] = $requester['id_empleado'];
		$data['solicitante_nombre'] = $requester['solicitante_nombre'];
		$data['jefe_directo_nombre'] = $requester['jefe_directo_nombre'];

		/*
		 * Departamento y puesto se muestran en frontend como etiqueta usando GetAreasFix/GetPuestosFix.
		 * Si el frontend ya mandó la etiqueta, la respetamos.
		 * Si no mandó nada, usamos el ID como fallback.
		 */
		if (!isset($data['solicitante_depto']) || trim((string)$data['solicitante_depto']) === '') {
			$data['solicitante_depto'] = $requester['solicitante_depto'];
		}

		if (!isset($data['solicitante_cargo']) || trim((string)$data['solicitante_cargo']) === '') {
			$data['solicitante_cargo'] = $requester['solicitante_cargo'];
		}

		return $data;
	}
}
