# Controlador de Actividades

El controlador `ActividadesController` es responsable de gestionar las actividades de los empleados en la aplicación Nextcloud. Este controlador proporciona endpoints para obtener, crear, modificar y eliminar actividades, así como exportar e importar datos en formato XLSX.

## Endpoints

### Obtener todas las actividades

**URL:** `/api/actividades`

**Método:** `GET`

**Descripción:** Obtiene la lista de todas las actividades registradas.

**Requisitos de acceso:** Debe estar autenticado y tener los roles 'admin', 'empleados' o 'recursos_humanos'.

**Respuesta:**
