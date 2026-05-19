<?php 
include_once 'header.php';

// Consulta estructurada para calcular goles a favor, goles en contra y puntos acumulados de manera interactiva
$query = "SELECT id_equipo, nombre, id_grupo, puntos_obtenidos, goles_dif,
          COALESCE((SELECT SUM(goles_equipo1) FROM Partido WHERE id_equipo1 = id_equipo AND estado='Finalizado'), 0) +
          COALESCE((SELECT SUM(goles_equipo2) FROM Partido WHERE id_equipo2 = id_equipo AND estado='Finalizado'), 0) as goles_favor
          FROM Equipo 
          ORDER BY id_grupo, puntos_obtenidos DESC, goles_dif DESC";
$res = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Tabla de Posiciones Oficial - Mundial 2026</h2>
    <div style="display: flex; gap: 10px;">
        <a href="reporte-calendario.php" class="logo" style="font-size:14px; background:#3b82f6; padding: 8px 12px; border-radius:4px;">📅 Ver Calendario</a>
        <a href="reporte-quinielas.php" class="logo" style="font-size:14px; background:#10b981; padding: 8px 12px; border-radius:4px;">🏆 Ver Tabla de Líderes</a>
    </div>
</div>

<table border="1" cellpadding="12" style="width:100%; border-collapse: collapse; text-align: left; margin-top: 10px;">
    <thead>
        <tr style="background: #0f2027; color: white;">
            <th>Selección</th>
            <th>Grupo</th>
            <th style="text-align: center;">Goles a Favor</th>
            <th style="text-align: center;">Gol Diferencia</th>
            <th style="text-align: center; background: #1e3a8a;">Puntos</th>
        </tr>
    </thead>
    <tbody>
        <?php if(count($res) == 0): ?>
            <tr><td colspan="5" style="text-align: center; color: #64748b;">No hay equipos cargados en el sistema actualmente.</td></tr>
        <?php endif; ?>
        <?php foreach($res as $row): ?>
        <tr style="background: #ffffff; border-bottom: 1px solid #e2e8f0;">
            <td><strong><?php echo $row['nombre']; ?></strong> (<?php echo $row['id_equipo']; ?>)</td>
            <td>Grupo <?php echo $row['id_grupo']; ?></td>
            <td style="text-align: center;"><?php echo $row['goles_favor']; ?></td>
            <td style="text-align: center; color: <?php echo $row['goles_dif'] >= 0 ? '#16a34a' : '#dc2626'; ?>;">
                <strong><?php echo $row['goles_dif'] > 0 ? '+'.$row['goles_dif'] : $row['goles_dif']; ?></strong>
            </td>
            <td style="text-align: center; font-weight: bold; background: #eff6ff; color: #1e40af;"><?php echo $row['puntos_obtenidos']; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include_once 'footer.php'; ?>