<?php

declare(strict_types=1);
namespace OCA\Empleados\Controller;

use OCA\Empleados\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\ISession;
use OCP\IL10N;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\Constants;
use OCA\Empleados\Db\capitalhumanoMapper;
use OCA\Empleados\Db\configuracionesMapper;

/**
 * @psalm-suppress UnusedClass
 */
class CapitalhumanoController extends Controller {

	private IUserSession $userSession;
	private capitalhumanoMapper $capitalhumanoMapper;
	private configuracionesMapper $configuracionesMapper;
	protected IRootFolder $rootFolder;
	private ISession $session;
	private IL10N $l10n;
	private IUserManager $userManager;
	private IManager $shareManager;
	private IGroupManager $groupManager;

	public function __construct(
		IRequest $request,
		ISession $session,
		IUserSession $userSession,
		IUserManager $userManager,
		IL10N $l10n,
		IRootFolder $rootFolder,
		capitalhumanoMapper $capitalhumanoMapper,
		IGroupManager $groupManager,
		IManager $shareManager,
		configuracionesMapper $configuracionesMapper
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->capitalhumanoMapper = $capitalhumanoMapper;
		$this->rootFolder = $rootFolder;
		$this->configuracionesMapper = $configuracionesMapper;
		$this->groupManager = $groupManager;
		$this->shareManager = $shareManager;
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function GetCapitalHumano(): array {
		// Obtener los datos desde la base de datos
		$data = $this->capitalhumanoMapper->GetCapitalHumano();
		$userList = [];

		// Iterar sobre los datos obtenidos y construir la lista de usuarios
		foreach ($data as $item) {
			$userId = $item['Id_empleado'];
			$user = $this->userManager->get($userId);

			if ($user !== null) {
				$userList[] = [
					'id' => $user->getUID(),
					'displayName' => $user->getDisplayName(),
					'email' => $user->getEMailAddress(),
					'lastLogin' => $user->getLastLogin(),
					'isEnabled' => $user->isEnabled()
				];
			}
		}

		return $userList;
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function UpdateCapitalHumano($capitalhumano): void {
		// Actualizar los datos en la base de datos
		$data = $this->capitalhumanoMapper->UpdateCapitalHumano($capitalhumano);
		$gestor = $this->configuracionesMapper->GetGestor();
		$folderPath = "/EMPLEADOS";

		// Procesar usuarios insertados
		foreach ($data['inserted'] as $userId) {
			$this->processUserInsertion($userId, $gestor, $folderPath);
		}

		// Procesar usuarios eliminados
		foreach ($data['deleted'] as $userId) {
			$this->processUserDeletion($userId, $gestor, $folderPath);
		}
	}

	private function processUserInsertion($userId, $gestor, $folderPath): void {
		// Verificar si el grupo "recursos_humanos" existe
		$group = $this->groupManager->get("recursos_humanos");
		if (!$group) {
			$this->groupManager->createGroup("recursos_humanos");
			$group = $this->groupManager->get("recursos_humanos");
		}

		$user = $this->userManager->get($userId);

		if ($user && $group && !$group->inGroup($user)) {
			$group->addUser($user);
			$this->shareFolderWithUser($userId, $gestor, $folderPath);
		}
	}

	private function processUserDeletion($userId, $gestor, $folderPath): void {
		$user = $this->userManager->get($userId);
		$group = $this->groupManager->get("recursos_humanos");

		if ($user && $group && $group->inGroup($user)) {
			$group->removeUser($user);
			$this->removeFolderShareWithUser($userId, $gestor, $folderPath);
		}
	}

	private function shareFolderWithUser($userId, $gestor, $folderPath): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder($gestor[0]['Data']);
			$folder = $userFolder->get($folderPath);

			$share = $this->shareManager->newShare();
			$share->setNode($folder);
			$share->setShareType(IShare::TYPE_USER);
			$share->setSharedWith($userId);
			$share->setPermissions(Constants::PERMISSION_READ | Constants::PERMISSION_UPDATE);
			$share->setSharedBy($gestor[0]['Data']);

			$this->shareManager->createShare($share);
		} catch (\Exception $e) {
			error_log("Error: La carpeta $folderPath no existe.");
		}
	}

	private function removeFolderShareWithUser($userId, $gestor, $folderPath): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder($gestor[0]['Data']);
			$folder = $userFolder->get($folderPath);

			if ($folder instanceof \OCP\Files\Node) {
				$shares = $this->shareManager->getSharesBy($gestor[0]['Data'], IShare::TYPE_USER, $folder);

				foreach ($shares as $share) {
					if ($share->getSharedWith() === $userId) {
						$this->shareManager->deleteShare($share);
					}
				}
			}
		} catch (\Exception $e) {
			error_log("Error: No se encontró la carpeta $folderPath para eliminar la compartición.");
		}
	}
}
