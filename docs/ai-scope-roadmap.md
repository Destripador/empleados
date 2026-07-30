# Roadmap de scopes de IA por submódulo

Este documento identifica puntos de integración futuros a partir de las vistas,
controladores, mappers y permisos que existen actualmente. No habilita ninguno
de estos scopes.

El principio rector es el aislamiento por dependencia: cada provider debe
inyectar únicamente mappers o servicios de su dominio y las referencias mínimas
necesarias para mostrar nombres. La IA es de solo lectura; un scope nunca debe
ejecutar altas, cambios, aprobaciones, rechazos o eliminaciones.

## Convenciones de la auditoría

- **Servidor** significa que Vue envía únicamente filtros escalares saneables y
  que PHP obtiene las filas después de comprobar permisos. No se aceptan listas
  ni respuestas Axios como parámetros.
- Las claves con punto, como `empleados.hr`, son permisos reales resueltos por
  `PermisosService`. Una clave sin punto, como `clientes`, representa acceso al
  módulo mediante cualquier entrada activa de su catálogo.
- `admin`, `empleados` y `recursos_humanos`, cuando aparecen sin punto, son IDs
  de grupos usados por controladores heredados; no equivalen a
  `empleados.admin`.
- **P1** es el siguiente bloque recomendado, **P2** es una segunda ola y **P3**
  corresponde a catálogos de menor valor analítico. **Bloqueado → Pn** exige
  cerrar primero una brecha de autorización o integración y conserva el orden
  sugerido después de resolverla.

## Matriz propuesta

| Submódulo | Scope propuesto | Vista de integración | Tipo de contexto | Provider | Permisos | Datos permitidos | Datos prohibidos | Prioridad |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Vacaciones y ausencias | `vacaciones-ausencias-admin` | `src/views/components/TiempoLibre/ReporteAusencias.vue`, abierto desde `TiempoLibre.vue` | Servidor; filtros de empleado, periodo, tipo y estado | `VacacionesAusenciasContextProvider`; fuentes: `ausenciasMapper`, `historialvacacionesMapper`, `historialausenciasMapper`, y únicamente nombres laborales desde `empleadosMapper` | `ausencias.admin`, `empleados.hr` o `empleados.admin`. Antes de integrarlo se deben alinear los endpoints heredados que todavía comprueban los grupos `admin`/`empleados` y el acceso privilegiado `admin`/`recursos_humanos`. | Periodos vacacionales, días de derecho, disfrutados, restantes y acumulados, expiración, solicitudes, fechas, duración, tipo, motivo acotado, estado y cadena de aprobación, prima vacacional y resúmenes por empleado/estado | Sueldo, cuenta bancaria, ahorro, RFC/CURP/IMSS, inventario, clientes, honorarios y reportes de tiempo | P1 |
| Ahorro | `ahorro-admin` | `src/views/components/ahorros/PanelAhorros.vue` | Servidor; año, estado y empleado opcional | `AhorroAdminContextProvider`; fuentes: `userahorroMapper`, `historialahorroMapper` y nombre/usuario mínimo desde `empleadosMapper` | `ahorro.admin`, `empleados.hr` o `empleados.admin`, exactamente como `AhorrosController::requireAhorroAdminAccess()` | Solicitudes, fecha, cantidad solicitada, cantidad total registrada en la solicitud, estado, nota acotada, empleado y totales por estado/periodo | Número de cuenta, clave del fondo, datos fiscales/personales, sueldo, vacaciones, clientes, honorarios y horas reportadas | P1 |
| Inventario TI | `inventario-ti-admin` | `src/views/components/Inventario/Inventario.vue`, secciones de modelos y dispositivos | Servidor; sección, estado, búsqueda, empleado o equipo opcional | `InventarioTiContextProvider`; fuentes: `InventarioModeloMapper`, `InventarioComputoMapper` y nombre laboral mínimo desde `empleadosMapper` | `inventario.admin`, clave usada por las operaciones administrativas de `InventarioController` | Modelos, marca, procesador, RAM, almacenamiento, tipo, dispositivos, nombre de sistema, serie, estado y asignación | Historial de soporte, expediente personal/fiscal/financiero del empleado, ahorro, ausencias, estructura laboral, clientes, honorarios y reportes de tiempo | P1 |
| Soporte TI | `soporte-ti-admin` | `src/views/components/Inventario/Inventario.vue`, sección de soporte | Servidor; equipo, fecha y acción opcionales | `SoporteTiContextProvider`; fuente: `SoporteHistorialMapper` y nombre funcional mínimo del dispositivo desde `InventarioComputoMapper` | `soporte.admin`, clave usada por `InventarioController`. La instalación debe comprobar que exista en el catálogo, pues no aparece entre las entradas base sembradas auditadas. | Dispositivo identificado de forma funcional, fecha, acción, detalles acotados, usuario actual y usuario de soporte | Especificaciones completas del inventario, asignaciones ajenas al equipo consultado, datos de empleados, ahorro, ausencias, clientes, honorarios y reportes de tiempo | Bloqueado → P2 |
| Áreas | `areas-admin` | `src/views/components/areas/AreasList.vue` | Servidor; área seleccionada opcional | `AreasContextProvider`; fuentes: `departamentosMapper` y ocupantes mínimos desde `empleadosMapper` | `empleados.hr` o `empleados.admin`; `AreasController` usa esas claves. La lectura compartida de `GetAreasList` para `clientes` no debe ampliar este scope de RH. | Catálogo, jerarquía padre/hijo, conteos, ocupantes por nombre laboral, puesto y estado, áreas vacías | Datos personales, fiscales o financieros, notas, archivos, ahorro, ausencias, inventario, clientes y reportes de tiempo | P2 |
| Puestos | `puestos-admin` | `src/views/components/puestos/PuestosList.vue` | Servidor; puesto seleccionado opcional | `PuestosContextProvider`; fuentes: `puestosMapper` y ocupantes mínimos desde `empleadosMapper` | `empleados.hr` o `empleados.admin`, como `PuestosController::requireHumanResourcesAccess()` | Nombre, nivel, conteos, ocupantes por nombre laboral y estado, puestos vacantes o duplicados | Datos personales/fiscales/financieros, notas, archivos, ahorro, ausencias, clientes, honorarios y reportes de tiempo | P2 |
| Equipos laborales | `equipos-laborales-admin` | `src/views/components/Equipos/EquiposList.vue` | Servidor; equipo seleccionado opcional | `EquiposLaboralesContextProvider`; fuentes: `equiposMapper` y miembros mínimos desde `empleadosMapper` | Scope: `empleados.hr` o `empleados.admin`. Los endpoints principales de `EquiposController` aún comprueban los grupos heredados `admin`/`recursos_humanos` y deben unificarse antes de integrar. | Nombre del equipo, jefe, miembros, conteos, equipos sin jefe o sin integrantes y distribución laboral | Dispositivos de Inventario TI, soporte, grupos no relacionados, datos personales/fiscales/financieros, ahorro, clientes y reportes de tiempo | P2 |
| Organigrama | `organigrama-admin` | `src/views/components/ListaEmpleados/Organigrama/OrganigramaNetwork.vue` | Servidor; empleado raíz o sección visible opcional | `OrganigramaContextProvider`; fuentes: `empleadosorganigramaMapper`, `empleadosMapper`, y etiquetas mínimas de área/puesto | `empleados.hr` o `empleados.admin`, como `OrganigramaController::requireHumanResourcesAccess()` | Relaciones jefe/dependiente, niveles, cadena de reporte, subordinados directos e indirectos, nodos huérfanos y nombres/puestos/áreas laborales | Coordenadas de presentación salvo que sean necesarias para depuración visual, datos personales/fiscales/financieros, notas, archivos, ahorro, clientes y reportes de tiempo | P2 |
| Capital humano | `capital-humano-admin` | No existe una vista Vue fuente activa. Candidato futuro: una sección explícita en `src/views/Settings/GroupSettings.vue` | Servidor; sin filas enviadas por Vue | `CapitalHumanoContextProvider`; fuentes: `capitalhumanoMapper` e `IUserManager`, sin acceso a expedientes | **No hay un permiso efectivo en `CapitalhumanoController`: ambos endpoints son `NoAdminRequired`.** No anunciar el scope hasta definir una autorización real y proteger también el controlador. | Una vez autorizado: usuarios asignados a Capital Humano, nombre visible, usuario, estado habilitado y resumen de membresía | Correo y último acceso salvo necesidad aprobada, contenido o rutas de carpetas compartidas, expedientes, salarios, datos fiscales/financieros y credenciales | Bloqueado → P3 |
| Reglas de aniversario | `reglas-aniversario-admin` | `src/views/Settings/TiempoLaboralSettings.vue` | Servidor; sin contexto visible, o número de aniversario como filtro | `AniversariosContextProvider`; fuente: `aniversarioMapper` | La lectura actual usa grupos `admin`/`empleados`, varias mutaciones son `NoAdminRequired` y la vista pertenece a `ISettings` de administrador. No habilitar IA hasta unificar la autorización con una clave real. | El origen real contiene reglas `numero_aniversario` y `dias`; se permiten la tabla, rangos, huecos y duplicados | No tratar estas reglas como aniversarios próximos de personas; no incluir empleados, saldos vacacionales, ausencias, prima, sueldo ni otros ajustes | Bloqueado → P3 |
| Clientes | `clientes-catalogo` | `src/views/components/clientes/CompaniesGroups.vue`, pestaña de información general | Servidor; cliente seleccionado, estado y tipo de agrupación | `ClientesContextProvider`; fuente: `clientesMapper`, con nombres mínimos de líder y colaboradores cuando sean necesarios | Lectura: `clientes`; administración visible: `clientes.admin`, igual que `ClientesController` | Nombre, descripción, jerarquía grupo/empresa, estado, bandera especial, razón social, ubicación y, si la consulta lo exige, contacto, líder y colaboradores visibles | Honorarios, parcialidades, costos y reportes de tiempo; datos personales/fiscales/financieros de empleados; información de otros módulos | P1 |
| Honorarios | `honorarios-admin` | `src/views/components/clientes/CompaniesGroups.vue`, pestaña de honorarios del cliente seleccionado | Servidor; cliente, honorario, estado y periodo opcionales | `HonorariosContextProvider`; fuentes: `honorariosMapper`, `honorariosParcialidadesMapper` y solo nombre/ID funcional del cliente desde `clientesMapper` | Lectura: `clientes`; operaciones administrativas: `clientes.admin`, como `HonorariosController` y el detalle visible | Servicio, tipo de honorario, importe y moneda tal como se almacenan, fechas, estado activo, número y estado de parcialidades, fechas e importes de pago y resúmenes por cliente/moneda | Contactos, teléfono, correo o expediente completo del cliente; datos de empleados; costos derivados de reportes de tiempo; cotizaciones y credenciales | P2 |
| Actividades | `actividades-catalogo` | `src/views/components/clientes/Actividades.vue` | Servidor; actividad seleccionada y filtro cargable opcional | `ActividadesContextProvider`; fuente: `actividadesMapper` | Lectura: `clientes`; administración: `clientes.admin`, como `ActividadesController` | Nombre, detalles acotados, tiempo estimado, tiempo real almacenado y bandera cargable; conteos y anomalías del catálogo | Filas de reportes de tiempo, horas por empleado/proyecto, costos, clientes completos, honorarios y datos de empleados | P2 |
| Festivos | `festivos-admin` | `src/views/Settings/TiempoLaboralSettings.vue` | Servidor; fecha o mes opcional | `FestivosContextProvider`; fuente: `festivosMapper` | El controlador usa los grupos heredados `admin`/`recursos_humanos`, mientras la vista pertenece a ajustes de administrador. No anunciar el scope hasta migrar esa regla a un permiso de aplicación inequívoco. | Nombre y fecha recurrente almacenada en formato mes-día, conteos, fechas duplicadas y cobertura del calendario | No inventar año; empleados, aniversarios personales, solicitudes de ausencia, saldos vacacionales, reportes de tiempo y configuraciones ajenas | Bloqueado → P3 |
| Configuraciones administrativas | `configuraciones-admin` | `src/views/Settings/ListSettings.vue`; el contenedor real es `src/views/Settings/Settings.vue` registrado como `ISettings` de administrador | Servidor; sección visible opcional | `ConfiguracionesContextProvider`; fuentes filtradas: `configuracionesMapper` e `IConfig` | La vista y las mutaciones principales son de administrador Nextcloud, pero `GetConfigurations`/`GetDataManager` son `NoAdminRequired`. El contrato actual de scopes no expresa “solo administrador Nextcloud”; mantener bloqueado hasta cerrar ese control sin ampliar acceso a `empleados.admin`. | Solo banderas de módulos, guardado automático, acumulación/read-only y opciones operativas no secretas expresamente listadas | `provisioning_admin_pass`, cualquier secreto o credencial, directorio completo de usuarios/grupos, gestor de almacenamiento, rutas, contenido de archivos/logos, capacidades del proveedor y configuración de otros apps | Bloqueado → P3 |

## Dependencias y límites confirmados

La auditoría se basó principalmente en:

- `src/router/index.js`, `src/views/navigator/Sidenavigation.vue` y las vistas
  indicadas en la tabla;
- `AusenciasController`, `AhorrosController`, `InventarioController`,
  `AreasController`, `PuestosController`, `EquiposController`,
  `OrganigramaController`, `CapitalhumanoController`,
  `AniversariosController`, `ClientesController`, `HonorariosController`,
  `ActividadesController`, `FestivosController` y
  `ConfiguracionesController`;
- los mappers nombrados en cada provider;
- `PermisosService`, las migraciones del catálogo de permisos y
  `GroupSettings.vue`.

Hallazgos que condicionan el orden:

1. `clientes`, `clientes.admin`, `inventario.admin`, `ahorro.admin`,
   `ausencias.admin`, `empleados.hr` y `empleados.admin` sí existen en el
   catálogo o se usan directamente en controladores.
2. `soporte.admin` se usa en `InventarioController` y el catálogo admite ese
   módulo, aunque no forma parte de las entradas base sembradas por las
   migraciones auditadas; la instalación debe comprobar que la entrada existe
   antes de anunciar contexto de soporte administrativo.
3. Equipos, aniversarios, festivos y partes de ausencias aún mezclan permisos
   de catálogo con IDs de grupos heredados.
4. El submódulo llamado “aniversarios” guarda reglas de días por número de
   aniversario. No es una fuente de próximos aniversarios de empleados.
5. Capital Humano no tiene consumidor Vue en `src/` y sus endpoints carecen de
   un gate de aplicación.
6. `GetConfigurations()` devuelve además usuarios, grupos y opciones de
   reportes. Un provider de configuraciones no debe reutilizar esa respuesta:
   debe consultar y reconstruir una lista blanca propia.

## Contrato de incorporación

Después de cerrar los permisos marcados como bloqueados, cada incorporación
debe requerir solamente:

1. una clase `Scope` que implemente `ContextScopeInterface` o
   `ServerContextScopeInterface`;
2. un `ContextProvider` de dominio, o un contexto visible reconstruido y
   saneado para una vista estrictamente local;
3. una entrada explícita en `ContextScopeRegistry`;
4. una integración de `ContextAssistant` en la vista indicada.

No debe ser necesario modificar `AiController`, `ContextAiService` ni
`ContextAssistant.vue`. Los filtros se declaran y sanean dentro del scope; la
comprobación de `getRequiredPermissions()` ocurre antes de construir contexto.
Cada provider debe devolver los metadatos comunes de `contexto`, aplicar
límites y truncamiento, evitar N+1 mediante consultas masivas y omitir claves
nulas o técnicas sin valor para la respuesta.

La lista de permisos de un scope tiene semántica OR. Si dos secciones requieren
permisos distintos, deben permanecer en scopes separados o depender de una
única autorización fuerte ya existente. Por eso Inventario TI y Soporte TI no
comparten provider ni scope aunque hoy aparezcan en la misma vista.

Una integración no está completa hasta probar que:

- el scope no aparece en capacidades para usuarios sin permiso;
- claves y tipos de filtro adicionales se rechazan;
- el provider no inyecta mappers de otro submódulo;
- el JSON no contiene los datos prohibidos de su fila;
- cambiar filtros u objeto seleccionado cambia `contextKey` y limpia la
  conversación.
