<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Db\honorarios;
use OCA\Empleados\Db\honorariosMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\clientesMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Service\XlsxTemplateFiller;
use OCA\Empleados\Service\LogoService;
use OCA\Empleados\Service\PermisosService;
use OCA\Empleados\Service\BitacoraService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IConfig;
use OCP\Mail\IMailer;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

use OCP\AppFramework\Http\DataDownloadResponse;
use Psr\Log\LoggerInterface;

class HonorariosController extends BaseController {

	protected honorariosMapper $honorariosMapper;
	protected clientesMapper $clientesMapper;
	private LoggerInterface $logger;
	private LogoService $logoService;
	private PermisosService $permisosService;
	private IConfig $config;
	private IMailer $mailer;
	private BitacoraService $bitacoraService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper,
		honorariosMapper $honorariosMapper,
		clientesMapper $clientesMapper,
		LoggerInterface $logger,
		LogoService $logoService,
		PermisosService $permisosService,
		IConfig $config,
		IMailer $mailer,
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
		$this->clientesMapper = $clientesMapper;
		$this->logger = $logger;
		$this->logoService = $logoService;
		$this->permisosService = $permisosService;
		$this->config = $config;
		$this->mailer = $mailer;
		$this->bitacoraService = $bitacoraService;
	}

	private function requireClientesAccess(): void {
		$this->permisosService->requireCanSee('clientes');
	}

	private function requireClientesAdminAccess(): void {
		$this->permisosService->requireCanSee('clientes.admin');
	}

	/**
	 * Registra un movimiento del módulo "honorarios" en la bitácora general.
	 */
	private function registrarMovimiento(
		?string $uidActor,
		?int $idReferencia,
		?string $nombreAfectado,
		string $tipo,
		string $mensaje
	): void {
		$this->bitacoraService->registrar('honorarios', $uidActor, null, $nombreAfectado, $tipo, $mensaje, $idReferencia);
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
	 * Nombre del cliente dueño de un honorario, usado para dar contexto
	 * en los mensajes de bitácora (ej. "el honorario de ACME").
	 */
	private function getNombreClienteDeHonorario(int $id_cliente): string {
		$cliente = $this->clientesMapper->findById($id_cliente);

		return $cliente['nombre'] ?? ('Cliente ' . $id_cliente);
	}

	/**
	 * Etiqueta legible del tipo de honorario para los mensajes.
	 */
	private function etiquetaTipoHonorario(string $tipo): string {
		return match ($tipo) {
			'iguala' => 'iguala',
			'eventual' => 'eventual',
			default => 'parcialidades',
		};
	}

	#[UseSession]
	#[NoAdminRequired]
	public function getHonorarios(): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->honorariosMapper->findAll(),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByCliente(int $id_cliente): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->honorariosMapper->findByCliente($id_cliente),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(int $id_honorario): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->honorariosMapper->findById($id_honorario),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(int $id_honorario): DataResponse {
		$this->requireClientesAdminAccess();

		$honorario = $this->honorariosMapper->findById($id_honorario);

		$this->honorariosMapper->deleteById($id_honorario);

		// --- Movimiento (bitácora) ---
		if ($honorario) {
			$nombreCliente = $this->getNombreClienteDeHonorario((int)$honorario['id_cliente']);
			[$uidActor, $nombreActor] = $this->getActorInfo();

			$mensaje = sprintf(
				'%s ha eliminado el honorario (%s) del cliente "%s".',
				$nombreActor,
				$this->etiquetaTipoHonorario($honorario['tipo_honorario'] ?? 'parcial'),
				$nombreCliente
			);

			$this->registrarMovimiento($uidActor, $id_honorario, $nombreCliente, 'eliminacion', $mensaje);
		}

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function crearHonorario(
		int $id_cliente,
		float $importe_total,
		string $tipo_moneda,
		string $fecha_inicio,
		string $fecha_fin,
		?string $tipo_servicio,
		bool $especial,
		string $tipo_honorario = 'parcial',
    	int $periodicidad_parcialidad = 1
	): DataResponse {
		$this->requireClientesAdminAccess();

		$honorario = new honorarios();

		$honorario->setId_cliente($id_cliente);
		$honorario->setImporte_total($importe_total);
		$honorario->setTipo_moneda($tipo_moneda);
		$honorario->setFecha_inicio($fecha_inicio);
		$honorario->setFecha_fin($fecha_fin);
		$honorario->setTipo_servicio($tipo_servicio);
		$honorario->setTipo_honorario($tipo_honorario);
		$honorario->setEspecial($especial);
		$honorario->setActivo(true);

		$this->honorariosMapper->crearHonorario($honorario, $periodicidad_parcialidad);

		// --- Movimiento (bitácora) ---
		$nombreCliente = $this->getNombreClienteDeHonorario($id_cliente);
		[$uidActor, $nombreActor] = $this->getActorInfo();

		$mensaje = sprintf(
			'%s ha creado un honorario de **%s** (%s) para el cliente "%s".',
			$nombreActor,
			number_format($importe_total, 2) . ' ' . strtoupper($tipo_moneda),
			$this->etiquetaTipoHonorario($tipo_honorario),
			$nombreCliente
		);

		$this->registrarMovimiento($uidActor, (int)$honorario->getId(), $nombreCliente, 'creacion', $mensaje);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function modificarHonorario(
		int $id_honorario,
		int $id_cliente,
		float $importe_total,
		string $tipo_moneda,
		string $fecha_inicio,
		string $fecha_fin,
		?string $tipo_servicio,
		bool $especial,
		string $tipo_honorario = 'parcial'
	): DataResponse {
		$this->requireClientesAdminAccess();

		$old = $this->honorariosMapper->findById($id_honorario);

		$this->honorariosMapper->updateHonorario(
			$id_honorario,
			$id_cliente,
			$importe_total,
			$tipo_moneda,
			$fecha_inicio,
			$fecha_fin,
			$tipo_servicio,
			$especial,
			$tipo_honorario
		);

		// --- Movimiento (bitácora) ---
		if ($old) {
			$this->registrarEdicionHonorario(
				$old,
				$id_cliente,
				$importe_total,
				$tipo_moneda,
				$fecha_inicio,
				$fecha_fin,
				$tipo_honorario,
				$id_honorario
			);
		}

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function completarHonorario(): DataResponse {
		$this->requireClientesAdminAccess();

		$idHonorario = (int)$this->request->getParam('id_honorario');
		$idCliente = (int)$this->request->getParam('id_cliente');
		$importeTotal = (float)$this->request->getParam('importe_total');
		$tipoMoneda = (string)$this->request->getParam('tipo_moneda', 'MXN');
		$fechaInicio = (string)$this->request->getParam('fecha_inicio');
		$fechaFin = (string)$this->request->getParam('fecha_fin');
		$tipoServicio = $this->request->getParam('tipo_servicio');
		$especial = (bool)$this->request->getParam('especial', false);
		$tipoHonorario = (string)$this->request->getParam('tipo_honorario', 'parcial');
		$periodicidadParcialidad = (int)$this->request->getParam('periodicidad_parcialidad', 1);

		$old = $this->honorariosMapper->findById($idHonorario);

		try {
			$this->honorariosMapper->updateHonorario(
				$idHonorario,
				$idCliente,
				$importeTotal,
				$tipoMoneda,
				$fechaInicio,
				$fechaFin,
				$tipoServicio !== null ? (string)$tipoServicio : null,
				$especial,
				$tipoHonorario,
				$periodicidadParcialidad
			);
		} catch (\Exception $e) {
			return new DataResponse(
				['status' => 'error', 'message' => $e->getMessage()],
				Http::STATUS_FORBIDDEN
			);
		}

		// --- Movimiento (bitácora) ---
		if ($old) {
			$this->registrarEdicionHonorario(
				$old,
				$idCliente,
				$importeTotal,
				$tipoMoneda,
				$fechaInicio,
				$fechaFin,
				$tipoHonorario,
				$idHonorario
			);
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	/**
	 * Compara los datos anteriores de un honorario contra los nuevos y,
	 * si hubo cambios relevantes, registra el movimiento en la bitácora.
	 * Usado por modificarHonorario() y completarHonorario(), que en
	 * esencia hacen lo mismo con parámetros distintos.
	 */
	private function registrarEdicionHonorario(
		array $old,
		int $id_cliente,
		float $importe_total,
		string $tipo_moneda,
		string $fecha_inicio,
		string $fecha_fin,
		string $tipo_honorario,
		int $id_honorario
	): void {
		$cambios = [];

		if ((float)$old['importe_total'] !== $importe_total) {
			$cambios[] = sprintf(
				'importe de %s a **%s**',
				number_format((float)$old['importe_total'], 2),
				number_format($importe_total, 2)
			);
		}

		if (strtoupper((string)($old['tipo_moneda'] ?? '')) !== strtoupper($tipo_moneda)) {
			$cambios[] = sprintf(
				'moneda de %s a **%s**',
				strtoupper((string)($old['tipo_moneda'] ?? '')),
				strtoupper($tipo_moneda)
			);
		}

		if (($old['tipo_honorario'] ?? 'parcial') !== $tipo_honorario) {
			$cambios[] = sprintf(
				'tipo de %s a **%s**',
				$this->etiquetaTipoHonorario($old['tipo_honorario'] ?? 'parcial'),
				$this->etiquetaTipoHonorario($tipo_honorario)
			);
		}

		if (($old['fecha_inicio'] ?? '') !== $fecha_inicio || ($old['fecha_fin'] ?? '') !== $fecha_fin) {
			$cambios[] = sprintf(
				'período de %s - %s a **%s - %s**',
				$old['fecha_inicio'] ?? '',
				$old['fecha_fin'] ?? '',
				$fecha_inicio,
				$fecha_fin
			);
		}

		if (empty($cambios)) {
			return;
		}

		$nombreCliente = $this->getNombreClienteDeHonorario($id_cliente);
		[$uidActor, $nombreActor] = $this->getActorInfo();

		$mensaje = sprintf(
			'%s ha actualizado el honorario del cliente "%s": cambió %s.',
			$nombreActor,
			$nombreCliente,
			implode(', ', $cambios)
		);

		$this->registrarMovimiento($uidActor, $id_honorario, $nombreCliente, 'edicion', $mensaje);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function finalizarHonorario(int $id_honorario): DataResponse {
		$this->requireClientesAdminAccess();

		$honorario = $this->honorariosMapper->findById($id_honorario);

		$this->honorariosMapper->desactivarHonorario($id_honorario);

		// --- Movimiento (bitácora) ---
		if ($honorario) {
			$nombreCliente = $this->getNombreClienteDeHonorario((int)$honorario['id_cliente']);
			[$uidActor, $nombreActor] = $this->getActorInfo();

			$mensaje = sprintf(
				'%s ha finalizado el honorario del cliente "%s".',
				$nombreActor,
				$nombreCliente
			);

			$this->registrarMovimiento($uidActor, $id_honorario, $nombreCliente, 'finalizacion', $mensaje);
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function reactivarHonorario(int $id_honorario): DataResponse {
		$this->requireClientesAdminAccess();

		$honorario = $this->honorariosMapper->findById($id_honorario);

		$this->honorariosMapper->reactivarHonorario($id_honorario);

		// --- Movimiento (bitácora) ---
		if ($honorario) {
			$nombreCliente = $this->getNombreClienteDeHonorario((int)$honorario['id_cliente']);
			[$uidActor, $nombreActor] = $this->getActorInfo();

			$mensaje = sprintf(
				'%s ha reactivado el honorario del cliente "%s".',
				$nombreActor,
				$nombreCliente
			);

			$this->registrarMovimiento($uidActor, $id_honorario, $nombreCliente, 'reactivacion', $mensaje);
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function actualizarMetadatos(): DataResponse {
		$this->requireClientesAdminAccess();

		$idHonorario = (int)$this->request->getParam('id_honorario');
		$tipoServicio = $this->request->getParam('tipo_servicio');
		$tipoMoneda = (string)$this->request->getParam('tipo_moneda', 'MXN');
		$especial = (bool)$this->request->getParam('especial', false);

		$old = $this->honorariosMapper->findById($idHonorario);

		$this->honorariosMapper->actualizarMetadatos(
			$idHonorario,
			$tipoServicio !== null ? (string)$tipoServicio : null,
			$tipoMoneda,
			$especial
		);

		// --- Movimiento (bitácora) ---
		if ($old) {
			$cambios = [];

			if (($old['tipo_servicio'] ?? '') !== (string)($tipoServicio ?? '')) {
				$cambios[] = 'el servicio';
			}

			if (strtoupper((string)($old['tipo_moneda'] ?? '')) !== strtoupper($tipoMoneda)) {
				$cambios[] = 'la moneda';
			}

			if ((bool)($old['especial'] ?? false) !== $especial) {
				$cambios[] = 'la marca de especial';
			}

			if (!empty($cambios)) {
				$nombreCliente = $this->getNombreClienteDeHonorario((int)$old['id_cliente']);
				[$uidActor, $nombreActor] = $this->getActorInfo();

				$mensaje = sprintf(
					'%s ha actualizado %s del honorario del cliente "%s".',
					$nombreActor,
					implode(' y ', $cambios),
					$nombreCliente
				);

				$this->registrarMovimiento($uidActor, $idHonorario, $nombreCliente, 'edicion', $mensaje);
			}
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function generarSolicitudRecibo(
		int $id_honorario,
		?string $departamento = null,
		?string $asunto = null,
		?string $quienSolicita = null,
		?string $quienAutoriza = null,
		?string $claveGerenteJunior = null,
		?string $nombreGerenteJunior = null,
		?string $claveSupervisorSenior = null,
		?string $nombreSupervisorSenior = null,
		?string $claveSupervisorJunior = null,
		?string $nombreSupervisorJunior = null,
		?string $claveOtro = null,
		?string $nombreOtro = null,
		?string $nombreGerente = null,
		?string $nombreSocio = null
	) {
		$this->requireClientesAdminAccess();

		try {
			$solicitud = $this->construirSolicitud(
				$id_honorario,
				$departamento,
				$asunto,
				$quienSolicita,
				$claveGerenteJunior,
				$nombreGerenteJunior,
				$claveSupervisorSenior,
				$nombreSupervisorSenior,
				$claveSupervisorJunior,
				$nombreSupervisorJunior,
				$claveOtro,
				$nombreOtro,
				$nombreGerente,
				$nombreSocio
			);

			return new DataDownloadResponse(
				$solicitud['contenido'],
				$solicitud['nombreArchivo'],
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
			);

		} catch (\Throwable $e) {
			$this->logger->error(
				$e->getMessage(),
				[
					'app' => 'empleados',
					'exception' => $e,
				]
			);

			return new DataResponse(
				[
					'status' => 'error',
					'message' => $e->getMessage(),
				],
				500
			);
		}
	}

	/**
	 * Construye el xlsx de solicitud de recibo (mismo formato usado para
	 * descarga y para envío por correo).
	 *
	 * @return array{contenido:string, nombreArchivo:string, cliente:array, honorario:array}
	 */
	private function construirSolicitud(
		int $id_honorario,
		?string $departamento,
		?string $asunto,
		?string $quienSolicita,
		?string $claveGerenteJunior,
		?string $nombreGerenteJunior,
		?string $claveSupervisorSenior,
		?string $nombreSupervisorSenior,
		?string $claveSupervisorJunior,
		?string $nombreSupervisorJunior,
		?string $claveOtro,
		?string $nombreOtro,
		?string $nombreGerente,
		?string $nombreSocio
	): array {
		$honorario = $this->honorariosMapper->findById($id_honorario);

		if (!$honorario) {
			throw new \Exception('No se encontró el honorario.');
		}

		$cliente = $this->clientesMapper->findById(
			(int)$honorario['id_cliente']
		);

		if (!$cliente) {
			throw new \Exception('No se encontró el cliente.');
		}

		$templatePath = __DIR__ . '/../../templates/PlantillaReporte.xlsx';

		$grupoNombre = '';

		if (!empty($cliente['cliente_padre'])) {
			try {
				$clientePadre = $this->clientesMapper->findById(
					(int)$cliente['cliente_padre']
				);

				if ($clientePadre) {
					$grupoNombre = $clientePadre['nombre'] ?? '';
				}
			} catch (\Throwable $e) {
				$grupoNombre = '';
			}
		}

		$importeTotal = (float)$honorario['importe_total'];
		$tipoHonorario = $honorario['tipo_honorario'] ?? 'parcial';
		$esIguala = $tipoHonorario === 'iguala';
		$esEventual = $tipoHonorario === 'eventual';

		$textoTipo = $esEventual
			? 'TRABAJOS ESPECIALES'
			: 'FACTURACIÓN DE IGUALAS Y PAGOS EN PARCIALIDADES';

		$numParcialidades = (int)($honorario['numero_parcialidades'] ?? 0);

		$montoParcialidad = $numParcialidades > 0
			? $importeTotal / $numParcialidades
			: $importeTotal;

		$mesesEs = [
			'ENERO',
			'FEBRERO',
			'MARZO',
			'ABRIL',
			'MAYO',
			'JUNIO',
			'JULIO',
			'AGOSTO',
			'SEPTIEMBRE',
			'OCTUBRE',
			'NOVIEMBRE',
			'DICIEMBRE',
		];

		$periodoTxt = '';

		if (!empty($honorario['fecha_inicio'])) {
			$fecha = new \DateTime($honorario['fecha_inicio']);
			$periodoTxt = $mesesEs[(int)$fecha->format('n') - 1] . ' ' . $fecha->format('Y');
		}

		$monedasTxt = [
			'MXN' => 'PESOS',
			'USD' => 'DÓLARES',
			'EUR' => 'EUROS',
		];

		$tipoMoneda = strtoupper((string)($honorario['tipo_moneda'] ?? 'MXN'));
		$monedaTxt = $monedasTxt[$tipoMoneda] ?? $tipoMoneda;

		$liderNombre = '';

		if (!empty($cliente['lider_proyecto'])) {
			$liderNombre = $this->empleadosMapper->getDisplayNameById(
				(int)$cliente['lider_proyecto']
			) ?? '';
		}

		$colaboradoresNombres = [];

		foreach (($cliente['colaboradores'] ?? []) as $idColaborador) {
			$nombre = $this->empleadosMapper->getDisplayNameById((int)$idColaborador);

			if ($nombre) {
				$colaboradoresNombres[] = $nombre;
			}
		}

		$colaboradoresTxt = implode(', ', $colaboradoresNombres);

		$replacements = [
			'{fecha}' => date('d/m/Y'),
			'{departamento}' => $departamento ?? '',

			'{cliente.nombre}' => $cliente['nombre'] ?? '',
			'{cliente.grupo}' => $grupoNombre,
			'{cliente.ubicacion}' => $cliente['ubicacion'] ?? '',
			'{cliente.telefono}' => $cliente['telefono'] ?? '',
			'{cliente.correo}' => $cliente['correo'] ?? '',
			'{cliente.contacto}' => $cliente['nombre_contacto'] ?? '',

			'{honorario.importe}' => number_format($importeTotal, 2, '.', ','),
			'{honorario.moneda}' => $monedaTxt,
			'{tipo_moneda}' => $tipoMoneda,
			'{texto_tipo}' => $textoTipo,
			'{honorario.iguala_mark}' => $esIguala ? 'X' : '',
			'{honorario.parcialidad_mark}' => (!$esIguala && !$esEventual) ? 'X' : '',
			'{honorario.num_parcialidades}' => $esIguala ? '1' : (($numParcialidades > 0) ? (string)$numParcialidades : ''),
			'{honorario.monto_parcialidad}' => $esIguala
				? number_format($importeTotal, 2, '.', ',')
				: number_format(round($montoParcialidad, 2), 2, '.', ','),
			'{honorario.asunto}' => $asunto ?? ($honorario['tipo_servicio'] ?? ''),
			'{honorario.periodo}' => $periodoTxt,

			'{lider}' => $liderNombre,
			'{colaborador}' => $colaboradoresTxt,

			'{gerente_junior.clave}' => $claveGerenteJunior ?? '',
			'{gerente_junior.nombre}' => $nombreGerenteJunior ?? '',
			'{supervisor_senior.clave}' => $claveSupervisorSenior ?? '',
			'{supervisor_senior.nombre}' => $nombreSupervisorSenior ?? '',
			'{supervisor_junior.clave}' => $claveSupervisorJunior ?? '',
			'{supervisor_junior.nombre}' => $nombreSupervisorJunior ?? '',
			'{otro.clave}' => $claveOtro ?? '',
			'{otro.nombre}' => $nombreOtro ?? '',

			'{solicita}' => $quienSolicita ?? '',
			'{autoriza}' => $this->userSession->getUser()?->getDisplayName() ?? '',
			'{gerente}' => $nombreGerente ?? '',
			'{socio}' => $nombreSocio ?? '',
		];

		$filler = new XlsxTemplateFiller($templatePath);
		$logo = $this->logoService->getLogo();
		$contenido = $filler->fill($replacements, $logo);

		$nombreArchivo =
			'Solicitud_Recibo_' .
			preg_replace('/[^A-Za-z0-9_]+/', '_', $cliente['nombre'] ?? 'cliente') .
			'_' .
			date('Y-m-d') .
			'.xlsx';

		return [
			'contenido' => $contenido,
			'nombreArchivo' => $nombreArchivo,
			'cliente' => $cliente,
			'honorario' => $honorario,
		];
	}

	#[UseSession]
	#[NoAdminRequired]
	public function enviarSolicitudRecibo(
		int $id_honorario,
		?string $departamento = null,
		?string $asunto = null,
		?string $quienSolicita = null,
		?string $quienAutoriza = null,
		?string $claveGerenteJunior = null,
		?string $nombreGerenteJunior = null,
		?string $claveSupervisorSenior = null,
		?string $nombreSupervisorSenior = null,
		?string $claveSupervisorJunior = null,
		?string $nombreSupervisorJunior = null,
		?string $claveOtro = null,
		?string $nombreOtro = null,
		?string $nombreGerente = null,
		?string $nombreSocio = null
	): DataResponse {
		$this->requireClientesAdminAccess();

		try {
			$solicitud = $this->construirSolicitud(
				$id_honorario,
				$departamento,
				$asunto,
				$quienSolicita,
				$claveGerenteJunior,
				$nombreGerenteJunior,
				$claveSupervisorSenior,
				$nombreSupervisorSenior,
				$claveSupervisorJunior,
				$nombreSupervisorJunior,
				$claveOtro,
				$nombreOtro,
				$nombreGerente,
				$nombreSocio
			);

			$destinatarios = $this->getHonorariosRecipients();

			if (empty($destinatarios)) {
				return new DataResponse(
					[
						'status' => 'error',
						'message' => 'No hay destinatarios configurados con correo para recibir solicitudes de honorarios. Revisa la configuración de "Groups with access to honorarios".',
					],
					Http::STATUS_BAD_REQUEST
				);
			}

			$asuntoCorreo = 'Nueva solicitud de recibo de facturación'
				. (!empty($solicitud['cliente']['nombre']) ? ' — ' . $solicitud['cliente']['nombre'] : '');

			$cuerpo = "Hola,\n\n"
				. "Se ha generado una nueva solicitud de recibo de facturación.\n\n"
				. "Cliente: " . ($solicitud['cliente']['nombre'] ?? '-') . "\n"
				. "Adjunto encontrarás el archivo con los detalles.\n\n"
				. "Este es un mensaje automático, no respondas a este correo.";

			$attachment = $this->mailer->createAttachment(
				$solicitud['contenido'],
				$solicitud['nombreArchivo'],
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
			);

			$enviados = 0;
			$fallidos = [];

			foreach ($destinatarios as $email => $nombre) {
				try {
					$message = $this->mailer->createMessage();
					$message->setSubject($asuntoCorreo);
					$message->setTo([$email => $nombre]);
					$message->setPlainBody($cuerpo);
					$message->attach($attachment);

					$this->mailer->send($message);
					$enviados++;
				} catch (\Throwable $e) {
					$fallidos[] = $email;
					$this->logger->warning(
						'No se pudo enviar la solicitud de honorario a ' . $email . ': ' . $e->getMessage(),
						['app' => 'empleados']
					);
				}
			}

			// --- Movimiento (bitácora) ---
			if ($enviados > 0) {
				$nombreCliente = $solicitud['cliente']['nombre'] ?? '';
				[$uidActor, $nombreActor] = $this->getActorInfo();

				$mensaje = sprintf(
					'%s ha enviado por correo la solicitud de recibo del honorario del cliente "%s" (%d destinatario(s)).',
					$nombreActor,
					$nombreCliente,
					$enviados
				);

				$this->registrarMovimiento($uidActor, $id_honorario, $nombreCliente, 'envio_solicitud', $mensaje);
			}

			return new DataResponse([
				'status' => 'ok',
				'sent' => $enviados,
				'failed' => $fallidos,
			], Http::STATUS_OK);

		} catch (\Throwable $e) {
			$this->logger->error(
				$e->getMessage(),
				[
					'app' => 'empleados',
					'exception' => $e,
				]
			);

			return new DataResponse(
				[
					'status' => 'error',
					'message' => $e->getMessage(),
				],
				500
			);
		}
	}

		#[UseSession]
	#[NoAdminRequired]
	public function descargarSolicitudesMultiples() {
		$this->requireClientesAdminAccess();

		$ids = $this->request->getParam('ids', []);

		if (!is_array($ids) || empty($ids)) {
			return new DataResponse(
				['status' => 'error', 'message' => 'No se proporcionaron honorarios.'],
				Http::STATUS_BAD_REQUEST
			);
		}

		try {
			$zip = new \ZipArchive();
			$tmpFile = tempnam(sys_get_temp_dir(), 'honorarios_zip_');

			$openResult = $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

			if ($openResult !== true) {
				return new DataResponse(
					[
						'status' => 'error',
						'message' => 'No se pudo abrir el archivo zip temporal.',
						'detalle' => [
							'tmpFile' => $tmpFile,
							'zip_open_code' => $openResult,
							'tmpFile_existe' => file_exists($tmpFile),
							'tmpFile_escribible' => is_writable(dirname($tmpFile)),
						],
					],
					Http::STATUS_BAD_REQUEST
				);
			}

			$errores = [];
			$agregados = 0;

			foreach ($ids as $id) {
				try {
					$solicitud = $this->construirSolicitud(
						(int)$id,
						null, null, null, null, null,
						null, null, null, null, null,
						null, null, null
					);

					$nombreUnico = pathinfo($solicitud['nombreArchivo'], PATHINFO_FILENAME)
						. '_' . $id
						. '.' . pathinfo($solicitud['nombreArchivo'], PATHINFO_EXTENSION);

					$added = $zip->addFromString($nombreUnico, $solicitud['contenido']);

					if ($added === false) {
						$errores[] = "Honorario $id: zip->addFromString devolvió false para " . $solicitud['nombreArchivo'];
					} else {
						$agregados++;
					}
				} catch (\Throwable $e) {
					$errores[] = 'Honorario ' . $id . ': ' . $e->getMessage()
						. ' (' . $e->getFile() . ':' . $e->getLine() . ')';

					$this->logger->warning(
						'No se pudo generar la solicitud del honorario ' . $id . ': ' . $e->getMessage(),
						['app' => 'empleados']
					);
				}
			}

			$closeResult = $zip->close();

			if (filesize($tmpFile) === 0 || $agregados === 0) {
				@unlink($tmpFile);
				return new DataResponse(
					[
						'status' => 'error',
						'message' => 'No se pudo generar ninguna solicitud.',
						'detalle' => $errores,
						'debug' => [
							'ids_recibidos' => $ids,
							'agregados' => $agregados,
							'zip_close_result' => $closeResult,
							'tmpFile_size' => filesize($tmpFile),
							'tmpFile_path' => $tmpFile,
							'tmp_dir_escribible' => is_writable(sys_get_temp_dir()),
						],
					],
					Http::STATUS_BAD_REQUEST
				);
			}

			$contenido = file_get_contents($tmpFile);
			@unlink($tmpFile);

			$nombreZip = 'Solicitudes_Recibo_' . date('Y-m-d_His') . '.zip';

			// --- Movimiento (bitácora) ---
			[$uidActor, $nombreActor] = $this->getActorInfo();

			$mensaje = sprintf(
				'%s ha descargado %d solicitud(es) de recibo en un archivo zip.',
				$nombreActor,
				$agregados
			);

			$this->registrarMovimiento($uidActor, null, null, 'descarga_masiva', $mensaje);

			return new DataDownloadResponse(
				$contenido,
				$nombreZip,
				'application/zip'
			);
		} catch (\Throwable $e) {
			$this->logger->error(
				$e->getMessage(),
				['app' => 'empleados', 'exception' => $e]
			);

			return new DataResponse(
				['status' => 'error', 'message' => $e->getMessage()],
				500
			);
		}
	}

		#[UseSession]
	#[NoAdminRequired]
	public function notificarHonorariosPendientes(): DataResponse {
		$this->requireClientesAdminAccess();

		$ids = $this->request->getParam('ids', []);

		if (!is_array($ids) || empty($ids)) {
			return new DataResponse(
				['status' => 'error', 'message' => 'No se proporcionaron honorarios.'],
				Http::STATUS_BAD_REQUEST
			);
		}

		try {
			$items = [];

			foreach ($ids as $id) {
				$honorario = $this->honorariosMapper->findById((int)$id);

				if (!$honorario) {
					continue;
				}

				$cliente = $this->clientesMapper->findById((int)$honorario['id_cliente']);

				$items[] = [
					'titulo' => $honorario['tipo_servicio'] ?? 'Servicio',
					'cliente' => $cliente['nombre'] ?? '-',
				];
			}

			if (empty($items)) {
				return new DataResponse(
					['status' => 'error', 'message' => 'No se encontraron los honorarios indicados.'],
					Http::STATUS_BAD_REQUEST
				);
			}

			$destinatarios = $this->getHonorariosRecipients();

			if (empty($destinatarios)) {
				return new DataResponse(
					[
						'status' => 'error',
						'message' => 'No hay destinatarios configurados con correo para recibir notificaciones de honorarios.',
					],
					Http::STATUS_BAD_REQUEST
				);
			}

			$asuntoCorreo = 'Honorarios pendientes por revisar (' . count($items) . ')';

			$listaTxt = '';

			foreach ($items as $item) {
				$listaTxt .= '- ' . $item['titulo'] . ' (' . $item['cliente'] . ")\n";
			}

			$cuerpo = "Hola,\n\n"
				. 'Hay ' . count($items) . " honorario(s) pendientes por revisar:\n\n"
				. $listaTxt
				. "\nIngresa al sistema para más detalles.\n\n"
				. 'Este es un mensaje automático, no respondas a este correo.';

			$enviados = 0;
			$fallidos = [];

			foreach ($destinatarios as $email => $nombre) {
				try {
					$message = $this->mailer->createMessage();
					$message->setSubject($asuntoCorreo);
					$message->setTo([$email => $nombre]);
					$message->setPlainBody($cuerpo);

					$this->mailer->send($message);
					$enviados++;
				} catch (\Throwable $e) {
					$fallidos[] = $email;
					$this->logger->warning(
						'No se pudo enviar la notificación de honorarios a ' . $email . ': ' . $e->getMessage(),
						['app' => 'empleados']
					);
				}
			}

			// --- Movimiento (bitácora) ---
			if ($enviados > 0) {
				[$uidActor, $nombreActor] = $this->getActorInfo();

				$mensaje = sprintf(
					'%s ha enviado una notificación de %d honorario(s) pendiente(s) por revisar.',
					$nombreActor,
					count($items)
				);

				$this->registrarMovimiento($uidActor, null, null, 'notificacion_pendientes', $mensaje);
			}

			return new DataResponse([
				'status' => 'ok',
				'sent' => $enviados,
				'failed' => $fallidos,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			$this->logger->error(
				$e->getMessage(),
				['app' => 'empleados', 'exception' => $e]
			);

			return new DataResponse(
				['status' => 'error', 'message' => $e->getMessage()],
				500
			);
		}
	}
	
	/**
	 * Resuelve los correos de los usuarios que pertenecen a alguno de los
	 * grupos configurados
	 *
	 * @return array<string,string> email => nombre para mostrar
	 */
	private function getHonorariosRecipients(): array {
		$groupsCsv = $this->config->getAppValue(Application::APP_ID, 'reportes_honorarios_group', '');
		$groupIds = array_values(array_filter(array_map('trim', explode(',', $groupsCsv))));

		$recipients = [];

		foreach ($groupIds as $gid) {
			$group = $this->groupManager->get($gid);

			if ($group === null) {
				continue;
			}

			foreach ($group->getUsers() as $user) {
				$email = $user->getEMailAddress();

				if ($email) {
					$recipients[strtolower($email)] = $user->getDisplayName() ?: $email;
				}
			}
		}

		return $recipients;
	}
}