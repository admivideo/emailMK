# emailMK

Proyecto base en PHP para iniciar y documentar un portal de bienvenida de **emailMK**. Incluye una página inicial en `index.php` y una configuración mínima para conexión a base de datos.

## Contenido
- [Objetivo](#objetivo)
- [Estructura del repositorio](#estructura-del-repositorio)
- [Requisitos](#requisitos)
- [Ejecución en local](#ejecucion-en-local)
- [Configuración](#configuracion)
- [Buenas prácticas](#buenas-practicas)
- [Próximos pasos sugeridos](#proximos-pasos-sugeridos)

## Objetivo
Proveer un punto de partida claro para construir, documentar y evolucionar el proyecto de forma organizada.

## Estructura del repositorio
- `index.php`: Página de bienvenida y punto de entrada del proyecto.
- `config/database.php`: Configuración de conexión a base de datos.
- `database/`: Carpeta reservada para scripts o migraciones de base de datos.
- `README.md`: Documentación principal del proyecto.

## Requisitos
- PHP 8.x (o superior).
- Servidor web local (opcional, se puede usar el servidor embebido de PHP).

## Ejecución en local
Desde la raíz del repositorio:

```bash
php -S localhost:8000
```

Luego abre en el navegador: `http://localhost:8000`.

## Configuración
La conexión a base de datos se declara en `config/database.php`. Para entornos reales:

- Evita versionar credenciales sensibles.
- Considera mover la configuración a variables de entorno o un archivo fuera del repositorio.

## Buenas prácticas
- Mantén los cambios pequeños y bien descritos.
- Documenta decisiones relevantes en este archivo.
- Revisa el estilo y consistencia del código antes de integrar cambios.

## Próximos pasos sugeridos
- Definir objetivos funcionales y alcance del proyecto.
- Agregar estructura para vistas, controladores y modelos si la aplicación crece.
- Incorporar pruebas automatizadas y un flujo de despliegue.
