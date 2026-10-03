<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class CompraSolicitud extends Entity implements JsonSerializable {

	protected $idSolicitud;
	protected $folio;
	protected $idUser;
	protected $idEmpleado;
	protected $idDepartamento;
	protected $idEquipo;
	protected $idCliente;
	protected $titulo;
	protected $descripcion;
	protected $justificacion;
	protected $montoEstimado;
	protected $montoFinal;
	protected $moneda;
	protected $prioridad;
	protected $estado;
	protected $fechaRequerida;
	protected $fechaEnvio;
	protected $fechaAutorizacion;
	protected $fechaCierre;
	protected $proveedorSeleccionado;
	protected $createdAt;
	protected $updatedAt;
	protected $createdBy;
	protected $updatedBy;

	protected $solicitanteNombre;
	protected $solicitanteDepto;
	protected $solicitanteCargo;
	protected $jefeDirectoNombre;
	protected $tipoCompra;
	protected $garantia;
	protected $usoCompra;
	protected $informacion;
	protected $motivo;
	protected $proveedorNombre;
	protected $atencion;
	protected $entrega;
	protected $marcaModelo;
	protected $especificaciones;
	protected $comentariosReq;
	protected $oficinaPct;
	protected $empleadoPct;
	protected $tipoPago;
	protected $quincenas;
	protected $totalExclIva;
	protected $iva;
	protected $totalInclIva;
	protected $comentariosAdmin;
	protected $pdfFileId;
	protected $pdfNombre;
	protected $pdfGeneradoAt;

	protected $firmadoFileId;
	protected $firmadoNombre;
	protected $firmadoMime;
	protected $firmadoSubidoAt;
	protected $firmadoSubidoBy;

	public function __construct() {
		$this->addType('idSolicitud', 'integer');
		$this->addType('idCliente', 'integer');
		$this->addType('proveedorSeleccionado', 'integer');
		$this->addType('garantia', 'integer');
		$this->addType('quincenas', 'integer');
		$this->addType('pdfFileId', 'integer');
		$this->addType('firmadoFileId', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id_solicitud' => $this->idSolicitud,
			'folio' => $this->folio,
			'id_user' => $this->idUser,
			'id_empleado' => $this->idEmpleado,
			'id_departamento' => $this->idDepartamento,
			'id_equipo' => $this->idEquipo,
			'id_cliente' => $this->idCliente,
			'titulo' => $this->titulo,
			'descripcion' => $this->descripcion,
			'justificacion' => $this->justificacion,
			'monto_estimado' => $this->montoEstimado,
			'monto_final' => $this->montoFinal,
			'moneda' => $this->moneda,
			'prioridad' => $this->prioridad,
			'estado' => $this->estado,
			'fecha_requerida' => $this->fechaRequerida,
			'fecha_envio' => $this->fechaEnvio,
			'fecha_autorizacion' => $this->fechaAutorizacion,
			'fecha_cierre' => $this->fechaCierre,
			'proveedor_seleccionado' => $this->proveedorSeleccionado,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
			'created_by' => $this->createdBy,
			'updated_by' => $this->updatedBy,

			'solicitante_nombre' => $this->solicitanteNombre,
			'solicitante_depto' => $this->solicitanteDepto,
			'solicitante_cargo' => $this->solicitanteCargo,
			'jefe_directo_nombre' => $this->jefeDirectoNombre,
			'tipo_compra' => $this->tipoCompra,
			'garantia' => $this->garantia,
			'uso_compra' => $this->usoCompra,
			'informacion' => $this->informacion,
			'motivo' => $this->motivo,
			'proveedor_nombre' => $this->proveedorNombre,
			'atencion' => $this->atencion,
			'entrega' => $this->entrega,
			'marca_modelo' => $this->marcaModelo,
			'especificaciones' => $this->especificaciones,
			'comentarios_req' => $this->comentariosReq,
			'oficina_pct' => $this->oficinaPct,
			'empleado_pct' => $this->empleadoPct,
			'tipo_pago' => $this->tipoPago,
			'quincenas' => $this->quincenas,
			'total_excl_iva' => $this->totalExclIva,
			'iva' => $this->iva,
			'total_incl_iva' => $this->totalInclIva,
			'comentarios_admin' => $this->comentariosAdmin,
			'pdf_file_id' => $this->pdfFileId,
			'pdf_nombre' => $this->pdfNombre,
			'pdf_generado_at' => $this->pdfGeneradoAt,

			'firmado_file_id' => $this->firmadoFileId,
			'firmado_nombre' => $this->firmadoNombre,
			'firmado_mime' => $this->firmadoMime,
			'firmado_subido_at' => $this->firmadoSubidoAt,
			'firmado_subido_by' => $this->firmadoSubidoBy,
		];
	}
}
