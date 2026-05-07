<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Luis Angel Alvarado Hernandez <luis.alvarado@crowe.mx>
// SPDX-License-Identifier: AGPL-3.0-or-later

return [
'routes' => [
		/********************************** INDEX **********************************************/ 
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		

		/******************************** EMPLEADOS ********************************************/ 
		# OBTENER DATOS DE USUARIO NEXTCLOUD
		['name' => 'empleados#GetUser', 'url' => '/GetUser', 'verb' => 'GET'],
		
		# OBTIENE LA LISTA DE EMPĹEADOS, USUARIOS Y USUARIOS DESACTIVADOS
		['name' => 'empleados#GetUserLists', 'url' => '/GetUserLists', 'verb' => 'GET'],
		
		# LISTADO COMPLETO DE EMPLEADOS CON SUS DATOS
		['name' => 'empleados#GetEmpleadosList', 'url' => '/GetEmpleadosList', 'verb' => 'GET'],
		
		# LISTADO DE EMPLEADOS POR AREA
		['name' => 'empleados#GetEmpleadosArea', 'url' => '/GetEmpleadosArea/{id_area}', 'verb' => 'GET'],
		
		# LISTADO DE EMPLEADOS POR PUESTO
		['name' => 'empleados#GetEmpleadosPuesto', 'url' => '/GetEmpleadosPuesto/{id_puesto}', 'verb' => 'GET'],
		
		# LISTADO DE EMPLEADOS POR EQUIPO
		['name' => 'empleados#GetEmpleadosEquipo', 'url' => '/GetEmpleadosEquipo/{id_equipo}', 'verb' => 'GET'],
		
		# EN DESUSO
		['name' => 'empleados#GetEmpleadosListFix', 'url' => '/GetEmpleadosListFix', 'verb' => 'GET'],
		
		# EXPORTA LISTA DE EMPLEADOS A EXCEL
		['name' => 'empleados#ExportListEmpleados', 'url' => '/ExportListEmpleados', 'verb' => 'GET'],
		
		# LISTADO DE EMPLEADOS EN EQUIPO DEL USUARIO ACTUAL
		['name' => 'empleados#GetMyEquipo', 'url' => '/GetMyEquipo', 'verb' => 'GET'],

		['name' => 'empleados#uploadAvatar', 'url' => '/uploadAvatar', 'verb' => 'POST'],
		
		['name' => 'empleados#GuardarNota', 'url' => '/GuardarNota', 'verb' => 'POST'],
		['name' => 'empleados#CambiosEmpleado', 'url' => '/CambiosEmpleado', 'verb' => 'POST'],
		['name' => 'empleados#CambiosPersonal', 'url' => '/CambiosPersonal', 'verb' => 'POST'],
		['name' => 'empleados#ActivarEmpleado', 'url' => '/ActivarEmpleado', 'verb' => 'POST'],
		['name' => 'empleados#ActivarUsuario', 'url' => '/ActivarUsuario', 'verb' => 'POST'],
		['name' => 'empleados#EliminarEmpleado', 'url' => '/EliminarEmpleado', 'verb' => 'POST'],
		['name' => 'empleados#DesactivarEmpleado', 'url' => '/DesactivarEmpleado', 'verb' => 'POST'],
		['name' => 'empleados#ImportListEmpleados', 'url' => '/ImportListEmpleados', 'verb' => 'POST'],
		['name' => 'empleados#ActualizarEstadoAhorro', 'url' => '/ActualizarEstadoAhorro', 'verb' => 'POST'],


		/******************************** AREAS ********************************************/ 
		['name' => 'areas#GetAreasFix', 'url' => '/GetAreasFix', 'verb' => 'GET'],
		['name' => 'areas#GetAreasList', 'url' => '/GetAreasList', 'verb' => 'GET'],
		['name' => 'areas#ExportListAreas', 'url' => '/ExportListAreas', 'verb' => 'GET'],

		['name' => 'areas#GuardarCambioArea', 'url' => '/GuardarCambioArea', 'verb' => 'POST'],
		['name' => 'areas#ImportListAreas', 'url' => '/ImportListAreas', 'verb' => 'POST'],
		['name' => 'areas#EliminarArea', 'url' => '/EliminarArea', 'verb' => 'POST'],
		['name' => 'areas#crearArea', 'url' => '/crearArea', 'verb' => 'POST'],


		/****************************** PUESTOS *********************************************/
		['name' => 'puestos#GetPuestosFix', 'url' => '/GetPuestosFix', 'verb' => 'GET'],
		['name' => 'puestos#GetPuestosList', 'url' => '/GetPuestosList', 'verb' => 'GET'],
		['name' => 'puestos#ExportListPuestos', 'url' => '/ExportListPuestos', 'verb' => 'GET'],
		
		['name' => 'puestos#GuardarCambioPuestos', 'url' => '/GuardarCambioPuestos', 'verb' => 'POST'],
		['name' => 'puestos#ImportListPuestos', 'url' => '/ImportListPuestos', 'verb' => 'POST'],
		['name' => 'puestos#EliminarPuesto', 'url' => '/EliminarPuesto', 'verb' => 'POST'],
		['name' => 'puestos#crearPuesto', 'url' => '/crearPuesto', 'verb' => 'POST'],

		
		/****************************** EQUIPOS *********************************************/
		['name' => 'equipos#GetEquiposFix', 'url' => '/GetEquiposFix', 'verb' => 'GET'],
		['name' => 'equipos#GetEquiposList', 'url' => '/GetEquiposList', 'verb' => 'GET'],
		['name' => 'equipos#ExportListEquipos', 'url' => '/ExportListEquipos', 'verb' => 'GET'],
				
		['name' => 'equipos#GuardarCambioEquipo', 'url' => '/GuardarCambioEquipo', 'verb' => 'POST'],
		['name' => 'equipos#ImportListEquipos', 'url' => '/ImportListEquipos', 'verb' => 'POST'],
		['name' => 'equipos#EliminarEquipo', 'url' => '/EliminarEquipo', 'verb' => 'POST'],
		['name' => 'equipos#GetEquipoJefe', 'url' => '/GetEquipoJefe', 'verb' => 'POST'],
		['name' => 'equipos#crearEquipo', 'url' => '/crearEquipo', 'verb' => 'POST'],
		

		/***************************** CONFIGURACIONES ***************************************/
		['name' => 'configuraciones#GetConfigurations', 'url' => '/GetConfigurations', 'verb' => 'GET'],
		['name' => 'configuraciones#GetDataManager', 'url' => '/GetDataManager', 'verb' => 'GET'],

		['name' => 'configuraciones#provisioning', 'url' => '/provisioning', 'verb' => 'POST'],
		['name' => 'configuraciones#ActualizarGestor', 'url' => '/ActualizarGestor', 'verb' => 'POST'],
		['name' => 'configuraciones#ActualizarConfiguracion', 'url' => '/ActualizarConfiguracion', 'verb' => 'POST'],
		['name' => 'configuraciones#ActualizarConfiguracionReportes', 'url' => '/ActualizarConfiguracionReportes', 'verb' => 'POST',],
		['name' => 'permisos#grupos', 'url' => '/permisos/grupos',	'verb' => 'GET',],
		['name' => 'permisos#usuario', 'url' => '/permisos/usuario/{uid}',	'verb' => 'GET',],
		['name' => 'permisos#actualizarUsuario', 'url' => '/permisos/usuario/{uid}',	'verb' => 'POST',],

		/***************************** CAPITAL HUMANO ***************************************/
		['name' => 'capitalhumano#GetCapitalHumano', 'url' => '/GetCapitalHumano', 'verb' => 'GET'],
		['name' => 'capitalhumano#UpdateCapitalHumano', 'url' => '/UpdateCapitalHumano', 'verb' => 'POST'],


		/****************************** ANIVERSARIOS ****************************************/
		['name' => 'aniversarios#Getaniversarios', 'url' => '/Getaniversarios', 'verb' => 'GET'],
		['name' => 'aniversarios#VaciarAniversarios', 'url' => '/VaciarAniversarios', 'verb' => 'GET'],
		['name' => 'aniversarios#AgregarNuevoAniversario', 'url' => '/AgregarNuevoAniversario', 'verb' => 'POST'],
		['name' => 'aniversarios#ExportListAniversarios', 'url' => '/ExportListAniversarios', 'verb' => 'GET'],
		['name' => 'aniversarios#GetAniversarioByDate', 'url' => '/GetAniversarioByDate', 'verb' => 'POST'],
		['name' => 'aniversarios#ImportListAniversarios', 'url' => '/ImportListAniversarios', 'verb' => 'POST'],

		
		/******************************* AUSENCIAS *****************************************/
		['name' => 'ausencias#GetNotificationsSubordinates', 'url' => '/GetNotificationsSubordinates', 'verb' => 'GET'],

		['name' => 'ausencias#GetAusenciasEmployeeHistorial', 'url' => '/GetAusenciasEmployeeHistorial', 'verb' => 'POST'],
		['name' => 'ausencias#GetAusenciasHistorialAll', 'url' => '/GetAusenciasHistorialAll', 'verb' => 'POST'],
		['name' => 'ausencias#GetAusenciasHistorial', 'url' => '/GetAusenciasHistorial', 'verb' => 'POST'],
		['name' => 'ausencias#GetAusenciasMyWorkers', 'url' => '/GetAusenciasMyWorkers', 'verb' => 'POST'],
		['name' => 'ausencias#GetAusenciasByUser', 'url' => '/GetAusenciasByUser', 'verb' => 'POST'],
		['name' => 'ausencias#EnviarAusencia', 'url' => '/EnviarAusencia', 'verb' => 'POST'],
		 

		/**************************** TIPO AUSENCIAS **************************************/
		['name' => 'tipoausencias#getTipo', 'url' => '/getTipo', 'verb' => 'GET'],
		['name' => 'tipoausencias#VaciarTipo', 'url' => '/VaciarTipo', 'verb' => 'GET'],
		['name' => 'tipoausencias#AgregarNuevoTipo', 'url' => '/AgregarNuevoTipo', 'verb' => 'POST'],
		['name' => 'tipoausencias#ExportarTipo', 'url' => '/ExportarTipo', 'verb' => 'GET'],
		['name' => 'tipoausencias#importarTipo', 'url' => '/importarTipo', 'verb' => 'POST'],


		/******************************** AHORRO ******************************************/
		['name' => 'ahorros#GetInfoAhorro', 'url' => '/GetInfoAhorro', 'verb' => 'POST'],
		['name' => 'ahorros#EnviarSolicitud', 'url' => '/EnviarSolicitud', 'verb' => 'POST'],
		['name' => 'ahorros#getHistorial', 'url' => '/getHistorial/{id_user}', 'verb' => 'GET'],
		['name' => 'ahorros#GetHistorialPanel', 'url' => '/GetHistorialPanel/{options_fechas_value}/{options_estado_values}', 'verb' => 'GET'],
		['name' => 'ahorros#GenerateReport', 'url' => '/GenerateReport/{options_fechas_value}/{options_estado_values}', 'verb' => 'GET'],
		['name' => 'ahorros#AceptarAhorro', 'url' => '/AceptarAhorro', 'verb' => 'POST'],
		['name' => 'ahorros#DenegarAhorro', 'url' => '/DenegarAhorro', 'verb' => 'POST'],

		/******************************* CLIENTES *****************************************/
		['name' => 'clientes#crearCliente', 'url' => '/crearCliente', 'verb' => 'POST'],
		['name' => 'clientes#modificarCliente', 'url' => '/modificarCliente', 'verb' => 'POST'],
		['name' => 'clientes#deleteById', 'url' => '/deleteCliente', 'verb' => 'POST'],
		['name' => 'clientes#findById', 'url' => '/GetCompanieGroup', 'verb' => 'POST'],
		['name' => 'clientes#importarClientes', 'url' => '/importarClientes', 'verb' => 'POST'],
		['name' => 'clientes#GetCompaniesGroups', 'url' => '/GetCompaniesGroups', 'verb' => 'GET'],
		['name' => 'clientes#Exportarclientes', 'url' => '/Exportarclientes', 'verb' => 'GET'],

		/****************************** ACTIVIDADES ***************************************/
		['name' => 'actividades#crearActividad', 'url' => '/crearActividad', 'verb' => 'POST'],
		['name' => 'actividades#modificarActividad', 'url' => '/ModificarActividad', 'verb' => 'POST'],
		['name' => 'actividades#findById', 'url' => '/GetActividad', 'verb' => 'POST'],
		['name' => 'actividades#deleteById', 'url' => '/DeleteActividad', 'verb' => 'POST'],
		['name' => 'actividades#ImportarActividades', 'url' => '/ImportarActividades', 'verb' => 'POST'],
		['name' => 'actividades#GetActividades', 'url' => '/GetActividades', 'verb' => 'GET'],
		['name' => 'actividades#ExportarActividades', 'url' => '/ExportarActividades', 'verb' => 'GET'],

		/************************** REPORTE DE TIEMPOS ************************************/
		['name' => 'reportetiempo#crearReporte', 'url' => '/crearReporte', 'verb' => 'POST'],
		['name' => 'reportetiempo#GetReportesAll', 'url' => '/GetReportesAll', 'verb' => 'GET'],
		['name' => 'reportetiempo#findById', 'url' => '/GetReportesById', 'verb' => 'POST'],

		['name' => 'reportetiempo#modificarReporte', 'url' => '/modificarReporte', 'verb' => 'POST'],
		['name' => 'reportetiempo#deleteReport', 'url' => '/deleteReport', 'verb' => 'POST'],

		['name' => 'reportetiempo#GetEmpleadosReports', 'url' => '/GetEmpleadosReports', 'verb' => 'POST'],
		['name' => 'reportetiempo#ExportarReportes', 'url' => '/ExportarReportes', 'verb' => 'POST'],
		['name' => 'reportetiempo#GetAdminReportsSummary', 'url' => '/GetAdminReportsSummary', 'verb' => 'POST',],
		['name' => 'reportetiempo#estadoReporteHoy', 'url' => '/estadoReporteHoy', 'verb' => 'GET',],
		['name' => 'reportetiempo#GetCumplimientoReportesHoy', 'url' => '/GetCumplimientoReportesHoy', 'verb' => 'GET',],
		['name' => 'reportetiempo#EnviarRecordatoriosPendientesHoy', 'url' => '/EnviarRecordatoriosPendientesHoy', 'verb' => 'POST',],

		/************************** EJEMPLO ************************************/
		['name' => 'ejemplo#nuevafuncion', 'url' => '/ejemplo', 'verb' => 'POST'],

		/************************** INVENTARIO TI ************************************/

		// Modelos de equipo
		['name' => 'inventario#GetInventarioModelos', 'url' => '/GetInventarioModelos', 'verb' => 'GET'],
		['name' => 'inventario#GetInventarioModelo', 'url' => '/GetInventarioModelo', 'verb' => 'POST'],
		['name' => 'inventario#CrearInventarioModelo', 'url' => '/CrearInventarioModelo', 'verb' => 'POST'],
		['name' => 'inventario#ActualizarInventarioModelo', 'url' => '/ActualizarInventarioModelo', 'verb' => 'POST'],
		['name' => 'inventario#EliminarInventarioModelo', 'url' => '/EliminarInventarioModelo', 'verb' => 'POST'],

		// Equipos de cómputo
		['name' => 'inventario#GetInventarioComputo', 'url' => '/GetInventarioComputo', 'verb' => 'GET'],
		['name' => 'inventario#GetInventarioEquipo', 'url' => '/GetInventarioEquipo', 'verb' => 'POST'],
		['name' => 'inventario#GetInventarioEmpleado', 'url' => '/GetInventarioEmpleado', 'verb' => 'POST'],
		['name' => 'inventario#CrearInventarioEquipo', 'url' => '/CrearInventarioEquipo', 'verb' => 'POST'],
		['name' => 'inventario#ActualizarInventarioEquipo', 'url' => '/ActualizarInventarioEquipo', 'verb' => 'POST'],
		['name' => 'inventario#EliminarInventarioEquipo', 'url' => '/EliminarInventarioEquipo', 'verb' => 'POST'],
		['name' => 'inventario#GetInventarioEquiposSelect', 'url' => '/GetInventarioEquiposSelect', 'verb' => 'GET'],
		// Historial de soporte
		['name' => 'inventario#GetSoporteEquipo', 'url' => '/GetSoporteEquipo', 'verb' => 'POST'],
		['name' => 'inventario#CrearSoporteEquipo', 'url' => '/CrearSoporteEquipo', 'verb' => 'POST'],
		['name' => 'inventario#ActualizarSoporteEquipo', 'url' => '/ActualizarSoporteEquipo', 'verb' => 'POST'],
		['name' => 'inventario#EliminarSoporteEquipo', 'url' => '/EliminarSoporteEquipo', 'verb' => 'POST'],

		/************************** COMPRAS ************************************/
		['name' => 'compra_solicitud#index','url' => '/compras/solicitudes','verb' => 'GET'],
		['name' => 'compra_solicitud#show','url' => '/compras/solicitudes/{id}','verb' => 'GET'],
		['name' => 'compra_solicitud#create','url' => '/compras/solicitudes','verb' => 'POST'],
		['name' => 'compra_solicitud#update','url' => '/compras/solicitudes/{id}','verb' => 'PUT'],
		['name' => 'compra_solicitud#sendToApproval','url' => '/compras/solicitudes/{id}/enviar-autorizacion','verb' => 'POST'],
		['name' => 'compra_solicitud#approve','url' => '/compras/solicitudes/{id}/autorizar','verb' => 'POST'],
		['name' => 'compra_solicitud#reject','url' => '/compras/solicitudes/{id}/rechazar','verb' => 'POST'],
	],
];
