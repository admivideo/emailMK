# Manual de uso de emailMK

## Descripción general
`emailMK` es un punto de inicio sencillo con una página de bienvenida en PHP que sirve como base para organizar el proyecto y documentar avances.

## Requisitos
- PHP 8.0 o superior instalado localmente.

## Puesta en marcha rápida
1. Abre una terminal en la raíz del repositorio.
2. Inicia el servidor embebido de PHP:
   ```bash
   php -S localhost:8000 -t .
   ```
3. Abre tu navegador en `http://localhost:8000`.

## Estructura principal
- `index.php`: página inicial con el mensaje de bienvenida.
- `app/`, `config/`, `database/`, `public/`, `resources/`, `workers/`: carpetas reservadas para crecimiento del proyecto.

## Personalización básica
- Modifica el contenido visible editando las variables `$projectName` y `$message` en `index.php`.
- Ajusta estilos en el bloque `<style>` del mismo archivo para cambiar tipografías, colores o disposición.

## Sugerencias de trabajo
- Documenta decisiones y avances en `README.md`.
- Mantén cambios pequeños y descriptivos para facilitar revisiones.

## Solución de problemas
- Si el puerto 8000 está en uso, prueba con otro: `php -S localhost:8080 -t .`.
- Verifica la versión de PHP con `php -v` si aparecen errores al iniciar el servidor.
