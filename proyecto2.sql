-- Database: proyecto2

-- DROP DATABASE IF EXISTS proyecto2;

CREATE DATABASE proyecto2
    WITH
    OWNER = postgres
    ENCODING = 'UTF8'
    LC_COLLATE = 'Spanish_Spain.1252'
    LC_CTYPE = 'Spanish_Spain.1252'
    LOCALE_PROVIDER = 'libc'
    TABLESPACE = pg_default
    CONNECTION LIMIT = -1
    IS_TEMPLATE = False;

	-- 1) Entidad: Usuario (Perfecta)
CREATE TABLE Usuario (
    id_usuario      SERIAL PRIMARY KEY,
    Username        VARCHAR(50) UNIQUE,
    nombre          VARCHAR(50),
    pass            VARCHAR(255), 
    es_admin        BOOLEAN DEFAULT FALSE, 
    fecha_registro  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2) Entidad: Grupo (Perfecta - Ej: id_grupo = 'A')
CREATE TABLE Grupo (
    id_grupo        VARCHAR(50) PRIMARY KEY,
    nombre_grupo    VARCHAR(50) UNIQUE
);

-- 3) Entidad: Fase (Perfecta - Ej: id_fase = 'F1')
CREATE TABLE Fase (
    id_fase         VARCHAR(50) PRIMARY KEY,
    nombre_fase     VARCHAR(50) UNIQUE,
    orden           INTEGER
);

-- 4) Entidad: Equipo
CREATE TABLE Equipo (
    id_equipo        VARCHAR(50) PRIMARY KEY, -- Usará el código FIFA de 3 letras (MEX, BRA)
    nombre           VARCHAR(50),
    pais             VARCHAR(50),
    bandera          VARCHAR(50), -- Cambiado a VARCHAR para guardar el emoji 🇲🇽 o el Unicode fácilmente
    bandera_blob     BYTEA NULL,  -- Dejamos este por si a ley quieres subir la imagen por formulario para los puntos extra
    puntos_obtenidos INTEGER DEFAULT 0,
    goles_dif        INTEGER DEFAULT 0,
    id_grupo         VARCHAR(50), 
    FOREIGN KEY (id_grupo) REFERENCES Grupo(id_grupo)
);

-- 5) Entidad: Partido (Modificada para fases de eliminación)
CREATE TABLE Partido (
    id_partido      SERIAL PRIMARY KEY, 
    fecha           DATE,
    hora            VARCHAR(20), -- Cambiado a VARCHAR para soportar formatos con zona horaria del JSON (Ej: '13:00 UTC-6')
    estado          VARCHAR(50) DEFAULT 'Pendiente', 
    goles_equipo1   INTEGER DEFAULT NULL,
    goles_equipo2   INTEGER DEFAULT NULL,
    id_fase         VARCHAR(50), 
    id_equipo1      VARCHAR(50), -- ELIMINADA la FK estricta para permitir textos como '1A' o 'W73'
    id_equipo2      VARCHAR(50), -- ELIMINADA la FK estricta para permitir textos como '2B' o 'W75'
    grupo_partido   VARCHAR(10) NULL, -- Para filtrar fácilmente el calendario de Fase de Grupos ('A', 'B', etc.)
    FOREIGN KEY (id_fase) REFERENCES Fase(id_fase)
);

-- 6) Entidad: Quiniela (Perfecta)
CREATE TABLE Quiniela (
    id_quiniela       SERIAL PRIMARY KEY,
    id_usuario        INTEGER NOT NULL,
    id_partido        INTEGER NOT NULL, 
    prediccion_goles1 INTEGER NOT NULL,
    prediccion_goles2 INTEGER NOT NULL,
    puntos_obtenidos  INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_partido) REFERENCES Partido(id_partido) ON DELETE CASCADE
);

-- Insertar un Administrador por defecto para pruebas
INSERT INTO Usuario (Username, nombre, pass, es_admin) 
VALUES ('juan', 'Administrador General', '1234', TRUE); 
-- Nota: La contraseña de este admin es 'admin123'