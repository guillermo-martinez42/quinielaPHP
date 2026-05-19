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

// Aseguramos que exista la Fase de Grupos en el catálogo (necesaria para FK al insertar Partido)
try {
    $db->exec("INSERT INTO Fase (id_fase, nombre_fase, orden) VALUES ('F1', 'Fase de Grupos', 1) ON CONFLICT (id_fase) DO NOTHING");
} catch (PDOException $e) { /* ignorar si la tabla aún no existe */ }

// Guardar Pronóstico
if (isset($_POST['guardar_pronostico'])) {
    $id_partido = intval($_POST['id_partido']);
    $g1 = intval($_POST['p_goles1']);
    $g2 = intval($_POST['p_goles2']);
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $home = $_POST['home_team'];
    $away = $_POST['away_team'];
    $grupo = $_POST['grupo'];

    // Validar fecha y hora para evitar ingresos extemporáneos
    $match_time = strtotime($fecha . ' ' . $hora);
    if (time() >= $match_time) {
        echo "<p class='error'>Error: Este partido ya comenzó o finalizó. No puedes modificar esta quiniela.</p>";
    } else {
        try {
            // Si el partido aún no existe en la BD, lo creamos a partir del JSON
            $chk = $db->prepare("SELECT id_partido FROM Partido WHERE id_partido = ?");
            $chk->execute([$id_partido]);
            if (!$chk->fetchColumn()) {
                $insP = $db->prepare("INSERT INTO Partido (id_partido, fecha, hora, estado, id_fase, id_equipo1, id_equipo2, grupo_partido)
                                      VALUES (?, ?, ?, 'Pendiente', 'F1', ?, ?, ?)");
                $insP->execute([$id_partido, $fecha, $hora, $home, $away, $grupo]);
            }

            // UPSERT manual del pronóstico
            $exist = $db->prepare("SELECT id_quiniela FROM Quiniela WHERE id_usuario = ? AND id_partido = ?");
            $exist->execute([$id_usuario, $id_partido]);
            $q_id = $exist->fetchColumn();

            if ($q_id) {
                $up = $db->prepare("UPDATE Quiniela SET prediccion_goles1 = ?, prediccion_goles2 = ? WHERE id_quiniela = ?");
                $up->execute([$g1, $g2, $q_id]);
            } else {
                $ins = $db->prepare("INSERT INTO Quiniela (id_usuario, id_partido, prediccion_goles1, prediccion_goles2) VALUES (?, ?, ?, ?)");
                $ins->execute([$id_usuario, $id_partido, $g1, $g2]);
            }
            echo "<p class='success'>¡Pronóstico guardado exitosamente!</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>Error al guardar: " . $e->getMessage() . "</p>";
        }
    }
}

// Pronósticos previos del usuario indexados por id_partido
$pronosticos = [];
$stmtP = $db->prepare("SELECT id_partido, prediccion_goles1, prediccion_goles2, puntos_obtenidos FROM Quiniela WHERE id_usuario = ?");
$stmtP->execute([$id_usuario]);
foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $pronosticos[$r['id_partido']] = $r;
}

// Cargar partidos de la fase de grupos desde groups.json
$grupos = [];
if (file_exists('groups.json')) {
    $groups_data = json_decode(file_get_contents('groups.json'), true);
    $grupos = $groups_data['groups'] ?? [];
}
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
                    <input type="hidden" name="id_partido"  value="<?php echo $mid; ?>">
                    <input type="hidden" name="fecha"       value="<?php echo htmlspecialchars($m['date']); ?>">
                    <input type="hidden" name="hora"        value="<?php echo htmlspecialchars($m['time']); ?>">
                    <input type="hidden" name="home_team"   value="<?php echo htmlspecialchars($m['home_team']); ?>">
                    <input type="hidden" name="away_team"   value="<?php echo htmlspecialchars($m['away_team']); ?>">
                    <input type="hidden" name="grupo"       value="<?php echo htmlspecialchars($g['group']); ?>">

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

<?php else: ?>
    <div style="background: #f1f5f9; padding: 40px; border-radius: 8px; text-align: center; color: #64748b; border: 1px dashed #cbd5e1;">
        <h3 style="margin: 0; color: #1e3a8a;">Fase de Eliminación</h3>
        <p>Esta sección estará disponible próximamente.</p>
    </div>
<?php endif; ?>
</div>

<?php include_once 'footer.php'; ?>
