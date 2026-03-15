# Guía de Instalación en cPanel
## Sistema de Gestión de Contratación Pública — Ecuador

---

## OPCIÓN A: Hosting con acceso al directorio padre de public_html ✅ (Recomendado)

Esta es la estructura más segura. El código PHP queda **fuera** de public_html.

```
/home/tuusuario/
├── public_html/          ← raíz web (solo esta carpeta es accesible)
│   ├── index.php
│   ├── .htaccess
│   └── assets/
└── contratacion-saas/   ← código PHP (NO accesible desde web)
    ├── app/
    ├── core/
    ├── config/
    ├── database/
    ├── resources/
    ├── routes/
    ├── storage/
    └── .env
```

### Pasos:

1. **Subir archivos vía FTP o Administrador de Archivos de cPanel**
   - Sube la carpeta `contratacion-saas/` a `/home/tuusuario/` (al mismo nivel que public_html)
   - Sube el contenido de `public_html/` a `/home/tuusuario/public_html/`

2. **Verificar que `index.php` apunte correctamente**
   - `ROOT_PATH = dirname(__DIR__)` → apunta a `/home/tuusuario/contratacion-saas`
   - ✅ Esto ya está configurado por defecto

---

## OPCIÓN B: Todo dentro de public_html (hosting básico)

Si tu hosting NO permite acceder al directorio padre:

```
/home/tuusuario/public_html/
├── index.php
├── .htaccess
├── assets/
├── app/
├── core/
├── config/
├── database/
├── resources/
├── routes/
├── storage/
└── .env
```

### Cambio requerido en `index.php`:
```php
// Línea 8 — cambiar:
define('ROOT_PATH', dirname(__DIR__));
// Por:
define('ROOT_PATH', __DIR__);
```

### Agregar a `.htaccess` para proteger carpetas sensibles:
```apache
# Bloquear acceso directo a carpetas del sistema
RedirectMatch 403 ^/(app|core|config|database|resources|routes|storage)/
```

---

## PASO 1: Crear Base de Datos en cPanel

1. Ir a **cPanel → MySQL Databases**
2. Crear base de datos: `tuusuario_contratacion`
3. Crear usuario MySQL: `tuusuario_dbuser` con contraseña segura
4. Asignar usuario a la base con **todos los privilegios**
5. En **phpMyAdmin**:
   - Seleccionar la base de datos
   - Ir a **SQL**
   - Ejecutar: `database/migrations/001_create_all_tables.sql`
   - Luego ejecutar: `database/seeders/001_demo_data.sql` (opcional, para datos demo)

---

## PASO 2: Configurar el archivo .env

1. Copiar `.env.example` a `.env`
2. Editar con tus datos reales:

```env
APP_NAME="Mi Empresa"
APP_URL="https://tudominio.ec"
APP_ENV=production
APP_DEBUG=false
APP_KEY="genera-32-caracteres-aleatorios-aqui"

DB_HOST=localhost
DB_NAME=tuusuario_contratacion
DB_USER=tuusuario_dbuser
DB_PASS=tu_password

OPENROUTER_KEY=sk-or-v1-tu-key-de-openrouter
MAIL_HOST=mail.tudominio.ec
MAIL_USER=sistema@tudominio.ec
MAIL_PASS=tu_password_correo
```

**Generar APP_KEY seguro:**
```
php -r "echo bin2hex(random_bytes(16));"
```

---

## PASO 3: Permisos de carpetas

En **cPanel → Administrador de Archivos** o por SSH/FTP:

```bash
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 storage/uploads/
chmod 755 storage/documents/
chmod 755 storage/sessions/
chmod 644 .env
chmod 644 public_html/.htaccess
```

---

## PASO 4: Configurar Cron Job para alertas automáticas

En **cPanel → Cron Jobs**, agregar:

```
Minuto: 0
Hora: 8
Día: *
Mes: *
Día semana: *
Comando: curl -s "https://tudominio.ec/cron/run?token=TOKEN_DEL_DIA" > /dev/null 2>&1
```

El token cambia cada día automáticamente. Puedes ver el token actual en:
**Configuración → (panel inferior derecho)**

---

## PASO 5: Verificar instalación

1. Abrir `https://tudominio.ec`
2. Debe redirigir al login
3. Credenciales con datos demo:
   - **Email:** `admin@tecnoservicios.ec`
   - **Password:** `Admin2024*`

---

## Configuración de Subdominios (Multi-tenant)

Si quieres separar clientes por subdominio (empresa1.tudominio.ec):

1. En cPanel → **Subdominios**: crear cada subdominio apuntando a `public_html/`
2. El sistema detecta el tenant por el slug en la sesión

---

## Solución de problemas frecuentes

| Error | Solución |
|-------|----------|
| Blank page / Error 500 | Activar `APP_DEBUG=true` temporalmente y revisar `storage/logs/php_errors.log` |
| Rutas no funcionan | Verificar que `mod_rewrite` esté activo. Contactar al hosting |
| Error de base de datos | Verificar credenciales en `.env`, host siempre es `localhost` en cPanel |
| Archivos no se suben | Verificar permisos 755 en `storage/uploads/` |
| Correos no salen | Usar el SMTP de cPanel (mail.tudominio.ec puerto 587) |
| IA no funciona | Verificar `OPENROUTER_KEY` en `.env`. Obtener key en openrouter.ai |

---

## Estructura de URLs

```
/                     → redirige a /login o /dashboard
/login                → inicio de sesión
/dashboard            → panel principal
/procesos             → lista de contratos
/procesos/crear       → nuevo proceso
/procesos/{id}        → expediente completo
/instituciones        → entidades contratantes
/documentos-habilitantes → docs del proveedor
/facturas             → control de cobros
/ia                   → análisis con IA
/reportes             → Business Intelligence
/configuracion        → configuración del sistema
/cron/run?token=XXX   → cron job de alertas
```

---

## Soporte técnico

- Revisar logs en: `storage/logs/app.log` y `storage/logs/php_errors.log`
- PHP requerido: 8.1 o superior
- MySQL requerido: 5.7 o superior
- Extensiones PHP requeridas: `pdo_mysql`, `curl`, `json`, `mbstring`, `fileinfo`
