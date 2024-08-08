<?php

declare(strict_types=1);

use OCP\Util;

// style('empleados', 'semantic');  // adds css/style.(s)css
style('empleados', 'chart');
Util::addScript(OCA\Empleados\AppInfo\Application::APP_ID, 'main');

echo "contexto contexto contexto contexto contexto ";

$data = array($_[0], $_[1])

?>
<!--div
    id="content">
</div-->
<div id="content"></div>
<div id="data" data-parameters='<?php echo json_encode($data); ?>'></div>

<h2><?php echo json_encode($_[0]); ?></h2>