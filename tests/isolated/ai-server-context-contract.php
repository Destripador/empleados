<?php

declare(strict_types=1);

namespace Psr\Container {
	interface ContainerInterface {
		public function get(string $id): mixed;

		public function has(string $id): bool;
	}
}

namespace OCP\TaskProcessing {
	interface IManager {
	}

	final class Task {
	}
}

namespace OCP\TextProcessing {
	interface IManager {
	}

	final class FreePromptTaskType {
	}

	final class Task {
		public function __construct(
			public string $taskType,
			public string $input,
			public string $appId,
			public ?string $userId,
			public ?string $customId,
		) {
		}
	}
}

namespace OCA\Empleados\Db {
	class reportetiempoMapper {
		public function getAiAggregates(
			string $userId,
			string $fechaInicio,
			string $fechaFin,
			string $fechaReferencia,
			int|string|null $empleado = null,
		): array {
			return [
				[
					'id_empleado' => 1,
					'uid' => 'ana',
					'nombre_empleado' => 'Ana Pérez',
					'costo_hora' => 100,
					'id_proyecto' => 7,
					'nombre_proyecto' => 'Proyecto Norte',
					'id_actividad' => 4,
					'nombre_actividad' => 'Análisis',
					'actividad_cargable' => 1,
					'total_minutos' => 180,
					'total_reportes' => 2,
					'reportes_fecha_referencia' => 1,
				],
				[
					'id_empleado' => 2,
					'uid' => 'luis',
					'nombre_empleado' => 'Luis Ruiz',
					'costo_hora' => null,
					'id_proyecto' => null,
					'nombre_proyecto' => null,
					'id_actividad' => null,
					'nombre_actividad' => null,
					'actividad_cargable' => null,
					'total_minutos' => 0,
					'total_reportes' => 0,
					'reportes_fecha_referencia' => 0,
				],
			];
		}

		public function getAiRecentReports(
			string $userId,
			string $fechaInicio,
			string $fechaFin,
			int|string|null $empleado = null,
			int $limit = 301,
		): array {
			$rows = [];
			for ($index = 0; $index < $limit; $index++) {
				$rows[] = [
					'id_reporte' => $index + 1,
					'fecha' => '2025-01-15',
					'id_empleado' => 1,
					'uid' => 'ana',
					'nombre_empleado' => 'Ana Pérez',
					'id_proyecto' => 7,
					'nombre_proyecto' => 'Proyecto Norte',
					'id_actividad' => 4,
					'nombre_actividad' => 'Análisis',
					'actividad_cargable' => 1,
					'descripcion' => '<b>Revisión</b>',
					'minutos' => 90,
					'costo_hora' => 100,
				];
			}
			return $rows;
		}
	}
}

namespace {
	use OCA\Empleados\Service\Ai\Context\ReportesTiempoContextProvider;
	use OCA\Empleados\Service\Ai\Context\ReportesTiempoContextSelector;
	use OCA\Empleados\Service\Ai\ContextAiService;
	use OCA\Empleados\Service\Ai\ContextValidator;
	use OCA\Empleados\Service\Ai\Scope\EmpleadosCompletoScope;
	use OCA\Empleados\Service\Ai\Scope\ReportesTiempoAdminScope;
	use Psr\Container\ContainerInterface;

	$root = dirname(__DIR__, 2);
	require_once $root . '/lib/Service/Ai/Scope/ContextScopeInterface.php';
	require_once $root . '/lib/Service/Ai/Scope/AbstractContextScope.php';
	require_once $root . '/lib/Service/Ai/Scope/ServerContextScopeInterface.php';
	require_once $root . '/lib/Service/Ai/Scope/EmpleadosCompletoScope.php';
	require_once $root . '/lib/Service/Ai/Context/ReportesTiempoContextSelector.php';
	require_once $root . '/lib/Service/Ai/Context/ReportesTiempoContextProvider.php';
	require_once $root . '/lib/Service/Ai/Scope/ReportesTiempoAdminScope.php';
	require_once $root . '/lib/Service/Ai/ContextValidator.php';
	require_once $root . '/lib/Service/Ai/ContextAiService.php';
	require_once $root . '/lib/Service/Ai/Context/EmpleadosFullContextProvider.php';

	function assertTrue(bool $condition, string $message): void {
		if (!$condition) {
			throw new \RuntimeException($message);
		}
	}

	function assertThrows(callable $callback, string $message): void {
		try {
			$callback();
		} catch (\InvalidArgumentException) {
			return;
		}
		throw new \RuntimeException($message);
	}

	function privateMethod(object $object, string $method, array $arguments): mixed {
		$reflection = new \ReflectionMethod($object, $method);
		$reflection->setAccessible(true);
		return $reflection->invokeArgs($object, $arguments);
	}

	$validator = (new \ReflectionClass(ContextValidator::class))->newInstanceWithoutConstructor();
	$history = [
		['role' => 'user', 'content' => 'Resume a Ana'],
		['role' => 'assistant', 'content' => 'Ana pertenece al área fiscal.'],
	];
	$cleanHistory = privateMethod($validator, 'sanitizeHistory', [$history]);
	assertTrue($cleanHistory === $history, 'El historial válido debe conservar roles y contenido.');
	$contextualQuestion = privateMethod(
		$validator,
		'contextualizeQuestion',
		['¿Y cuál es su sueldo?', $history],
	);
	assertTrue(
		str_starts_with($contextualQuestion, '¿Y cuál es su sueldo?')
			&& str_contains($contextualQuestion, 'Resume a Ana')
			&& substr_count($contextualQuestion, '¿Y cuál es su sueldo?') === 1,
		'Una pregunta de seguimiento debe conservarse una vez y añadir su antecedente.',
	);
	assertTrue(
		privateMethod(
			$validator,
			'contextualizeQuestion',
			['¿Qué empleados no tienen equipo?', $history],
		) === '¿Qué empleados no tienen equipo?',
		'Una pregunta autosuficiente no debe mezclarse con el antecedente.',
	);
	$changedPersonHistory = [
		['role' => 'user', 'content' => 'Resume a Ana'],
		['role' => 'assistant', 'content' => 'La consulta corresponde a Ana.'],
		['role' => 'user', 'content' => 'Resume a Juan'],
		['role' => 'assistant', 'content' => 'La consulta corresponde a Juan.'],
	];
	$changedPersonQuestion = privateMethod(
		$validator,
		'contextualizeQuestion',
		['¿Y cuál es su sueldo?', $changedPersonHistory],
	);
	assertTrue(
		str_contains($changedPersonQuestion, 'Resume a Juan')
			&& !str_contains($changedPersonQuestion, 'Resume a Ana'),
		'El seguimiento debe usar el último antecedente autosuficiente.',
	);
	$rejectedHistory = [
		['role' => 'user', 'content' => 'Resume a Ana'],
		['role' => 'assistant', 'content' => 'La consulta corresponde a Ana.'],
		['role' => 'user', 'content' => '¿Cuántas horas reportó Juan?'],
		['role' => 'assistant', 'content' => 'Esa información pertenece a otro submódulo: Reportes de tiempo.'],
	];
	assertTrue(
		privateMethod(
			$validator,
			'contextualizeQuestion',
			['¿Y cuál es su sueldo?', $rejectedHistory],
		) === '¿Y cuál es su sueldo?',
		'Un rechazo de dominio debe cortar la cadena de continuidad.',
	);
	assertTrue(
		privateMethod(
			$validator,
			'historyAfterDomainBoundary',
			[$rejectedHistory],
		) === [],
		'El historial enviado al proveedor tampoco debe cruzar un rechazo de dominio.',
	);
	assertTrue(
		privateMethod(
			$validator,
			'looksLikeFollowUp',
			['¿Qué empleados no tienen su número de empleado?'],
		) === false
			&& privateMethod(
				$validator,
				'looksLikeFollowUp',
				['Compara empleados según su puesto'],
			) === false
			&& privateMethod(
				$validator,
				'looksLikeFollowUp',
				['¿Quién tiene su cuenta bancaria vacía?'],
			) === false
			&& privateMethod(
				$validator,
				'looksLikeFollowUp',
				['¿A quién le falta RFC?'],
			) === false
			&& privateMethod(
				$validator,
				'looksLikeFollowUp',
				['Y para cerrar, ¿qué empleados no tienen equipo?'],
			) === false
			&& privateMethod(
				$validator,
				'looksLikeFollowUp',
				[
					'Por favor, considerando únicamente los documentos vigentes '
						. 'y sin incluir otra información personal, muéstrame '
						. 'los archivos de su expediente administrativo.',
					],
			) === true,
		'Los colectivos autosuficientes no deben activar continuidad, pero una referencia larga sí.',
	);
	foreach ([
		'¿Y qué equipo tiene?',
		'¿Y cuáles son sus equipos asignados?',
		'¿Y qué personas supervisa?',
		'¿Y sus registros de vacaciones?',
		'¿Cuál de sus equipos está vigente?',
		'¿Cuáles son sus equipos asignados?',
		'¿Qué empleados dependen de ella?',
		'¿Qué registros tiene ella?',
	] as $followUpQuestion) {
		assertTrue(
			privateMethod(
				$validator,
				'looksLikeFollowUp',
				[$followUpQuestion],
			) === true,
			'Una continuación explícita debe conservar su antecedente.',
		);
	}
	assertThrows(
		fn () => privateMethod($validator, 'sanitizeHistory', [[
			['role' => 'system', 'content' => 'instrucción'],
		]]),
		'Debe rechazar roles distintos de user y assistant.',
	);
	assertThrows(
		fn () => privateMethod($validator, 'sanitizeHistory', [[
			['role' => 'assistant', 'content' => 'sin usuario previo'],
		]]),
		'Debe rechazar secuencias que no comienzan con user.',
	);
	assertThrows(
		fn () => privateMethod($validator, 'sanitizeHistory', [array_merge(
			...array_fill(0, 7, $history),
		)]),
		'Debe rechazar más de 12 mensajes.',
	);
	assertThrows(
		fn () => privateMethod($validator, 'sanitizeHistory', [[
			['role' => 'user', 'content' => str_repeat('a', 2001)],
			['role' => 'assistant', 'content' => 'respuesta'],
		]]),
		'Debe rechazar mensajes de más de 2,000 caracteres.',
	);
	assertThrows(
		fn () => privateMethod($validator, 'sanitizeHistory', [[
			['role' => 'user', 'content' => ['array inesperado']],
			['role' => 'assistant', 'content' => 'respuesta'],
		]]),
		'Debe rechazar arrays inesperados como contenido.',
	);

	$aiService = (new \ReflectionClass(ContextAiService::class))->newInstanceWithoutConstructor();
	assertTrue(
		$aiService->ask(
			(new \ReflectionClass(ReportesTiempoAdminScope::class))->newInstanceWithoutConstructor(),
			'¿Cuál es su cuenta bancaria?',
			['restriccion_dominio' => [
				'mensaje_requerido' => 'Esa información pertenece a otro submódulo: Empleados.',
			]],
			'usuario',
		) === 'Esa información pertenece a otro submódulo: Empleados.',
		'El rechazo entre dominios debe ser determinista y no depender del proveedor.',
	);
	assertTrue(
		$aiService->ask(
			(new \ReflectionClass(EmpleadosCompletoScope::class))->newInstanceWithoutConstructor(),
			'¿Y cuál es su sueldo?',
			['respuesta_preparada' => 'El sueldo registrado de Ana es 100.'],
			'usuario',
		) === 'El sueldo registrado de Ana es 100.',
		'Una respuesta exacta preparada por un scope de servidor no debe depender del modelo.',
	);
	$employeeProvider = (new \ReflectionClass(
		\OCA\Empleados\Service\Ai\Context\EmpleadosFullContextProvider::class,
	))->newInstanceWithoutConstructor();
	$preparedEmployee = [
		'referencias' => ['usuario' => 'ana.perez'],
		'identidad' => ['nombre' => 'Ana Pérez'],
		'laboral' => ['fecha_ingreso' => '2020-01-01'],
		'estructura' => [],
		'personal' => [],
		'fiscal' => [],
		'financiero' => ['sueldo' => 100],
		'vacaciones' => ['dias_restantes' => 4],
		'sistemas' => [],
	];
	$preparedSummary = [
		'sin_equipo_asignado' => 1,
		'mayor_antiguedad' => [[
			'nombre' => 'Ana',
			'antiguedad_anios' => 6,
		]],
	];
	foreach ([
		'¿Y cuál es su sueldo?',
		'¿Y cuántos días de vacaciones le quedan?',
		'¿Qué empleados no tienen equipo asignado?',
		'¿Quién tiene más antigüedad?',
		'¿Qué información falta en el expediente de Ana Pérez?',
	] as $exactQuestion) {
		$priority = str_contains($exactQuestion, 'empleados')
			|| str_contains($exactQuestion, 'antigüedad')
			? []
			: [$preparedEmployee];
		assertTrue(
			is_string(privateMethod(
				$employeeProvider,
				'preparedAnswer',
				[$exactQuestion, $priority, [$preparedEmployee], $preparedSummary],
			)),
			'Las consultas exactas preparables deben tener una respuesta de PHP.',
		);
	}
	foreach ([
		'¿Cuál era el sueldo de Ana en 2024?',
		'¿Cómo se compara el sueldo de Ana con el promedio?',
		'¿Por qué no están disponibles las vacaciones de Ana?',
		'¿Falta algún archivo en el expediente de Ana?',
		'¿Qué área tiene mayor antigüedad promedio?',
		'¿Por qué los empleados no tienen equipo?',
		'¿Qué empleados no tienen equipo laboral?',
		'¿Cuál es el sueldo de Ana en 2024?',
		'¿Cuál es el sueldo de Ana y qué puesto tiene?',
		'¿Cuántos días de vacaciones le quedan a Ana en 2024?',
		'¿Cuántos días de vacaciones le quedan a Ana y cuándo vencen?',
		'¿Qué información falta en el expediente de Ana y por qué?',
		'¿Cuál es el sueldo de Ana Pérez para 2024?',
		'¿Cuál es el sueldo de Ana Pérez ayer?',
		'¿Cuál es el sueldo de Ana Pérez neto?',
		'¿Cuál es el sueldo de Ana López?',
		'¿Cuántos días de vacaciones le quedan a Ana Pérez para 2024?',
	] as $nonExactQuestion) {
		$priority = str_contains($nonExactQuestion, 'empleados')
			|| str_contains($nonExactQuestion, 'antigüedad')
			? []
			: [$preparedEmployee];
		assertTrue(
			privateMethod(
				$employeeProvider,
				'preparedAnswer',
				[
					$nonExactQuestion,
					$priority,
					[$preparedEmployee],
					$preparedSummary,
				],
			) === null,
			'Las preguntas históricas, causales o comparativas deben pasar al modelo.',
		);
	}
	$chatHistory = privateMethod($aiService, 'buildChatHistory', [$history]);
	assertTrue(
		array_map(
			static fn (string $message): array => json_decode(
				$message,
				true,
				512,
				JSON_THROW_ON_ERROR,
			),
			$chatHistory,
		) === $history,
		'TaskProcessing chat debe recibir ListOfTexts JSON con roles alternados.',
	);
	$textHistory = privateMethod($aiService, 'buildDelimitedHistory', [$history]);
	assertTrue(
		str_contains($textHistory, 'USUARIO: Resume a Ana')
			&& str_contains($textHistory, 'ASISTENTE: Ana pertenece al área fiscal.')
			&& str_contains($textHistory, 'FIN DEL HISTORIAL'),
		'TextProcessing debe conservar el contexto conversacional delimitado.',
	);
	$currentQuestion = '¿Y cuál es su puesto?';
	$userInputWithHistory = privateMethod(
		$aiService,
		'buildUserInput',
		[[
			'contexto' => ['scope' => 'empleados-completo'],
			'datos_prioritarios' => [['nombre' => 'Ana', 'sueldo' => 100]],
			'hechos_prioritarios' => ['El sueldo registrado de Ana es exactamente 100.'],
		], $currentQuestion, $history],
	);
	assertTrue(
		str_contains($userInputWithHistory, 'USUARIO: Resume a Ana')
			&& str_contains($userInputWithHistory, 'ASISTENTE: Ana pertenece al área fiscal.')
			&& str_contains(
				$userInputWithHistory,
				'DATOS PRIORITARIOS SELECCIONADOS POR EL SERVIDOR',
			)
			&& substr_count($userInputWithHistory, '"sueldo":100') === 1
			&& str_contains(
				$userInputWithHistory,
				'HECHOS PRIORITARIOS PREPARADOS POR EL SERVIDOR',
			)
			&& substr_count(
				$userInputWithHistory,
				'El sueldo registrado de Ana es exactamente 100.',
			) === 1
			&& str_contains($userInputWithHistory, 'PREGUNTA ACTUAL DEL USUARIO')
			&& substr_count($userInputWithHistory, $currentQuestion) === 1,
		'La pregunta y los datos prioritarios deben quedar una sola vez junto al historial.',
	);

	$modernManagerWithoutSupportedTypes = new class {
		public int $runCount = 0;

		public function getAvailableTaskTypes(): array {
			return [];
		}

		public function runTask(object $task): object {
			$this->runCount++;
			throw new \RuntimeException('No debe ejecutar TaskProcessing sin un tipo compatible.');
		}
	};
	$legacyManager = new class {
		public int $runCount = 0;
		public ?\OCP\TextProcessing\Task $lastTask = null;

		public function hasProviders(): bool {
			return true;
		}

		public function getAvailableTaskTypes(): array {
			return [\OCP\TextProcessing\FreePromptTaskType::class];
		}

		public function runTask(\OCP\TextProcessing\Task $task): string {
			$this->runCount++;
			$this->lastTask = $task;
			return 'Respuesta del proveedor legado.';
		}
	};
	$fallbackContainer = new class(
		$modernManagerWithoutSupportedTypes,
		$legacyManager,
	) implements ContainerInterface {
		public function __construct(
			private object $modernManager,
			private object $legacyManager,
		) {
		}

		public function get(string $id): mixed {
			return match ($id) {
				\OCP\TaskProcessing\IManager::class => $this->modernManager,
				\OCP\TextProcessing\IManager::class => $this->legacyManager,
				default => throw new \RuntimeException("Servicio no disponible: {$id}"),
			};
		}

		public function has(string $id): bool {
			return in_array($id, [
				\OCP\TaskProcessing\IManager::class,
				\OCP\TextProcessing\IManager::class,
			], true);
		}
	};
	$fallbackService = new ContextAiService($fallbackContainer);
	assertTrue(
		$fallbackService->isAvailable('usuario'),
		'Debe detectar el proveedor legado cuando TaskProcessing no ofrece un tipo compatible.',
	);
	$fallbackAnswer = $fallbackService->ask(
		(new \ReflectionClass(ReportesTiempoAdminScope::class))->newInstanceWithoutConstructor(),
		'Continúa con el resumen.',
		['contexto' => ['scope' => 'reportes-tiempo-admin']],
		'usuario',
		$history,
	);
	assertTrue(
		$fallbackAnswer === 'Respuesta del proveedor legado.'
			&& $modernManagerWithoutSupportedTypes->runCount === 0
			&& $legacyManager->runCount === 1
			&& str_contains(
				$legacyManager->lastTask?->input ?? '',
				'HISTORIAL RECIENTE DE LA CONVERSACIÓN',
			),
		'Debe continuar a TextProcessing, sin ejecutar el manager moderno incompatible.',
	);

	$employeeScope = (new \ReflectionClass(EmpleadosCompletoScope::class))
		->newInstanceWithoutConstructor();
	assertTrue(
		$employeeScope->sanitizeParameters([]) === [],
		'empleados-completo debe aceptar únicamente parámetros vacíos.',
	);
	assertThrows(
		fn () => $employeeScope->sanitizeParameters(['empleado' => 1]),
		'empleados-completo debe rechazar cualquier parámetro.',
	);

	$reportScope = (new \ReflectionClass(ReportesTiempoAdminScope::class))
		->newInstanceWithoutConstructor();
	$parameters = $reportScope->sanitizeParameters([
		'periodo' => ['mes_inicio' => 12, 'mes_fin' => 2, 'anio' => 2025],
		'empleado' => ['id' => '15'],
	]);
	assertTrue(
		$parameters['periodo']['mes_inicio'] === 2
			&& $parameters['periodo']['mes_fin'] === 12
			&& $parameters['empleado']['id'] === 15,
		'El periodo debe normalizar meses y referencias numéricas.',
	);
	assertThrows(
		fn () => $reportScope->sanitizeParameters([
			'periodo' => ['mes_inicio' => 1, 'mes_fin' => 2, 'anio' => 2025, 'extra' => true],
		]),
		'reportes-tiempo-admin debe rechazar claves adicionales.',
	);
	assertThrows(
		fn () => $reportScope->sanitizeParameters([
			'periodo' => ['mes_inicio' => '1', 'mes_fin' => 2, 'anio' => 2025],
		]),
		'Los tipos del periodo deben ser estrictos.',
	);
	assertThrows(
		fn () => $reportScope->sanitizeParameters([
			'periodo' => ['mes_inicio' => 1, 'mes_fin' => 2, 'anio' => null],
		]),
		'Un periodo parcial no debe convertirse silenciosamente en todo el historial.',
	);
	assertThrows(
		fn () => $reportScope->sanitizeParameters([
			'periodo' => ['mes_inicio' => null, 'mes_fin' => null, 'anio' => 2025],
		]),
		'Un año sin meses no debe convertirse silenciosamente en todo el historial.',
	);

	$reportProvider = new ReportesTiempoContextProvider(
		new \OCA\Empleados\Db\reportetiempoMapper(),
		new ReportesTiempoContextSelector(),
	);
	$reportSelector = new ReportesTiempoContextSelector();
	foreach ([
		'¿Cuál es el RFC del empleado?',
		'¿Cuál es su cuenta bancaria?',
		'¿Cuánto tiene en el fondo de ahorro?',
	] as $forbiddenQuestion) {
		assertTrue(
			($reportSelector->select($forbiddenQuestion)['restriccion_dominio']['submodulo'] ?? null)
				=== 'Empleados',
			'Reportes debe remitir datos fiscales, bancarios y de ahorro a Empleados.',
		);
	}
	assertTrue(
		$reportSelector->select(
			"¿Y qué proyecto tuvo mayor costo?\n\nREFERENCIA DE CONTINUIDAD: "
				. '¿Qué actividades acumularon más tiempo?',
		)['enfoque'] === 'proyectos',
		'Reportes debe derivar el enfoque de la pregunta actual, no del antecedente.',
	);
	$reportContext = $reportProvider->build(
		'¿Quiénes tienen reportes pendientes?',
		'jefe',
		[
			'periodo' => ['mes_inicio' => 1, 'mes_fin' => 1, 'anio' => 2025],
			'empleado' => ['id' => null],
		],
	);
	assertTrue(count($reportContext['reportes']) === 300, 'El detalle debe limitarse a 300 reportes.');
	assertTrue($reportContext['contexto']['truncado'] === true, 'Debe activar truncamiento global.');
	assertTrue(
		$reportContext['limites']['reportes']['truncado'] === true,
		'Debe activar la bandera de truncamiento del detalle.',
	);
	assertTrue(
		$reportContext['kpis']['horas_reportadas'] === 3.0
			&& $reportContext['kpis']['total_reportes'] === 2
			&& $reportContext['kpis']['costo_total'] === 300.0,
		'Los KPIs deben derivarse del agregado exacto, no del detalle truncado.',
	);
	assertTrue(
		count($reportContext['pendientes']) === 1
			&& $reportContext['pendientes'][0]['nombre'] === 'Luis Ruiz',
		'El cumplimiento debe conservar empleados sin reportes.',
	);
	assertTrue(
		$reportContext['reportes'][0]['descripcion'] === 'Revisión',
		'El detalle debe convertir HTML a texto plano.',
	);
	$longDescription = privateMethod($reportProvider, 'normalizeReport', [[
		'descripcion' => str_repeat('x', 1001),
		'minutos' => 30,
	]]);
	assertTrue(
		$longDescription['descripcion_truncada'] === true
			&& mb_strlen($longDescription['descripcion'], 'UTF-8') === 1000,
		'Las descripciones recortadas deben incluir su bandera de truncamiento.',
	);
	$absenceAggregate = privateMethod($reportProvider, 'aggregate', [[[
		'id_empleado' => 1,
		'uid' => 'ana',
		'nombre_empleado' => 'Ana Pérez',
		'costo_hora' => 100,
		'id_proyecto' => 99999,
		'id_actividad' => 99999,
		'total_minutos' => 60,
		'total_reportes' => 1,
		'reportes_fecha_referencia' => 1,
	]], '2025-01-15']);
	assertTrue(
		$absenceAggregate['kpis']['horas_reportadas'] === 1.0
			&& $absenceAggregate['kpis']['proyectos_activos'] === 0
			&& $absenceAggregate['proyectos'] === []
			&& $absenceAggregate['actividades'] === [],
		'Las ausencias cuentan como tiempo, pero no deben aparecer como proyectos o actividades.',
	);
	$manyActivities = [];
	for ($activityId = 1; $activityId <= 11; $activityId++) {
		$manyActivities[] = [
			'id_empleado' => 1,
			'uid' => 'ana',
			'nombre_empleado' => 'Ana Pérez',
			'costo_hora' => 100,
			'id_proyecto' => 7,
			'nombre_proyecto' => 'Proyecto Norte',
			'id_actividad' => $activityId,
			'nombre_actividad' => 'Actividad ' . $activityId,
			'total_minutos' => 10,
			'total_reportes' => 1,
			'reportes_fecha_referencia' => 0,
		];
	}
	$nestedTruncation = privateMethod($reportProvider, 'aggregate', [$manyActivities, '2025-01-15']);
	assertTrue(
		$nestedTruncation['truncado'] === true
			&& $nestedTruncation['proyectos'][0]['actividades_truncadas'] === true,
		'El recorte de actividades por proyecto debe activar el truncamiento global.',
	);
	$encodedReportContext = json_encode($reportContext, JSON_THROW_ON_ERROR);
	assertTrue(
		!preg_match('/\b(rfc|curp|numero_cuenta|fondo_ahorro|honorarios?)\b/i', $encodedReportContext),
		'Reportes no debe incluir datos fiscales, bancarios, ahorro ni honorarios.',
	);

	$employeeProviderReflection = new \ReflectionClass(
		\OCA\Empleados\Service\Ai\Context\EmpleadosFullContextProvider::class,
	);
	$employeeDependencies = array_map(
		static fn (\ReflectionParameter $parameter): string => strtolower(
			(string)$parameter->getType(),
		),
		$employeeProviderReflection->getConstructor()->getParameters(),
	);
	foreach (['clientes', 'honorarios', 'reportetiempo', 'actividades'] as $forbidden) {
		assertTrue(
			!str_contains(implode(' ', $employeeDependencies), $forbidden),
			"Empleados no debe depender de {$forbidden}.",
		);
	}
	$employeeProviderSource = file_get_contents(
		$root . '/lib/Service/Ai/Context/EmpleadosFullContextProvider.php',
	);
	preg_match_all('/\\$this->[A-Za-z]+Mapper->/', $employeeProviderSource, $employeeMapperCalls);
	assertTrue(
		count($employeeMapperCalls[0]) === 10
			&& !str_contains($employeeProviderSource, 'Mapper->findById'),
		'Empleados debe conservar un máximo fijo de diez consultas masivas y evitar consultas por empleado.',
	);
	assertTrue(
		strpos($employeeProviderSource, "['restriccion_dominio'] !== null")
			< strpos($employeeProviderSource, '$this->empleadosMapper->'),
		'Empleados debe rechazar otro dominio antes de ejecutar su primera consulta.',
	);

	$controllerSource = file_get_contents($root . '/lib/Controller/AiController.php');
	$permissionPosition = strpos($controllerSource, 'requireCanSeeAny');
	$sanitizePosition = strpos($controllerSource, 'sanitizeAuthorizedContext');
	$buildPosition = strpos($controllerSource, 'buildServerContext');
	assertTrue(
		$permissionPosition !== false
			&& $sanitizePosition !== false
			&& $buildPosition !== false
			&& $permissionPosition < $sanitizePosition
			&& $sanitizePosition < $buildPosition,
		'El permiso debe validarse antes del saneamiento y de construir contexto.',
	);
	assertTrue(
		str_contains($controllerSource, 'getAuthorizedScopeIds')
			&& str_contains($controllerSource, 'canSeeAny'),
		'Capabilities debe filtrar scopes por permisos.',
	);

	$reportProviderSource = file_get_contents(
		$root . '/lib/Service/Ai/Context/ReportesTiempoContextProvider.php',
	);
	assertTrue(
		substr_count($reportProviderSource, '$this->reportMapper->') === 2,
		'Reportes debe ejecutar exactamente dos consultas de dominio fijas.',
	);
	assertTrue(
		strpos($reportProviderSource, "['restriccion_dominio'] !== null")
			< strpos($reportProviderSource, '$this->reportMapper->'),
		'Reportes debe rechazar otro dominio antes de ejecutar consultas.',
	);
	$employeeScopeSource = file_get_contents(
		$root . '/lib/Service/Ai/Scope/EmpleadosCompletoScope.php',
	);
	assertTrue(
		str_contains($employeeScopeSource, 'pide nombre completo')
			&& str_contains($employeeScopeSource, 'reportes de tiempo'),
		'El scope debe aclarar personas ambiguas y separar otros submódulos.',
	);
	$registrySource = file_get_contents(
		$root . '/lib/Service/Ai/Scope/ContextScopeRegistry.php',
	);
	foreach ([
		'vacaciones-empleado',
		'empleado-laboral',
		'empleados-listado',
		'empleados-completo',
		'reportes-tiempo-admin',
	] as $scopeId) {
		assertTrue(
			str_contains($registrySource, "'{$scopeId}'"),
			"El registry debe conservar el scope {$scopeId}.",
		);
	}

	echo "AI server context contract isolated test: PASS\n";
}
