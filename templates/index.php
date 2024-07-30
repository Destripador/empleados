<?php

declare(strict_types=1);

use OCP\Util;

// style('empleados', 'semantic');  // adds css/style.(s)css
style('empleados', 'chart');

Util::addScript(OCA\Empleados\AppInfo\Application::APP_ID, 'main');
