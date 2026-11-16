-- PROYECTO: Asesorías FIME - Plataforma de Tutorías entre Estudiantes

CREATE DATABASE IF NOT EXISTS asesorias_fime 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE asesorias_fime;

-- Desactivar temporalmente revisión de llaves foráneas para permitir DROP limpio
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS Calificaciones;
DROP TABLE IF EXISTS PeticionesMateria;
DROP TABLE IF EXISTS SolicitudMaterias;
DROP TABLE IF EXISTS SolicitudesTutor;
DROP TABLE IF EXISTS Citas;
DROP TABLE IF EXISTS Horarios;
DROP TABLE IF EXISTS TutorMaterias;
DROP TABLE IF EXISTS Materias;
DROP TABLE IF EXISTS Usuarios;
DROP TABLE IF EXISTS Turnos;
DROP TABLE IF EXISTS EstadosRevision;
DROP TABLE IF EXISTS Urgencias;
DROP TABLE IF EXISTS Carreras;
DROP TABLE IF EXISTS Roles;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 1. TABLAS CATÁLOGO (Buenas prácticas: Reemplazo formal de tipos ENUM)
-- =============================================================================

-- Catálogo de Roles del sistema (Estudiante, Tutor, Administrador)
CREATE TABLE Roles (
    id_rol TINYINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(20) NOT NULL UNIQUE
);

-- Catálogo de Carreras de FIME (ITS, IAS, etc.)
CREATE TABLE Carreras (
    id_carrera TINYINT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(5) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL
);

-- Catálogo de Niveles de Urgencia para peticiones de materias
CREATE TABLE Urgencias (
    id_urgencia TINYINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(20) NOT NULL UNIQUE
);

-- Catálogo compartido de Estados de Revisión (Solicitudes y Peticiones)
CREATE TABLE EstadosRevision (
    id_estado TINYINT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(20) NOT NULL UNIQUE
);

-- Catálogo de Turnos oficiales de FIME (Matutino, Vespertino, Nocturno)
CREATE TABLE Turnos (
    id_turno VARCHAR(2) PRIMARY KEY,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL
);

-- =============================================================================
-- 2. TABLAS PRINCIPALES DE USUARIOS Y MATERIAS
-- =============================================================================

-- Tabla central de Usuarios: maneja autenticación y perfil unificado.
-- El estado de la cuenta se maneja con 'activo' (Soft Delete / suspensión).
CREATE TABLE Usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    matricula VARCHAR(8) NOT NULL UNIQUE,
    id_carrera TINYINT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    id_rol TINYINT NOT NULL DEFAULT 1,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_carrera) REFERENCES Carreras(id_carrera) ON DELETE RESTRICT,
    FOREIGN KEY (id_rol) REFERENCES Roles(id_rol) ON DELETE RESTRICT
);

-- Catálogo institucional de Materias aprobadas por Jefatura Académica
CREATE TABLE Materias (
    id_materia INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(10) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(200) NULL
);

-- Relación N:N entre Tutores y las Materias que tienen autorizadas
CREATE TABLE TutorMaterias (
    id_tutor INT NOT NULL,
    id_materia INT NOT NULL,
    publicada BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id_tutor, id_materia),
    FOREIGN KEY (id_tutor) REFERENCES Usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_materia) REFERENCES Materias(id_materia) ON DELETE RESTRICT
);

-- =============================================================================
-- 3. HORARIOS, CITAS Y GESTIÓN DE CUPOS (ASESORÍAS GRUPALES)
-- =============================================================================

-- Horarios ofertados por el tutor.
-- Clave foránea compuesta hacia TutorMaterias para blindar que solo abra
-- horarios de materias que efectivamente tiene autorizadas.
-- Incluye 'cupo' para soportar asesorías individuales (cupo=1) o grupales (cupo>1).
CREATE TABLE Horarios (
    id_horario INT AUTO_INCREMENT PRIMARY KEY,
    id_tutor INT NOT NULL,
    id_materia INT NOT NULL,
    fecha DATE NOT NULL,
    id_turno VARCHAR(2) NOT NULL,
    aula VARCHAR(50) NOT NULL,
    cupo TINYINT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (id_tutor, id_materia) REFERENCES TutorMaterias(id_tutor, id_materia) ON DELETE CASCADE,
    FOREIGN KEY (id_turno) REFERENCES Turnos(id_turno) ON DELETE RESTRICT,
    UNIQUE KEY uq_tutor_fecha_turno (id_tutor, fecha, id_turno),
    UNIQUE KEY uq_fecha_turno_aula (fecha, id_turno, aula),
    CHECK (cupo >= 1)
);

-- Citas reservadas por los alumnos (Tabla asociativa con PK compuesta).
-- Un horario admite múltiples citas hasta alcanzar su 'cupo'.
-- La llave primaria compuesta (id_horario, id_estudiante) impide que un mismo
-- estudiante inscriba varias citas en el mismo horario.
CREATE TABLE Citas (
    id_horario INT NOT NULL,
    id_estudiante INT NOT NULL,
    asistio BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_agendada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_horario, id_estudiante),
    FOREIGN KEY (id_horario) REFERENCES Horarios(id_horario) ON DELETE CASCADE,
    FOREIGN KEY (id_estudiante) REFERENCES Usuarios(id_usuario) ON DELETE CASCADE
);

-- =============================================================================
-- 4. SOLICITUDES PARA SER TUTOR Y PETICIONES DE MATERIAS
-- =============================================================================

-- Solicitudes enviadas por estudiantes para aspirar a ser Tutores.
-- id_usuario es NOT NULL: ninguna solicitud existe sin un usuario que la origine.
CREATE TABLE SolicitudesTutor (
    id_solicitud INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    kardex_pdf VARCHAR(255) NOT NULL,
    id_estado TINYINT NOT NULL DEFAULT 1,
    fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_estado) REFERENCES EstadosRevision(id_estado) ON DELETE RESTRICT
);

-- Materias propuestas en la solicitud del aspirante a tutor (Relación N:N)
CREATE TABLE SolicitudMaterias (
    id_solicitud INT NOT NULL,
    id_materia INT NOT NULL,
    PRIMARY KEY (id_solicitud, id_materia),
    FOREIGN KEY (id_solicitud) REFERENCES SolicitudesTutor(id_solicitud) ON DELETE CASCADE,
    FOREIGN KEY (id_materia) REFERENCES Materias(id_materia) ON DELETE RESTRICT
);

-- Peticiones de estudiantes solicitando materias que no están en el catálogo
CREATE TABLE PeticionesMateria (
    id_peticion INT AUTO_INCREMENT PRIMARY KEY,
    id_estudiante INT NOT NULL,
    nombre_materia VARCHAR(100) NOT NULL,
    semestre VARCHAR(20) NULL,
    id_urgencia TINYINT NOT NULL DEFAULT 1,
    comentarios TEXT NULL,
    id_estado TINYINT NOT NULL DEFAULT 1,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_estudiante) REFERENCES Usuarios(id_usuario) ON DELETE CASCADE,
    FOREIGN KEY (id_urgencia) REFERENCES Urgencias(id_urgencia) ON DELETE RESTRICT,
    FOREIGN KEY (id_estado) REFERENCES EstadosRevision(id_estado) ON DELETE RESTRICT
);

-- =============================================================================
-- 5. EVALUACIÓN Y CALIFICACIONES POR SESIÓN (HORARIO)
-- =============================================================================

-- Puntuación de 1 a 5 asignada por un estudiante a la sesión específica (id_horario) tomada.
-- La PK y FK compuesta hacia Citas(id_horario, id_estudiante) garantiza por integridad
-- referencial que solo un alumno que agendó esa cita pueda calificar la sesión, y solo una vez.
-- Se separa de Citas para no almacenar valores NULL de puntuacion en citas futuras o no evaluadas.
CREATE TABLE Calificaciones (
    id_horario INT NOT NULL,
    id_estudiante INT NOT NULL,
    puntuacion TINYINT NOT NULL,
    PRIMARY KEY (id_horario, id_estudiante),
    FOREIGN KEY (id_horario, id_estudiante) REFERENCES Citas(id_horario, id_estudiante) ON DELETE CASCADE,
    CHECK (puntuacion BETWEEN 1 AND 5)
);

-- =============================================================================
-- 6. INSERCIÓN DE DATOS SEMILLA (CATÁLOGOS OFICIALES)
-- =============================================================================

INSERT INTO Roles (id_rol, nombre) VALUES
(1, 'Estudiante'),
(2, 'Tutor'),
(3, 'Administrador');

INSERT INTO Carreras (id_carrera, clave, nombre) VALUES
(1, 'ITS', 'Ingeniería en Tecnología de Software'),
(2, 'IAS', 'Ingeniería en Administración de Sistemas');

INSERT INTO Urgencias (id_urgencia, nombre) VALUES
(1, 'Normal'),
(2, 'Alta'),
(3, 'Crítica');

INSERT INTO EstadosRevision (id_estado, nombre) VALUES
(1, 'Pendiente'),
(2, 'Aprobada'),
(3, 'Rechazada');

-- Turnos oficiales FIME (Matutino, Vespertino, Nocturno)
INSERT INTO Turnos (id_turno, hora_inicio, hora_fin) VALUES
('M1', '07:00:00', '07:50:00'),
('M2', '07:50:00', '08:40:00'),
('M3', '08:40:00', '09:30:00'),
('M4', '09:30:00', '10:20:00'),
('M5', '10:20:00', '11:10:00'),
('M6', '11:10:00', '12:00:00'),
('V1', '12:00:00', '12:50:00'),
('V2', '12:50:00', '13:40:00'),
('V3', '13:40:00', '14:30:00'),
('V4', '14:30:00', '15:20:00'),
('V5', '15:20:00', '16:10:00'),
('V6', '16:10:00', '17:00:00'),
('N1', '17:00:00', '17:45:00'),
('N2', '17:45:00', '18:30:00'),
('N3', '18:30:00', '19:15:00'),
('N4', '19:15:00', '20:00:00'),
('N5', '20:00:00', '20:45:00'),
('N6', '20:45:00', '21:30:00');
