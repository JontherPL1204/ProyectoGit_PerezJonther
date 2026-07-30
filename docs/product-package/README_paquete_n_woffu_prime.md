# Paquete de documentacion - N-Woffu Prime

**Fecha:** 30 de julio de 2026  
**Objetivo:** Reunir en una sola carpeta la documentacion funcional, mejoras, reglas configurables, estructura administrativa, diseno responsive y alcance de trabajo del proyecto.

## Orden recomendado de lectura

1. `documentacion_sistema_gestion_ausencias.docx`
   - Documento funcional y tecnico base del sistema.

2. `hoja_mejoras_para_aprobacion.rtf`
   - Resumen ejecutivo de mejoras propuestas para revisar y aprobar.

3. `reglas_base_configurables_n_woffu_prime.rtf`
   - Reglas iniciales del proyecto: vacaciones, anticipacion, saldos, documentos medicos y notificaciones.

4. `estructura_admin_configuracion_reglas_n_woffu_prime.rtf`
   - Estructura del apartado del administrador para modificar reglas dentro de la aplicacion.

5. `guia_diseno_responsive_y_despliegue_n_woffu_prime.rtf`
   - Guia para adaptar la aplicacion a PC, tablet y telefono, y consideraciones para publicarla.

6. `reevaluacion_alcance_trabajo_codex_n_woffu_prime.rtf`
   - Matriz de lo que Codex puede hacer solo y lo que requiere decision, acceso o validacion del usuario.

7. `informe_pre_ejecucion_n_woffu_prime.rtf`
   - Veredicto sobre si la documentacion esta lista para iniciar ejecucion tecnica y que bloqueantes quedan.

## Versiones incluidas

Cada documento derivado esta incluido en dos formatos:

- `.rtf`: para abrir en Word u otro editor de documentos.
- `.md`: para leer o editar como texto tecnico.

## Decisiones ya incorporadas

- Nombre del producto: N-Woffu Prime.
- Uso inicial: interno para el equipo.
- Posible evolucion futura: SaaS multiempresa.
- Vacaciones base: 15 dias anuales configurables.
- Anticipacion base: 30 dias naturales configurables.
- Canal oficial inicial de notificaciones: correo electronico.
- Documentos medicos: privados y visibles para administrador autorizado.
- Reglas importantes: editables desde el apartado administrativo.
- Diseno: minimalista, moderno, responsive para PC y telefono.

## Pendientes principales

- Confirmar si "un mes" significa 30 dias naturales o mismo dia del mes anterior.
- Definir prorrateo de vacaciones para ingresos a mitad de periodo.
- Definir traspaso o perdida de vacaciones no usadas.
- Definir politica de conservacion de documentos medicos.
- Confirmar capacidades del hosting final, especialmente soporte PHP, SMTP, variables de entorno y tareas programadas.

## Veredicto de pre-ejecucion

La documentacion esta lista para iniciar ejecucion tecnica controlada: arquitectura, migraciones, API, prototipo responsive y estructura Laravel. Todavia no esta lista para produccion hasta cerrar credenciales, SMTP, hosting final, prorrateo, traspaso y politica de conservacion de documentos medicos.
