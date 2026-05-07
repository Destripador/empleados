<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use Exception;
use OCA\Empleados\Service\CompraSolicitudService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class CompraSolicitudController extends Controller {

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
	 */
	public function index(): DataResponse {
		try {
			$userId = $this->getUserId();

			$todas = $this->toBool($this->request->getParam('todas', false));
			$limit = (int)$this->request->getParam('limit', 50);
			$offset = (int)$this->request->getParam('offset', 0);

			$limit = max(1, min($limit, 200));
			$offset = max(0, $offset);

			return new DataResponse([
				'success' => true,
				'data' => $this->service->listar($userId, $todas, $limit, $offset),
			]);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function pendientes(): DataResponse {
		try {
			$userId = $this->getUserId();

			$limit = (int)$this->request->getParam('limit', 100);
			$offset = (int)$this->request->getParam('offset', 0);

			$limit = max(1, min($limit, 200));
			$offset = max(0, $offset);

			return new DataResponse([
				'success' => true,
				'data' => $this->service->listarPendientesAutorizacion($userId, $limit, $offset),
			]);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function show(int $id): DataResponse {
		try {
			return new DataResponse([
				'success' => true,
				'data' => $this->service->obtenerDetalle($id, $this->getUserId()),
			]);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(): DataResponse {
		try {
			return new DataResponse([
				'success' => true,
				'data' => $this->service->crear($this->getPayload(), $this->getUserId()),
			], Http::STATUS_CREATED);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	* @NoAdminRequired
	*/
	public function update(int $id): DataResponse {
		try {
			$payload = $this->getPayload();

			return new DataResponse([
				'success' => true,
				'data' => $this->service->actualizar(
					$id,
					$payload,
					$this->getUserId()
				),
			]);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function sendToApproval(int $id): DataResponse {
		try {
			return new DataResponse([
				'success' => true,
				'data' => $this->service->enviarAutorizacion($id, $this->getUserId()),
			]);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function approve(int $id): DataResponse {
		try {
			$payload = $this->getPayload();

			return new DataResponse([
				'success' => true,
				'data' => $this->service->autorizar(
					$id,
					$this->getUserId(),
					isset($payload['comentario']) ? (string)$payload['comentario'] : null
				),
			]);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function reject(int $id): DataResponse {
		try {
			$payload = $this->getPayload();

			return new DataResponse([
				'success' => true,
				'data' => $this->service->rechazar(
					$id,
					$this->getUserId(),
					isset($payload['comentario']) ? (string)$payload['comentario'] : null
				),
			]);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}

	private function getUserId(): string {
		$user = $this->userSession->getUser();

		if ($user === null) {
			throw new Exception('Usuario no autenticado.');
		}

		return $user->getUID();
	}

	private function getPayload(): array {
		$params = $this->request->getParams();

		unset($params['_route']);
		unset($params['id']);

		return $params;
	}

	private function toBool($value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'true', 'yes', 'si', 'sí'], true);
		}

		return (bool)$value;
	}

	private function errorResponse(Exception $e): DataResponse {
		return new DataResponse([
			'success' => false,
			'message' => $e->getMessage(),
		], Http::STATUS_BAD_REQUEST);
	}

	/**
	* @NoAdminRequired
	*/
	public function cancel(int $id): DataResponse {
		try {
			$payload = $this->getPayload();

			return new DataResponse([
				'success' => true,
				'data' => $this->service->cancelar(
					$id,
					$this->getUserId(),
					isset($payload['comentario']) ? (string)$payload['comentario'] : null
				),
			]);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}
	/**
	* @NoAdminRequired
	*/
	public function context(): DataResponse {
		try {
			return new DataResponse([
				'success' => true,
				'data' => $this->service->contexto($this->getUserId()),
			]);
		} catch (Exception $e) {
			return $this->errorResponse($e);
		}
	}
}
