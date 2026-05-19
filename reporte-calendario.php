<?php 
// 1. Incluir el encabezado común (que ya inicia sesión y conecta a PostgreSQL)
include_once 'header.php';

if (!isset($_SESSION['id_usuario'])) {
    die("<p class='error'>Debes iniciar sesión para visualizar el calendario del torneo.</p>");
}

// 2. Controlar filtros dinámicos mediante parámetros URL (Pestañas)
// Si no se define grupo ni fase, por defecto mostramos el Grupo A
$grupo_activo = isset($_GET['grupo']) ? strtoupper($_GET['grupo']) : null;
$fase_activa  = isset($_GET['fase']) ? strtoupper($_GET['fase']) : null;

if (!$grupo_activo && !$fase_activa) {
    $grupo_activo = 'A'; 
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
    <h2>Calendario Oficial - FIFA World Cup 2026™</h2>
    <div style="display: flex; gap: 10px;">
        <a href="reporte-posiciones.php" style="font-size:13px; background:#1e3a8a; color:white; padding: 8px 12px; border-radius:4px; text-decoration:none; font-weight:bold;">📊 Tabla General</a>
        <a href="reporte-quinielas.php" style="font-size:13px; background:#059669; color:white; padding: 8px 12px; border-radius:4px; text-decoration:none; font-weight:bold;">🏆 Ranking Quinielas</a>
    </div>
</div>

<div class="pestanas-container" style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e2e8f0; line-height: 2.2;">
    <span style="font-weight: bold; color: #1e293b; margin-right: 10px;">Fase de Grupos:</span>
    <?php 
    // Generar dinámicamente pestañas de la A a la L
    foreach (range('A', 'L') as $letra) {
        $estilo_link = "margin-right: 8px; padding: 4px 8px; text-decoration: none; border-radius: 4px; font-weight: bold; ";
        if ($grupo_activo === $letra) {
            $estilo_link .= "background: #3b82f6; color: white;";
        } else {
            $estilo_link .= "color: #475569; background: #fff; border: 1px solid #cbd5e1;";
        }
        echo "<a href='reporte-calendario.php?grupo=$letra' style='$estilo_link'>$letra</a>";
    }
    ?>
    
    <br><span style="font-weight: bold; color: #1e293b; margin-right: 10px; margin-top: 10px; display: inline-block;">Rondas Eliminatorias:</span>
    <?php
    // Estructura de llaves según catálogo F2 a F7
    $fases_eliminacion = [
        'F2' => 'Dieciseisavos',
        'F3' => 'Octavos',
        'F4' => 'Cuartos',
        'F5' => 'Semifinal',
        'F6' => '3er Lugar',
        'F7' => 'Final'
    ];

    foreach ($fases_eliminacion as $id_fase => $nombre_fase) {
        $estilo_link = "margin-right: 8px; padding: 4px 10px; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: bold; ";
        if ($fase_activa === $id_fase) {
            $estilo_link .= "background: #dc2626; color: white;";
        } else {
            $estilo_link .= "color: #475569; background: #fff; border: 1px solid #cbd5e1;";
        }
        echo "<a href='reporte-calendario.php?fase=$id_fase' style='$estilo_link'>$nombre_fase</a>";
    }
    ?>
</div>

<?php 
try {
    if ($grupo_activo) {
        echo "<h3 style='margin-bottom:15px; color:#1e293b;'>Partidos Programados - Grupo $grupo_activo</h3>";
        
        // Hacemos LEFT JOIN para jalar el nombre real de los equipos. 
        // Si no encuentra (en fases avanzadas), COALESCE mantendrá el texto del JSON.
        $query = "SELECT p.*, f.nombre_fase,
                         COALESCE(e1.nombre, p.id_equipo1) AS nombre_local,
                         COALESCE(e2.nombre, p.id_equipo2) AS nombre_visitante,
                         e1.bandera AS bandera_local,
                         e2.bandera AS bandera_visitante
                  FROM Partido p
                  LEFT JOIN Equipo e1 ON p.id_equipo1 = e1.id_equipo
                  LEFT JOIN Equipo e2 ON p.id_equipo2 = e2.id_equipo
                  JOIN Fase f ON p.id_fase = f.id_fase
                  WHERE p.grupo_partido = ? AND p.id_fase = 'F1'
                  ORDER BY p.fecha ASC, p.hora ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([$grupo_activo]);
    } else {
        $nombre_fase_titulo = $fases_eliminacion[$fase_activa];
        echo "<h3 style='margin-bottom:15px; color:#1e293b;'>Llaves de Eliminación Directa - $nombre_fase_titulo</h3>";
        
        $query = "SELECT p.*, f.nombre_fase,
                         COALESCE(e1.nombre, p.id_equipo1) AS nombre_local,
                         COALESCE(e2.nombre, p.id_equipo2) AS nombre_visitante,
                         e1.bandera AS bandera_local,
                         e2.bandera AS bandera_visitante
                  FROM Partido p
                  LEFT JOIN Equipo e1 ON p.id_equipo1 = e1.id_equipo
                  LEFT JOIN Equipo e2 ON p.id_equipo2 = e2.id_equipo
                  JOIN Fase f ON p.id_fase = f.id_fase
                  WHERE p.id_fase = ?
                  ORDER BY p.fecha ASC, p.hora ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([$fase_activa]);
    }
    
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<p class='error'>Error al consultar partidos: " . $e->getMessage() . "</p>");
}
?>

<table border="1" cellpadding="10" style="width:100%; border-collapse: collapse; background: #ffffff; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <thead>
        <tr style="background: #0f172a; color: white; text-align: left;">
            <th style="width: 15%;">Fecha / Hora</th>
            <th style="width: 15%;">Instancia</th>
            <th style="text-align: right; width: 30%;">Equipo Local</th>
            <th style="text-align: center; width: 10%;">Resultado</th>
            <th style="text-align: left; width: 30%;">Equipo Visitante</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($partidos) == 0): ?>
            <tr>
                <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">
                    No hay partidos registrados o clasificados para esta sección todavía.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($partidos as $p): ?>
                <tr style="border-bottom: 1px solid #e2e8f0; hover:background:#f8fafc;">
                    <td style="color: #475569; font-size: 13px;">
                        <strong><?php echo date('d/m/Y', strtotime($p['fecha'])); ?></strong>
                        <br><span style="color:#94a3b8;"><?php echo substr($p['hora'], 0, 5); ?></span>
                    </td>
                    
                    <td style="font-size: 12px; color: #64748b; font-weight: 500;">
                        <?php echo htmlspecialchars($p['nombre_fase']); ?>
                    </td>
                    
                    <td style="text-align: right; font-weight: 600;">
                        <?php echo htmlspecialchars($p['nombre_local']); ?>
                        <span style="font-size: 18px; margin-left: 6px; vertical-align: middle;">
                            <?php echo $p['bandera_local'] ? htmlspecialchars($p['bandera_local']) : '🏳️'; ?>
                        </span>
                    </td>
                    
                    <td style="text-align: center; background: #f8fafc; font-weight: bold; font-size: 16px; color: #1e293b;">
                        <?php 
                        if ($p['estado'] === 'Finalizado') {
                            echo $p['goles_equipo1'] . " - " . $p['goles_equipo2'];
                        } else {
                            echo " vs ";
                        }
                        ?>
                    </td>
                    
                    <td style="text-align: left; font-weight: 600;">
                        <span style="font-size: 18px; margin-right: 6px; vertical-align: middle;">
                            <?php echo $p['bandera_visitante'] ? htmlspecialchars($p['bandera_visitante']) : '🏳️'; ?>
                        </span>
                        <?php echo htmlspecialchars($p['nombre_visitante']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php 
if ($grupo_activo): 
    try {
        // Query de cálculo matemático dinámico de estadísticas por grupo
        $q_grupo = "SELECT id_equipo, nombre, bandera, puntos_obtenidos, goles_dif,
                    COALESCE((SELECT SUM(goles_equipo1) FROM Partido WHERE id_equipo1 = id_equipo AND estado='Finalizado'), 0) +
                    COALESCE((SELECT SUM(goles_equipo2) FROM Partido WHERE id_equipo2 = id_equipo AND estado='Finalizado'), 0) as gf,
                    COALESCE((SELECT SUM(goles_equipo2) FROM Partido WHERE id_equipo1 = id_equipo AND estado='Finalizado'), 0) +
                    COALESCE((SELECT SUM(goles_equipo1) FROM Partido WHERE id_equipo2 = id_equipo AND estado='Finalizado'), 0) as gc,
                    COALESCE((SELECT COUNT(*) FROM Partido WHERE (id_equipo1 = id_equipo OR id_equipo2 = id_equipo) AND estado='Finalizado'), 0) as pj
                    FROM Equipo 
                    WHERE id_grupo = ?
                    ORDER BY puntos_obtenidos DESC, goles_dif DESC, gf DESC";
        
        $stmt_g = $db->prepare($q_grupo);
        $stmt_g->execute([$grupo_activo]);
        $tabla_resumen = $stmt_g->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "<p class='error'>Error al calcular la tabla del grupo: " . $e->getMessage() . "</p>";
    }
?>
    <div style="max-width: 600px; margin-top: 35px; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div style="background: #334155; color: white; padding: 10px 15px; font-weight: bold; font-size: 14px;">
            Tabla de Posiciones Resumida - Grupo <?php echo $grupo_activo; ?>
        </div>
        <table border="1" cellpadding="8" style="width:100%; border-collapse: collapse; font-size:13px; text-align: center; background:#ffffff;">
            <thead>
                <tr style="background:#f8fafc; color:#475569; font-weight: bold;">
                    <th style="text-align: left; padding-left:15px;">Selección</th>
                    <th>PJ</th>
                    <th>GF</th>
                    <th>GC</th>
                    <th>DG</th>
                    <th style="background:#e2e8f0; color:#0f172a; width: 60px;">Pts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tabla_resumen as $index => $tr): ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="text-align: left; font-weight: bold; padding-left:15px; color:#1e293b;">
                        <span style="color:#94a3b8; font-size:11px; margin-right:5px;"><?php echo ($index + 1); ?>.</span>
                        <span style="font-size:16px; margin-right:4px; vertical-align:middle;"><?php echo $tr['bandera'] ? htmlspecialchars($tr['bandera']) : '🏳️'; ?></span>
                        <?php echo htmlspecialchars($tr['nombre']); ?>
                    </td>
                    <td><?php echo $tr['pj']; ?></td>
                    <td style="color:#16a34a;"><?php echo $tr['gf']; ?></td>
                    <td style="color:#dc2626;"><?php echo $tr['gc']; ?></td>
                    <td style="font-weight:600; color:<?php echo $tr['goles_dif'] >= 0 ? '#15803d':'#b91c1c'; ?>;">
                        <?php echo $tr['goles_dif'] > 0 ? '+'.$tr['goles_dif'] : $tr['goles_dif']; ?>
                    </td>
                    <td style="font-weight: bold; background:#f1f5f9; color:#1e40af;"><?php echo $tr['puntos_obtenidos']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php 
// 4. Renderizar el cierre común del sitio
include_once 'footer.php'; 
?>