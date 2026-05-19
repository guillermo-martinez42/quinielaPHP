<?php include_once 'conexion.php'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Quiniela Mundial 2026 - Galileo</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>
        <nav class="navbar">
            <a href="index.php" class="logo">Mundial 2026</a>
            <ul class="nav-links">
                <li><a href="index.php">Inicio</a></li>
                <?php if (isset($_SESSION['id_usuario'])): ?>
                    <li><a href="quinielas.php">Mis Pronósticos (Quiniela)</a></li>
                    <li><a href="reporte-calendario.php">Calendario</a></li>
                    <li><a href="reporte-tabla-posiciones.php">Tabla de Posiciones</a></li>
                    <li><a href="reporte-quinielas.php">Puntajes Participantes</a></li>
                    <?php if (isset($_SESSION['es_admin']) && $_SESSION['es_admin'] == true): ?>
                        <li><a href="admin-equipos.php" style="color: #fbbf24;">⚙️ ADMIN Partidos</a></li>
                        <li><a href="admin-partidos.php" style="color: #fbbf24;">⚙️ ADMIN Partidos</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php" style="color: #f87171; font-weight: bold;">Salir
                            (<?php echo $_SESSION['username']; ?>)</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main class="container">