<?php
// 1. Incluir el encabezado común (que ya inicia sesión y conecta a PostgreSQL)
include_once 'header.php';

if (!isset($_SESSION['id_usuario'])) {
    die("<p class='error'>Debes iniciar sesión para visualizar el calendario del torneo.</p>");
}

// 2. Pestañas: si no se define grupo ni fase, mostramos el Grupo A por defecto
$grupo_activo = isset($_GET['grupo']) ? strtoupper($_GET['grupo']) : null;
$fase_activa  = isset($_GET['fase']) ? strtoupper($_GET['fase']) : null;
if (!$grupo_activo && !$fase_activa) {
    $grupo_activo = 'A';
}

// 3. Catálogo de equipos: id_equipo (FIFA) -> {nombre, bandera, id_grupo} y nombre -> id_equipo
$by_code = [];
$by_name = [];
try {
    foreach ($db->query("SELECT id_equipo, nombre, bandera, id_grupo FROM Equipo") as $e) {
        $by_code[$e['id_equipo']] = $e;
        $by_name[$e['nombre']]    = $e['id_equipo'];
    }
} catch (PDOException $e) { /* Equipo aún no cargada */ }

// Alias: nombres en inglés que vienen en groups.json -> nombres en español que están guardados en Equipo
$alias_ingles_a_espanol = [
    'Mexico' => 'México', 'South Africa' => 'Sudáfrica', 'Korea Republic' => 'Corea del Sur', 'South Korea' => 'Corea del Sur',
    'Czechia' => 'Chequia', 'Czech Republic' => 'Chequia',
    'Canada' => 'Canadá', 'Bosnia and Herzegovina' => 'Bosnia y Herzegovina', 'Bosnia & Herzegovina' => 'Bosnia y Herzegovina',
    'Qatar' => 'Catar', 'Switzerland' => 'Suiza',
    'Brazil' => 'Brasil', 'Morocco' => 'Marruecos', 'Haiti' => 'Haití', 'Scotland' => 'Escocia',
    'USA' => 'Estados Unidos', 'United States' => 'Estados Unidos', 'Türkiye' => 'Turquía', 'Turkey' => 'Turquía', 'Turkiye' => 'Turquía',
    'Germany' => 'Alemania', 'Curaçao' => 'Curazao', 'Curacao' => 'Curazao',
    "Côte d'Ivoire" => 'Costa de Marfil', "Cote d'Ivoire" => 'Costa de Marfil', 'Ivory Coast' => 'Costa de Marfil',
    'Netherlands' => 'Países Bajos', 'Japan' => 'Japón', 'Sweden' => 'Suecia', 'Tunisia' => 'Túnez',
    'Belgium' => 'Bélgica', 'Egypt' => 'Egipto', 'IR Iran' => 'Irán', 'Iran' => 'Irán', 'New Zealand' => 'Nueva Zelanda',
    'Spain' => 'España', 'Cape Verde' => 'Cabo Verde', 'Saudi Arabia' => 'Arabia Saudita',
    'France' => 'Francia', 'Iraq' => 'Irak', 'Norway' => 'Noruega',
    'Algeria' => 'Argelia', 'Jordan' => 'Jordania',
    'Congo DR' => 'Congo DR', 'DR Congo' => 'Congo DR', 'Uzbekistan' => 'Uzbekistán',
    'England' => 'Inglaterra', 'Croatia' => 'Croacia', 'Panama' => 'Panamá',
];

function resolver_codigo(string $nombre_json, array $by_name, array $alias): ?string {
    if (isset($by_name[$nombre_json])) return $by_name[$nombre_json];
    if (isset($alias[$nombre_json]) && isset($by_name[$alias[$nombre_json]])) return $by_name[$alias[$nombre_json]];
    return null;
}

$fases_eliminacion = [
    'F2' => 'Dieciseisavos',
    'F3' => 'Octavos',
    'F4' => 'Cuartos',
    'F5' => 'Semifinal',
    'F6' => '3er Lugar',
    'F7' => 'Final'
];
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
    <h2>Calendario Oficial - FIFA World Cup 2026™</h2>
    <div style="display: flex; gap: 10px;">
        <a href="reporte-tabla-posiciones.php" style="font-size:13px; background:#1e3a8a; color:white; padding: 8px 12px; border-radius:4px; text-decoration:none; font-weight:bold;">📊 Tabla General</a>
        <a href="reporte-quinielas.php" style="font-size:13px; background:#059669; color:white; padding: 8px 12px; border-radius:4px; text-decoration:none; font-weight:bold;">🏆 Ranking Quinielas</a>
    </div>
</div>

<div class="pestanas-container" style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e2e8f0; line-height: 2.2;">
    <span style="font-weight: bold; color: #1e293b; margin-right: 10px;">Fase de Grupos:</span>
    <?php
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
// ===========================================================
// TABLA DE POSICIONES RESUMIDA (solo para Fase de Grupos)
// Se muestra ARRIBA de los partidos programados.
// ===========================================================
if ($grupo_activo):
    try {
        $q_grupo = "SELECT id_equipo, nombre, bandera, puntos_obtenidos, goles_dif,
                    COALESCE((SELECT SUM(goles_equipo1) FROM Partido WHERE id_equipo1 = id_equipo AND estado='Finalizado'), 0) +
                    COALESCE((SELECT SUM(goles_equipo2) FROM Partido WHERE id_equipo2 = id_equipo AND estado='Finalizado'), 0) AS gf,
                    COALESCE((SELECT SUM(goles_equipo2) FROM Partido WHERE id_equipo1 = id_equipo AND estado='Finalizado'), 0) +
                    COALESCE((SELECT SUM(goles_equipo1) FROM Partido WHERE id_equipo2 = id_equipo AND estado='Finalizado'), 0) AS gc,
                    COALESCE((SELECT COUNT(*) FROM Partido WHERE (id_equipo1 = id_equipo OR id_equipo2 = id_equipo) AND estado='Finalizado'), 0) AS pj
                    FROM Equipo
                    WHERE id_grupo = ?
                    ORDER BY puntos_obtenidos DESC, goles_dif DESC, gf DESC";
        $stmt_g = $db->prepare($q_grupo);
        $stmt_g->execute([$grupo_activo]);
        $tabla_resumen = $stmt_g->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "<p class='error'>Error al calcular la tabla del grupo: " . $e->getMessage() . "</p>";
        $tabla_resumen = [];
    }
?>
    <div style="max-width: 600px; margin-bottom: 30px; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
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
                        <span style="font-size:16px; margin-right:4px; vertical-align:middle;"><?php echo $tr['bandera'] ? $tr['bandera'] : '🏳️'; ?></span>
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
// ===========================================================
// PARTIDOS PROGRAMADOS
// - Fase de Grupos: leemos TODOS los partidos del grupo desde groups.json
//   y enlazamos por id_partido con Partido para mostrar el resultado si ya existe.
// - Fases Eliminatorias: seguimos consultando la tabla Partido.
// ===========================================================
if ($grupo_activo) {
    echo "<h3 style='margin-bottom:15px; color:#1e293b;'>Partidos Programados - Grupo $grupo_activo</h3>";

    // Cargar groups.json y filtrar el grupo activo
    $grupo_match = null;
    if (file_exists('groups.json')) {
        $data = json_decode(file_get_contents('groups.json'), true);
        foreach ($data['groups'] ?? [] as $g) {
            if ($g['group'] === $grupo_activo) { $grupo_match = $g; break; }
        }
    }

    // Map id_partido -> {goles_equipo1, goles_equipo2, estado} desde Partido
    $resultados_db = [];
    try {
        foreach ($db->query("SELECT id_partido, goles_equipo1, goles_equipo2, estado FROM Partido WHERE id_fase = 'F1'") as $r) {
            $resultados_db[(int)$r['id_partido']] = $r;
        }
    } catch (PDOException $e) { /* tabla Partido vacía */ }

    $partidos_render = [];
    if ($grupo_match) {
        foreach ($grupo_match['matches'] as $m) {
            $mid = intval($m['match_id']);
            $cod_l = resolver_codigo($m['home_team'], $by_name, $alias_ingles_a_espanol);
            $cod_v = resolver_codigo($m['away_team'], $by_name, $alias_ingles_a_espanol);

            $partidos_render[] = [
                'fecha'             => $m['date'],
                'hora'              => $m['time'],
                'nombre_fase'       => 'Fase de Grupos',
                'nombre_local'      => $cod_l ? $by_code[$cod_l]['nombre'] : $m['home_team'],
                'nombre_visitante'  => $cod_v ? $by_code[$cod_v]['nombre'] : $m['away_team'],
                'bandera_local'     => $cod_l ? $by_code[$cod_l]['bandera'] : '',
                'bandera_visitante' => $cod_v ? $by_code[$cod_v]['bandera'] : '',
                'estado'            => $resultados_db[$mid]['estado'] ?? 'Pendiente',
                'goles_equipo1'     => $resultados_db[$mid]['goles_equipo1'] ?? null,
                'goles_equipo2'     => $resultados_db[$mid]['goles_equipo2'] ?? null,
            ];
        }
    }
} else {
    $nombre_fase_titulo = $fases_eliminacion[$fase_activa] ?? 'Eliminación';
    echo "<h3 style='margin-bottom:15px; color:#1e293b;'>Llaves de Eliminación Directa - $nombre_fase_titulo</h3>";

    try {
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
        $partidos_render = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("<p class='error'>Error al consultar partidos: " . $e->getMessage() . "</p>");
    }
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
        <?php if (count($partidos_render) == 0): ?>
            <tr>
                <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">
                    No hay partidos registrados o clasificados para esta sección todavía.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($partidos_render as $p): ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
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
                            <?php echo $p['bandera_local'] ? $p['bandera_local'] : '🏳️'; ?>
                        </span>
                    </td>

                    <td style="text-align: center; background: #f8fafc; font-weight: bold; font-size: 16px; color: #1e293b;">
                        <?php
                        if ($p['estado'] === 'Finalizado' && $p['goles_equipo1'] !== null && $p['goles_equipo2'] !== null) {
                            echo intval($p['goles_equipo1']) . " - " . intval($p['goles_equipo2']);
                        } else {
                            echo "vs";
                        }
                        ?>
                    </td>

                    <td style="text-align: left; font-weight: 600;">
                        <span style="font-size: 18px; margin-right: 6px; vertical-align: middle;">
                            <?php echo $p['bandera_visitante'] ? $p['bandera_visitante'] : '🏳️'; ?>
                        </span>
                        <?php echo htmlspecialchars($p['nombre_visitante']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php
// 4. Cierre común del sitio
include_once 'footer.php';
?>
