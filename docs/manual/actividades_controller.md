# Documentación del Controlador ActividadesController

## Descripción
El controlador actividadesController es responsable de gestionar las actividades de los empleados en Nextcloud. Proporciona endpoints para obtener, crear, modificar y eliminar actividades, así como exportar e importar datos en formato XLSX.

## Endpoints

### Obtener la lista de actividades
- **URL**: /api/actividades
- **Método**: GET
- **Descripción**: Obtiene la lista completa de actividades.
- **Acceso Requerido**: admin, empleados, recursos_humanos

### Obtener actividad por ID
- **URL**: /api/actividades/{id}
- **Método**: GET
- **Descripción**: Obtiene una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
- **Acceso Requerido**: admin, recursos_humanos

### Eliminar actividad
- **URL**: /api/actividades/{id}
- **Método**: DELETE
- **Descripción**: Elimina una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
- **Acceso Requerido**: admin, recursos_humanos

### Modificar actividad
- **URL**: /api/actividades/{id}
- **Método**: PUT
- **Descripción**: Modifica una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
  - nombre: Nombre de la actividad (string)
  - detalles: Detalles de la actividad (string, opcional)
  - tiempoestimado: Tiempo estimado en horas (float)
  - tipo: Tipo de tiempo ('horas' o 'minutos') (string)
- **Acceso Requerido**: admin, recursos_humanos

### Crear nueva actividad
- **URL**: /api/actividades
- **Método**: POST
- **Descripción**: Crea una nueva actividad.
- **Parámetros**:
  - nombre: Nombre de la actividad (string)
  - detalles: Detalles de la actividad (string, opcional)
  - tiempoestimado: Tiempo estimado en horas (float)
  - tipo: Tipo de tiempo ('horas' o 'minutos') (string)
- **Acceso Requerido**: admin, recursos_humanos

### Exportar actividades a XLSX
- **URL**: /api/actividades/exportar
- **Método**: GET
- **Descripción**: Exporta la lista de actividades a un archivo XLSX.
- **Acceso Requerido**: admin, recursos_humanos

### Importar actividades desde XLSX
- **URL**: /api/actividades/importar
- **Método**: POST
- **Descripción**: Importa la lista de actividades desde un archivo XLSX.
- **Parámetros**:
  - ActividadesfileXLSX: Archivo XLSX con los datos de las actividades
- **Acceso Requerido**: admin, recursos_humanos

## Métodos Privados

### getUploadedFile(string $key): array
- **Descripción**: Obtiene un archivo subido y maneja posibles errores.
- **Parámetros**:
  - $key: Clave del archivo subido en la solicitud
- **Retorno**: Arreglo con información del archivo subido

## Notas Adicionales
- Todos los endpoints requieren autenticación.
- El tipo de tiempo puede ser 'horas' o 'minutos', y se convierte automáticamente a minutos si es 'horas'.
- Los archivos XLSX deben tener una estructura específica para que sean importados correctamente.

## Documentación del Controlador ActividadesController

### Descripción
El controlador `actividadesController` es responsable de gestionar las actividades de los empleados en Nextcloud. Proporciona endpoints para obtener, crear, modificar y eliminar actividades, así como exportar e importar datos en formato XLSX.

## Endpoints

### Obtener la lista de actividades
- **URL**: /api/actividades
- **Método**: GET
- **Descripción**: Obtiene la lista completa de actividades.
- **Acceso Requerido**: admin, empleados, recursos_humanos

### Obtener actividad por ID
- **URL**: /api/actividades/{id}
- **Método**: GET
- **Descripción**: Obtiene una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
- **Acceso Requerido**: admin, recursos_humanos

### Eliminar actividad
- **URL**: /api/actividades/{id}
- **Método**: DELETE
- **Descripción**: Elimina una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
- **Acceso Requerido**: admin, recursos_humanos

### Modificar actividad
- **URL**: /api/actividades/{id}
- **Método**: PUT
- **Descripción**: Modifica una actividad específica por su ID.
- **Parámetros**:
  - id: ID de la actividad (integer)
  - nombre: Nombre de la actividad (string)
  - detalles: Detalles de la actividad (string, opcional)
  - tiempoestimado: Tiempo estimado en horas (float)
  - tipo: Tipo de tiempo ('horas' o 'minutos') (string)
- **Acceso Requerido**: admin, recursos_humanos

### Crear nueva actividad
- **URL**: /api/actividades
- **Método**: POST
- **Descripción**: Crea una nueva actividad.
- **Parámetros**:
  - nombre: Nombre de la actividad (string)
  - detalles: Detalles de la actividad (string, opcional)
  - tiempoestimado: Tiempo estimado en horas (float)
  - tipo: Tipo de tiempo ('horas' o 'minutos') (string)
- **Acceso Requerido**: admin, recursos_humanos

### Exportar actividades a XLSX
- **URL**: /api/actividades/exportar
- **Método**: GET
- **Descripción**: Exporta la lista de actividades a un archivo XLSX.
- **Acceso Requerido**: admin, recursos_humanos

### Importar actividades desde XLSX
- **URL**: /api/actividades/importar
- **Método**: POST
- **Descripción**: Importa la lista de actividades desde un archivo XLSX.
- **Parámetros**:
  - ActividadesfileXLSX: Archivo XLSX con los datos de las actividades
- **Acceso Requerido**: admin, recursos_humanos

## Métodos Privados

### getUploadedFile(string $key): array
- **Descripción**: Obtiene un archivo subido y maneja posibles errores.
- **Parámetros**:
  - $key: Clave del archivo subido en la solicitud
- **Retorno**: Arreglo con información del archivo subido

## Notas Adicionales
- Todos los endpoints requieren autenticación.
- El tipo de tiempo puede ser 'horas' o 'minutos', y se convierte automáticamente a minutos si es 'horas'.
- Los archivos XLSX deben tener una estructura específica para que sean importados correctamente.
