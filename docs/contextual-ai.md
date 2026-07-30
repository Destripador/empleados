# Asistente contextual de IA

El asistente contextual permite hacer preguntas sobre los datos que ya están
visibles en una vista de la aplicación. Cada vista usa un **scope** que define
qué datos puede recibir el backend, cómo se sanean y qué instrucciones se
envían al proveedor de IA.

Un scope no concede acceso a datos. La vista debe tener los datos disponibles
por los mecanismos y permisos habituales de la aplicación antes de construir
el contexto.

## Arquitectura

```text
Vista Vue
→ ContextAssistant
→ AiController
→ ContextValidator
→ ContextScopeRegistry
→ Scope
→ ContextAiService
→ Proveedor de IA de Nextcloud
```

Las responsabilidades están separadas de la siguiente manera:

| Componente | Responsabilidad |
| --- | --- |
| Vista Vue | Construye manualmente un contexto visible o, para scopes de servidor, únicamente filtros escalares controlados; también define la clave de conversación. |
| `ContextAssistant` | Consulta capacidades, muestra la interfaz y envía `scope`, `question`, `context` e historial breve en memoria. |
| `AiController` | Exige una sesión autenticada, conserva las respuestas HTTP seguras y coordina la validación y el proveedor. |
| `ContextValidator` | Aplica límites comunes a la pregunta y al tamaño del contexto, resuelve el scope y delega el saneamiento. |
| `ContextScopeRegistry` | Mantiene el registro explícito de identificadores permitidos y resuelve su implementación desde el contenedor. |
| Scope | Declara la descripción e instrucciones de la vista y reconstruye una lista blanca de datos con tipos estrictos. |
| `ContextAiService` | Compone las reglas globales y específicas y usa el proveedor de IA compatible de Nextcloud. |

Los endpoints son compartidos por todos los scopes:

```text
GET  /apps/empleados/api/ai/capabilities
POST /apps/empleados/api/ai/ask
```

`capabilities` solo anuncia los identificadores registrados cuando existe un
proveedor disponible. El navegador envía un identificador de scope, nunca un
nombre de clase PHP. `ContextScopeRegistry` rechaza cualquier identificador que
no aparezca en su registro explícito.

Los scopes iniciales son:

- `vacaciones-empleado`: resumen de vacaciones de un empleado y un periodo
  vacacional.
- `empleado-laboral`: información laboral, organizacional, vacacional y del
  equipo asignado del empleado mostrado.
- `empleados-listado`: directorio laboral de los empleados cargados en el
  módulo.
- `empleados-completo`: directorio y expedientes administrativos construidos
  completamente en el servidor.
- `reportes-tiempo-admin`: análisis administrativo de reportes, cumplimiento,
  actividades, proyectos y costos del periodo visible, construido en servidor.

## Scopes con contexto de servidor

Un scope que implementa `ServerContextScopeInterface` recibe filtros, no filas:

```php
public function sanitizeParameters(array $parameters): array;

public function buildServerContext(
	string $question,
	string $userId,
	array $parameters,
): array;
```

El orden del endpoint es estricto: resuelve el scope, comprueba sus permisos,
sanea los parámetros, construye el contexto mediante consultas PHP y por
último llama al proveedor. Los parámetros tienen un máximo de 16 KB; los
contextos visibles conservan el máximo general de 64 KB.

Todo contexto generado en servidor incluye `contexto.scope`,
`contexto.generado_en`, `contexto.periodo`, conteos, `contexto.truncado` y las
secciones incorporadas. No contiene rutas físicas ni datos del proveedor.

## Historial conversacional

El endpoint acepta hasta 12 mensajes —seis intercambios— alternados entre
`user` y `assistant`, con un máximo de 2,000 caracteres por mensaje.
`ContextAssistant` conserva solamente intercambios exitosos y los limpia al
cambiar `scope` o `contextKey`; errores, cargas y HTML no forman parte del
historial. No se usa almacenamiento local, de sesión ni IndexedDB.

Para `core:text2text:chat`, el servicio convierte el contrato con roles a la
`ListOfTexts` de mensajes JSON alternados que exige Nextcloud. Para
`core:text2text` y el API
heredado de `TextProcessing`, conserva los roles dentro de delimitadores de
texto. La pregunta actual se envía aparte y nunca se duplica en el historial.
Si la pregunta contiene una referencia de seguimiento, el validador recorre
la cadena inmediata hasta la última consulta autosuficiente, con un máximo de
tres consultas. No cruza un rechazo de dominio ni mezcla una persona
autosuficiente anterior. Así el provider vuelve a seleccionar el expediente
correcto sin almacenar conversación en el servidor; la pregunta actual sigue
apareciendo una sola vez. En chat, el historial delimitado no se duplica en el
prompt porque ya viaja en el campo nativo `history`. Un rechazo de dominio
también corta el historial que se entrega al proveedor.

La detección de proveedor hace fallthrough: si existe el manager moderno de
`TaskProcessing` pero no anuncia `core:text2text:chat` ni `core:text2text`, se
intenta el manager heredado de `TextProcessing`. La presencia de la API moderna
sin un tipo compatible no oculta un proveedor legado funcional.

## Scope administrativo `empleados-completo`

`empleados-completo` implementa un contexto generado en servidor. El frontend
envía la pregunta con `context: {}` y no envía registros de empleados. El
backend rechaza cualquier campo de contexto inyectado por el cliente, exige
`empleados.hr` y solo entonces consulta los mappers permitidos. El permiso
`empleados.admin` no basta porque su catálogo excluye explícitamente el acceso
completo de Recursos Humanos y este scope contiene datos sensibles.

El directorio escalar de toda la plantilla contiene identidad, datos laborales
y organizacionales, personales, fiscales, financieros, ahorro, vacaciones,
equipo y notas disponibles. Los historiales pesados se cargan selectivamente
para hasta cinco empleados identificados de forma determinista por nombre,
usuario o número, o como resumen global cuando la pregunta lo requiere. Esto
evita una consulta por empleado: las fuentes detalladas se leen mediante
consultas masivas y se agrupan en PHP.

Para esos empleados seleccionados, PHP prepara además una proyección escalar y
hechos prioritarios —por ejemplo, sueldo o días de vacaciones con su semántica
explícita— que se colocan junto a la pregunta. No se consulta otra fuente ni se
calcula un dato nuevo: es una representación breve de los mismos valores
autorizados para proveedores con ventanas de atención pequeñas.

Un conjunto reducido de preguntas factuales con patrón cerrado puede responder
directamente con texto preparado por PHP: sueldo actual sin moneda, vacaciones
restantes, empleados sin inventario asignado, mayor antigüedad y campos
administrativos faltantes. Esta ruta solo se acepta en scopes de servidor y no
se activa para preguntas históricas, comparativas, causales o de promedios. Las
demás preguntas continúan pasando por el proveedor de IA.

Los expedientes de archivos contienen exclusivamente metadatos (nombre, ruta
relativa, tipo, tamaño, modificación y si es carpeta), con un máximo de 200
entradas por empleado. Nunca se incluyen binarios, previsualizaciones ni rutas
físicas. Las notas se convierten a texto plano y se limitan a 5,000 caracteres.
El JSON generado tiene un límite inicial de 512 KB; si se rebasa se eliminan
notas del directorio y se reducen archivos e historiales, marcando el contexto
como truncado. Si aún no cabe, la petición falla de forma segura.

Este scope incluye datos sensibles y está pensado para proveedores locales
controlados. La autorización ocurre antes de consultar datos. El modelo no
ejecuta SQL, no dispone de herramientas y no decide consultas; recibe solamente
el JSON ya construido por PHP.

Las dependencias de `EmpleadosFullContextProvider` están limitadas al dominio
de empleados: directorio, estructura laboral, organigrama, vacaciones,
ausencias, ahorro, inventario asignado, expediente y metadatos. No inyecta ni
consulta clientes, honorarios, actividades de reportes o reportes de tiempo.

## Scope `reportes-tiempo-admin`

La integración en `Adminreports.vue` envía exclusivamente el periodo
normalizado y el identificador independiente del empleado seleccionado. Nunca
envía las listas, gráficas, sueldo ni respuestas de Axios. El scope exige el
permiso backend real `reporte_tiempos.admin`; `PermisosService` también
comprueba que el módulo esté habilitado.

El provider ejecuta dos consultas fijas, independientemente de la cantidad de
empleados: una agregada por empleado, proyecto y actividad, y otra con los 301
reportes más recientes para devolver 300 y detectar truncamiento. La
visibilidad replica la vista administrativa: usuario actual y subordinados
directos por gerente o socio. Los joins de clientes seleccionan únicamente el
nombre visible; no cargan razón social, contactos, honorarios ni expedientes.

Los costos son estimaciones con la tarifa actual del empleado, igual que el
módulo existente. No existe costo histórico ni moneda en cada reporte, por lo
que el contexto no inventa ninguno. El cumplimiento declara su regla y fecha:
al menos un reporte en la fecha de referencia. Los máximos son 300 reportes,
200 empleados, 100 actividades y 100 proyectos, con metadatos por sección y
bandera global de truncamiento.

> **Advertencia:** Si en el futuro el administrador cambia a un proveedor
> externo, debe revisar este scope antes de habilitarlo, ya que transmite
> identidades laborales, actividades, nombres visibles de proyectos y la
> tarifa actual utilizada para estimar costos. No transmite datos personales,
> fiscales, bancarios ni de ahorro.

### Un empleado frente al directorio

`empleado-laboral` y `empleados-listado` representan contextos distintos y no
deben usarse como si fueran equivalentes:

| Scope | Contexto |
| --- | --- |
| `empleado-laboral` | Un empleado seleccionado. |
| `empleados-listado` | Múltiples empleados cargados en el directorio. |

`empleados-listado` se conserva por compatibilidad para integraciones de
contexto visible y mantiene su lista blanca en
`EmpleadosListadoScope::sanitize()`. La vista
`src/views/components/ListaEmpleados/EmployeeList.vue` utiliza ahora
`empleados-completo`: envía `context: {}` y el backend construye el directorio
autorizado, sin depender de las filas cargadas en Vue ni de un empleado
seleccionado.

El scope `empleados-listado` requiere que el usuario tenga permisos de RH o de
administrador. La autorización se declara en el scope y se comprueba en el
backend tanto al anunciarlo en `capabilities` como antes de consultar al
proveedor de IA. Una sesión iniciada por sí sola no concede acceso al scope.

Los datos financieros y personales no forman parte del directorio de IA. Si un
caso de uso necesita datos financieros, debe implementarse otro scope
explícito, restringido y sometido a una revisión de permisos específica; no se
debe ampliar la lista blanca de `empleados-listado`.

## Cómo agregar un scope

1. Crear una clase que implemente `ContextScopeInterface`, normalmente
   extendiendo `AbstractContextScope`.
2. Crear una lista blanca manual en `sanitize()`. Reconstruir cada objeto y
   cada elemento de las listas; no devolver estructuras recibidas del
   frontend.
3. Registrar el identificador y la clase en `ContextScopeRegistry`. El
   identificador debe ser fijo, único y no derivarse de datos enviados por el
   cliente.
4. Agregar el componente `ContextAssistant` en la vista que ya posee los datos.
5. Crear `aiContext` con únicamente los valores visibles y necesarios.
6. Crear `aiContextKey` con una identidad estable del objeto mostrado. La clave
   limpia la conversación al cambiar de objeto, pero no debe incluirse dentro
   de `aiContext`.
7. Agregar sugerencias específicas de la vista.
8. Probar campos adicionales y tipos incorrectos. Verificar que los campos no
   permitidos desaparezcan, que las estructuras incorrectas se rechacen y que
   se cumplan los límites del scope.

Además, se debe probar que el scope aparece en `capabilities`, que un
identificador inexistente se rechaza y que el prompt contiene las instrucciones
del scope correcto. No se deben ejecutar consultas HTTP nuevas solo para
enriquecer el contexto de IA.

## Reglas de seguridad

Estas reglas son obligatorias para cualquier integración:

- Nunca enviar `this.$data`.
- Nunca enviar respuestas completas de API.
- Nunca confiar únicamente en el frontend.
- Nunca incluir tokens, permisos o configuraciones.
- Nunca incluir identificadores si no son necesarios.
- Nunca registrar el contexto completo.
- Nunca reutilizar `contextKey` entre objetos diferentes.
- Nunca agregar un campo sensible a un scope general.

El frontend reduce los datos antes de enviarlos, pero ese filtrado solo evita
exposición accidental en el navegador. La frontera de seguridad real está en
el backend: `sanitize()` debe reconstruir la salida campo por campo, ignorar
claves adicionales y rechazar tipos incorrectos en campos permitidos.

Los helpers de `AbstractContextScope` permiten únicamente los tipos declarados.
No deben convertirse arrays u objetos a texto, serializarse estructuras
completas ni incluirse valores recibidos en mensajes de excepción. Los textos
se recortan y se limitan; las colecciones también deben tener un máximo
explícito.

Los datos del contexto se consideran contenido no confiable. Una instrucción
escrita dentro de un nombre, una nota o cualquier otro valor no puede
reemplazar las reglas globales ni las instrucciones del scope. Tampoco se debe
registrar la respuesta completa del proveedor.

Antes de construir el contexto, la vista debe haber aplicado sus comprobaciones
normales de acceso. El scope limita campos, pero no sustituye la autorización
del recurso. Para una revisión se debe inspeccionar también el payload real de
`/apps/empleados/api/ai/ask` en las herramientas de red del navegador.

### Datos sensibles en scopes separados

Los siguientes dominios no deben incorporarse a `empleado-laboral` ni a otro
scope general:

- `empleado-personal`
- `empleado-financiero`
- `empleado-notas`
- `empleado-archivos`

Esos scopes requerirían una revisión de permisos más estricta antes de
implementarse. La revisión debe definir quién puede abrir la vista, qué campos
puede ver, si está permitido enviarlos al proveedor configurado, qué
restricciones adicionales necesita el endpoint y cómo se evita que la
respuesta revele datos de otro empleado.

En particular, un scope financiero no debe heredar por conveniencia la lista
blanca de un scope laboral; notas y archivos deben respetar sus permisos de
visibilidad y acceso; y un scope personal debe limitarse a los datos
estrictamente necesarios para su caso de uso. La separación de scopes permite
auditar permisos, listas blancas e instrucciones sin ampliar silenciosamente
el acceso de asistentes ya integrados.

## Ejemplo mínimo de integración Vue

El contexto se construye manualmente. El identificador usado como
`contextKey` sirve para invalidar la conversación, pero no se envía dentro del
contexto:

```vue
<template>
	<ContextAssistant
		v-if="mostrarAsistenteIa"
		scope="ejemplo-recurso"
		:context="aiContext"
		:context-key="aiContextKey"
		:title="t('empleados', 'Asistente del recurso')"
		:suggestions="aiSuggestions" />
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import ContextAssistant from 'RUTA_RELATIVA/components/Ai/ContextAssistant.vue'

export default {
	components: {
		ContextAssistant,
	},

	computed: {
		mostrarAsistenteIa() {
			return Boolean(this.recurso?.id)
				&& !this.editando
				&& !this.cargando
		},

		aiContextKey() {
			return String(this.recurso?.id ?? '')
		},

		aiContext() {
			return {
				recurso: {
					nombre: typeof this.recurso?.nombre === 'string'
						? this.recurso.nombre
						: null,
					estado: typeof this.estadoVisible === 'string'
						? this.estadoVisible
						: null,
				},
			}
		},

		aiSuggestions() {
			return [
				t('empleados', '¿Cuál es el estado actual?'),
			]
		},
	},

	methods: {
		t,
	},
}
</script>
```

Se debe sustituir `RUTA_RELATIVA` por la ruta real desde la vista. No se deben
usar `this.recurso`, `this.$data` ni otro objeto completo como valor de
`context`.

## Plantilla de scope PHP

La clase reconstruye la estructura permitida y usa mensajes de error que no
revelan el valor recibido:

```php
<?php

declare(strict_types=1);

namespace OCA\Empleados\Service\Ai\Scope;

final class EjemploRecursoScope extends AbstractContextScope {
	private const MAX_ITEMS = 50;

	public function getId(): string {
		return 'ejemplo-recurso';
	}

	public function getDescription(): string {
		return 'Resumen del recurso mostrado en la vista.';
	}

	public function getInstructions(): string {
		return <<<'PROMPT'
La vista muestra un único recurso.

Responde solamente sobre su nombre, estado y elementos visibles.
PROMPT;
	}

	public function sanitize(array $context): array {
		$recurso = $this->arrayOrEmpty($context['recurso'] ?? null);
		if ($recurso !== [] && array_is_list($recurso)) {
			throw new \InvalidArgumentException('La sección del recurso no es válida.');
		}

		$items = $this->arrayOrEmpty($context['items_visibles'] ?? null);
		if (!array_is_list($items) || count($items) > self::MAX_ITEMS) {
			throw new \InvalidArgumentException('Los elementos visibles no son válidos.');
		}

		$cleanItems = [];
		foreach ($items as $item) {
			$item = $this->arrayOrEmpty($item);
			if ($item !== [] && array_is_list($item)) {
				throw new \InvalidArgumentException('Un elemento visible no es válido.');
			}

			$cleanItems[] = [
				'nombre' => $this->stringOrNull($item['nombre'] ?? null),
			];
		}

		return [
			'recurso' => [
				'nombre' => $this->stringOrNull($recurso['nombre'] ?? null),
				'estado' => $this->stringOrNull($recurso['estado'] ?? null),
			],
			'items_visibles' => $cleanItems,
		];
	}
}
```

Finalmente se registra de forma explícita:

```php
private const SCOPES = [
	// ...
	'ejemplo-recurso' => EjemploRecursoScope::class,
];
```

No se deben usar detección automática de clases, eventos ni atributos para
registrar scopes.
