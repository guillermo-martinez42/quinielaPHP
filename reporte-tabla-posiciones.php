<?php
include_once 'header.php';

// Traemos los equipos con su grupo, bandera, puntos y goles a favor (calculados sobre partidos finalizados)
$query = "SELECT id_equipo, nombre, bandera, id_grupo, puntos_obtenidos, goles_dif,
          COALESCE((SELECT SUM(goles_equipo1) FROM Partido WHERE id_equipo1 = id_equipo AND estado='Finalizado'), 0) +
          COALESCE((SELECT SUM(goles_equipo2) FROM Partido WHERE id_equipo2 = id_equipo AND estado='Finalizado'), 0) AS goles_favor
          FROM Equipo
          ORDER BY id_grupo ASC, puntos_obtenidos DESC, goles_dif DESC, goles_favor DESC";
$res = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Agrupamos por letra de grupo (la info de qué equipo va en qué grupo viene de Equipo.id_grupo)
$equipos_por_grupo = [];
foreach ($res as $row) {
    $g = $row['id_grupo'] ?? 'SIN GRUPO';
    $equipos_por_grupo[$g][] = $row;
}
ksort($equipos_por_grupo);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h2>Tabla de Posiciones por Grupo - Mundial 2026</h2>
    <div style="display: flex; gap: 10px;">
        <a href="reporte-calendario.php" class="logo" style="font-size:14px; background:#3b82f6; padding: 8px 12px; border-radius:4px;">📅 Ver Calendario</a>
        <a href="reporte-quinielas.php" class="logo" style="font-size:14px; background:#10b981; padding: 8px 12px; border-radius:4px;">🏆 Ver Tabla de Líderes</a>
    </div>
</div>

<p style="color:#475569;">Los <strong>dos primeros equipos</strong> de cada grupo (resaltados en verde) clasifican a la <strong>Fase de Eliminación</strong>.</p>

<?php if (empty($equipos_por_grupo)): ?>
    <p class="error">No hay equipos cargados en la base de datos.</p>
<?php endif; ?>

<?php foreach ($equipos_por_grupo as $letra => $equipos): ?>
    <h3 style="margin-top: 30px; color: #1e3a8a; border-left: 5px solid #10b981; padding-left: 10px;">Grupo <?php echo htmlspecialchars($letra); ?></h3>

    <table border="1" cellpadding="10" style="width:100%; border-collapse: collapse; text-align: left; margin-top: 8px;">
        <thead>
            <tr style="background: #0f2027; color: white;">
                <th style="text-align: center; width: 50px;">#</th>
                <th>Selección</th>
                <th style="text-align: center;">Goles a Favor</th>
                <th style="text-align: center;">Gol Diferencia</th>
                <th style="text-align: center; background: #1e3a8a;">Puntos</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($equipos) == 0): ?>
                <tr><td colspan="5" style="text-align: center; color: #64748b;">No hay equipos cargados en este grupo.</td></tr>
            <?php endif; ?>

            <?php foreach ($equipos as $i => $row):
                $pos = $i + 1;
                $clasifica = ($pos <= 2);
                $row_bg = $clasifica ? '#dcfce7' : '#ffffff';
                $row_border = $clasifica ? '2px solid #16a34a' : '1px solid #e2e8f0';
                $gd = intval($row['goles_dif']);
            ?>
                <tr style="background: <?php echo $row_bg; ?>; border-bottom: <?php echo $row_border; ?>;">
                    <td style="text-align: center; font-weight: bold; color: <?php echo $clasifica ? '#15803d' : '#64748b'; ?>;">
                        <?php echo $pos; ?><?php echo $clasifica ? ' ✅' : ''; ?>
                    </td>
                    <td>
                        <span style="font-size:20px; margin-right:8px; vertical-align:middle;">
                            <?php echo $row['bandera'] ? $row['bandera'] : '🏳️'; ?>
                        </span>
                        <strong><?php echo htmlspecialchars($row['nombre']); ?></strong>
                        <span style="color:#64748b;">(<?php echo htmlspecialchars($row['id_equipo']); ?>)</span>
                    </td>
                    <td style="text-align: center;"><?php echo intval($row['goles_favor']); ?></td>
                    <td style="text-align: center; color: <?php echo $gd >= 0 ? '#16a34a' : '#dc2626'; ?>;">
                        <strong><?php echo $gd > 0 ? '+'.$gd : $gd; ?></strong>
                    </td>
                    <td style="text-align: center; font-weight: bold; background: <?php echo $clasifica ? '#bbf7d0' : '#eff6ff'; ?>; color: <?php echo $clasifica ? '#14532d' : '#1e40af'; ?>;">
                        <?php echo intval($row['puntos_obtenidos']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>

<?php include_once 'footer.php'; ?>
