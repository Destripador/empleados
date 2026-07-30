<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Context;

use OCA\Empleados\Db\InventarioComputoMapper;
use OCA\Empleados\Db\aniversarioMapper;
use OCA\Empleados\Db\configuracionesMapper;
use OCA\Empleados\Db\empleadosMapper;
use OCA\Empleados\Db\empleadosorganigramaMapper;
use OCA\Empleados\Db\historialahorroMapper;
use OCA\Empleados\Db\historialausenciasMapper;
use OCA\Empleados\Db\historialvacacionesMapper;
use OCA\Empleados\Db\primavacacionalpagoMapper;
use OCA\Empleados\Db\userahorroMapper;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

final class EmpleadosFullContextProvider {
	private const MAX_BYTES = 524288;
	private const MAX_FILES = 200;
	private const MAX_NOTE_LENGTH = 5000;
	private const MAX_HISTORY_ITEMS = 100;
	private const MAX_ORGANIZATION_RELATIONS = 500;
	private bool $gestorLoaded = false;
	private ?string $gestor = null;

	public function __construct(
		private empleadosMapper $empleadosMapper,
		private InventarioComputoMapper $inventarioMapper,
		private historialvacacionesMapper $vacacionesMapper,
		private historialausenciasMapper $ausenciasMapper,
		private historialahorroMapper $ahorroMapper,
		private userahorroMapper $userAhorroMapper,
		private aniversarioMapper $aniversarioMapper,
		private empleadosorganigramaMapper $organigramaMapper,
		private primavacacionalpagoMapper $primaVacacionalPagoMapper,
		private configuracionesMapper $configuracionesMapper,
		private IRootFolder $rootFolder,
		private EmpleadosContextSelector $selector,
	) {
	}

	public function build(string $question, string $userId): array {
		$preselection = $this->selector->select($question, []);
		if ($preselection['restriccion_dominio'] !== null) {
			return [
				'contexto' => [
					'scope' => 'empleados-completo',
					'generado_en' => (new \DateTimeImmutable())->format(DATE_ATOM),
					'periodo' => null,
					'empleados_incluidos' => 0,
					'registros_incluidos' => 0,
					'truncado' => false,
					'secciones' => ['restriccion_dominio'],
				],
				'restriccion_dominio' => $preselection['restriccion_dominio'],
			];
		}

		$rows = $this->empleadosMapper->getAllForAiContext();
		$inventario = $this->groupInventory($this->inventarioMapper->getAllForAiContext());
		$vacationPeriods = $this->group($this->vacacionesMapper->getAllForAiContext(), 'id_empleado');
		$allAbsences = $this->ausenciasMapper->getAllForAiContext();
		$absencesByEmployee = $this->group($allAbsences, 'id_empleado');
		$savingsAccess = $this->indexLatest(
			$this->userAhorroMapper->getAllForAiContext(),
			'id_user',
		);
		$vacationRules = $this->index(
			$this->aniversarioMapper->GetAniversarios(),
			'numero_aniversario',
		);
		$organization = $this->organigramaMapper->GetOrganigrama();
		$vacationPremiumPayments = $this->group(
			$this->primaVacacionalPagoMapper->getAllForAiContext(),
			'id_empleado',
		);
		$namesById = [];
		$namesByUid = [];
		foreach ($rows as $row) {
			$name = $row['displayname'] ?? $row['uid'] ?? $row['Id_user'] ?? null;
			$namesById[(string)($row['Id_empleados'] ?? '')] = $name;
			$namesByUid[(string)($row['uid'] ?? $row['Id_user'] ?? '')] = $name;
		}
		$directory = [];
		$notesByEmployee = [];
		foreach ($rows as $row) {
			$id = (int)($row['Id_empleados'] ?? 0);
			$note = $this->plainText($row['Notas'] ?? null, self::MAX_NOTE_LENGTH);
			$fullNote = $this->plainText($row['Notas'] ?? null, PHP_INT_MAX) ?? '';
			$notesByEmployee[(string)$id] = [
				'contenido' => $note,
				'notas_truncadas' => mb_strlen($fullNote, 'UTF-8') > self::MAX_NOTE_LENGTH,
			];
			$equipment = ($inventario[(string)$id] ?? [])[0] ?? null;
			$employeeVacationPeriods = $vacationPeriods[(string)$id] ?? [];
			$employeeAbsences = $absencesByEmployee[(string)$id] ?? [];
			$currentAnniversary = $this->anniversaryNumber($row['Ingreso'] ?? null);
			$currentVacation = $this->vacationPeriod(
				$employeeVacationPeriods,
				$currentAnniversary,
			);
			$configuredVacationDays = $currentAnniversary === null
				? null
				: $this->number(
					$vacationRules[(string)$currentAnniversary]['dias'] ?? null,
				);
			$vacation = [
				'aniversario_actual' => $currentAnniversary,
				'dias_derecho' => $this->number($currentVacation['dias_derecho'] ?? null),
				'dias_derecho_configurados' => $configuredVacationDays,
				'dias_restantes' => $this->number($row['dias_disponibles'] ?? null),
				'dias_disfrutados' => $this->vacationDaysTaken($employeeAbsences, $currentAnniversary),
				'dias_acumulados' => $this->currentAccumulatedDays($currentVacation),
				'fecha_expiracion_acumulados' => $this->date($currentVacation['fecha_expiracion_acumulados'] ?? null),
				'prima_vacacional_solicitada' => isset($row['prima_vacacional_actual'])
					? (bool)$row['prima_vacacional_actual']
					: null,
			];
			$uid = (string)($row['uid'] ?? $row['Id_user'] ?? '');
			$savingsAccessRow = $savingsAccess[(string)$id] ?? [];
			$directory[] = $this->removeNulls([
				'referencias' => ['id_empleado' => $id, 'usuario' => $uid],
				'identidad' => [
					'nombre' => $this->firstNonEmptyString($row['displayname'] ?? null, $uid),
					'numero_empleado' => $row['Numero_empleado'] ?? null,
					'correo_corporativo' => $this->firstNonEmptyString(
						$row['correo_corporativo_primario'] ?? null,
						$row['correo_corporativo_sistema'] ?? null,
					),
				],
				'laboral' => [
					'fecha_ingreso' => $this->date($row['Ingreso'] ?? null),
					'antiguedad_anios' => $this->seniority($row['Ingreso'] ?? null),
					'estado' => $row['Estado'] ?? null,
				],
				'estructura' => [
					'area' => $row['area_nombre'] ?? null,
					'area_padre' => $row['area_padre_nombre'] ?? null,
					'puesto' => $row['puesto_nombre'] ?? null,
					'nivel_puesto' => $this->number($row['puesto_nivel'] ?? null),
					'gerente' => $this->relatedEmployeeName($row['Id_gerente'] ?? null, $namesByUid, $namesById),
					'socio' => $this->relatedEmployeeName($row['Id_socio'] ?? null, $namesByUid, $namesById),
					'equipo' => $row['equipo_laboral_nombre'] ?? null,
					'jefe_equipo' => $this->relatedEmployeeName(
						$row['jefe_equipo_referencia'] ?? null,
						$namesByUid,
						$namesById,
					),
				],
				'personal' => [
					'direccion' => $row['Direccion'] ?? null,
					'telefono' => $row['Telefono_contacto'] ?? null,
					'correo_contacto' => $row['Correo_contacto'] ?? null,
					'fecha_nacimiento' => $this->date($row['Fecha_nacimiento'] ?? null),
					'estado_civil' => $row['Estado_civil'] ?? null,
					'genero' => $row['Genero'] ?? null,
				],
				'fiscal' => [
					'rfc' => $row['Rfc'] ?? null,
					'curp' => $row['Curp'] ?? null,
					'imss' => $row['Imss'] ?? null,
				],
				'emergencia' => ['contacto' => $row['Contacto_emergencia'] ?? null, 'telefono' => $row['Numero_emergencia'] ?? null],
				'financiero' => ['sueldo' => $this->number($row['Sueldo'] ?? null), 'numero_cuenta' => $row['Numero_cuenta'] ?? null],
				'ahorro' => [
					'clave' => $row['Fondo_clave'] ?? null,
					'saldo_o_total_registrado' => $this->number($row['Fondo_ahorro'] ?? null),
					'puede_solicitar' => isset($savingsAccessRow['state'])
						? (string)$savingsAccessRow['state'] === '1'
						: null,
					'estado_acceso' => $this->savingsAccessStatus($savingsAccessRow['state'] ?? null),
				],
				'vacaciones' => $vacation,
				'sistemas' => ['equipo_asignado' => $this->equipment($equipment)],
				'notas' => [
					'tiene_notas' => $note !== null,
					'contenido_truncado_en_expediente' => mb_strlen(
						$fullNote,
						'UTF-8',
					) > self::MAX_NOTE_LENGTH,
				],
				'metadatos_expediente' => [
					'fecha_alta' => $this->date($row['created_at'] ?? null),
					'ultima_actualizacion' => $this->date($row['updated_at'] ?? null),
					'usuario_nextcloud_encontrado' => isset($row['uid']) && (string)$row['uid'] !== '',
					'tiene_notas' => $note !== null,
				],
			]);
		}

		$selection = $this->selector->select($question, $directory);
		$ids = $selection['employee_ids'];
		$sections = $selection['include_all_sections']
			? [
				'laboral',
				'personal',
				'fiscal',
				'financiero',
				'vacaciones',
				'ausencias',
				'ahorro',
				'sistemas',
				'notas',
				'archivos',
			]
			: $selection['sections'];
		$details = $this->buildDetails(
			$ids,
			$sections,
			$directory,
			$inventario,
			$vacationPeriods,
			$absencesByEmployee,
			$vacationPremiumPayments,
			$notesByEmployee,
		);
		$priorityEmployees = array_values(array_filter(
			$directory,
			static fn (array $employee): bool => in_array(
				(int)($employee['referencias']['id_empleado'] ?? 0),
				$ids,
				true,
			),
		));
		$organizationContext = $this->organizationContext($organization, $namesById);
		$contextTruncated = $organizationContext['truncado']
			|| $this->detailsAreTruncated($details);
		$recordsIncluded = count($directory)
			+ $this->countDetailRecords($details)
			+ count($organizationContext['relaciones']);
		$summary = $this->buildSummary($directory, count($details));
		$priorityFacts = $ids === []
			? $this->summaryFacts($directory, $summary, $sections)
			: $this->priorityFacts($priorityEmployees, $sections);
		$preparedAnswer = $this->preparedAnswer(
			$question,
			$priorityEmployees,
			$directory,
			$summary,
		);
		$context = [
			'contexto' => [
				'scope' => 'empleados-completo',
				'generado_en' => (new \DateTimeImmutable())->format(DATE_ATOM),
				'periodo' => null,
				'empleados_incluidos' => count($directory),
				'registros_incluidos' => $recordsIncluded,
				'truncado' => $contextTruncated,
				'secciones' => array_values(array_unique(array_merge(
					['identidad', 'laboral', 'estructura', 'organigrama', 'personal', 'fiscal', 'financiero', 'ahorro', 'vacaciones', 'sistemas', 'notas', 'metadatos_expediente'],
					$sections,
				))),
			],
			'restriccion_dominio' => $selection['restriccion_dominio'],
			'datos_prioritarios' => $priorityEmployees,
			'hechos_prioritarios' => $priorityFacts,
			'respuesta_preparada' => $preparedAnswer,
			'resumen' => $summary,
			'directorio' => $directory,
			'organigrama' => $organizationContext,
			'expedientes_detallados' => $details,
		];
		if ($context['restriccion_dominio'] === null) {
			unset($context['restriccion_dominio']);
		}
		if ($context['respuesta_preparada'] === null) {
			unset($context['respuesta_preparada']);
		}
		if (in_array('ausencias', $sections, true) && $ids === []) {
			$context['datos_globales_seleccionados']['ausencias'] = ['total' => count($allAbsences), 'registros' => array_slice($allAbsences, 0, self::MAX_HISTORY_ITEMS), 'truncado' => count($allAbsences) > self::MAX_HISTORY_ITEMS];
			$context['contexto']['registros_incluidos'] += min(count($allAbsences), self::MAX_HISTORY_ITEMS);
			$context['contexto']['truncado'] = $context['contexto']['truncado']
				|| count($allAbsences) > self::MAX_HISTORY_ITEMS;
		}
		return $this->enforceLimit($context);
	}

	private function priorityFacts(array $employees, array $sections): array {
		$facts = [];
		foreach ($employees as $employee) {
			$name = (string)($employee['identidad']['nombre'] ?? 'empleado seleccionado');
			$facts[] = "Empleado seleccionado: {$name}.";

			if (in_array('laboral', $sections, true)) {
				foreach ([
					'Puesto registrado' => $employee['estructura']['puesto'] ?? null,
					'Área registrada' => $employee['estructura']['area'] ?? null,
					'Fecha de ingreso registrada' => $employee['laboral']['fecha_ingreso'] ?? null,
					'Estado laboral registrado' => $employee['laboral']['estado'] ?? null,
					'Número de empleado registrado' => $employee['identidad']['numero_empleado'] ?? null,
				] as $label => $value) {
					if ($value !== null && $value !== '') {
						$facts[] = "{$label} para {$name}: {$value}.";
					}
				}
			}

			if (in_array('financiero', $sections, true)) {
				$salary = $employee['financiero']['sueldo'] ?? null;
				$facts[] = $salary === null
					? "No hay sueldo registrado para {$name}."
					: "El sueldo registrado de {$name} es exactamente {$salary}; "
						. 'el contexto no indica moneda.';
			}

			if (in_array('vacaciones', $sections, true)) {
				foreach ([
					'Días de vacaciones restantes' => $employee['vacaciones']['dias_restantes'] ?? null,
					'Días de vacaciones de derecho' => $employee['vacaciones']['dias_derecho'] ?? null,
					'Días de vacaciones disfrutados' => $employee['vacaciones']['dias_disfrutados'] ?? null,
					'Días de vacaciones acumulados' => $employee['vacaciones']['dias_acumulados'] ?? null,
				] as $label => $value) {
					if ($value !== null) {
						$facts[] = "{$label} para {$name}: exactamente {$value}.";
					}
				}
			}
		}
		return $facts;
	}

	private function preparedAnswer(
		string $question,
		array $priorityEmployees,
		array $directory,
		array $summary,
	): ?string {
		$currentQuestion = explode(
			"\n\nREFERENCIA DE CONTINUIDAD:",
			$question,
			2,
		)[0];
		$normalized = $this->normalizeQuestionText($currentQuestion);

		if (count($priorityEmployees) === 1) {
			$employee = $priorityEmployees[0];
			$name = $this->safeAnswerText(
				(string)($employee['identidad']['nombre'] ?? 'el empleado seleccionado'),
			);
			if (preg_match(
				'/^(?:y )?(?:cual|cuanto) (?:es )?(?:el |su )?'
					. '(?:sueldo|salario)(?: (?:actual )?de (?<reference>.+))?$/',
				$normalized,
				$matches,
			) === 1
				&& $this->isPreparedReference(
					$matches['reference'] ?? null,
					$employee,
				)) {
				$salary = $employee['financiero']['sueldo'] ?? null;
				return $salary === null
					? "No hay sueldo registrado para {$name}."
					: "El sueldo registrado de {$name} es {$salary}. "
						. 'El contexto no indica moneda.';
			}
			if (preg_match(
				'/^(?:y )?cuantos? dias de vacaciones '
					. '(?:le )?(?:quedan|restan)(?: a (?<reference>.+))?$/',
				$normalized,
				$matches,
			) === 1
				&& $this->isPreparedReference(
					$matches['reference'] ?? null,
					$employee,
				)) {
				$days = $employee['vacaciones']['dias_restantes'] ?? null;
				return $days === null
					? "No hay un dato de días de vacaciones restantes para {$name}."
					: "{$name} tiene {$days} días de vacaciones restantes.";
			}
			if (preg_match(
				'/^que (?:informacion|datos|campos) faltan? en el '
					. 'expediente(?: administrativo)? de (?<reference>.+)$/',
				$normalized,
				$matches,
			) === 1
				&& $this->isPreparedReference(
					$matches['reference'] ?? null,
					$employee,
				)) {
				$missing = $this->missingEmployeeFields($employee);
				return $missing === []
					? "No se identificaron campos administrativos faltantes en el "
						. "directorio de {$name}."
					: "Información faltante en el directorio administrativo de "
						. "{$name}:\n\n- "
						. implode("\n- ", $missing);
			}
		}

		if ($priorityEmployees === []
			&& preg_match(
				'/^(?:que|cuales) empleados no tienen (?:inventario|'
					. 'equipo(?: de computo)?) asignado$/',
				$normalized,
			) === 1) {
			$names = [];
			foreach ($directory as $employee) {
				if (!isset($employee['sistemas']['equipo_asignado'])) {
					$names[] = $this->safeAnswerText(
						(string)($employee['identidad']['nombre'] ?? 'Empleado sin nombre'),
					);
				}
			}
			$total = (int)($summary['sin_equipo_asignado'] ?? count($names));
			if ($names === []) {
				return 'No hay empleados sin inventario o equipo de cómputo asignado.';
			}
			return "Hay {$total} empleados sin inventario o equipo de cómputo "
				. "asignado:\n\n- "
				. implode("\n- ", $names);
		}

		if ($priorityEmployees === []
			&& preg_match(
				'/^quien(?:es)? tiene(?:n)? (?:la )?(?:mas|mayor) antiguedad$/',
				$normalized,
			) === 1) {
			$maximum = $summary['mayor_antiguedad'] ?? [];
			if ($maximum === []) {
				return 'No hay datos de antigüedad registrados.';
			}
			$years = $maximum[0]['antiguedad_anios'] ?? null;
			$names = array_values(array_filter(array_map(
				fn (array $item): ?string => isset($item['nombre'])
					? $this->safeAnswerText((string)$item['nombre'])
					: null,
				$maximum,
			), 'is_string'));
			$lead = count($names) === 1
				? "{$names[0]} tiene la mayor antigüedad registrada"
				: 'Hay un empate en la mayor antigüedad registrada: '
					. implode(', ', $names);
			return "{$lead}, con {$years} años. Se comparó la antigüedad "
				. 'calculada por PHP a partir de la fecha de ingreso.';
		}

		return null;
	}

	private function isPreparedReference(
		?string $reference,
		array $employee,
	): bool {
		if ($reference === null || $reference === '') {
			return true;
		}
		$knownReferences = array_values(array_filter(array_map(
			fn (mixed $value): string => $this->normalizeQuestionText((string)$value),
			[
				$employee['identidad']['nombre'] ?? null,
				$employee['identidad']['numero_empleado'] ?? null,
				$employee['referencias']['usuario'] ?? null,
			],
		)));
		return in_array($reference, $knownReferences, true);
	}

	private function normalizeQuestionText(string $value): string {
		$value = mb_strtolower($value, 'UTF-8');
		$ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		$value = preg_replace(
			'/[^a-z0-9@._ -]+/',
			'',
			$ascii !== false ? $ascii : $value,
		) ?? '';
		return preg_replace('/\\s+/', ' ', trim($value)) ?? '';
	}

	private function missingEmployeeFields(array $employee): array {
		$fields = [
			'Número de empleado' => $employee['identidad']['numero_empleado'] ?? null,
			'Correo corporativo' => $employee['identidad']['correo_corporativo'] ?? null,
			'Fecha de ingreso' => $employee['laboral']['fecha_ingreso'] ?? null,
			'Estado laboral' => $employee['laboral']['estado'] ?? null,
			'Área' => $employee['estructura']['area'] ?? null,
			'Puesto' => $employee['estructura']['puesto'] ?? null,
			'Gerente' => $employee['estructura']['gerente'] ?? null,
			'Socio' => $employee['estructura']['socio'] ?? null,
			'Equipo laboral' => $employee['estructura']['equipo'] ?? null,
			'Dirección' => $employee['personal']['direccion'] ?? null,
			'Teléfono de contacto' => $employee['personal']['telefono'] ?? null,
			'Correo de contacto' => $employee['personal']['correo_contacto'] ?? null,
			'Fecha de nacimiento' => $employee['personal']['fecha_nacimiento'] ?? null,
			'RFC' => $employee['fiscal']['rfc'] ?? null,
			'CURP' => $employee['fiscal']['curp'] ?? null,
			'IMSS' => $employee['fiscal']['imss'] ?? null,
			'Sueldo' => $employee['financiero']['sueldo'] ?? null,
			'Número de cuenta' => $employee['financiero']['numero_cuenta'] ?? null,
			'Inventario asignado' => $employee['sistemas']['equipo_asignado'] ?? null,
		];
		return array_keys(array_filter(
			$fields,
			static fn (mixed $value): bool => $value === null || $value === '',
		));
	}

	private function safeAnswerText(string $value): string {
		$value = preg_replace('/\\s+/u', ' ', trim(strip_tags($value))) ?? '';
		return preg_replace('/([\\\\`*_[\\]{}()#+.!|>])/u', '\\\\$1', $value) ?? '';
	}

	private function summaryFacts(
		array $directory,
		array $summary,
		array $sections,
	): array {
		$facts = [];
		if (in_array('sistemas', $sections, true)) {
			$withoutInventory = array_values(array_filter(
				array_map(
					static fn (array $employee): ?string =>
						!isset($employee['sistemas']['equipo_asignado'])
							? ($employee['identidad']['nombre'] ?? null)
							: null,
					$directory,
				),
				static fn (mixed $name): bool => is_string($name) && $name !== '',
			));
			$facts[] = 'Total exacto de empleados sin inventario o equipo de '
				. 'cómputo asignado: '
				. ($summary['sin_equipo_asignado'] ?? 0)
				. '.';
			$facts[] = $withoutInventory === []
				? 'No hay empleados sin inventario o equipo de cómputo asignado.'
				: 'Empleados sin inventario o equipo de cómputo asignado: '
					. implode(', ', $withoutInventory)
					. '.';
		}
		if (in_array('laboral', $sections, true)) {
			$facts[] = 'Empleados activos: '
				. ($summary['empleados_activos'] ?? 0)
				. '; empleados inactivos: '
				. ($summary['empleados_inactivos'] ?? 0)
				. '.';
			$maximumSeniority = $summary['mayor_antiguedad'] ?? [];
			if ($maximumSeniority !== []) {
				$years = $maximumSeniority[0]['antiguedad_anios'] ?? null;
				$names = array_values(array_filter(array_map(
					static fn (array $item): mixed => $item['nombre'] ?? null,
					$maximumSeniority,
				), 'is_string'));
				$facts[] = 'Mayor antigüedad registrada: exactamente '
					. $years
					. ' años. Empleados empatados: '
					. implode(', ', $names)
					. '.';
			}
		}
		if (in_array('financiero', $sections, true)
			&& isset($summary['sueldos'])) {
			$facts[] = 'Sueldos registrados, sin moneda indicada: mínimo '
				. $summary['sueldos']['minimo']
				. ', máximo '
				. $summary['sueldos']['maximo']
				. ', promedio '
				. $summary['sueldos']['promedio']
				. '.';
		}
		if (in_array('vacaciones', $sections, true)) {
			$facts[] = 'Empleados con vacaciones acumuladas: '
				. ($summary['empleados_con_vacaciones_acumuladas'] ?? 0)
				. '.';
		}
		return $facts;
	}

	private function buildDetails(
		array $ids,
		array $sections,
		array $directory,
		array $inventario,
		array $vacationPeriods,
		array $absencesByEmployee,
		array $vacationPremiumPayments,
		array $notesByEmployee,
	): array {
		if ($ids === []) {
			return [];
		}
		$vacations = in_array('vacaciones', $sections, true) ? $vacationPeriods : [];
		$absences = in_array('ausencias', $sections, true) ? $absencesByEmployee : [];
		$savings = in_array('ahorro', $sections, true) ? $this->group($this->ahorroMapper->getAllForAiContext(), 'id_empleado') : [];
		$byId = [];
		foreach ($directory as $employee) {
			$byId[(string)$employee['referencias']['id_empleado']] = $employee;
		}
		$details = [];
		foreach ($ids as $id) {
			$employee = $byId[(string)$id] ?? null;
			if ($employee === null) {
				continue;
			}
			$detail = [
				'empleado' => [
					'referencias' => $employee['referencias'],
					'identidad' => $employee['identidad'],
				],
			];
			if (in_array('vacaciones', $sections, true)) {
				$periods = $this->vacationHistory(
					$vacations[(string)$id] ?? [],
					$absencesByEmployee[(string)$id] ?? [],
				);
				$payments = $vacationPremiumPayments[(string)$id] ?? [];
				$detail['vacaciones'] = [
					'periodos' => array_slice($periods, 0, self::MAX_HISTORY_ITEMS),
					'pagos_prima_vacacional' => array_slice(
						$payments,
						0,
						self::MAX_HISTORY_ITEMS,
					),
					'historial_truncado' => count($periods) > self::MAX_HISTORY_ITEMS
						|| count($payments) > self::MAX_HISTORY_ITEMS,
				];
			}
			if (in_array('ausencias', $sections, true)) {
				$detail['ausencias'] = ['historial' => array_slice($absences[(string)$id] ?? [], 0, self::MAX_HISTORY_ITEMS), 'historial_truncado' => count($absences[(string)$id] ?? []) > self::MAX_HISTORY_ITEMS];
			}
			if (in_array('ahorro', $sections, true)) {
				$detail['ahorro'] = ['movimientos' => array_slice($savings[(string)$id] ?? [], 0, self::MAX_HISTORY_ITEMS), 'historial_truncado' => count($savings[(string)$id] ?? []) > self::MAX_HISTORY_ITEMS];
			}
			if (in_array('sistemas', $sections, true)) {
				$detail['sistemas'] = ['equipos' => array_map(fn (array $row): array => $this->equipment($row), $inventario[(string)$id] ?? [])];
			}
			if (in_array('notas', $sections, true)) {
				$detail['notas'] = $notesByEmployee[(string)$id] ?? [];
			}
			if (in_array('archivos', $sections, true)) {
				$detail += $this->files($employee['referencias']['usuario'] ?? '', $employee['identidad']['nombre'] ?? '');
			}
			$details[] = $detail;
		}
		return $details;
	}

	private function files(string $uid, string $name): array {
		try {
			if (!$this->gestorLoaded) {
				$value = $this->configuracionesMapper->GetGestor()[0]['Data'] ?? null;
				$this->gestor = is_string($value) && $value !== '' ? $value : null;
				$this->gestorLoaded = true;
			}
			$gestor = $this->gestor;
			if (!is_string($gestor) || $gestor === '') {
				return ['archivos' => [], 'archivos_truncados' => false];
			}
			$root = $this->rootFolder->getUserFolder($gestor);
			$path = 'EMPLEADOS/' . $uid . ' - ' . mb_strtoupper($name, 'UTF-8');
			if (!$root->nodeExists($path)) {
				return ['archivos' => [], 'archivos_truncados' => false];
			}
			$entries = [];
			$this->walk($root->get($path), '', $entries);
			return ['archivos' => array_slice($entries, 0, self::MAX_FILES), 'archivos_truncados' => count($entries) > self::MAX_FILES];
		} catch (\Throwable) {
			return ['archivos' => [], 'archivos_truncados' => false, 'archivos_no_disponibles' => true];
		}
	}

	private function walk(object $node, string $relative, array &$entries): void {
		if (count($entries) > self::MAX_FILES) {
			return;
		}
		foreach ($node instanceof Folder ? $node->getDirectoryListing() : [] as $child) {
			$path = ltrim($relative . '/' . $child->getName(), '/');
			$entries[] = ['nombre' => $child->getName(), 'ruta_relativa' => $path, 'tipo' => $child->getMimetype(), 'tamano' => $child->getSize(), 'fecha_modificacion' => date('c', $child->getMTime()), 'es_carpeta' => $child instanceof Folder];
			if ($child instanceof Folder) {
				$this->walk($child, $path, $entries);
			}
			if (count($entries) > self::MAX_FILES) {
				return;
			}
		}
	}

	private function enforceLimit(array $context): array {
		if ($this->size($context) <= self::MAX_BYTES) {
			return $context;
		}
		foreach ($context['directorio'] as &$employee) {
			unset($employee['notas']);
		}
		unset($employee);
		foreach ($context['expedientes_detallados'] as &$detail) {
			if (isset($detail['archivos'])) {
				$detail['archivos'] = array_slice($detail['archivos'], 0, 25);
				$detail['archivos_truncados'] = true;
			}
			foreach (['vacaciones', 'ausencias', 'ahorro'] as $section) {
				foreach (['periodos', 'pagos_prima_vacacional', 'historial', 'movimientos'] as $list) {
					if (isset($detail[$section][$list])) {
						$detail[$section][$list] = array_slice($detail[$section][$list], 0, 20);
						$detail[$section]['historial_truncado'] = true;
					}
				}
			}
		}
		unset($detail);
		$context['contexto']['truncado'] = true;
		$context['contexto']['registros_incluidos'] = $this->contextRecordCount($context);
		if ($this->size($context) > self::MAX_BYTES) {
			throw new \RuntimeException('El contexto generado supera el tamaño permitido.');
		}
		return $context;
	}

	private function size(array $context): int {
		return strlen(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	}
	private function index(array $rows, string $key): array {
		$result = [];
		foreach ($rows as $row) {
			$result[(string)($row[$key] ?? '')] = $row;
		}
		return $result;
	}
	private function indexLatest(array $rows, string $key): array {
		$result = [];
		foreach ($rows as $row) {
			$index = (string)($row[$key] ?? '');
			if ($index !== '' && !array_key_exists($index, $result)) {
				$result[$index] = $row;
			}
		}
		return $result;
	}
	private function group(array $rows, string $key): array {
		$result = [];
		foreach ($rows as $row) {
			$result[(string)($row[$key] ?? '')][] = $this->removeNulls($row);
		}
		return $result;
	}
	private function groupInventory(array $rows): array {
		$result = [];
		foreach ($rows as $row) {
			$id = (int)($row['id_empleado'] ?? $row['empleado_id'] ?? 0);
			if ($id <= 0) {
				continue;
			}
			$result[(string)$id][] = $this->removeNulls($row);
		}
		return $result;
	}
	private function equipment(?array $row): ?array {
		return $row === null ? null : $this->removeNulls([
			'id' => $row['id_equipo'] ?? null,
			'nombre_dispositivo' => $row['nombre_dispositivo'] ?? null,
			'nombre_sistema' => $row['nombre_sistema'] ?? null,
			'numero_serie' => $row['numero_serie'] ?? null,
			'marca' => $row['marca'] ?? null,
			'modelo' => $row['modelo'] ?? null,
			'procesador' => $row['procesador'] ?? null,
			'ram' => $row['ram'] ?? null,
			'disco_duro' => $row['disco_duro'] ?? null,
			'tipo' => $row['tipo'] ?? null,
			'estado' => $row['estado'] ?? null,
			'informacion' => $this->plainText($row['info'] ?? null, self::MAX_NOTE_LENGTH),
			'fecha_alta' => $this->date($row['created_at'] ?? null),
			'ultima_actualizacion' => $this->date($row['updated_at'] ?? null),
		]);
	}
	private function removeNulls(array $value): array {
		foreach ($value as $key => $item) {
			if (is_array($item)) {
				$item = $this->removeNulls($item);
			}
			if ($item === null || $item === []) {
				unset($value[$key]);
			} else {
				$value[$key] = $item;
			}
		}
		return $value;
	}
	private function number(mixed $value): int|float|null {
		return is_numeric($value) ? (str_contains((string)$value, '.') ? (float)$value : (int)$value) : null;
	}
	private function date(mixed $value): ?string {
		if (!is_string($value) || trim($value) === '') {
			return null;
		}
		$timestamp = strtotime($value);
		return $timestamp === false ? null : date('Y-m-d', $timestamp);
	}
	private function seniority(mixed $value): ?int {
		$date = $this->date($value);
		if ($date === null) {
			return null;
		}
		$start = new \DateTimeImmutable($date);
		$today = new \DateTimeImmutable('today');
		return $start <= $today ? $start->diff($today)->y : null;
	}
	private function plainText(mixed $value, int $limit): ?string {
		if (!is_string($value) || trim($value) === '') {
			return null;
		}
		return mb_substr(trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, $limit, 'UTF-8');
	}

	private function vacationDaysTaken(array $absences, int|float|null $anniversary): float {
		if ($anniversary === null) {
			return 0.0;
		}
		$total = 0.0;
		foreach ($absences as $absence) {
			if ((int)($absence['id_aniversario'] ?? -1) !== (int)$anniversary
				|| in_array((int)($absence['a_gerente'] ?? 0), [2, 3], true)
				|| in_array((int)($absence['a_socio'] ?? 0), [2, 3], true)
				|| (int)($absence['solicitar_prima_vacacional'] ?? 0) !== 1) {
				continue;
			}
			$total += max(
				0.0,
				(float)($absence['dias_solicitados'] ?? 0)
					- (float)($absence['dias_de_acumulado'] ?? 0),
			);
		}
		return $total;
	}

	private function currentAccumulatedDays(array $vacation): int|float|null {
		$days = $this->number($vacation['dias_acumulados_restantes'] ?? null);
		$expiration = $this->date($vacation['fecha_expiracion_acumulados'] ?? null);
		if ($days === null || $expiration === null) {
			return $days;
		}
		return $expiration >= (new \DateTimeImmutable('today'))->format('Y-m-d') ? $days : 0;
	}

	private function anniversaryNumber(mixed $startDate): ?int {
		$date = $this->date($startDate);
		if ($date === null) {
			return null;
		}
		$start = new \DateTimeImmutable($date);
		$today = new \DateTimeImmutable('today');
		return $start <= $today ? $start->diff($today)->y : null;
	}

	private function vacationPeriod(array $periods, ?int $anniversary): array {
		if ($anniversary === null) {
			return [];
		}
		foreach ($periods as $period) {
			if ((int)($period['numero_aniversario'] ?? -1) === $anniversary) {
				return $period;
			}
		}
		return [];
	}

	private function vacationHistory(array $periods, array $absences): array {
		$result = [];
		foreach ($periods as $period) {
			$anniversary = $this->number($period['numero_aniversario'] ?? null);
			$daysEntitled = $this->number($period['dias_derecho'] ?? null);
			$daysTaken = $this->vacationDaysTaken($absences, $anniversary);
			$result[] = $this->removeNulls([
				'numero_aniversario' => $anniversary,
				'periodo_inicio' => $this->date($period['periodo_inicio'] ?? null),
				'periodo_fin' => $this->date($period['periodo_fin'] ?? null),
				'dias_derecho' => $daysEntitled,
				'dias_disfrutados' => $daysTaken,
				'dias_restantes' => $daysEntitled === null
					? null
					: $daysEntitled - $daysTaken,
				'dias_acumulados_otorgados' => $this->number($period['dias_acumulados'] ?? null),
				'dias_acumulados_restantes' => $this->number($period['dias_acumulados_restantes'] ?? null),
				'fecha_expiracion_acumulados' => $this->date($period['fecha_expiracion_acumulados'] ?? null),
				'asignado_manualmente' => isset($period['asignado_manualmente'])
					? (bool)$period['asignado_manualmente']
					: null,
			]);
		}
		return $result;
	}

	private function savingsAccessStatus(mixed $state): ?string {
		if ($state === null) {
			return null;
		}
		return match ((string)$state) {
			'1' => 'habilitado',
			'2' => 'pendiente',
			default => 'no_habilitado',
		};
	}

	private function organizationContext(array $rows, array $namesById): array {
		$relations = [];
		foreach ($rows as $row) {
			$managerId = (int)($row['id_empleado'] ?? 0);
			$dependentId = (int)($row['id_dependiente'] ?? 0);
			if ($managerId <= 0 || $dependentId <= 0) {
				continue;
			}
			$relations[] = $this->removeNulls([
				'jefe' => [
					'id_empleado' => $managerId,
					'nombre' => $namesById[(string)$managerId] ?? null,
				],
				'dependiente' => [
					'id_empleado' => $dependentId,
					'nombre' => $namesById[(string)$dependentId] ?? null,
				],
			]);
		}
		return [
			'relaciones' => array_slice(
				$relations,
				0,
				self::MAX_ORGANIZATION_RELATIONS,
			),
			'truncado' => count($relations) > self::MAX_ORGANIZATION_RELATIONS,
		];
	}

	private function firstNonEmptyString(mixed ...$values): ?string {
		foreach ($values as $value) {
			if (is_string($value) && trim($value) !== '') {
				return trim($value);
			}
		}
		return null;
	}

	private function relatedEmployeeName(mixed $reference, array $namesByUid, array $namesById): ?string {
		$key = trim((string)($reference ?? ''));
		if ($key === '') {
			return null;
		}
		$name = $namesByUid[$key] ?? $namesById[$key] ?? null;
		return is_string($name) && trim($name) !== '' ? trim($name) : null;
	}

	private function buildSummary(array $directory, int $detailCount): array {
		$summary = [
			'total_empleados' => count($directory),
			'empleados_activos' => 0,
			'empleados_inactivos' => 0,
			'estado_no_definido' => 0,
			'por_area' => [],
			'por_puesto' => [],
			'por_gerente' => [],
			'sin_equipo_asignado' => 0,
			'sin_equipo_laboral' => 0,
			'sin_inventario_asignado' => 0,
			'sin_numero_empleado' => 0,
			'empleados_con_vacaciones_acumuladas' => 0,
			'expedientes_detallados' => $detailCount,
		];
		$salaryValues = [];
		$seniorities = [];
		foreach ($directory as $employee) {
			$state = $employee['laboral']['estado'] ?? null;
			if ((string)$state === '1' || $state === true) {
				$summary['empleados_activos']++;
			} elseif ((string)$state === '0' || $state === false) {
				$summary['empleados_inactivos']++;
			} else {
				$summary['estado_no_definido']++;
			}
			foreach (['area' => 'por_area', 'puesto' => 'por_puesto', 'gerente' => 'por_gerente'] as $field => $summaryKey) {
				$value = trim((string)($employee['estructura'][$field] ?? ''));
				$value = $value !== '' ? $value : 'Sin asignar';
				$summary[$summaryKey][$value] = ($summary[$summaryKey][$value] ?? 0) + 1;
			}
			if (!isset($employee['sistemas']['equipo_asignado'])) {
				$summary['sin_equipo_asignado']++;
				$summary['sin_inventario_asignado']++;
			}
			if (!isset($employee['estructura']['equipo'])) {
				$summary['sin_equipo_laboral']++;
			}
			if (empty($employee['identidad']['numero_empleado'])) {
				$summary['sin_numero_empleado']++;
			}
			if ((float)($employee['vacaciones']['dias_acumulados'] ?? 0) > 0) {
				$summary['empleados_con_vacaciones_acumuladas']++;
			}
			if (isset($employee['financiero']['sueldo'])) {
				$salaryValues[] = (float)$employee['financiero']['sueldo'];
			}
			if (isset($employee['laboral']['antiguedad_anios'])) {
				$seniorities[] = [
					'nombre' => $employee['identidad']['nombre'] ?? null,
					'antiguedad_anios' => (int)$employee['laboral']['antiguedad_anios'],
				];
			}
		}
		if ($salaryValues !== []) {
			$summary['sueldos'] = [
				'minimo' => min($salaryValues),
				'maximo' => max($salaryValues),
				'promedio' => round(array_sum($salaryValues) / count($salaryValues), 2),
			];
		}
		if ($seniorities !== []) {
			usort($seniorities, static fn (array $a, array $b): int => $a['antiguedad_anios'] <=> $b['antiguedad_anios']);
			$minimum = $seniorities[0]['antiguedad_anios'];
			$maximum = $seniorities[array_key_last($seniorities)]['antiguedad_anios'];
			$summary['menor_antiguedad'] = array_values(array_filter(
				$seniorities,
				static fn (array $item): bool => $item['antiguedad_anios'] === $minimum,
			));
			$summary['mayor_antiguedad'] = array_values(array_filter(
				$seniorities,
				static fn (array $item): bool => $item['antiguedad_anios'] === $maximum,
			));
		}
		return $summary;
	}

	private function countDetailRecords(array $details): int {
		$count = 0;
		foreach ($details as $detail) {
			$count += count($detail['vacaciones']['periodos'] ?? []);
			$count += count($detail['vacaciones']['pagos_prima_vacacional'] ?? []);
			$count += count($detail['ausencias']['historial'] ?? []);
			$count += count($detail['ahorro']['movimientos'] ?? []);
			$count += count($detail['sistemas']['equipos'] ?? []);
			$count += count($detail['archivos'] ?? []);
		}
		return $count;
	}

	private function detailsAreTruncated(array $details): bool {
		foreach ($details as $detail) {
			if (($detail['archivos_truncados'] ?? false) === true) {
				return true;
			}
			if (($detail['notas']['notas_truncadas'] ?? false) === true) {
				return true;
			}
			foreach (['vacaciones', 'ausencias', 'ahorro'] as $section) {
				if (($detail[$section]['historial_truncado'] ?? false) === true) {
					return true;
				}
			}
		}
		return false;
	}

	private function contextRecordCount(array $context): int {
		$count = count($context['directorio'] ?? [])
			+ $this->countDetailRecords($context['expedientes_detallados'] ?? []);
		$count += count($context['organigrama']['relaciones'] ?? []);
		$count += count($context['datos_globales_seleccionados']['ausencias']['registros'] ?? []);
		return $count;
	}
}
