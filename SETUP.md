# SETUP — Cómo correr el proyecto

Aplicación web de quinielas para el Mundial 2026. Stack: **Apache + PHP + PostgreSQL** (sugerido por el PDF del proyecto).

XAMPP no trae PostgreSQL por defecto, así que el flujo recomendado para Windows es **XAMPP (Apache + PHP) + PostgreSQL instalado aparte**.

---

## 1. Requisitos previos

| Componente | Versión sugerida | Notas |
|---|---|---|
| Sistema Operativo | Windows 10/11 | También funciona en macOS/Linux con XAMPP equivalente |
| XAMPP | 8.2 o superior | Provee Apache + PHP 8.x |
| PostgreSQL | 14 o superior | Instalación independiente |
| pgAdmin 4 | (incluido con PostgreSQL) | Para correr el script SQL |
| Navegador moderno | Chrome / Edge / Firefox | |

Descargas:
- XAMPP: <https://www.apachefriends.org/>
- PostgreSQL + pgAdmin: <https://www.postgresql.org/download/windows/>

---

## 2. Instalar y configurar XAMPP

1. Instala XAMPP en la ruta por defecto (`C:\xampp`).
2. Abre el **XAMPP Control Panel** y arranca **Apache** (no se necesita MySQL).
3. Confirma que funciona abriendo <http://localhost/> en el navegador.

### Habilitar la extensión PDO de PostgreSQL en PHP

El driver `pdo_pgsql` viene con XAMPP pero suele estar deshabilitado.

1. Abre `C:\xampp\php\php.ini` con un editor de texto.
2. Busca y **descomenta** (quita el `;` del inicio) estas dos líneas:
   ```
   extension=pdo_pgsql
   extension=pgsql
   ```
3. Guarda el archivo y **reinicia Apache** desde el XAMPP Control Panel.
4. (Opcional) Verifica creando `C:\xampp\htdocs\info.php` con:
   ```php
   <?php phpinfo(); ?>
   ```
   Abre <http://localhost/info.php> y busca **pdo_pgsql** en la página. Debe aparecer como *enabled*.

---

## 3. Instalar y configurar PostgreSQL

1. Instala PostgreSQL aceptando los valores por defecto.
2. Durante la instalación te pedirá una **contraseña** para el usuario `postgres`.
   - El proyecto está configurado con `password = "2003"` en `Proyecto2/conexion.php`.
   - **O bien** usa esa misma contraseña al instalar, **o** edita `conexion.php` después con la contraseña que hayas elegido.
3. Deja el puerto por defecto **5432**.

---

## 4. Crear la base de datos

1. Abre **pgAdmin 4**, conéctate al servidor local con el usuario `postgres`.
2. Abre la **Query Tool** (Tools → Query Tool).
3. Copia y ejecuta el contenido completo del archivo `proyecto2.sql` (en la raíz del repo). Esto crea la base `proyecto2` y todas las tablas.
4. **Importante (ver `TODO.md`):** el usuario admin insertado por el script tiene la contraseña en texto plano y no podrá iniciar sesión hasta arreglarlo. Como solución rápida, ejecuta en pgAdmin (después del script) reemplazando el hash:
   ```sql
   UPDATE Usuario
   SET pass = '<hash_bcrypt_generado_con_PHP>'
   WHERE Username = 'juan';
   ```
   Para generar el hash, crea un PHP temporal:
   ```php
   <?php echo password_hash('1234', PASSWORD_BCRYPT); ?>
   ```
   Copia la salida y úsala en el `UPDATE` anterior. Login: `juan` / `1234`.

---

## 5. Copiar el proyecto al servidor web

1. Copia (o mueve) toda la carpeta `Proyecto2/` (la que contiene los `.php`, `.css`, `.json`) a:
   ```
   C:\xampp\htdocs\Proyecto2\
   ```
2. La estructura debe quedar así:
   ```
   C:\xampp\htdocs\Proyecto2\
       index.php
       header.php
       footer.php
       conexion.php
       admin-equipos.php
       admin-partidos.php
       quinielas.php
       cargar_mundial.php
       reporte-calendario.php
       reporte-posiciones.php
       reporte-quinielas.php
       logout.php
       style.css
       worldcup.json
       worldcup.teams_meta.json
       css\
   ```

---

## 6. Cargar los datos iniciales del Mundial 2026

1. Abre <http://localhost/Proyecto2/cargar_mundial.php> **una sola vez**. Esto:
   - Inserta los 48 equipos y 12 grupos desde `worldcup.teams_meta.json`.
   - Crea las fases F1–F7.
   - Inserta el calendario completo desde `worldcup.json`.
2. Verás un resumen "Se han registrado N partidos…".
3. **Nota (ver `TODO.md` §2.2):** el script intenta insertar en columnas `team1`/`team2` que no existen en el schema actual. Antes de correr este paso debes corregir el `INSERT` para usar `id_equipo1` / `id_equipo2`.

---

## 7. Usar la aplicación

1. Abre <http://localhost/Proyecto2/index.php>.
2. **Login como administrador:** `juan` / `1234` (tras aplicar el hash bcrypt del paso 4).
3. Como admin tienes acceso a:
   - Administración de Equipos (CRUD)
   - Administración de Partidos / Resultados (CRUD)
4. **Registro de participantes:** desde el formulario "Crear Cuenta Nueva" en la página principal. Los usuarios normales pueden:
   - Llenar sus quinielas (Mis Pronósticos)
   - Ver Calendario, Tabla de Posiciones y Ranking de Quinielas

---

## 8. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| `could not find driver` al abrir el sitio | `pdo_pgsql` no habilitado | Paso 2: descomentar extensiones en `php.ini` y reiniciar Apache |
| `SQLSTATE[08006] connection refused` | PostgreSQL no está corriendo | Inicia el servicio `postgresql-x64-XX` desde `services.msc` |
| `password authentication failed for user "postgres"` | Contraseña distinta a la del código | Edita `Proyecto2/conexion.php` y pon tu contraseña real |
| Login del admin falla | Hash bcrypt no aplicado | Paso 4 final: actualizar `Usuario.pass` con hash bcrypt |
| Links del menú dan 404 (`page-commercial.php`, etc.) | Bug en `header.php` | Ver `TODO.md` §2.1 |
| `cargar_mundial.php` falla con `column "team1" does not exist` | Bug en el script de carga | Ver `TODO.md` §2.2 |

---

## 9. URLs útiles (cuando todo está arriba)

- Login / Registro: <http://localhost/Proyecto2/index.php>
- Cargar Mundial 2026 (one-shot): <http://localhost/Proyecto2/cargar_mundial.php>
- Calendario: <http://localhost/Proyecto2/reporte-calendario.php>
- Tabla de Posiciones: <http://localhost/Proyecto2/reporte-posiciones.php>
- Ranking de Quinielas: <http://localhost/Proyecto2/reporte-quinielas.php>
- Admin Equipos: <http://localhost/Proyecto2/admin-equipos.php> (solo admin)
- Admin Partidos: <http://localhost/Proyecto2/admin-partidos.php> (solo admin)
