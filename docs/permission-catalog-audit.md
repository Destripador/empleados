# Auditoría del catálogo de permisos

Auditoría realizada el 2 de octubre de 2026 sobre controladores, servicios,
migraciones, rutas, frontend y pruebas. La identidad de una entrada es
`module + permission + group_id`.

## Inventario oficial

| Permiso lógico | Acción / grupo esperado | Uso comprobado | Migración histórica | En `REQUIRED_PERMISSION_GROUPS` anterior | UI administrativa antes de reparar |
|---|---|---|---|---|---|
| `compras.request` | solicitar / `compras_solicitantes` | `PermisosService`, `CompraPermisosService` | 2013 | No | Sólo si la fila existía |
| `compras.approve` | aprobar / `compras_autorizadores` | wrappers y flujo de compras | 2013 | No | Sólo si la fila existía |
| `compras.admin` | administrar / `compras_admin` | wrappers y flujo de compras | 2013 | No | Sólo si la fila existía |
| `compras.accounting` | contabilidad / `compras_contabilidad` | wrappers y flujo de compras | 2013 | No | Sólo si la fila existía |
| `empleados.hr` | RH / `recursos_humanos` | `canManageHumanResources` y controladores históricos | 2013 | No | Sólo si la fila existía |
| `empleados.admin` | administrar / `empleados_admin` | `canManageHumanResources` | 2014 | No | Sólo si la fila existía |
| `clientes.admin` | administrar / `clientes_admin` | controladores y frontend de clientes | 2014 | Sí | Sí |
| `clientes.view` | consultar / `clientes_view` | acceso de módulo `clientes` | No | Sí | Sí |
| `inventario.admin` | administrar / `ti_admin` | controladores, widget y frontend | 2014 | No | Sólo si la fila existía |
| `inventario.technician` | mantenimiento / `ti_tecnicos` | `canWorkMaintenance` y controlador | 2033 | No | Sólo si la fila existía |
| `inventario.view` | consultar / `ti_consulta` | `canViewInventory` y controlador | 2033 | No | Sólo si la fila existía |
| `soporte.view` | consultar / `soporte_view` | `canSee('soporte')` protege historial y navegación | No | No | No |
| `reporte_tiempos.admin` | administrar / `reportes_admin` | servicio, controlador y navegación | 2014 | Sí | Sí |
| `reporte_tiempos.view` | consultar / `reportes_view` | servicio y navegación | 2044 | Sí | Sí |
| `ahorro.admin` | administrar / `ahorro_admin` | navegación y acceso histórico | 2014 | No | Sólo si la fila existía |
| `ausencias.admin` | administrar / `ausencias_admin` | catálogo histórico y administración de ausencias | 2014 | No | Sólo si la fila existía |

La instancia auditada estaba en la migración 2065 pero sólo tenía cuatro
filas: `clientes.admin`, `clientes.view`, `reporte_tiempos.admin` y
`reporte_tiempos.view`. Esto reproduce el problema de instalaciones donde las
semillas de `postSchemaChange()` no quedaron aplicadas durante la instalación.

## Inconsistencias encontradas

- El controlador mantenía un segundo catálogo de cuatro entradas, distinto de
  las semillas repartidas entre las migraciones 2013, 2014, 2033 y 2044.
- Los wrappers de compras, empleados e inventario podían consultar permisos que
  no podían concederse desde la UI cuando faltaban sus filas.
- `soporte` tiene flag global y se consulta mediante `canSee('soporte')`, pero no
  tenía ninguna fila; para usuarios no administradores el acceso era imposible.
- No se encontraron triples oficiales duplicados. Sí había diferencias de
  textos y orden para Clientes entre migración y controlador.
- `CompraPermisosService` conserva grupos configurables históricos. Sus valores
  por defecto coinciden con los cuatro grupos oficiales de compras.
- La UI de administración y la UI por usuario ya consumían la base de datos de
  forma dinámica; la limitación provenía de las filas ausentes, no de un filtro
  de frontend.

## Grupos base y módulos globales

- `empleados` sigue siendo un grupo base: lo usan flujos generales, simulación
  y el acceso de compatibilidad al catálogo de reportes. No representa por sí
  mismo una acción administrativa nueva.
- `recursos_humanos` sigue siendo grupo base por compatibilidad con numerosos
  controladores y, además, es el grupo oficial de `empleados.hr`. No se elimina
  ni se duplica al reparar.
- `ahorro`, `ausencias`, `clientes`, `compras`, `inventario`, `soporte` y
  `reporte_tiempos` respetan sus flags `modulo_*`. `empleados` no tiene switch y
  conserva el comportamiento de estar habilitado por defecto.
- Los permisos personalizados no forman parte del mínimo oficial, pero continúan
  visibles, asignables y reparables si están habilitados.

## Permisos obsoletos o sin uso

No se eliminó ninguna entrada. `ahorro.admin` y `ausencias.admin` conviven con
controles históricos directos por grupo; siguen siendo configurables en la UI y
son parte de las semillas existentes. No hay evidencia suficiente para declarar
obsoleto ningún permiso histórico.
