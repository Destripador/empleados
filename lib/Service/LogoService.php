<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;

class LogoService {

	private const FOLDER = 'compras';
	private const FILES = ['logo-documento.png', 'logo-documento.jpg'];

	private IAppData $appData;

	public function __construct(IAppData $appData) {
		$this->appData = $appData;
	}

	/**
	 * @return array{content:string,mime:string}|null
	 */
	public function getLogo(): ?array {
		try {
			$folder = $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException $e) {
			return null;
		}

		foreach (self::FILES as $fileName) {
			if (!$folder->fileExists($fileName)) {
				continue;
			}

			$file = $folder->getFile($fileName);
			$content = $file->getContent();

			if ($content === '') {
				continue;
			}

			$mime = $this->detectMime($content);

			if ($mime === '') {
				continue;
			}

			return ['content' => $content, 'mime' => $mime];
		}

		return null;
	}

	private function detectMime(string $content): string {
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