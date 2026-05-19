<?php 
// Asegúrate de incluir tu header, el cual ya importa conexion.php y valida la sesión
include_once 'header.php';

// Seguridad: Validar que el usuario esté logueado para ver el reporte
if (!isset($_SESSION['id_usuario'])) {
    die("<p class='error'>Debes iniciar sesión para visualizar el reporte de puntajes.</p>");
}

// Consulta SQL avanzada para agrupar los puntajes reales acumulados por cada alumno en el sistema
$query = "SELECT u.nombre, u.Username, COALESCE(SUM(q.puntos_obtenidos), 0) as total_puntos 
          FROM Usuario u
          LEFT JOIN Quiniela q ON u.id_usuario = q.id_usuario
          WHERE u.es_admin = FALSE
          GROUP BY u.id_usuario, u.nombre, u.Username
          ORDER BY total_puntos DESC, u.nombre ASC";

try {
    $stmt = $db->query($query);
    $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<p class='error'>Error al generar el reporte de quinielas: " . $e->getMessage() . "</p>");
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Resultados Globales de las Quinielas</h2>
    <div style="display: flex; gap: 10px;">
        <a href="reporte-calendario.php" style="font-size:13px; background:#3b82f6; color:white; padding: 8px 12px; border-radius:4px; text-decoration:none; font-weight:bold;">📅 Ver Calendario</a>
        <a href="reporte-posiciones.php" style="font-size:13px; background:#1e3a8a; color:white; padding: 8px 12px; border-radius:4px; text-decoration:none; font-weight:bold;">📊 Tabla del Mundial</a>
    </div>
</div>

<p style="color: #475569; margin-bottom: 20px;">
    A continuación se listan los alumnos inscritos y sus respectivos punteos calculados automáticamente por la aplicación (3 puntos por acertar resultado y 3 puntos más por marcador exacto).
</p>

<table border="1" cellpadding="12" style="width:100%; border-collapse: collapse; text-align: left; background: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
    <thead>
        <tr style="background: #111827; color: white;">
            <th style="width: 80px; text-align: center;">Posición</th>
            <th>Nombre del Participante</th>
            <th>Usuario (Username)</th>
            <th style="text-align: center; background: #059669; width: 150px;">Puntos Totales</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $posicion = 1;
        if (count($ranking) == 0): 
        ?>
            <tr>
                <td colspan="4" style="text-align: center; color: #64748b; padding: 20px;">
                    No hay participantes registrados en la quiniela todavía.
                </td>
            </tr>
        <?php 
        else:
            foreach($ranking as $row): 
        ?>
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="text-align: center;">
                    <span style="<?php echo ($posicion <= 3) ? 'color:#b45309; font-weight:bold;' : 'color:#475569;'; ?>">
                        #<?php echo $posicion++; ?>
                    </span>
                </td>
                <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                <td>
                    <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 13px;">
                        <?php echo htmlspecialchars($row['username']); ?>
                    </code>
                </td>
                <td style="text-align: center; font-weight: bold; color: #065f46; background: #ecfdf5; font-size: 15px;">
                    <?php echo $row['total_puntos']; ?> pts
                </td>
            </tr>
        <?php 
            endforeach; 
        endif;
        ?>
    </tbody>
</table>

<?php 
// Cerrar con la maquetación del pie de página
include_once 'footer.php'; 
?>