# Guia de diseno responsive y despliegue

**Proyecto:** N-Woffu Prime  
**Fecha:** 30 de julio de 2026  
**Estado:** Propuesta para aprobacion  
**Objetivo:** Definir como la aplicacion debe funcionar correctamente en PC, tablet y telefono antes de publicarse.

## Decision principal

N-Woffu Prime debe desarrollarse como una aplicacion web responsive desde el inicio. Esto significa que la misma aplicacion debe adaptarse a escritorio y telefono sin crear dos sistemas separados.

El despliegue en una plataforma de hosting no vuelve responsive la aplicacion automaticamente. La adaptacion a PC y telefono debe resolverse en el diseno, HTML, CSS y componentes del frontend.

## Enfoque recomendado

| Dispositivo | Enfoque |
|---|---|
| PC / escritorio | Panel completo, tablas, filtros visibles, calendario amplio y acciones rapidas. |
| Tablet | Diseno intermedio, filtros compactos y calendario adaptable. |
| Telefono | Navegacion simple, formularios verticales, tablas convertidas en tarjetas y acciones tactiles. |

## Puntos de corte recomendados

| Vista | Ancho aproximado |
|---|---:|
| Movil | hasta 767 px |
| Tablet | 768 px a 1023 px |
| Escritorio | 1024 px en adelante |

## Navegacion

### Escritorio

- Menu lateral fijo o superior compacto.
- Acceso directo a solicitudes, calendario, usuarios, informes y configuracion.
- Filtros visibles en el panel administrativo.

### Telefono

- Menu inferior o menu lateral plegable.
- Botones grandes y faciles de tocar.
- Priorizar las acciones principales: nueva solicitud, calendario, historial y perfil.
- En administracion movil, mostrar primero pendientes y acciones esenciales.

## Dashboard de usuario

### Escritorio

- Resumen de saldo en la parte superior.
- Calendario y solicitudes recientes visibles en la misma pantalla.
- Boton principal: Nueva solicitud.

### Telefono

- Tarjetas compactas: saldo disponible, pendiente y proxima ausencia.
- Boton Nueva solicitud siempre visible o facil de alcanzar.
- Historial como lista vertical.

## Panel administrador

### Escritorio

- Bandeja de solicitudes con tabla, filtros y acciones rapidas.
- Calendario de equipo amplio.
- Acceso completo a configuracion de reglas.

### Telefono

- La bandeja debe convertirse en tarjetas.
- Los filtros deben abrirse en un panel desplegable.
- Las acciones de aprobar/rechazar deben pedir confirmacion.
- La configuracion avanzada puede ser visible, pero con formularios divididos por secciones.

## Calendario

### Escritorio

- Vista mensual completa.
- Filtros por empleado, estado, motivo, equipo y ubicacion.
- Diferenciar festivos, no laborables, pendientes y aprobadas.

### Telefono

- Vista agenda como predeterminada.
- Vista mensual compacta opcional.
- Cada dia debe abrir un detalle claro con solicitudes y estados.

## Formularios

- En escritorio pueden tener dos columnas cuando el contenido lo permita.
- En telefono deben ser de una sola columna.
- Los campos de fecha y hora deben ser grandes y faciles de usar.
- El calculo preliminar de dias u horas debe mostrarse antes de enviar.
- Los errores deben aparecer junto al campo correspondiente.

## Tablas y listados

En escritorio se pueden usar tablas completas.

En telefono, las tablas deben convertirse en tarjetas con esta estructura:

- Nombre del empleado o motivo.
- Rango de fechas.
- Estado.
- Duracion.
- Accion principal.
- Acciones secundarias en menu.

## Estilo visual

Se recomienda mantener el estilo minimalista inspirado en la referencia visual indicada:

- Fondo claro.
- Texto negro o gris muy oscuro.
- Verde lima como acento principal.
- Bordes visibles y limpios.
- Tarjetas sencillas, sin exceso de decoracion.
- Iconos claros para acciones.
- Uso moderado del color para no cansar en uso diario.

## Accesibilidad tactil

- Botones tactiles de al menos 44 px de alto.
- Espaciado suficiente entre acciones.
- Contraste alto en textos importantes.
- Estados identificables por color y texto, no solo por color.
- Formularios navegables con teclado en escritorio.

## PWA opcional

Aunque no se desarrollen aplicaciones nativas para iOS o Android en el MVP, se puede preparar la aplicacion como PWA en una fase posterior.

Ventajas:

- Acceso desde icono en el telefono.
- Mejor experiencia movil.
- Posibilidad futura de notificaciones push.
- No requiere crear una app nativa al inicio.

Para el MVP, la prioridad debe ser primero una web responsive estable. La PWA puede ser una mejora posterior.

## Consideracion sobre despliegue

Si la plataforma donde se subira el proyecto soporta PHP, la aplicacion completa puede desplegarse alli junto con la conexion a Supabase.

Si la plataforma no soporta PHP o esta orientada solo a frontend estatico/JavaScript, se recomienda separar:

- Frontend responsive en la plataforma de despliegue.
- Backend PHP en un hosting compatible con PHP.
- Base de datos y archivos en Supabase.

Antes de publicar, se debe confirmar que el hosting elegido soporta:

- PHP.
- Variables de entorno.
- HTTPS.
- Conexion externa a Supabase/PostgreSQL.
- Envio de correos o integracion SMTP.
- Tareas programadas o alternativa para recordatorios.

## Criterios de aceptacion responsive

Antes de considerar lista una version publicada, deben validarse estos puntos:

1. El usuario puede iniciar sesion desde telefono y PC.
2. El usuario puede crear una solicitud completa desde telefono.
3. El administrador puede aprobar o rechazar desde PC.
4. El administrador puede revisar pendientes desde telefono.
5. El calendario no se rompe en pantallas pequenas.
6. Los formularios no tienen textos montados o botones fuera de pantalla.
7. Los adjuntos pueden subirse desde telefono.
8. Las tablas administrativas se convierten correctamente en tarjetas moviles.
9. Los correos se envian correctamente despues de crear, aprobar o rechazar solicitudes.
10. La configuracion de reglas puede verse y editarse en escritorio; en movil debe poder consultarse y modificar reglas simples.

## Recomendacion para N-Woffu Prime

Construir primero una web responsive completa. Para escritorio, priorizar productividad administrativa. Para telefono, priorizar solicitudes rapidas, consulta de saldo, historial y aprobaciones simples.

La aplicacion debe verse moderna y minimalista, pero su diseno debe seguir siendo practico para uso diario de empleados y administradores.
