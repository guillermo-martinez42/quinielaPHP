<?php
include_once 'header.php';

// 1) Obtener la composición de los grupos (qué equipos van en cada grupo) desde groups.json
$grupos_json = [];
if (file_exists('groups.json')) {
    $data = json_decode(file_get_contents('groups.json'), true);
    foreach (($data['groups'] ?? []) as $g) {
        $letra = $g['group'];
        $equipos = [];
        foreach ($g['matches'] as $m) {
            $equipos[$m['home_team']] = true;
            $equipos[$m['away_team']] = true;
        }
        $grupos_json[$letra] = array_keys($equipos);
    }
    ksort($grupos_json);
}

// 2) Traer las estadísticas (puntos, gol diferencia y goles a favor) desde la base de datos
$query = "SELECT id_equipo, nombre, id_grupo, puntos_obtenidos, goles_dif,
          COALESCE((SELECT SUM(goles_equipo1) FROM Partido WHERE id_equipo1 = id_equipo AND estado='Finalizado'), 0) +
          COALESCE((SELECT SUM(goles_equipo2) FROM Partido WHERE id_equipo2 = id_equipo AND estado='Finalizado'), 0) AS goles_favor
          FROM Equipo";
$stats_db = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Indexamos por nombre (que es como vienen en groups.json: "Mexico", "Korea Republic", etc.)
$stats_por_nombre = [];
foreach ($stats_db as $r) {
    $stats_por_nombre[$r['nombre']] = $r;
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
    <h2>Tabla de Posiciones por Grupo - Mundial 2026</h2>
    <div style="display: flex; gap: 10px;">
        <a href="reporte-calendario.php" class="logo" style="font-size:14px; background:#3b82f6; padding: 8px 12px; border-radius:4px;">📅 Ver Calendario</a>
        <a href="reporte-quinielas.php" class="logo" style="font-size:14px; background:#10b981; padding: 8px 12px; border-radius:4px;">🏆 Ver Tabla de Líderes</a>
    </div>
</div>

<p style="color:#475569;">Los <strong>dos primeros equipos</strong> de cada grupo (resaltados en verde) clasifican a la <strong>Fase de Eliminación</strong>.</p>

<?php if (empty($grupos_json)): ?>
    <p class="error">No se encontró el archivo groups.json o está vacío.</p>
<?php endif; ?>

<?php foreach ($grupos_json as $letra => $nombres_equipos):
    // Construir la lista de equipos del grupo combinando JSON + estadísticas
    $equipos_grupo = [];
    foreach ($nombres_equipos as $nombre) {
        if (isset($stats_por_nombre[$nombre])) {
            $equipos_grupo[] = $stats_por_nombre[$nombre];
        } else {
            // Si el equipo aún no está en la BD, lo mostramos con stats en cero
            $equipos_grupo[] = [
                'id_equipo'        => '-',
                'nombre'           => $nombre,
                'id_grupo'         => $letra,
                'puntos_obtenidos' => 0,
                'goles_dif'        => 0,
                'goles_favor'      => 0,
            ];
        }
    }

    // Ordenar el grupo: puntos DESC, gol diferencia DESC, goles a favor DESC
    usort($equipos_grupo, function($a, $b) {
        if ($b['puntos_obtenidos'] != $a['puntos_obtenidos'])
            return $b['puntos_obtenidos'] <=> $a['puntos_obtenidos'];
        if ($b['goles_dif'] != $a['goles_dif'])
            return $b['goles_dif'] <=> $a['goles_dif'];
        return $b['goles_favor'] <=> $a['goles_favor'];
    });
?>
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
            <?php foreach ($equipos_grupo as $i => $row):
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
                        <strong><?php echo htmlspecialchars($row['nombre']); ?></strong>
                        <?php if (!empty($row['id_equipo']) && $row['id_equipo'] !== '-'): ?>
                            <span style="color:#64748b;">(<?php echo htmlspecialchars($row['id_equipo']); ?>)</span>
                        <?php endif; ?>
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
