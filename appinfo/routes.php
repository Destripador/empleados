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
		['name' => 'page#ExportListEmpleados', 'url' => '/ExportListEmpleados', 'verb' => 'GET'],
		
		['name' => 'page#ActivarEmpleado', 'url' => '/ActivarEmpleado', 'verb' => 'POST'],
		['name' => 'page#EliminarEmpleado', 'url' => '/EliminarEmpleado', 'verb' => 'POST'],
		['name' => 'page#ImportListEmpleados', 'url' => '/ImportListEmpleados', 'verb' => 'POST'],

		// AREAS
		['name' => 'page#Areas', 'url' => '/Areas', 'verb' => 'GET'],
		['name' => 'page#GetAreasList', 'url' => '/GetAreasList', 'verb' => 'GET'],

	],
	
];
