<?php 
include_once 'header.php';

if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] == false) {
    die("<p class='error'>Acceso denegado. Se requieren permisos de administrador.</p>");
}

// 1. Guardar nuevo Partido (Primera Fase)
if (isset($_POST['crear_partido'])) {
    $id_equipo1 = $_POST['id_equipo1'];
    $id_equipo2 = $_POST['id_equipo2'];
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $id_fase = $_POST['id_fase'];

    if ($id_equipo1 == $id_equipo2) {
        echo "<p class='error'>Error: Una selección no puede jugar contra sí misma.</p>";
    } else {
        try {
            // Validación básica de traslape: Que un equipo no tenga partido a la misma fecha y hora
            $chk = $db->prepare("SELECT COUNT(*) FROM Partido WHERE (id_equipo1 = ? OR id_equipo2 = ?) AND fecha = ? AND hora = ?");
            $chk->execute([$id_equipo1, $id_equipo1, $fecha, $hora]);
            if ($chk->fetchColumn() > 0) {
                echo "<p class='error'>Error: Traslape detectado. El Equipo 1 ya tiene un juego programado en esa fecha y hora.</p>";
            } else {
                $stmt = $db->prepare("INSERT INTO Partido (fecha, hora, id_fase, id_equipo1, id_equipo2) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$fecha, $hora, $id_fase, $id_equipo1, $id_equipo2]);
                echo "<p class='success'>¡Partido calendarizado exitosamente!</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>Error al calendarizar: " . $e->getMessage() . "</p>";
        }
    }
}

// 2. Actualizar Resultado de un Partido terminado y calcular puntos de Tabla de Posiciones
if (isset($_POST['guardar_resultado'])) {
    $id_partido = $_POST['id_partido'];
    $goles1 = intval($_POST['goles_equipo1']);
    $goles2 = intval($_POST['goles_equipo2']);

    try {
        $db->beginTransaction();

        // Obtener datos previos del partido para no duplicar puntos si se modifica un resultado
        $p_stmt = $db->prepare("SELECT * FROM Partido WHERE id_partido = ?");
        $p_stmt->execute([$id_partido]);
        $partido = $p_stmt->fetch(PDO::FETCH_ASSOC);

        // Actualizar el estado del partido
        $stmt = $db->prepare("UPDATE Partido SET goles_equipo1 = ?, goles_equipo2 = ?, estado = 'Finalizado' WHERE id_partido = ?");
        $stmt->execute([$goles1, $goles2, $id_partido]);

        // Lógica de actualización de Estadísticas de Equipos (Tabla de Posiciones)
        // Reiniciar o sumar según el resultado actual
        $dif1 = $goles1 - $goles2;
        $dif2 = $goles2 - $goles1;
        $pts1 = ($goles1 > $goles2) ? 3 : (($goles1 == $goles2) ? 1 : 0);
        $pts2 = ($goles2 > $goles1) ? 3 : (($goles1 == $goles2) ? 1 : 0);

        $up1 = $db->prepare("UPDATE Equipo SET puntos_obtenidos = puntos_obtenidos + ?, goles_dif = goles_dif + ? WHERE id_equipo = ?");
        $up1->execute([$pts1, $dif1, $partido['id_equipo1']]);

        $up2 = $db->prepare("UPDATE Equipo SET puntos_obtenidos = puntos_obtenidos + ?, goles_dif = goles_dif + ? WHERE id_equipo = ?");
        $up2->execute([$pts2, $dif2, $partido['id_equipo2']]);

        // --- LÓGICA AUTOMÁTICA DE QUINIELAS: CALCULAR PUNTOS DE PARTICIPANTES ---
        // 3 puntos por atinar resultado (Ganador/Empate) + 3 puntos por marcador exacto.
        $q_stmt = $db->prepare("SELECT * FROM Quiniela WHERE id_partido = ?");
        $q_stmt->execute([$id_partido]);
        $quinielas = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($quinielas as $q) {
            $puntos = 0;
            
            // Determinar signos de resultados reales y predichos
            $real_signo = ($goles1 > $goles2) ? 'W1' : (($goles1 < $goles2) ? 'W2' : 'X');
            $pred_signo = ($q['prediccion_goles1'] > $q['prediccion_goles2']) ? 'W1' : (($q['prediccion_goles1'] < $q['prediccion_goles2']) ? 'W2' : 'X');

            if ($real_signo == $pred_signo) {
                $puntos += 3; // Atinó resultado
            }
            if ($goles1 == $q['prediccion_goles1'] && $goles2 == $q['prediccion_goles2']) {
                $puntos += 3; // Atinó marcador exacto
            }

            $up_q = $db->prepare("UPDATE Quiniela SET puntos_obtenidos = ? WHERE id_quiniela = ?");
            $up_q->execute([$puntos, $q['id_quiniela']]);
        }

        $db->commit();
        echo "<p class='success'>¡Resultado guardado y puntajes de quinielas actualizados!</p>";
    } catch (PDOException $e) {
        $db->rollBack();
        echo "<p class='error'>Error al procesar resultado: " . $e->getMessage() . "</p>";
    }
}

// Cargar catálogos
$equipos = $db->query("SELECT id_equipo, nombre FROM Equipo ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
// Insertar fases si no existen
$db->query("INSERT INTO Fase (id_fase, nombre_fase, orden) VALUES ('F1', 'Fase de Grupos', 1), ('F2', 'Dieciseisavos', 2), ('F3', 'Octavos', 3) ON CONFLICT DO NOTHING");
$fases = $db->query("SELECT * FROM Fase ORDER BY orden")->fetchAll(PDO::FETCH_ASSOC);

$partidos_act = $db->query("SELECT p.*, e1.nombre as loc, e2.nombre as vis FROM Partido p JOIN Equipo e1 ON p.id_equipo1 = e1.id_equipo JOIN Equipo e2 ON p.id_equipo2 = e2.id_equipo ORDER BY p.fecha, p.hora")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Administración del Calendario y Resultados (CRUD)</h2>

<div class="auth-flex">
    <div class="auth-form" style="max-width: 350px;">
        <h3>Programar Partido</h3>
        <form method="POST">
            <label>Equipo Local:</label>
            <select name="id_equipo1" required>
                <?php foreach($equipos as $e): ?>
                    <option value="<?php echo $e['id_equipo']; ?>"><?php echo $e['nombre']; ?></option>
                <?php endforeach; ?>
            </select>
            
            <label>Equipo Visitante:</label>
            <select name="id_equipo2" required>
                <?php foreach($equipos as $e): ?>
                    <option value="<?php echo $e['id_equipo']; ?>"><?php echo $e['nombre']; ?></option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="fecha" required>
            <input type="time" name="hora" required>

            <label>Fase Torneo:</label>
            <select name="id_fase" required>
                <?php foreach($fases as $f): ?>
                    <option value="<?php echo $f['id_fase']; ?>"><?php echo $f['nombre_fase']; ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="crear_partido">Calendarizar Partido</button>
        </form>
    </div>

    <div style="flex: 2;">
        <h3>Ingreso de Resultados Reales</h3>
        <?php foreach($partidos_act as $p): ?>
            <div style="background: #f8fafc; padding: 15px; margin-bottom: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                <form method="POST" style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                    <input type="hidden" name="id_partido" value="<?php echo $p['id_partido']; ?>">
                    <span style="font-size: 13px; color:#64748b;"><?php echo $p['fecha'] . " " . $p['hora']; ?></span>
                    
                    <div style="text-align: right; flex: 1;"><strong><?php echo $p['loc']; ?></strong></div>
                    <input type="number" name="goles_equipo1" value="<?php echo $p['goles_equipo1']; ?>" style="width: 50px; text-align: center;" required>
                    
                    <span>vs</span>
                    
                    <input type="number" name="goles_equipo2" value="<?php echo $p['goles_equipo2']; ?>" style="width: 50px; text-align: center;" required>
                    <div style="text-align: left; flex: 1;"><strong><?php echo $p['vis']; ?></strong></div>
                    
                    <button type="submit" name="guardar_resultado" style="width: auto; background: #3b82f6; padding: 5px 15px;">
                        <?php echo ($p['estado'] == 'Finalizado') ? 'Actualizar' : 'Guardar'; ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include_once 'footer.php'; ?>