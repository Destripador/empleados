<?php

declare(strict_types=1);

namespace OCA\Empleados\Controller;

use Exception;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IRequest;

class CompraLogoController extends Controller {

	private IAppData $appData;

	private const FOLDER = 'compras';
	private const LOGO_PNG = 'logo-documento.png';
	private const LOGO_JPG = 'logo-documento.jpg';

	public function __construct(
		string $appName,
		IRequest $request,
		IAppData $appData
	) {
		parent::__construct($appName, $request);

		$this->appData = $appData;
	}

	/**
	 * Admin only.
	 */
	public function upload(): DataResponse {
		try {
			$file = $this->request->getUploadedFile('logo');

			if (!is_array($file) || empty($file['tmp_name'])) {
				throw new Exception('No se recibió ningún archivo.');
			}

			if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
				throw new Exception('Error al subir el archivo.');
			}

			$tmpName = (string)$file['tmp_name'];
			$size = (int)($file['size'] ?? 0);

			if ($size <= 0) {
				throw new Exception('El archivo está vacío.');
			}

			if ($size > 2 * 1024 * 1024) {
				throw new Exception('El logo no debe pesar más de 2 MB.');
			}

			$content = file_get_contents($tmpName);

			if ($content === false || $content === '') {
				throw new Exception('No se pudo leer el archivo.');
			}

			$mime = $this->detectImageMime($content);

			if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
				throw new Exception('Solo se permiten logos PNG o JPG.');
			}

			$targetName = $mime === 'image/png' ? self::LOGO_PNG : self::LOGO_JPG;

			$folder = $this->getOrCreateFolder();

			$this->deleteLogoFiles($folder);

			if ($folder->fileExists($targetName)) {
				$folder->getFile($targetName)->putContent($content);
			} else {
				$folder->newFile($targetName, $content);
			}

			return new DataResponse([
				'success' => true,
				'message' => 'Logo guardado correctamente.',
				'data' => [
					'file_name' => $targetName,
					'mime' => $mime,
				],
			]);
		} catch (\Throwable $e) {
			return new DataResponse([
				'success' => false,
				'message' => 'No se pudo guardar el logo: ' . $e->getMessage(),
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Admin only.
	 */
	public function show(): DataDisplayResponse {
		try {
			$logo = $this->getLogoContent();

			if ($logo === null) {
				return new DataDisplayResponse(
					'No hay logo configurado.',
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
		} catch (\Throwable $e) {
			return new DataDisplayResponse(
				'No se pudo abrir el logo: ' . $e->getMessage(),
				Http::STATUS_BAD_REQUEST,
				['Content-Type' => 'text/plain; charset=utf-8']
			);
		}
	}

	/**
	 * Admin only.
	 */
	public function delete(): DataResponse {
		try {
			$folder = $this->getOrCreateFolder();
			$this->deleteLogoFiles($folder);

			return new DataResponse([
				'success' => true,
				'message' => 'Logo eliminado correctamente.',
			]);
		} catch (\Throwable $e) {
			return new DataResponse([
				'success' => false,
				'message' => 'No se pudo eliminar el logo: ' . $e->getMessage(),
			], Http::STATUS_BAD_REQUEST);
		}
	}

	private function getOrCreateFolder() {
		try {
			return $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder(self::FOLDER);
		}
	}

	private function getLogoContent(): ?array {
		try {
			$folder = $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			return null;
		}

		foreach ([self::LOGO_PNG, self::LOGO_JPG] as $fileName) {
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

			return [
				'content' => $content,
				'mime' => $mime,
			];
		}

		return null;
	}

	private function deleteLogoFiles($folder): void {
		foreach ([self::LOGO_PNG, self::LOGO_JPG] as $fileName) {
			if ($folder->fileExists($fileName)) {
				$folder->getFile($fileName)->delete();
			}
		}
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

			if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
				return $mime;
			}
		}

		return '';
	}
}