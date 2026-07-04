<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IL10N;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCA\Empleados\UploadException;

use DateTime;

use OCA\Empleados\Db\tipoausenciaMapper;
use OCA\Empleados\Db\tipoausencia;

require_once 'SimpleXLSXGen.php';
require_once 'SimpleXLSX.php';

/**
 * Controlador para la gestión de tipo de ausencias en Nextcloud.
 */
class TipoausenciasController extends Controller {

    protected $l10n;
    protected $tipoausenciaMapper;

    public function __construct(
        IRequest $request,
        IL10N $l10n,
        tipoausenciaMapper $tipoausenciaMapper,
    ) {
        parent::__construct(Application::APP_ID, $request);
        
        $this->l10n = $l10n;
        $this->tipoausenciaMapper = $tipoausenciaMapper;
    }

    /**
     * Obtiene la lista de tipoausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function getTipo(): array {
        return $this->tipoausenciaMapper->getTipo();
    }

    /**
     * Exporta la lista de tipo de ausencias a un archivo XLSX.
     */
    public function ExportarTipo(): array {
        $tipoausencias = $this->tipoausenciaMapper->getTipo();
        $books = [['nombre', 'descripcion', 'solicitar_archivo', 'solicitar_prima_vacacional']];

        foreach ($tipoausencias as $tipo) {
            $books[] = [
                $tipo['nombre'],
                $tipo['descripcion'],
                $tipo['solicitar_archivo'],
                $tipo['solicitar_prima_vacacional'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('tipoausencias.xlsx');
        return $books;
    }

    /**
     * Importa la lista de tipo de ausencias desde un archivo XLSX.
     */
    public function importarTipo(): void {
        $file = $this->getUploadedFile('fileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                $tipo = new tipoausencia();
                $tipo->setnombre($row[0]);
                $tipo->setdescripcion($row[1]);
                $tipo->setsolicitar_archivo($row[2]);
                $tipo->setsolicitar_prima_vacacional($row[3]);
                $this->tipoausenciaMapper->insert($tipo);
            }
        }
    }
        
    /**
     * Vacia tipo de ausencia.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function VaciarTipo(): string {
        try {
            $this->tipoausenciaMapper->VaciarTipo();
            return "ok";
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Elimina un tipo de ausencia por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarArea(int $id_departamento): string {
        try {
            $this->departamentosMapper->EliminarArea((string) $id_departamento);
            return "ok";
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Guarda cambios en los tipo de ausencia.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarCambioArea(int $id_departamento, string $padre, string $nombre): void {
        $this->departamentosMapper->updateTipoAusencias((string) $id_departamento, $padre, $nombre);
    }

    /**
     * Crea un nuevo tipo de ausencia.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function AgregarNuevoTipo(string $nombre, string $descripcion, int $solicitar_archivo, int $solicitar_prima_vacacional, int $cargable): void {
        $tipo = new tipoausencia();
        $tipo->setnombre($nombre);
        $tipo->setdescripcion($descripcion);
        $tipo->setsolicitar_archivo($solicitar_archivo);
        $tipo->setsolicitar_prima_vacacional($solicitar_prima_vacacional);
        $tipo->setcargable($cargable);
        $this->tipoausenciaMapper->insert($tipo);
    }

    /**
     * Obtiene un archivo subido y maneja posibles errores.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function getUploadedFile(string $key): array {
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del archivo.'));
        }
        return $file;
    }

    /**
     * Obtiene la lista de tipo ausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAniversarioByDate(string $ingreso): array {
        $fechaInicio = new DateTime($ingreso);
        $hoy = new DateTime();
    
        $diferencia = $hoy->diff($fechaInicio);
    
        return $this->tipoausenciaMapper->GetAniversarioByDate($diferencia->y);
    }

    /**
     * Modifica la lista de tipo ausencias.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function ModificarTipo(int $id, string $nombre, string $descripcion, int $solicitar_archivo, int $solicitar_prima_vacacional, int $cargable): DataResponse {
        try {
            $this->tipoausenciaMapper->updateTipoAusencias($id, $nombre, $descripcion, $solicitar_archivo, $solicitar_prima_vacacional, $cargable);
            return new DataResponse('ok', Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
        /**
     * Elimina un tipo de ausencia.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function DeleteTipo(int $id): DataResponse {
        try {
            $this->tipoausenciaMapper->deleteById($id);
            return new DataResponse('ok', Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
