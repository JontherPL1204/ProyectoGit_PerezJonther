# Reevaluacion de alcance de trabajo

**Proyecto:** N-Woffu Prime  
**Fecha:** 30 de julio de 2026  
**Objetivo:** Definir que partes puede desarrollar Codex sin ayuda directa y que partes requieren decision, acceso o validacion del propietario del proyecto.

## Conclusion corta

Codex puede preparar y construir casi todo el proyecto a nivel tecnico: documentacion derivada, diseno responsive, frontend, backend PHP, base de datos, reglas configurables, panel de administrador, API, pruebas y despliegue guiado.

Lo que Codex no puede hacer completamente solo son las decisiones de negocio, aprobaciones legales/laborales, credenciales reales, cuentas externas y validacion final con usuarios reales.

## Puede hacerlo Codex sin ayuda directa

| Area | Trabajo que puede realizar Codex | Estado |
|---|---|---|
| Documentacion | Convertir el Word en documentos tecnicos derivados | Puede hacerlo |
| Arquitectura | Definir estructura PHP, servicios, repositorios, validadores y controladores | Puede hacerlo |
| Base de datos | Crear migraciones PostgreSQL, restricciones, indices y datos iniciales | Puede hacerlo |
| API | Definir e implementar contrato `/api/v1` | Puede hacerlo |
| Frontend | Crear interfaz responsive para PC, tablet y telefono | Puede hacerlo |
| Diseno | Aplicar estilo minimalista inspirado en la referencia visual | Puede hacerlo |
| Panel de usuario | Dashboard, calendario, saldo, historial y nueva solicitud | Puede hacerlo |
| Panel administrador | Solicitudes, aprobaciones, usuarios, jornadas, festivos, informes y reglas | Puede hacerlo |
| Configuracion de reglas | Crear estructura para editar reglas desde administracion | Puede hacerlo |
| Adjuntos | Validar archivos, guardar metadatos y preparar storage privado | Puede hacerlo |
| Auditoria | Registrar eventos de solicitudes, saldo, adjuntos y cambios de reglas | Puede hacerlo |
| Notificaciones | Preparar envio por correo y plantillas base | Puede hacerlo |
| Pruebas | Crear pruebas unitarias, integracion y seguridad basica | Puede hacerlo |
| Responsive | Validar pantallas moviles y escritorio durante desarrollo | Puede hacerlo |
| Manuales | Preparar manual de administracion y despliegue | Puede hacerlo |

## Puede hacerlo Codex usando supuestos razonables

| Area | Supuesto recomendado | Necesita aprobacion posterior |
|---|---|---|
| Autenticacion | PHP/Laravel gestiona sesiones; Supabase se usa como PostgreSQL y Storage | Si |
| Framework | Laravel para acelerar seguridad, migraciones, colas, politicas y pruebas | Si |
| Vacaciones | 15 dias anuales configurables por empresa | Ya indicado, confirmar |
| Anticipacion | 30 dias naturales como interpretacion de "un mes" | Si |
| Solicitudes pendientes | Reservan saldo provisional | Si |
| Saldos negativos | No permitidos por defecto | Si |
| Documentos medicos | Visibles para administrador con permiso separado | Ya indicado, confirmar |
| Notificaciones | Correo electronico como canal oficial inicial | Ya indicado |
| SaaS futuro | Modelo preparado con `organization_id` desde el inicio | Si |
| Movil | Web responsive primero, PWA despues | Si |

## Necesita ayuda, decision o acceso del usuario

| Tema | Por que necesita al usuario |
|---|---|
| Credenciales de Supabase | Codex no debe inventar ni conocer claves reales sin que se configuren de forma segura |
| Cuenta de correo/SMTP | Hace falta servidor, usuario, clave y remitente autorizado |
| Hosting final | Se necesita acceso o datos tecnicos de la plataforma donde se publicara |
| Hosting de produccion | Hay que confirmar si soporta PHP, variables de entorno, HTTPS, SMTP y tareas programadas |
| Dominio | Requiere compra, configuracion DNS o acceso al proveedor |
| Politica laboral | Los 15 dias y reglas internas deben estar aprobados por la empresa |
| Documentos medicos | La politica de acceso y conservacion debe validarse por responsabilidad legal/privacidad |
| Prorrateo | Debe definirse como regla de negocio de la empresa |
| Traspaso de vacaciones | Debe definirse si los dias no usados se pierden o pasan al siguiente periodo |
| Usuarios reales | Hace falta listado o alta manual desde admin |
| Validacion final | Usuarios y administradores deben probar los flujos reales |

## Alcance recomendado que Codex si puede ejecutar ahora

1. Crear el documento tecnico final del MVP.
2. Crear migraciones PostgreSQL.
3. Crear contrato de API.
4. Crear diseno base responsive.
5. Crear prototipo navegable de interfaz.
6. Crear backend PHP/Laravel.
7. Crear panel de usuario.
8. Crear panel administrador.
9. Crear modulo de configuracion de reglas.
10. Crear modulo de solicitudes, aprobaciones y saldos.
11. Crear auditoria.
12. Preparar notificaciones por correo.
13. Preparar pruebas.
14. Preparar guia de despliegue.

## Riesgos si Codex avanza sin aprobacion

- Se podria escoger una interpretacion de "un mes" que luego la empresa no use.
- Se podria definir una regla de prorrateo diferente a la esperada.
- Se podria preparar un despliegue para PHP y descubrir que el hosting elegido no lo soporta.
- Se podria activar visibilidad medica para administradores cuando la empresa necesite permisos mas restrictivos.
- Se podria crear un flujo SaaS demasiado avanzado para la primera version interna.

## Recomendacion practica

Codex puede avanzar sin ayuda en una primera version tecnica usando estos valores base:

- Producto: N-Woffu Prime.
- Uso inicial: interno.
- Preparado para SaaS: si, con `organization_id`.
- Vacaciones: 15 dias anuales configurables.
- Anticipacion: 30 dias naturales configurables.
- Pendientes: reservan saldo provisional.
- Notificaciones: correo.
- Documentos medicos: privados, visibles solo para administrador con permiso especifico.
- Reglas: editables desde panel administrador.
- Diseno: minimalista, claro, responsive para PC y telefono.

Antes de publicar en produccion, el usuario debe aportar o confirmar:

- Acceso a Supabase o variables de entorno.
- Datos SMTP.
- Plataforma de hosting final y sus capacidades.
- Politica de conservacion de documentos medicos.
- Regla de prorrateo y traspaso de vacaciones.
- Pruebas con al menos un administrador y un empleado real.
