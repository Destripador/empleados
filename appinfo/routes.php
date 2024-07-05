<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Luis Angel Alvarado Hernandez <luis.alvarado@crowe.mx>
// SPDX-License-Identifier: AGPL-3.0-or-later

return [

	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#GetUserLists', 'url' => '/GetUserLists', 'verb' => 'GET'],

		['name' => 'page#ActivarEmpleado', 'url' => '/ActivarEmpleado', 'verb' => 'POST'],
		['name' => 'page#EliminarEmpleado', 'url' => '/EliminarEmpleado', 'verb' => 'POST'],
	],
	
];
