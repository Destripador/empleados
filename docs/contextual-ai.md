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
| Vista Vue | Construye manualmente el contexto con los valores visibles y define una clave de identidad para la conversación. |
| `ContextAssistant` | Consulta capacidades, muestra la interfaz y envía `scope`, `question` y `context`. |
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

### Un empleado frente al directorio

`empleado-laboral` y `empleados-listado` representan contextos distintos y no
deben usarse como si fueran equivalentes:

| Scope | Contexto |
| --- | --- |
| `empleado-laboral` | Un empleado seleccionado. |
| `empleados-listado` | Múltiples empleados cargados en el directorio. |

El chat de `empleados-listado` vive en
`src/views/components/ListaEmpleados/EmployeeList.vue`. No depende de
`EmployeeDetails` ni de que exista un empleado seleccionado. Abrir el detalle
de otro empleado no cambia el contexto del chat: el contexto depende de la
lista cargada y su versión, no de la selección activa.

`EmployeeList.vue` reconstruye cada entrada mediante una lista blanca antes de
enviarla. El backend vuelve a reconstruir la lista campo por campo en
`EmpleadosListadoScope::sanitize()`; no acepta el array original ni objetos
completos de empleados.

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
