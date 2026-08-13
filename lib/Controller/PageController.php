<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\configuracionesMapper;

class PageController extends BaseController {

    protected $empleadosMapper;
	protected $configuracionesMapper;
	protected $session;

	public function __construct(
		IRequest $request, 
		ISession $session, 
		IUserSession $userSession, 
		IUserManager $userManager,
        empleadosMapper $empleadosMapper,
		configuracionesMapper $configuracionesMapper, 
		IGroupManager $groupManager
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $empleadosMapper, $configuracionesMapper);

		$this->session = $session;
		$this->configuracionesMapper = $configuracionesMapper;
		$this->empleadosMapper = $empleadosMapper;
	}

	#[UseSession]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function index(): TemplateResponse {
		$params = $this->getConfigParams();
		$group = $this->GroupCheckAccess();
		$employee = $this->getEmployeeInfo();
		$subordinates = $this->GetSubordinates();

		return new TemplateResponse(Application::APP_ID, 'index', [
				'config' => $params,
				'group' => $group, 
				'employee' => $employee,
				'subordinates' => $subordinates,
			]);
	}
}
