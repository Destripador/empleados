<?php

declare(strict_types=1);

use OCA\Empleados\AppInfo\Application;

script(Application::APP_ID, 'empleados-settings');
?>

<div id="admin"></div>


<div id="content"></div>
<div id="data" data-parameters='<?php echo json_encode($_['config'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'></div>