-- ==========================================================
-- Script: adding_teams.sql
-- Limpia los datos existentes y recarga los 48 equipos del Mundial 2026
-- usando los nombres oficiales en español.
-- ==========================================================

SET CLIENT_ENCODING TO 'UTF8';

-- ----------------------------------------------------------
-- 1) Limpieza de datos previos
--    Quiniela depende de Partido (FK ON DELETE CASCADE),
--    pero la borramos explícitamente por claridad.
-- ----------------------------------------------------------
DELETE FROM Quiniela;
DELETE FROM Partido;
DELETE FROM Equipo;

-- ----------------------------------------------------------
-- 2) Asegurar que existan los grupos A - L (FK requerida por Equipo.id_grupo)
-- ----------------------------------------------------------
INSERT INTO Grupo (id_grupo, nombre_grupo) VALUES
    ('A', 'Grupo A'),
    ('B', 'Grupo B'),
    ('C', 'Grupo C'),
    ('D', 'Grupo D'),
    ('E', 'Grupo E'),
    ('F', 'Grupo F'),
    ('G', 'Grupo G'),
    ('H', 'Grupo H'),
    ('I', 'Grupo I'),
    ('J', 'Grupo J'),
    ('K', 'Grupo K'),
    ('L', 'Grupo L')
ON CONFLICT (id_grupo) DO NOTHING;

-- ----------------------------------------------------------
-- 3) Insertar equipos con NOMBRE EN ESPAÑOL
--    id_equipo = código FIFA de 3 letras
-- ----------------------------------------------------------
INSERT INTO Equipo (id_equipo, nombre, pais, bandera, id_grupo) VALUES
    -- Grupo A
    ('MEX', 'México',                  'México',               '🇲🇽', 'A'),
    ('RSA', 'Sudáfrica',               'Sudáfrica',            '🇿🇦', 'A'),
    ('KOR', 'Corea del Sur',           'Corea del Sur',        '🇰🇷', 'A'),
    ('CZE', 'República Checa',         'República Checa',      '🇨🇿', 'A'),

    -- Grupo B
    ('CAN', 'Canadá',                  'Canadá',               '🇨🇦', 'B'),
    ('BIH', 'Bosnia y Herzegovina',    'Bosnia y Herzegovina', '🇧🇦', 'B'),
    ('QAT', 'Catar',                   'Catar',                '🇶🇦', 'B'),
    ('SUI', 'Suiza',                   'Suiza',                '🇨🇭', 'B'),

    -- Grupo C
    ('BRA', 'Brasil',                  'Brasil',               '🇧🇷', 'C'),
    ('MAR', 'Marruecos',               'Marruecos',            '🇲🇦', 'C'),
    ('HAI', 'Haití',                   'Haití',                '🇭🇹', 'C'),
    ('SCO', 'Escocia',                 'Escocia',              '🏴', 'C'),

    -- Grupo D
    ('USA', 'Estados Unidos',          'Estados Unidos',       '🇺🇸', 'D'),
    ('PAR', 'Paraguay',                'Paraguay',             '🇵🇾', 'D'),
    ('AUS', 'Australia',               'Australia',            '🇦🇺', 'D'),
    ('TUR', 'Turquía',                 'Turquía',              '🇹🇷', 'D'),

    -- Grupo E
    ('GER', 'Alemania',                'Alemania',             '🇩🇪', 'E'),
    ('CUW', 'Curazao',                 'Curazao',              '🇨🇼', 'E'),
    ('CIV', 'Costa de Marfil',         'Costa de Marfil',      '🇨🇮', 'E'),
    ('ECU', 'Ecuador',                 'Ecuador',              '🇪🇨', 'E'),

    -- Grupo F
    ('NED', 'Países Bajos',            'Países Bajos',         '🇳🇱', 'F'),
    ('JPN', 'Japón',                   'Japón',                '🇯🇵', 'F'),
    ('SWE', 'Suecia',                  'Suecia',               '🇸🇪', 'F'),
    ('TUN', 'Túnez',                   'Túnez',                '🇹🇳', 'F'),

    -- Grupo G
    ('BEL', 'Bélgica',                 'Bélgica',              '🇧🇪', 'G'),
    ('EGY', 'Egipto',                  'Egipto',               '🇪🇬', 'G'),
    ('IRN', 'Irán',                    'Irán',                 '🇮🇷', 'G'),
    ('NZL', 'Nueva Zelanda',           'Nueva Zelanda',        '🇳🇿', 'G'),

    -- Grupo H
    ('ESP', 'España',                  'España',               '🇪🇸', 'H'),
    ('CPV', 'Cabo Verde',              'Cabo Verde',           '🇨🇻', 'H'),
    ('KSA', 'Arabia Saudita',          'Arabia Saudita',       '🇸🇦', 'H'),
    ('URU', 'Uruguay',                 'Uruguay',              '🇺🇾', 'H'),

    -- Grupo I
    ('FRA', 'Francia',                 'Francia',              '🇫🇷', 'I'),
    ('SEN', 'Senegal',                 'Senegal',              '🇸🇳', 'I'),
    ('IRQ', 'Irak',                    'Irak',                 '🇮🇶', 'I'),
    ('NOR', 'Noruega',                 'Noruega',              '🇳🇴', 'I'),

    -- Grupo J
    ('ARG', 'Argentina',               'Argentina',            '🇦🇷', 'J'),
    ('ALG', 'Argelia',                 'Argelia',              '🇩🇿', 'J'),
    ('AUT', 'Austria',                 'Austria',              '🇦🇹', 'J'),
    ('JOR', 'Jordania',                'Jordania',             '🇯🇴', 'J'),

    -- Grupo K
    ('POR', 'Portugal',                'Portugal',             '🇵🇹', 'K'),
    ('COD', 'RD del Congo',            'RD del Congo',         '🇨🇩', 'K'),
    ('UZB', 'Uzbekistán',              'Uzbekistán',           '🇺🇿', 'K'),
    ('COL', 'Colombia',                'Colombia',             '🇨🇴', 'K'),

    -- Grupo L
    ('ENG', 'Inglaterra',              'Inglaterra',           '🏴', 'L'),
    ('CRO', 'Croacia',                 'Croacia',              '🇭🇷', 'L'),
    ('GHA', 'Ghana',                   'Ghana',                '🇬🇭', 'L'),
    ('PAN', 'Panamá',                  'Panamá',               '🇵🇦', 'L');

-- ----------------------------------------------------------
-- 4) Verificación rápida (opcional)
-- ----------------------------------------------------------
-- SELECT id_grupo, id_equipo, nombre FROM Equipo ORDER BY id_grupo, nombre;
-- SELECT COUNT(*) AS total_equipos FROM Equipo; -- debe regresar 48
