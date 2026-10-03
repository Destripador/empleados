<?php

declare(strict_types=1);

use OCA\Empleados\Controller\CompraDocumentoController;
use OCA\Empleados\Service\CompraPermisosService;
use OCA\Empleados\Service\CompraSolicitudService;

require '/var/www/html/lib/base.php';

class DenyPurchaseDocumentPermissions extends CompraPermisosService {
	public function __construct() {
	}

	public function canProcessPurchase(string $userId): bool {
		return false;
	}
}

function assertDetail(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Falló: ' . $name);
	}

	echo 'ok - ', $name, PHP_EOL;
}

$controllerReflection = new ReflectionClass(CompraDocumentoController::class);
$controller = $controllerReflection->newInstanceWithoutConstructor();
$permissionsProperty = $controllerReflection->getProperty('permisosService');
$permissionsProperty->setValue($controller, new DenyPurchaseDocumentPermissions());

$authorizationMethod = $controllerReflection->getMethod('assertCanManageOfficialDocuments');
$unauthorized = false;
try {
	$authorizationMethod->invoke($controller, ['estado' => 'autorizada'], 'sin-permiso');
} catch (ReflectionException $e) {
	throw $e;
} catch (Throwable $e) {
	$unauthorized = str_contains($e->getMessage(), 'permisos');
}
assertDetail($unauthorized, 'documentos oficiales requieren permiso de proceso');

$validationMethod = $controllerReflection->getMethod('validarArchivoFirmado');
$invalidFile = false;
try {
	$validationMethod->invoke($controller, 'archivo.pdf', 'application/pdf', 'contenido inválido', 18);
} catch (ReflectionException $e) {
	throw $e;
} catch (Throwable $e) {
	$invalidFile = str_contains($e->getMessage(), 'contenido');
}
assertDetail($invalidFile, 'archivo firmado inválido es rechazado por contenido');

$serviceReflection = new ReflectionClass(CompraSolicitudService::class);
$service = $serviceReflection->newInstanceWithoutConstructor();
$totalsMethod = $serviceReflection->getMethod('calcularTotales');
$totals = $totalsMethod->invoke($service, [[
	'subtotal' => 200.0,
	'iva' => 32.0,
]], [
	'total_excl_iva' => 1,
	'iva' => 1,
	'total_incl_iva' => 2,
]);
assertDetail(
	$totals === ['total_excl_iva' => 200.0, 'iva' => 32.0, 'total_incl_iva' => 232.0],
	'totales enviados por frontend no sustituyen el cálculo del backend'
);

echo '1..3', PHP_EOL;
