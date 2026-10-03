<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Service\ClienteLogoService;
use OCA\Empleados\Service\PermisosService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

class ClienteLogoController extends Controller {

	private ClienteLogoService $logoService;
	private PermisosService $permisosService;
	private IL10N $l10n;

	public function __construct(
		IRequest $request,
		ClienteLogoService $logoService,
		PermisosService $permisosService,
		IL10N $l10n
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->logoService = $logoService;
		$this->permisosService = $permisosService;
		$this->l10n = $l10n;
	}

	#[UseSession]
	#[NoAdminRequired]
	public function upload(int $id): DataResponse {
		$this->permisosService->requireCanSee('clientes.admin');

		try {
			$file = $this->request->getUploadedFile('logo');
			if (!is_array($file) || empty($file['tmp_name'])) {
				throw new \InvalidArgumentException($this->l10n->t('No file was received.'));
			}

			if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
				throw new \InvalidArgumentException($this->l10n->t('Error uploading the file.'));
			}

			$content = file_get_contents((string)$file['tmp_name']);
			if ($content === false) {
				throw new \InvalidArgumentException($this->l10n->t('The file could not be read.'));
			}

			$ext = $this->logoService->saveLogo(
				$id,
				$content,
				(int)($file['size'] ?? strlen($content))
			);

			return new DataResponse([
				'success' => true,
				'message' => $this->l10n->t('Logo saved successfully.'),
				'data' => [
					'id' => $id,
					'logo' => $ext,
				],
			]);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse([
				'success' => false,
				'message' => $e->getMessage(),
			], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return new DataResponse([
				'success' => false,
				'message' => $this->l10n->t('The logo could not be saved.'),
			], Http::STATUS_BAD_REQUEST);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function show(int $id): DataDisplayResponse {
		try {
			$this->permisosService->requireCanSee('clientes');
		} catch (\Throwable $e) {
			return new DataDisplayResponse(
				$this->l10n->t('You do not have permission to view this logo.'),
				Http::STATUS_FORBIDDEN,
				['Content-Type' => 'text/plain; charset=utf-8']
			);
		}

		$logo = $this->logoService->getLogo($id);
		if ($logo === null) {
			return new DataDisplayResponse(
				$this->l10n->t('No logo configured.'),
				Http::STATUS_NOT_FOUND,
				['Content-Type' => 'text/plain; charset=utf-8']
			);
		}

		return new DataDisplayResponse(
			$logo['content'],
			Http::STATUS_OK,
			[
				'Content-Type' => $logo['mime'],
				'Cache-Control' => 'no-store, no-cache, must-revalidate',
				'Pragma' => 'no-cache',
			]
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function delete(int $id): DataResponse {
		$this->permisosService->requireCanSee('clientes.admin');

		try {
			$this->logoService->deleteLogo($id);

			return new DataResponse([
				'success' => true,
				'message' => $this->l10n->t('Logo deleted successfully.'),
			]);
		} catch (\Throwable $e) {
			return new DataResponse([
				'success' => false,
				'message' => $this->l10n->t('The logo could not be deleted.'),
			], Http::STATUS_BAD_REQUEST);
		}
	}
}
