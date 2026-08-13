<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

use OCA\Empleados\AppInfo\Application;
use OCA\Empleados\Exception\TutorialLessonNotFoundException;
use OCA\Empleados\Tutorial\TutorialCatalog;
use OCP\IConfig;

class TutorialProgressService {

	private const COMPLETED_VALUE = '1';

	public function __construct(
		private IConfig $config,
	) {
	}

	public function isCompleted(string $userId, string $lessonId): bool {
		$this->validateLesson($lessonId);

		$value = $this->config->getUserValue(
			$userId,
			Application::APP_ID,
			TutorialCatalog::configKey($lessonId),
			''
		);

		return $value === self::COMPLETED_VALUE;
	}

	public function complete(string $userId, string $lessonId): void {
		$this->validateLesson($lessonId);

		$this->config->setUserValue(
			$userId,
			Application::APP_ID,
			TutorialCatalog::configKey($lessonId),
			self::COMPLETED_VALUE
		);
	}

	public function reset(string $userId, string $lessonId): void {
		$this->validateLesson($lessonId);

		$this->config->deleteUserValue(
			$userId,
			Application::APP_ID,
			TutorialCatalog::configKey($lessonId)
		);
	}

	/**
	 * Reset all known lessons for the given user.
	 *
	 * @return int Number of lessons whose preference was cleared
	 */
	public function resetAll(string $userId): int {
		$reset = 0;

		foreach (TutorialCatalog::LESSONS as $lessonId) {
			$key = TutorialCatalog::configKey($lessonId);
			$current = $this->config->getUserValue(
				$userId,
				Application::APP_ID,
				$key,
				''
			);

			if ($current === '') {
				continue;
			}

			$this->config->deleteUserValue(
				$userId,
				Application::APP_ID,
				$key
			);
			$reset++;
		}

		return $reset;
	}

	/**
	 * @throws TutorialLessonNotFoundException
	 */
	private function validateLesson(string $lessonId): void {
		if (!TutorialCatalog::isValid($lessonId)) {
			throw new TutorialLessonNotFoundException('Lección no encontrada.');
		}
	}
}
