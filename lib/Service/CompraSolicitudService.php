<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use Exception;
use OCA\Empleados\Db\CompraDetalleMapper;
use OCA\Empleados\Db\CompraHistorialMapper;
use OCA\Empleados\Db\CompraSolicitudMapper;
use OCA\Empleados\Db\empleadosMapper;

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

	public function __construct(
		CompraSolicitudMapper $solicitudMapper,
		CompraDetalleMapper $detalleMapper,
		CompraHistorialMapper $historialMapper,
		CompraFolioService $folioService,
		CompraPermisosService $permisosService,
		empleadosMapper $empleadosMapper
	) {
		$this->solicitudMapper = $solicitudMapper;
		$this->detalleMapper = $detalleMapper;
		$this->historialMapper = $historialMapper;
		$this->folioService = $folioService;
		$this->permisosService = $permisosService;
		$this->empleadosMapper = $empleadosMapper;
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

		$this->historialMapper->insertHistorial(
			$idSolicitud,
			'creada',
			null,
			self::ESTADO_BORRADOR,
			'Solicitud creada.',
			[
				'total_excl_iva' => $totales['total_excl_iva'],
				'iva' => $totales['iva'],
				'total_incl_iva' => $totales['total_incl_iva'],
			],
			$userId
		);

		return $this->obtenerDetalle($idSolicitud, $userId);
	}

	public function actualizar(int $idSolicitud, array $data, string $userId): array {
		$data = $this->applyRequesterRules($data, $userId);
		$solicitud = $this->solicitudMapper->find($idSolicitud);

		if ((string)$solicitud->getIdUser() !== $userId && !$this->permisosService->canViewAll($userId)) {
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

		if (array_key_exists('total_excl_iva', $data) && $data['total_excl_iva'] !== '') {
			$totalExclIva = (float)$data['total_excl_iva'];
		} elseif ($fallback !== null && $totalExclIva <= 0 && $fallback['total_excl_iva'] !== null) {
			$totalExclIva = (float)$fallback['total_excl_iva'];
		}

		if (array_key_exists('iva', $data) && $data['iva'] !== '') {
			$iva = (float)$data['iva'];
		} elseif ($fallback !== null && $iva <= 0 && $fallback['iva'] !== null) {
			$iva = (float)$fallback['iva'];
		}

		$totalInclIva = $totalExclIva + $iva;

		if (array_key_exists('total_incl_iva', $data) && $data['total_incl_iva'] !== '') {
			$totalInclIva = (float)$data['total_incl_iva'];
		} elseif ($fallback !== null && $totalInclIva <= 0 && $fallback['total_incl_iva'] !== null) {
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

		if ((string)$solicitud->getIdUser() !== $userId && !$this->permisosService->canViewAll($userId)) {
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

		$this->solicitudMapper->cambiarEstado(
			$idSolicitud,
			self::ESTADO_CANCELADA,
			$userId,
			'fecha_cierre'
		);

		$this->historialMapper->insertHistorial(
			$idSolicitud,
			'cancelada',
			$estadoAnterior,
			self::ESTADO_CANCELADA,
			$comentario ?: 'Solicitud cancelada.',
			null,
			$userId
		);

		return $this->obtenerDetalle($idSolicitud, $userId);
	}
	public function canSelectRequester(string $userId): bool {
			return $this->isInConfiguredGroup($userId, 'compras_grupo_admin', 'compras_admin')
				|| $this->groupManager->isInGroup($userId, 'admin');
		}
		public function contexto(string $userId): array {
		return [
			'uid' => $userId,
			'can_select_requester' => $this->permisosService->canSelectRequester($userId),
			'requester' => $this->getRequesterData($userId),
		];
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
			'raw' => $empleado,
		];
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
