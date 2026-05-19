# TODO — Pendientes vs Requerimientos del PDF (proyecto_02_-_Final.pdf)

Este documento lista todo lo que **falta o está incompleto/roto** en `Proyecto2/` comparado contra los requerimientos del PDF del proyecto final.

---

## 1. Requerimientos obligatorios faltantes

### 1.1 Tag `<UG>6A0E4B00</UG>` (OBLIGATORIO)
- El PDF dice: *"Colocar en las páginas un tag `<UG>6A0E4B00</UG>`"*.
- Una búsqueda en todo el proyecto no encuentra el tag en ningún archivo.
- **Acción:** agregar `<UG>6A0E4B00</UG>` en `header.php` (o `footer.php`) para que aparezca en todas las páginas.

### 1.2 Generación automática de fases siguientes
- El PDF exige: *"la aplicación deberá generar automáticamente los equipos que se enfrentan de las fases siguientes a partir de los resultados ya ingresados"*.
- Actualmente `cargar_mundial.php` carga los partidos de eliminación con placeholders del JSON (`1A`, `2B`, `W73`, etc.), pero **no existe lógica que resuelva esos placeholders** una vez terminados los partidos de grupos / fases previas.
- **Acción:** implementar una función (al guardar resultados en `admin-partidos.php`) que:
  - Calcule los 2 mejores de cada grupo (1A, 2A, 1B, 2B, ...).
  - Reemplace los placeholders `1A`, `2B`, etc. en `Partido.id_equipo1/2` con los `id_equipo` reales.
  - Para fases de eliminación: cuando un partido termine, resolver los `W<id_partido>` / `L<id_partido>` en los partidos posteriores.

### 1.3 Validación robusta de traslapes
- El PDF pide *"verificando que no existan traslapes"*.
- En `admin-partidos.php` solo se valida que el **Equipo 1** no tenga otro partido a la misma fecha+hora exacta. **No valida el Equipo 2**, ni considera duración del partido (~2 horas), ni el uso de la misma sede.
- **Acción:** ampliar la validación para ambos equipos y para una ventana temporal razonable.

### 1.4 CRUD completo (solo hay Create/Read)
- El PDF exige *"páginas de administración (CRUD)"*.
- `admin-equipos.php`: solo crea y lista. **Falta Update y Delete.**
- `admin-partidos.php`: solo crea, lista y actualiza marcador. **Falta editar fecha/hora/fase y eliminar partidos.**
- **Acción:** agregar formularios/botones Editar y Eliminar para Equipos y Partidos.

### 1.5 Documentación a entregar
El PDF exige entregar:
- [ ] **Modelo E/R** — no existe en el proyecto.
- [ ] **Manual de Usuario** — no existe en el proyecto.
- [ ] Subir código fuente y script SQL por **GES**.

---

## 2. Bugs que bloquean el funcionamiento

### 2.1 Links rotos en `header.php`
`header.php` apunta a archivos que no existen:
- `reporte-tabla-posiciones.php` → el archivo real es `reporte-posiciones.php`.
- `page-commercial.php` → el archivo real es `admin-equipos.php`.
- `page-residential.php` → el archivo real es `admin-partidos.php`.

**Acción:** corregir los `href` en `header.php`.

### 2.2 `cargar_mundial.php` usa columnas inexistentes
Hace `INSERT INTO Partido (..., team1, team2, ...)`, pero el esquema (`proyecto2.sql`) define las columnas como `id_equipo1` e `id_equipo2`. Esto hará fallar la carga inicial del Mundial.

**Acción:** renombrar a `id_equipo1` / `id_equipo2` en el `INSERT`.

### 2.3 Usuario admin por defecto no puede iniciar sesión
`proyecto2.sql` inserta:
```sql
INSERT INTO Usuario (Username, nombre, pass, es_admin)
VALUES ('juan', 'Administrador General', '1234', TRUE);
```
La contraseña `'1234'` está en texto plano, pero `index.php` usa `password_verify()` (bcrypt). El login del admin va a fallar.

**Acción:** insertar el hash bcrypt, por ejemplo:
```sql
INSERT INTO Usuario (Username, nombre, pass, es_admin)
VALUES ('juan', 'Administrador General',
        '$2y$10$<hash_bcrypt_de_1234>', TRUE);
```
o crear un script PHP que genere el hash y lo inserte.

### 2.4 Tabla `Quiniela` sin UNIQUE para `ON CONFLICT`
`quinielas.php` usa `ON CONFLICT (id_usuario, id_partido)` pero el script SQL **no define** esa restricción única (solo PK serial). Aunque el código tiene fallback manual, el primer `INSERT ... ON CONFLICT` lanzaría error.

**Acción:** añadir al schema:
```sql
ALTER TABLE Quiniela ADD CONSTRAINT uq_quiniela_usuario_partido
    UNIQUE (id_usuario, id_partido);
```

### 2.5 `quinielas.php` referencia columna inexistente
Usa `$p['fase']` en la vista, pero la columna del JOIN es `id_fase` (y el nombre legible está en `Fase.nombre_fase`, que no se trae en el query). Imprimirá vacío.

**Acción:** unir `Fase f ON p.id_fase = f.id_fase` y mostrar `$p['nombre_fase']`.

### 2.6 Fases F4–F7 faltan en el `INSERT` de `admin-partidos.php`
`admin-partidos.php` solo crea F1, F2, F3. Si nunca se corre `cargar_mundial.php`, no existirán Cuartos, Semifinal, Tercer Lugar ni Final.

**Acción:** añadir F4–F7 al `INSERT ... ON CONFLICT DO NOTHING`.

### 2.7 `Fase.orden` no se llena en `cargar_mundial.php`
El `INSERT` omite la columna `orden`, dejándola `NULL`. Los `ORDER BY orden` mostrarán las fases en orden indeterminado.

**Acción:** incluir `orden` en el insert.

---

## 3. Puntos extra (opcionales según el PDF)

- [ ] **Parametrizar número de equipos y grupos.** Actualmente el JSON tiene 48 equipos / 12 grupos hardcodeados; no hay UI ni configuración para cambiarlo.
- [x] **Almacenar imágenes en BD.** Ya hay columna `bandera_blob BYTEA` y `admin-equipos.php` la usa. (✔ Listo.)

---

## 4. Mejoras menores recomendadas

- `proyecto2.sql` define `Fase` con solo F1–F3 en el comentario pero ningún `INSERT`. Confiar en `cargar_mundial.php` para inicializar el catálogo es frágil — agregar inserts base directamente en el script SQL.
- `Partido.id_equipo1` y `id_equipo2` están declaradas `VARCHAR(50)` sin FK para permitir placeholders. Considerar documentar este compromiso en el script SQL.
- En `index.php` la asignación `$_SESSION['username'] = $user['username']` usa la clave en minúsculas, pero la columna se llama `Username`. PostgreSQL retorna nombres en minúsculas por defecto, así que funciona — verificar.
- Las hojas de estilo `css/style2.css … style5.css` no están enlazadas desde ninguna parte. Eliminar o referenciar.
