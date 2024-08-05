<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Luis Angel Alvarado Hernandez <luis.alvarado@crowe.mx>
// SPDX-License-Identifier: AGPL-3.0-or-later

return [

	'routes' => [
		// EMPLEADOS
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#GetUserLists', 'url' => '/GetUserLists', 'verb' => 'GET'],
		['name' => 'page#GetEmpleadosList', 'url' => '/GetEmpleadosList', 'verb' => 'GET'],
		['name' => 'page#GetEmpleadosArea', 'url' => '/GetEmpleadosArea/{id_area}', 'verb' => 'GET'],
		['name' => 'page#GetEmpleadosListFix', 'url' => '/GetEmpleadosListFix', 'verb' => 'GET'],
		['name' => 'page#ExportListEmpleados', 'url' => '/ExportListEmpleados', 'verb' => 'GET'],
		
		['name' => 'page#ActivarEmpleado', 'url' => '/ActivarEmpleado', 'verb' => 'POST'],
		['name' => 'page#EliminarEmpleado', 'url' => '/EliminarEmpleado', 'verb' => 'POST'],
		['name' => 'page#ImportListEmpleados', 'url' => '/ImportListEmpleados', 'verb' => 'POST'],

		// AREAS
		['name' => 'page#Areas', 'url' => '/Areas', 'verb' => 'GET'],
		['name' => 'areas#GetAreasFix', 'url' => '/GetAreasFix', 'verb' => 'GET'],
		['name' => 'page#GetAreasList', 'url' => '/GetAreasList', 'verb' => 'GET'],
		['name' => 'areas#ExportListAreas', 'url' => '/ExportListAreas', 'verb' => 'GET'],

		['name' => 'areas#GuardarCambioArea', 'url' => '/GuardarCambioArea', 'verb' => 'POST'],
		['name' => 'areas#ImportListAreas', 'url' => '/ImportListAreas', 'verb' => 'POST'],
		['name' => 'areas#EliminarArea', 'url' => '/EliminarArea', 'verb' => 'POST'],
		['name' => 'areas#crearArea', 'url' => '/crearArea', 'verb' => 'POST'],

		// CONFIGURACIONES
		['name' => 'configuraciones#GetConfigurations', 'url' => '/GetConfigurations', 'verb' => 'GET'],

		['name' => 'configuraciones#ActualizarGestor', 'url' => '/ActualizarGestor', 'verb' => 'POST'],
	],
	
];
