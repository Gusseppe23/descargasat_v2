# Proyecto descarga_sat

- Monolito modular: cada módulo vive en app-modules/<modulo> con sus propias rutas, modelos, migraciones y pruebas.
- Un módulo solo usa a otro a través de su interfaz pública (contratos en src/Contracts); nunca sus modelos directamente.
- Todo acceso al SAT pasa por el módulo sat-autenticacion (fábrica de Service). En pruebas se usa un doble de WebClientInterface; nunca se llama al SAT real en pruebas.
- La FIEL (.cer, .key, contraseña) se guarda cifrada en storage/app/private/fiel y en BD con el cast encrypted. Nunca se registra en logs ni se muestra en respuestas.
- Antes de terminar una tarea: php artisan test, vendor/bin/pint, vendor/bin/phpstan analyse --memory-limit=1G.
- Idioma del dominio en español (Solicitud, Paquete, Fiel, Verificacion); ver CONTEXT.md.
- Todo corre dentro del contenedor Docker laravel-dev; el proyecto está en /app.
