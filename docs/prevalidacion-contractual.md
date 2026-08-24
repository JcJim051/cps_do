# Prevalidación contractual

## Puesta en marcha

1. Ingresar como `admin` o `diana` a **Prevalidación contractual → Configurar Google Drive**.
2. Seguir la guía vertical para crear el proyecto, habilitar Google Sheets API y Google Drive API y crear un cliente OAuth 2.0 tipo aplicación web.
3. Copiar en Google Cloud la URI de redirección que Integra muestra en pantalla.
4. Registrar el Client ID y Client Secret en el formulario. Integra los guarda cifrados y no vuelve a mostrar el secreto.
5. Conectar la cuenta de Google que tiene acceso a los cuadros.
6. Entrar a **Fuentes y cuadros** y crear la fuente piloto AIM manualmente o descargar la plantilla de configuración masiva.

El Client ID y el Client Secret se administran exclusivamente desde esta pantalla; no es necesario modificar el archivo `.env`.

La cuenta OAuth debe tener acceso de edición a los cuadros porque Integra devuelve el estado y crea/actualiza la columna `EDITADO_EN_INTEGRA`.

### Error 403 de Google durante la conexión

Si Google informa que Integra está en pruebas y devuelve `access_denied`, abrir **Google Auth Platform → Audience** en el mismo proyecto del Client ID y agregar el correo que se va a conectar en **Test users → Add users**. Las autorizaciones de una aplicación externa en modo Testing caducan a los siete días; para operación permanente debe usarse una aplicación interna de la misma organización Google Workspace o completar la publicación/verificación correspondiente.

## Sincronizaciones

- Los cuadros de Drive se sincronizan desde el botón de cada fuente o desde **Sincronizar todas**.
- Cada fuente define un año objetivo. AIM sincroniza únicamente 2026 y, dentro de ese año, solo crea o mantiene visibles las filas cuyo estado sea exactamente `APROBADO`; los demás registros permanecen exclusivamente en Drive.
- Cada fila del año objetivo representa una intención contractual. Integra crea una columna `ID_INTEGRA` y asigna códigos estables con formato `CEDULA-AÑO-CONSECUTIVO`; cuando no existe documento usa `FUENTE-SD-AÑO-CONSECUTIVO`.
- El ID se escribe una sola vez en Drive y no se recalcula aunque la fila sea ordenada, trasladada o cambie de persona.
- En cada sincronización, Integra localiza por encabezado las columnas `ID_INTEGRA` y `EDITADO_EN_INTEGRA` y configura una advertencia de Google antes de editarlas. La regla cubre toda la columna y las filas futuras, aunque su letra cambie entre cuadros.
- La bandeja de prevalidación muestra solo aprobados. Al cambiar a cualquier otro estado deja de estar visible.
- Los registros `CONTRATADO` no ingresan por la sincronización ordinaria; su conciliación con Seguimiento será un proceso masivo posterior.
- El nombre original se conserva en `nombre_reportado`. Los nombres con cambios, reemplazos, cargos o comentarios quedan marcados para normalización manual antes de promoverlos.
- SECOP se actualiza al abrir un registro cuando la última consulta tiene más de 10 minutos, mediante botón y diariamente a las 02:30.
- El servidor debe ejecutar el scheduler de Laravel cada minuto (`php artisan schedule:run`) y mantener disponible el proceso habitual de colas del proyecto.

## Reglas relevantes

- Drive entrega el estado inicial. Después de una edición local, Integra prevalece y reporta conflictos.
- Un contrato SECOP solo puede vincularse a un seguimiento/prevalidación y el vínculo puede deshacerse.
- Los valores de planeación nunca se reemplazan con SECOP; las diferencias se presentan por separado.
- Solo los aprobados se promueven. La promoción crea la persona si hace falta, crea un único seguimiento, activa Despacho, cambia la fila del cuadro a `EN CONTRATACIÓN` y protege toda esa fila contra edición.
- El estado `EN CONTRATACIÓN` se distingue en el cuadro con el morado institucional de Integra (`#6A0DAD`), texto blanco y negrita.
- Integra localiza la fila por `ID_INTEGRA`, no por su posición, para evitar modificar otra fila si el cuadro fue ordenado. La cuenta Google conectada conserva permiso sobre la protección; el propietario del archivo conserva el control administrativo propio de Google Sheets.
- El cambio de estado, la marca `EDITADO_EN_INTEGRA=SI` y la protección se envían a Google en una operación atómica. Si Drive falla, el seguimiento no se duplica ni reaparece en la bandeja; se muestra una alerta con la acción **Reintentar Drive**.
- En cualquier seguimiento, `APROBADO` activa Despacho; `PENDIENTE APROBACIÓN` y `CAMBIO` lo retiran.

## Estados SECOP vigentes

El conjunto inicial está en `config/prevalidacion.php`: ejecución, suspendido y modificado. Debe validarse con los valores reales observados durante el piloto AIM antes de usar el conteo como indicador definitivo.
