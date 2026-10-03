<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\clientesMapper;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;

class ClienteLogoService {
	public const MAX_BYTES = 2 * 1024 * 1024;
	public const MAX_SIDE = 512;
	public const FOLDER = 'clientes';

	private const MIME_PNG = 'image/png';
	private const MIME_JPEG = 'image/jpeg';
	private const MIME_WEBP = 'image/webp';

	private const EXT_BY_MIME = [
		self::MIME_PNG => 'png',
		self::MIME_JPEG => 'jpg',
		self::MIME_WEBP => 'webp',
	];

	private IAppData $appData;
	private clientesMapper $clientesMapper;

	public function __construct(IAppData $appData, clientesMapper $clientesMapper) {
		$this->appData = $appData;
		$this->clientesMapper = $clientesMapper;
	}

	public static function allowedMimes(): array {
		return [self::MIME_PNG, self::MIME_JPEG, self::MIME_WEBP];
	}

	public static function detectMime(string $content): string {
		if (strncmp($content, "\x89PNG", 4) === 0) {
			return self::MIME_PNG;
		}

		if (strncmp($content, "\xFF\xD8\xFF", 3) === 0) {
			return self::MIME_JPEG;
		}

		if (strlen($content) >= 12
			&& strncmp($content, 'RIFF', 4) === 0
			&& substr($content, 8, 4) === 'WEBP'
		) {
			return self::MIME_WEBP;
		}

		if (function_exists('finfo_buffer')) {
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			$mime = (string)$finfo->buffer($content);
			if (in_array($mime, self::allowedMimes(), true)) {
				return $mime;
			}
		}

		return '';
	}

	/**
	 * @return array{content:string,mime:string}|null
	 */
	public function getLogo(int $idCliente): ?array {
		$cliente = $this->clientesMapper->findById($idCliente);
		if ($cliente === []) {
			return null;
		}

		$ext = $this->normalizeStoredExt($cliente['logo'] ?? null);
		if ($ext === null) {
			return $this->findExistingFile($idCliente);
		}

		try {
			$folder = $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			return null;
		}

		$fileName = $this->fileName($idCliente, $ext);
		if (!$folder->fileExists($fileName)) {
			return $this->findExistingFile($idCliente);
		}

		$content = $folder->getFile($fileName)->getContent();
		if ($content === '') {
			return null;
		}

		$mime = self::detectMime($content);
		if ($mime === '') {
			return null;
		}

		return ['content' => $content, 'mime' => $mime];
	}

	public function saveLogo(int $idCliente, string $content, int $reportedSize = 0): string {
		if ($this->clientesMapper->findById($idCliente) === []) {
			throw new \InvalidArgumentException('Cliente no encontrado.');
		}

		$size = $reportedSize > 0 ? $reportedSize : strlen($content);
		if ($size <= 0 || $content === '') {
			throw new \InvalidArgumentException('El archivo está vacío.');
		}

		if ($size > self::MAX_BYTES) {
			throw new \InvalidArgumentException('El logo no debe pesar más de 2 MB.');
		}

		$mime = self::detectMime($content);
		if (!in_array($mime, self::allowedMimes(), true)) {
			throw new \InvalidArgumentException('Solo se permiten logos PNG, JPEG o WebP.');
		}

		$normalized = $this->normalizeImage($content, $mime);
		$ext = self::EXT_BY_MIME[$normalized['mime']];
		$folder = $this->getOrCreateFolder();
		$this->deleteLogoFiles($folder, $idCliente);

		$fileName = $this->fileName($idCliente, $ext);
		if ($folder->fileExists($fileName)) {
			$folder->getFile($fileName)->putContent($normalized['content']);
		} else {
			$folder->newFile($fileName, $normalized['content']);
		}

		$this->clientesMapper->updateLogo($idCliente, $ext);

		return $ext;
	}

	public function deleteLogo(int $idCliente): void {
		try {
			$folder = $this->appData->getFolder(self::FOLDER);
			$this->deleteLogoFiles($folder, $idCliente);
		} catch (NotFoundException $e) {
			// Sin carpeta no hay archivo que borrar.
		}

		$cliente = $this->clientesMapper->findById($idCliente);
		if ($cliente !== []) {
			$this->clientesMapper->updateLogo($idCliente, null);
		}
	}

	/**
	 * @return array{content:string,mime:string}
	 */
	private function normalizeImage(string $content, string $mime): array {
		if (!function_exists('imagecreatefromstring')) {
			return ['content' => $content, 'mime' => $mime];
		}

		$image = @imagecreatefromstring($content);
		if ($image === false) {
			throw new \InvalidArgumentException('El archivo de imagen está dañado o no es válido.');
		}

		$width = imagesx($image);
		$height = imagesy($image);
		if ($width < 1 || $height < 1) {
			imagedestroy($image);
			throw new \InvalidArgumentException('El archivo de imagen está dañado o no es válido.');
		}

		$max = max($width, $height);
		if ($max > self::MAX_SIDE) {
			$scale = self::MAX_SIDE / $max;
			$newWidth = max(1, (int)round($width * $scale));
			$newHeight = max(1, (int)round($height * $scale));
			$resized = imagecreatetruecolor($newWidth, $newHeight);
			if ($resized === false) {
				imagedestroy($image);
				throw new \InvalidArgumentException('No se pudo procesar el logo.');
			}

			imagealphablending($resized, false);
			imagesavealpha($resized, true);
			$transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
			imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
			imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
			imagedestroy($image);
			$image = $resized;
		}

		ob_start();
		$encodedMime = $mime;

		if ($mime === self::MIME_JPEG) {
			imagejpeg($image, null, 88);
		} elseif ($mime === self::MIME_WEBP && function_exists('imagewebp')) {
			imagewebp($image, null, 88);
		} else {
			imagesavealpha($image, true);
			imagepng($image, null, 6);
			$encodedMime = self::MIME_PNG;
		}

		imagedestroy($image);
		$encoded = (string)ob_get_clean();

		if ($encoded === '') {
			throw new \InvalidArgumentException('No se pudo procesar el logo.');
		}

		return ['content' => $encoded, 'mime' => $encodedMime];
	}

	private function getOrCreateFolder() {
		try {
			return $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			return $this->appData->newFolder(self::FOLDER);
		}
	}

	/**
	 * @return array{content:string,mime:string}|null
	 */
	private function findExistingFile(int $idCliente): ?array {
		try {
			$folder = $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			return null;
		}

		foreach (['png', 'jpg', 'webp'] as $ext) {
			$fileName = $this->fileName($idCliente, $ext);
			if (!$folder->fileExists($fileName)) {
				continue;
			}

			$content = $folder->getFile($fileName)->getContent();
			if ($content === '') {
				continue;
			}

			$mime = self::detectMime($content);
			if ($mime === '') {
				continue;
			}

			return ['content' => $content, 'mime' => $mime];
		}

		return null;
	}

	private function deleteLogoFiles($folder, int $idCliente): void {
		foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
			$fileName = $this->fileName($idCliente, $ext);
			if ($folder->fileExists($fileName)) {
				$folder->getFile($fileName)->delete();
			}
		}
	}

	private function fileName(int $idCliente, string $ext): string {
		return $idCliente . '.' . $ext;
	}

	private function normalizeStoredExt(?string $logo): ?string {
		$logo = strtolower(trim((string)$logo));
		if ($logo === '') {
			return null;
		}

		if (in_array($logo, ['png', 'jpg', 'jpeg', 'webp'], true)) {
			return $logo === 'jpeg' ? 'jpg' : $logo;
		}

		$ext = strtolower((string)pathinfo($logo, PATHINFO_EXTENSION));
		if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
			return $ext === 'jpeg' ? 'jpg' : $ext;
		}

		return null;
	}
}
