-- Datos iniciales de catálogo de materias para pruebas locales de Asesorías FIME
USE asesorias_fime;

INSERT INTO Materias (clave, nombre, descripcion) VALUES
('ITS101', 'Sistemas Digitales', 'Sistemas numéricos, álgebra booleana, mapas de Karnaugh y circuitos combinacionales.'),
('ITS105', 'Bases de Datos', 'Modelo entidad-relación, normalización en 3FN y consultas SQL relacionales.'),
('ITS204', 'Programación Web', 'Desarrollo front-end con HTML5, CSS3, JavaScript y back-end PHP conectado a MySQL.'),
('MAT103', 'Álgebra Lineal', 'Matrices, determinantes, espacios vectoriales y sistemas de ecuaciones lineales.')
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion);
