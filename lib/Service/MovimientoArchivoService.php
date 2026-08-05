<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use InvalidArgumentException;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\MovimientoArchivo;
use OCA\Empleados\Db\MovimientoArchivoMapper;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

class MovimientoArchivoService {
	public const EVENTO_CREADO = 'creado';
	public const EVENTO_MODIFICADO = 'modificado';
	public const EVENTO_MOVIDO = 'movido';
	public const EVENTO_COPIADO = 'copiado';
	public const EVENTO_ELIMINADO = 'eliminado';

	private const EVENTOS_VALIDOS = [
		self::EVENTO_CREADO,
		self::EVENTO_MODIFICADO,
		self::EVENTO_MOVIDO,
		self::EVENTO_COPIADO,
		self::EVENTO_ELIMINADO,
	];

	public function __construct(
		private MovimientoArchivoMapper $movimientoArchivoMapper,
		private empleadosMapper $empleadosMapper,
		private IUserSession $userSession,
		private IRequest $request,
	) {
	}

	public function registrarMovimiento(string $tipoEvento, ?Node $nodoActual, ?Node $nodoAnterior = null): void {
		if (!in_array($tipoEvento, self::EVENTOS_VALIDOS, true)) {
			throw new InvalidArgumentException('Tipo de movimiento de archivo no soportado');
		}

		$usuario = $this->userSession->getUser();
		if ($usuario === null) {
			return;
		}

		$uidActor = $usuario->getUID();
		$actual = $this->leerNodo($nodoActual);
		$anterior = $this->leerNodo($nodoAnterior);
		if ($this->esOperacionTecnica($actual) || $this->esOperacionTecnica($anterior)) {
			return;
		}

		$referencia = $actual ?? $anterior;
		if ($referencia === null) {
			return;
		}

		$movimiento = new MovimientoArchivo();
		$movimiento->setIdEmpleado($this->resolverIdEmpleado($uidActor));
		$movimiento->setUidActor($uidActor);
		$movimiento->setTipoEvento($tipoEvento);
		$movimiento->setFileId($referencia['file_id']);
		$movimiento->setStorageId($referencia['storage_id']);
		$movimiento->setRutaAnterior($anterior['ruta'] ?? null);
		$movimiento->setRutaActual($actual['ruta'] ?? null);
		$movimiento->setNombreArchivo($referencia['nombre']);
		$movimiento->setMimeType($referencia['mime_type']);
		$movimiento->setTamanio($referencia['tamanio']);
		$movimiento->setEsCarpeta($referencia['es_carpeta']);
		$movimiento->setFechaEvento(date('Y-m-d H:i:s'));
		[$remoteAddr, $userAgent] = $this->obtenerContextoHttp();
		$movimiento->setRemoteAddr($remoteAddr);
		$movimiento->setUserAgent($userAgent);

		$this->movimientoArchivoMapper->insert($movimiento);
	}

	/**
	 * @return array{file_id: ?int, storage_id: ?string, ruta: ?string, nombre: ?string, mime_type: ?string, tamanio: ?int, es_carpeta: bool}|null
	 */
	private function leerNodo(?Node $nodo): ?array {
		if ($nodo === null) {
			return null;
		}

		$tipo = $this->intentar(static fn() => $nodo->getType());
		$esCarpeta = $tipo === FileInfo::TYPE_FOLDER;
		$tamanio = $esCarpeta ? null : $this->intentar(static fn() => (int)$nodo->getSize(false));
		$storageId = $this->intentar(static fn() => $nodo->getStorage()->getId());

		return [
			'file_id' => $this->intentar(static fn() => $nodo->getId()),
			'storage_id' => is_string($storageId) ? $this->limitar($storageId, 255) : null,
			'ruta' => $this->normalizarTexto($this->intentar(static fn() => $nodo->getPath())),
			'nombre' => $this->limitar($this->normalizarTexto($this->intentar(static fn() => $nodo->getName())), 255),
			'mime_type' => $this->limitar($this->normalizarTexto($this->intentar(static fn() => $nodo->getMimetype())), 255),
			'tamanio' => is_int($tamanio) && $tamanio >= 0 ? $tamanio : null,
			'es_carpeta' => $esCarpeta,
		];
	}

	/** @param array{storage_id: ?string, ruta: ?string, nombre: ?string}|null $nodo */
	private function esOperacionTecnica(?array $nodo): bool {
		if ($nodo === null) {
			return false;
		}

		$ruta = ltrim(strtolower(str_replace('\\', '/', (string)$nodo['ruta'])), '/');
		if (preg_match('~^(?:appdata_[^/]+|[^/]+/(?:files_versions|files_trashbin|files_encryption))(?:/|$)~', $ruta) === 1) {
			return true;
		}

		$storageId = strtolower((string)$nodo['storage_id']);
		foreach (['appdata_', 'files_versions', 'files_trashbin', 'files_encryption'] as $namespaceTecnico) {
			if (str_contains($storageId, $namespaceTecnico)) {
				return true;
			}
		}

		$nombre = strtolower((string)$nodo['nombre']);
		return $nombre !== '' && (
			preg_match('/\.(?:part|filepart)$/i', $nombre) === 1
			|| str_starts_with($nombre, '.octransferid')
			|| str_starts_with($nombre, '.~lock.')
			|| str_starts_with($nombre, '.nfs')
		);
	}

	private function resolverIdEmpleado(string $uid): ?int {
		$empleados = $this->empleadosMapper->GetMyEmployeeInfo($uid);
		$idEmpleado = $empleados[0]['Id_empleados'] ?? $empleados[0]['id_empleados'] ?? null;
		if (!is_numeric($idEmpleado) || (int)$idEmpleado <= 0) {
			return null;
		}

		return (int)$idEmpleado;
	}

	/** @return array{?string, ?string} */
	private function obtenerContextoHttp(): array {
		if (PHP_SAPI === 'cli') {
			return [null, null];
		}

		$remoteAddr = $this->normalizarTexto($this->intentar(fn() => $this->request->getRemoteAddress()));
		$userAgent = $this->normalizarTexto($this->intentar(fn() => $this->request->getHeader('User-Agent')));

		return [$this->limitar($remoteAddr, 45), $this->limitar($userAgent, 512)];
	}

	private function intentar(callable $operacion): mixed {
		try {
			return $operacion();
		} catch (Throwable) {
			return null;
		}
	}

	private function normalizarTexto(mixed $valor): ?string {
		if (!is_string($valor)) {
			return null;
		}

		$valor = trim($valor);
		return $valor === '' ? null : $valor;
	}

	private function limitar(?string $valor, int $longitud): ?string {
		if ($valor === null) {
			return null;
		}

		return function_exists('mb_substr') ? mb_substr($valor, 0, $longitud) : substr($valor, 0, $longitud);
	}
}
