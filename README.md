# Asesorías FIME

Plataforma web para la gestión de asesorías académicas entre estudiantes de la Facultad de Ingeniería Mecánica y Eléctrica (FIME - UANL), con control de acceso por roles, validación documental de tutores (Kardex PDF), reserva de cupos con transacciones MySQL y calificación por sesión.

* **Materia:** Laboratorio de Programación Web (Grupo 207 · Carrera ITS · Hora N1)
* **Instructor:** Ing. Luis Daniel Lepe Rodríguez
* **Semestre:** AGO-DIC 2026
* **Equipo (Matrículas):** `2160859`, `2158224`, `2105894`, `2226653`

---

## Tecnologías utilizadas

* **Front-end:** HTML5, CSS3, JavaScript (Fetch API) y Bootstrap 5 + Bootstrap Icons.
* **Back-end:** PHP 8+ (PDO con sentencias preparadas, sesiones PHP, `password_hash()` / `password_verify()`).
* **Base de datos:** MySQL 8+ (esquema `asesorias_fime` con 14 tablas en Tercera Forma Normal).
* **Control de versiones:** Git y GitHub.

---

## Estructura del proyecto

```text
AsesoriasFIME/
├── Frontend/
│   ├── index.html, mapa.html, faq.html, auth.html, recuperar_password.html, solicitud_tutor.html
│   ├── Estudiante/   # Panel, búsqueda de materias/horarios, tutores, horario semanal, peticiones
│   ├── Tutor/        # Panel, materias ofertadas, CRUD de horarios (cupo 1–30), pase de asistencia
│   ├── Admin/        # Panel Jefatura, solicitudes con Kardex PDF, gestión de tutores, catálogo, peticiones
│   ├── css/          # variables.css, navbar.css, componentes.css, auth.css, estilos.css
│   ├── js/           # main.js (navbar por rol, sincronización de sesión, fetch helper y toast)
│   └── img/          # Recursos gráficos y mapa del campus
├── Backend/
│   ├── config/db.php         # Conexión PDO (acepta variables de entorno o Backend/config/local.php)
│   ├── helpers/              # auth_guard.php y response.php
│   ├── api/
│   │   ├── auth/             # registro, login, logout, me, recuperar, solicitud_tutor
│   │   ├── catalogos/        # carreras, materias
│   │   ├── estudiante/       # horarios_disponibles, tutores, citas, calificar, peticiones
│   │   ├── tutor/            # materias, horarios, alumnos_asistencia
│   │   └── admin/            # resumen, solicitudes, ver_kardex, tutores, catalogo, peticiones
│   ├── uploads/kardex/       # Almacenamiento de Kardex PDF subidos (ignorado en Git)
│   └── seed_demo.php         # Script idempotente para cargar datos y cuentas de prueba
├── asesoriasfime.sql         # DDL completo (14 tablas) + catálogos iniciales
├── seed.sql                  # Materias iniciales opcionales
└── .gitignore
```

---

## Cómo se monta y ejecuta

### Requisitos previos
* **PHP 8.0+** con extensiones `pdo_mysql`, `mbstring` y `fileinfo` habilitadas.
* **MySQL 8.0+** (o XAMPP / Laragon).

### 1. Crear la base de datos
Importa y ejecuta completo el archivo [`asesoriasfime.sql`](./asesoriasfime.sql) en **MySQL Workbench** o **phpMyAdmin** (o vía CLI):

```bash
mysql -u root -p < asesoriasfime.sql
```

Esto crea la base de datos `asesorias_fime`, sus 14 tablas y los catálogos base (`Roles`, `Carreras`, `Urgencias`, `EstadosRevision`, `Turnos`).

### 2. Configurar credenciales de MySQL (si aplica)
Por defecto [`Backend/config/db.php`](./Backend/config/db.php) se conecta a `127.0.0.1:3306` con usuario `root` y contraseña vacía (`''`).  
Si tu servidor MySQL utiliza contraseña, crea el archivo **`Backend/config/local.php`** *(ignorado por `.gitignore`)*:

```php
<?php
return [
    'host' => '127.0.0.1',
    'port' => '3306',
    'name' => 'asesorias_fime',
    'user' => 'root',
    'pass' => 'TU_CONTRASENA_MYSQL',
];
```

*(También puedes definir las variables de entorno `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS`).*

### 3. Levantar el servidor local

* **Opción A — Servidor integrado de PHP:**
  ```bash
  cd AsesoriasFIME
  php -S localhost:8000
  ```
  URL base: `http://localhost:8000/Frontend/index.html`

* **Opción B — XAMPP / Laragon (Apache):**
  Coloca el proyecto dentro de `htdocs/AsesoriasFIME` (o `www/AsesoriasFIME`) y enciende Apache y MySQL.  
  URL base: `http://localhost/AsesoriasFIME/Frontend/index.html`

### 4. Cargar las cuentas y datos de prueba
Ejecuta **una sola vez** el sembrador para insertar cuentas demo con contraseña cifrada (`password_hash`), materias y horarios de ejemplo:

* Desde navegador: `http://localhost:8000/Backend/seed_demo.php` *(o `http://localhost/AsesoriasFIME/Backend/seed_demo.php` en XAMPP)*
* O desde terminal: `php Backend/seed_demo.php`

---

## Cuentas de prueba (`seed_demo.php`)

Contraseña común para todas las cuentas: **`Fime2026!`** (puedes iniciar sesión con **correo o matrícula**).

| Rol | Nombre | Correo / Matrícula | Contraseña |
| :--- | :--- | :--- | :--- |
| **Administrador (Jefatura)** | Jefatura FIME | `admin@uanl.edu.mx` / `1000001` | `Fime2026!` |
| **Tutor** | Tutor Prueba Uno | `tutor1@uanl.edu.mx` / `2160859` | `Fime2026!` |
| **Tutor** | Tutor Prueba Dos | `tutor2@uanl.edu.mx` / `2158224` | `Fime2026!` |
| **Estudiante** | Estudiante Uno | `alumno1@uanl.edu.mx` / `2105894` | `Fime2026!` |
| **Estudiante** | Estudiante Dos | `alumno2@uanl.edu.mx` / `2226653` | `Fime2026!` |
| **Estudiante** | Estudiante Tres | `alumno3@uanl.edu.mx` / `2200111` | `Fime2026!` |

---

## Funcionalidades por rol

* **Visitante:** Consulta página de inicio, mapa del campus, preguntas frecuentes, registro de estudiantes y recuperación de contraseña.
* **Estudiante:** Busca horarios disponibles por materia/tutor/aula, consulta tutores activos, agenda o cancela asesorías (validación transaccional `SELECT ... FOR UPDATE` contra sobrecupo y empalme de turno), califica asesorías inscritas (1 a 5 estrellas por sesión), solicita nuevas materias a Jefatura y postula a Tutor adjuntando su Kardex en PDF.
* **Tutor:** Publica materias previamente autorizadas por Jefatura, crea/edita/elimina horarios con turnos oficiales (`M1–M6`, `V1–V6`, `N1–N6`) y cupo de `1` a `30` alumnos por sesión, y registra asistencia por clase.
* **Administrador (Jefatura):** Revisa solicitudes y descarga Kardex PDF para aprobar o rechazar aspirantes a tutor, autoriza materias por tutor, suspende o da de baja tutores, administra el catálogo oficial de materias (CRUD) y gestiona peticiones de los estudiantes.
