# emailMK

Proyecto base en PHP para iniciar y documentar un portal de bienvenida de **emailMK**. Incluye páginas simples para bienvenida, inicio de sesión y un dashboard protegido, además de un esquema SQL inicial para la base de datos.

## Contenido
- [Objetivo](#objetivo)
- [Funcionalidad actual](#funcionalidad-actual)
- [Estructura del repositorio](#estructura-del-repositorio)
- [Requisitos](#requisitos)
- [Ejecución en local](#ejecucion-en-local)
- [Configuración](#configuracion)
- [Base de datos](#base-de-datos)
- [Buenas prácticas](#buenas-practicas)
- [Próximos pasos sugeridos](#proximos-pasos-sugeridos)

## Objetivo
Proveer un punto de partida claro para construir, documentar y evolucionar el proyecto de forma organizada.

## Funcionalidad actual
- **Landing** de bienvenida en `index.php`.
- **Login** básico en `login.php` que valida usuarios en base de datos y crea sesión.
- **Dashboard** protegido en `dashboard.php` (requiere sesión activa).

## Estructura del repositorio
- `index.php`: Página de bienvenida y punto de entrada del proyecto.
- `login.php`: Formulario de acceso y lógica de autenticación.
- `dashboard.php`: Vista privada para usuarios autenticados.
- `config/database.php`: Configuración de conexión a base de datos.
- `database/schema.sql`: Esquema SQL inicial.
- `database/`: Carpeta reservada para scripts o migraciones de base de datos.
- `README.md`: Documentación principal del proyecto.

## Requisitos
- PHP 8.x (o superior) con extensión **PDO MySQL** habilitada.
- MySQL o MariaDB.
- Servidor web local (opcional, se puede usar el servidor embebido de PHP).

## Ejecución en local
Desde la raíz del repositorio:

```bash
php -S localhost:8000
```

Luego abre en el navegador: `http://localhost:8000`.

## Configuración
La conexión a base de datos se declara en `config/database.php`.

- Evita versionar credenciales sensibles en entornos reales.
- Considera mover la configuración a variables de entorno o un archivo fuera del repositorio.
- Asegúrate de que los valores de `host`, `user`, `password` y `database` apunten a tu entorno local.

## Base de datos
1. Crea la base de datos indicada en la configuración.
2. Importa el esquema inicial:

```bash
mysql -u <usuario> -p <base_de_datos> < database/schema.sql
```

### Tablas principales
El archivo `database/schema.sql` define tablas para usuarios, listas, campañas y eventos. La tabla `users` es la que se utiliza en el login.

> **Nota:** el login (`login.php`) espera una columna `password` para validar credenciales, mientras que el esquema actual define `password_hash`. Ajusta el esquema o la consulta según la convención que prefieras antes de iniciar pruebas.

### Credenciales de acceso (login)
- El formulario consulta el usuario por email y valida la contraseña con `password_verify` o comparación directa.
- Para pruebas rápidas, crea un usuario manualmente con un hash generado por `password_hash()` y almacénalo en la columna que definas para la contraseña.

## Buenas prácticas
- Mantén los cambios pequeños y bien descritos.
- Documenta decisiones relevantes en este archivo.
- Revisa el estilo y consistencia del código antes de integrar cambios.
- Evita exponer claves reales en el repositorio.

## Próximos pasos sugeridos
- Definir objetivos funcionales y alcance del proyecto.
- Unificar la convención de contraseñas (columna y validación) entre el login y el esquema.
- Agregar estructura para vistas, controladores y modelos si la aplicación crece.
- Incorporar pruebas automatizadas y un flujo de despliegue.
