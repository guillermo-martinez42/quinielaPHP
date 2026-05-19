<?php
include_once 'header.php';

if (!isset($_SESSION['id_usuario'])) {
    die("<p class='error'>Debes iniciar sesión para ingresar tus quinielas.</p>");
}

$id_usuario = $_SESSION['id_usuario'];

// Fase activa de la quiniela (grupos | eliminacion)
$fase_activa = $_GET['fase'] ?? 'grupos';
if (!in_array($fase_activa, ['grupos', 'eliminacion'])) {
    $fase_activa = 'grupos';
}

// Mapas desde la tabla Equipo:
//   - $equipos_map: nombre del equipo -> id_equipo (FIFA)
//   - $by_code:     id_equipo (FIFA)  -> { nombre, bandera }
$equipos_map = [];
$by_code     = [];
try {
    foreach ($db->query("SELECT id_equipo, nombre, bandera FROM Equipo") as $e) {
        $equipos_map[$e['nombre']] = $e['id_equipo'];
        $by_code[$e['id_equipo']]  = $e;
    }
} catch (PDOException $e) { /* tabla Equipo aún no cargada */ }

// Aseguramos que existan las Fases que usaremos (FK requerida para insertar Partido)
try {
    $db->exec("INSERT INTO Fase (id_fase, nombre_fase, orden) VALUES
        ('F1', 'Fase de Grupos', 1),
        ('F2', 'Dieciseisavos de Final', 2),
        ('F3', 'Octavos de Final', 3),
        ('F4', 'Cuartos de Final', 4),
        ('F5', 'Semifinales', 5),
        ('F7', 'Final', 7)
        ON CONFLICT (id_fase) DO NOTHING");
} catch (PDOException $e) { /* ignorar */ }

// ===============================
// Guardar Pronóstico (POST)
// Distinguimos por rango de id_partido:
//   - 8901..9301 -> partido del bracket de eliminación (creado por admin-partidos.php)
//   - cualquier otro -> partido de Fase de Grupos (puede aún no existir en la BD)
// ===============================
if (isset($_POST['guardar_pronostico'])) {
    $id_partido = intval($_POST['id_partido']);
    $g1 = intval($_POST['p_goles1']);
    $g2 = intval($_POST['p_goles2']);
    $es_bracket = ($id_partido >= 8901 && $id_partido <= 9301);

    if ($es_bracket) {
        // --- Flujo eliminación ---
        $chk = $db->prepare("SELECT id_equipo1, id_equipo2, estado FROM Partido WHERE id_partido = ?");
        $chk->execute([$id_partido]);
        $p = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            echo "<p class='error'>Este partido del bracket aún no está inicializado. Pídele al admin que abra el panel de Eliminación primero.</p>";
        } elseif (!$p['id_equipo1'] || !$p['id_equipo2']) {
            echo "<p class='error'>Este partido todavía no tiene definidos a los dos equipos. Espera a que termine la ronda anterior.</p>";
        } elseif ($p['estado'] === 'Finalizado') {
            echo "<p class='error'>Este partido ya finalizó. No puedes modificar tu pronóstico.</p>";
        } else {
            try {
                $exist = $db->prepare("SELECT id_quiniela FROM Quiniela WHERE id_usuario = ? AND id_partido = ?");
                $exist->execute([$id_usuario, $id_partido]);
                $q_id = $exist->fetchColumn();
                if ($q_id) {
                    $db->prepare("UPDATE Quiniela SET prediccion_goles1 = ?, prediccion_goles2 = ? WHERE id_quiniela = ?")
                       ->execute([$g1, $g2, $q_id]);
                } else {
                    $db->prepare("INSERT INTO Quiniela (id_usuario, id_partido, prediccion_goles1, prediccion_goles2) VALUES (?, ?, ?, ?)")
                       ->execute([$id_usuario, $id_partido, $g1, $g2]);
                }
                echo "<p class='success'>¡Pronóstico de eliminación guardado!</p>";
            } catch (PDOException $e) {
                echo "<p class='error'>Error al guardar: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        // --- Flujo Fase de Grupos (igual que antes) ---
        $fecha = $_POST['fecha'];
        $hora  = $_POST['hora'];
        $home  = $_POST['home_team'];
        $away  = $_POST['away_team'];
        $grupo = $_POST['grupo'];

        $match_time = strtotime($fecha . ' ' . $hora);
        if (time() >= $match_time) {
            echo "<p class='error'>Error: Este partido ya comenzó o finalizó. No puedes modificar esta quiniela.</p>";
        } else {
            try {
                $chk = $db->prepare("SELECT id_partido FROM Partido WHERE id_partido = ?");
                $chk->execute([$id_partido]);
                if (!$chk->fetchColumn()) {
                    $id_eq1 = $equipos_map[$home] ?? $home;
                    $id_eq2 = $equipos_map[$away] ?? $away;
                    $insP = $db->prepare("INSERT INTO Partido (id_partido, fecha, hora, estado, id_fase, id_equipo1, id_equipo2, grupo_partido)
                                          VALUES (?, ?, ?, 'Pendiente', 'F1', ?, ?, ?)");
                    $insP->execute([$id_partido, $fecha, $hora, $id_eq1, $id_eq2, $grupo]);
                }

                $exist = $db->prepare("SELECT id_quiniela FROM Quiniela WHERE id_usuario = ? AND id_partido = ?");
                $exist->execute([$id_usuario, $id_partido]);
                $q_id = $exist->fetchColumn();
                if ($q_id) {
                    $db->prepare("UPDATE Quiniela SET prediccion_goles1 = ?, prediccion_goles2 = ? WHERE id_quiniela = ?")
                       ->execute([$g1, $g2, $q_id]);
                } else {
                    $db->prepare("INSERT INTO Quiniela (id_usuario, id_partido, prediccion_goles1, prediccion_goles2) VALUES (?, ?, ?, ?)")
                       ->execute([$id_usuario, $id_partido, $g1, $g2]);
                }
                echo "<p class='success'>¡Pronóstico guardado exitosamente!</p>";
            } catch (PDOException $e) {
                echo "<p class='error'>Error al guardar: " . $e->getMessage() . "</p>";
            }
        }
    }
}

// Cargar pronósticos previos del usuario indexados por id_partido
$pronosticos = [];
$stmtP = $db->prepare("SELECT id_partido, prediccion_goles1, prediccion_goles2, puntos_obtenidos FROM Quiniela WHERE id_usuario = ?");
$stmtP->execute([$id_usuario]);
foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $pronosticos[$r['id_partido']] = $r;
}

// Estructura de grupos y partidos desde groups.json (define el orden A -> L y la lista de juegos)
$grupos = [];
if (file_exists('groups.json')) {
    $data = json_decode(file_get_contents('groups.json'), true);
    $grupos = $data['groups'] ?? [];
}

// Cargar el bracket de eliminación desde la tabla Partido (ids 8901..9301)
$bracket_partidos = [];
try {
    $rsB = $db->query("SELECT id_partido, id_fase, id_equipo1, id_equipo2, goles_equipo1, goles_equipo2, estado
                       FROM Partido
                       WHERE id_partido BETWEEN 8901 AND 9301");
    foreach ($rsB->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $bracket_partidos[(int)$r['id_partido']] = $r;
    }
} catch (PDOException $e) { /* ignorar */ }
?>

<h2>Mis Pronósticos - Llenar mi Quiniela</h2>
<p>Selecciona la fase del torneo y registra tus marcadores estimados antes de que arranquen los partidos. Los juegos expirados se bloquearán automáticamente.</p>

<!-- Selector de Fase -->
<div style="display: flex; gap: 8px; margin-top: 20px; border-bottom: 2px solid #e2e8f0;">
    <a href="?fase=grupos" style="padding: 10px 22px; text-decoration: none; font-weight: bold; color: <?php echo $fase_activa === 'grupos' ? '#ffffff' : '#1e293b'; ?>; background: <?php echo $fase_activa === 'grupos' ? '#10b981' : '#f1f5f9'; ?>; border-radius: 6px 6px 0 0;">Fase de Grupos</a>
    <a href="?fase=eliminacion" style="padding: 10px 22px; text-decoration: none; font-weight: bold; color: <?php echo $fase_activa === 'eliminacion' ? '#ffffff' : '#1e293b'; ?>; background: <?php echo $fase_activa === 'eliminacion' ? '#10b981' : '#f1f5f9'; ?>; border-radius: 6px 6px 0 0;">Fase de Eliminación</a>
</div>

<div style="margin-top: 25px;">
<?php if ($fase_activa === 'grupos'): ?>
    <?php if (empty($grupos)): ?>
        <p class="error">No se encontró el archivo groups.json o está vacío.</p>
    <?php endif; ?>

    <?php foreach ($grupos as $g): ?>
        <h3 style="margin-top: 25px; color: #1e3a8a; border-left: 5px solid #10b981; padding-left: 10px;">Grupo <?php echo htmlspecialchars($g['group']); ?></h3>

        <?php foreach ($g['matches'] as $m):
            $is_expired = (time() >= strtotime($m['date'] . ' ' . $m['time']));
            $mid = intval($m['match_id']);
            $g1_val = $pronosticos[$mid]['prediccion_goles1'] ?? '';
            $g2_val = $pronosticos[$mid]['prediccion_goles2'] ?? '';
            $puntos = $pronosticos[$mid]['puntos_obtenidos'] ?? 0;
        ?>
            <div style="background: <?php echo $is_expired ? '#f1f5f9' : '#ffffff'; ?>; padding: 20px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <form method="POST" action="?fase=grupos" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                    <input type="hidden" name="id_partido" value="<?php echo $mid; ?>">
                    <input type="hidden" name="fecha"      value="<?php echo htmlspecialchars($m['date']); ?>">
                    <input type="hidden" name="hora"       value="<?php echo htmlspecialchars($m['time']); ?>">
                    <input type="hidden" name="home_team"  value="<?php echo htmlspecialchars($m['home_team']); ?>">
                    <input type="hidden" name="away_team"  value="<?php echo htmlspecialchars($m['away_team']); ?>">
                    <input type="hidden" name="grupo"      value="<?php echo htmlspecialchars($g['group']); ?>">

                    <div>
                        <span style="display:block; font-size:12px; color:#64748b;">Grupo <?php echo htmlspecialchars($g['group']); ?> · <?php echo htmlspecialchars($m['stadium']); ?></span>
                        <strong><?php echo date('d/m/Y H:i', strtotime($m['date'] . ' ' . $m['time'])); ?></strong>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px; justify-content: center; flex: 1;">
                        <span style="text-align: right; width: 140px;"><strong><?php echo htmlspecialchars($m['home_team']); ?></strong></span>
                        <input type="number" name="p_goles1" value="<?php echo $g1_val; ?>" min="0" style="width: 60px; text-align: center;" <?php echo $is_expired ? 'disabled' : ''; ?> required>
                        <span>-</span>
                        <input type="number" name="p_goles2" value="<?php echo $g2_val; ?>" min="0" style="width: 60px; text-align: center;" <?php echo $is_expired ? 'disabled' : ''; ?> required>
                        <span style="text-align: left; width: 140px;"><strong><?php echo htmlspecialchars($m['away_team']); ?></strong></span>
                    </div>

                    <div>
                        <?php if ($is_expired): ?>
                            <span style="color: #94a3b8; font-weight: bold;">🔒 Expirado</span>
                            <div style="font-size:12px; color:#10b981;">Puntos Ganados: <strong><?php echo $puntos; ?></strong></div>
                        <?php else: ?>
                            <button type="submit" name="guardar_pronostico" style="width: auto; background: #10b981; padding: 8px 20px;">Guardar</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>

<?php else:
    // ===========================================================
    // Bracket de Eliminación - misma estructura que admin-partidos.php
    // ===========================================================
    $rondas = [
        ['titulo' => 'Dieciseisavos',    'ids' => [8901,8902,8903,8904,8905,8906,8907,8908,8909,8910,8911,8912,8913,8914,8915,8916], 'color' => '#0ea5e9'],
        ['titulo' => 'Octavos de Final', 'ids' => [9001,9002,9003,9004,9005,9006,9007,9008], 'color' => '#3b82f6'],
        ['titulo' => 'Cuartos de Final', 'ids' => [9101,9102,9103,9104],                     'color' => '#8b5cf6'],
        ['titulo' => 'Semifinales',      'ids' => [9201,9202],                               'color' => '#ec4899'],
        ['titulo' => 'Final',            'ids' => [9301],                                    'color' => '#f59e0b'],
    ];
?>
    <h3 style="color:#1e3a8a; margin-top:0;">Bracket de Eliminación</h3>
    <p style="color:#475569;">Ingresa tu pronóstico para cada partido. Las tarjetas con "Por definir" se desbloquearán cuando el admin guarde los resultados de la ronda anterior y los equipos avancen automáticamente.</p>

    <?php if (empty($bracket_partidos)): ?>
        <p class="error">El bracket todavía no está inicializado. Pídele al admin que abra <strong>Administración &rarr; Fase de Eliminación</strong> al menos una vez.</p>
    <?php endif; ?>

    <div style="display: flex; gap: 18px; align-items: stretch; overflow-x: auto; padding: 10px 0 30px 0;">
        <?php foreach ($rondas as $ronda): ?>
            <div style="flex: 1; min-width: 210px; display: flex; flex-direction: column;">
                <h4 style="text-align: center; color: <?php echo $ronda['color']; ?>; margin: 0 0 15px 0; padding-bottom: 8px; border-bottom: 2px solid <?php echo $ronda['color']; ?>;">
                    <?php echo $ronda['titulo']; ?>
                </h4>
                <div style="display: flex; flex-direction: column; justify-content: space-around; flex: 1; gap: 10px; min-height: 900px;">
                    <?php foreach ($ronda['ids'] as $mid):
                        $bp = $bracket_partidos[$mid] ?? null;
                        $e1 = $bp['id_equipo1'] ?? null;
                        $e2 = $bp['id_equipo2'] ?? null;
                        $finalizado = isset($bp['estado']) && $bp['estado'] === 'Finalizado';
                        $listo      = $e1 && $e2;

                        $home_label = ($e1 && isset($by_code[$e1])) ? $by_code[$e1]['nombre'] : ($e1 ?: 'Por definir');
                        $away_label = ($e2 && isset($by_code[$e2])) ? $by_code[$e2]['nombre'] : ($e2 ?: 'Por definir');
                        $home_flag  = ($e1 && isset($by_code[$e1])) ? $by_code[$e1]['bandera'] : '';
                        $away_flag  = ($e2 && isset($by_code[$e2])) ? $by_code[$e2]['bandera'] : '';

                        $p1_val = $pronosticos[$mid]['prediccion_goles1'] ?? '';
                        $p2_val = $pronosticos[$mid]['prediccion_goles2'] ?? '';
                        $puntos = $pronosticos[$mid]['puntos_obtenidos'] ?? 0;

                        $puede_pronosticar = ($listo && !$finalizado);

                        // Color de fondo y borde según estado
                        $bg     = $finalizado ? '#f1f5f9' : ($listo ? '#ffffff' : '#f8fafc');
                        $border = $finalizado ? '#94a3b8' : ($listo ? '#cbd5e1' : '#e2e8f0');
                    ?>
                        <div style="background: <?php echo $bg; ?>; padding: 10px; border-radius: 8px; border: 1px solid <?php echo $border; ?>;">
                            <div style="font-size:10px; color:#94a3b8; text-align:center; margin-bottom:6px; letter-spacing:0.5px;">Partido #<?php echo $mid; ?></div>

                            <?php if ($finalizado): ?>
                                <div style="font-size:11px; text-align:center; background:#fef3c7; color:#92400e; padding:4px; border-radius:4px; margin-bottom:6px; font-weight:bold;">
                                    Resultado oficial: <?php echo intval($bp['goles_equipo1']); ?> - <?php echo intval($bp['goles_equipo2']); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="?fase=eliminacion">
                                <input type="hidden" name="id_partido" value="<?php echo $mid; ?>">

                                <div style="display:flex; align-items:center; gap:6px; padding:5px 4px;">
                                    <span style="font-size:16px;"><?php echo $home_flag ?: '🏳️'; ?></span>
                                    <span style="flex:1; font-size:13px; color: <?php echo $e1 ? '#1e293b' : '#94a3b8'; ?>;">
                                        <?php echo htmlspecialchars($home_label); ?>
                                    </span>
                                    <input type="number" name="p_goles1" value="<?php echo $p1_val; ?>" min="0"
                                           style="width: 42px; text-align: center; padding:3px;"
                                           <?php echo $puede_pronosticar ? 'required' : 'disabled'; ?>>
                                </div>

                                <div style="display:flex; align-items:center; gap:6px; padding:5px 4px; margin-top:3px; border-top:1px dashed #e2e8f0;">
                                    <span style="font-size:16px;"><?php echo $away_flag ?: '🏳️'; ?></span>
                                    <span style="flex:1; font-size:13px; color: <?php echo $e2 ? '#1e293b' : '#94a3b8'; ?>;">
                                        <?php echo htmlspecialchars($away_label); ?>
                                    </span>
                                    <input type="number" name="p_goles2" value="<?php echo $p2_val; ?>" min="0"
                                           style="width: 42px; text-align: center; padding:3px;"
                                           <?php echo $puede_pronosticar ? 'required' : 'disabled'; ?>>
                                </div>

                                <?php if ($finalizado): ?>
                                    <div style="font-size:12px; color:#10b981; text-align:center; margin-top:8px;">
                                        🔒 Puntos Ganados: <strong><?php echo $puntos; ?></strong>
                                    </div>
                                <?php elseif ($listo): ?>
                                    <button type="submit" name="guardar_pronostico"
                                            style="width:100%; margin-top:8px; padding:6px 0; font-size:12px; font-weight:bold;
                                                   background: <?php echo $ronda['color']; ?>; color:#fff; border:none; border-radius:4px; cursor:pointer;">
                                        Guardar
                                    </button>
                                <?php else: ?>
                                    <div style="font-size:11px; color:#94a3b8; text-align:center; margin-top:8px; font-style:italic;">
                                        Esperando equipos
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>
