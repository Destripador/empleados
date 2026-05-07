<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\Db\CompraSolicitudMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class CompraFolioService {

	private CompraSolicitudMapper $solicitudMapper;

	public function __construct(CompraSolicitudMapper $solicitudMapper) {
		$this->solicitudMapper = $solicitudMapper;
	}

	public function generarFolio(): string {
		for ($i = 0; $i < 10; $i++) {
			$folio = sprintf(
				'COMP-%s-%s',
				date('Ymd-His'),
				random_int(1000, 9999)
			);

			try {
				$this->solicitudMapper->findByFolio($folio);
			} catch (DoesNotExistException $e) {
				return $folio;
			}
		}

		return sprintf(
			'COMP-%s-%s',
			date('Ymd-His'),
			random_int(100000, 999999)
		);
	}
}
