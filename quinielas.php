<?php 
include_once 'header.php';

if (!isset($_SESSION['id_usuario'])) {
    die("<p class='error'>Debes iniciar sesión para ingresar tus quinielas.</p>");
}

$id_usuario = $_SESSION['id_usuario'];

// Guardar Pronóstico
if (isset($_POST['guardar_pronostico'])) {
    $id_partido = $_POST['id_partido'];
    $g1 = intval($_POST['p_goles1']);
    $g2 = intval($_POST['p_goles2']);

    // Validar fecha y hora para evitar ingresos extemporáneos
    $chk = $db->prepare("SELECT fecha, hora FROM Partido WHERE id_partido = ?");
    $chk->execute([$id_partido]);
    $partido = $chk->fetch(PDO::FETCH_ASSOC);

    $match_time = strtotime($partido['fecha'] . ' ' . $partido['hora']);
    if (time() >= $match_time) {
        echo "<p class='error'>Error: Este partido ya comenzó o finalizó. No puedes modificar esta quiniela.</p>";
    } else {
        try {
            // Guardar con estrategia UPSERT (Insertar o Actualizar si ya existe)
            $stmt = $db->prepare("INSERT INTO Quiniela (id_usuario, id_partido, prediccion_goles1, prediccion_goles2) 
                                  VALUES (?, ?, ?, ?)
                                  ON CONFLICT (id_usuario, id_partido) 
                                  DO UPDATE SET prediccion_goles1 = EXCLUDED.prediccion_goles1, prediccion_goles2 = EXCLUDED.prediccion_goles2");
            
            // Como no definimos llave compuesta nativa en el script inicial, validamos manualmente por limpieza si ya existe:
            $exist = $db->prepare("SELECT id_quiniela FROM Quiniela WHERE id_usuario = ? AND id_partido = ?");
            $exist->execute([$id_usuario, $id_partido]);
            $q_id = $exist->fetchColumn();

            if($q_id) {
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

// Consultar partidos disponibles y traer los pronósticos ya guardados por el usuario actual
$query = "SELECT p.*, e1.nombre as loc, e2.nombre as vis, q.prediccion_goles1, q.prediccion_goles2, q.puntos_obtenidos
          FROM Partido p 
          JOIN Equipo e1 ON p.id_equipo1 = e1.id_equipo 
          JOIN Equipo e2 ON p.id_equipo2 = e2.id_equipo
          LEFT JOIN Quiniela q ON p.id_partido = q.id_partido AND q.id_usuario = ?
          ORDER BY p.fecha, p.hora";
$stmt = $db->prepare($query);
$stmt->execute([$id_usuario]);
$partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Mis Pronósticos - Llenar mi Quiniela</h2>
<p>Ingresa tus marcadores estimados antes de que arranquen los partidos. Los juegos expirados se bloquearán automáticamente.</p>

<div style="margin-top: 20px;">
    <?php foreach($partidos as $p): 
        $is_expired = (time() >= strtotime($p['fecha'] . ' ' . $p['hora']));
    ?>
        <div style="background: <?php echo $is_expired ? '#f1f5f9' : '#ffffff'; ?>; padding: 20px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <form method="POST" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <input type="hidden" name="id_partido" value="<?php echo $p['id_partido']; ?>">
                
                <div>
                    <span style="display:block; font-size:12px; color:#64748b;"><?php echo $p['fase']; ?></span>
                    <strong><?php echo date('d/m/Y H:i', strtotime($p['fecha'] . ' ' . $p['hora'])); ?></strong>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; justify-content: center; flex: 1;">
                    <span style="text-align: right; width: 120px;"><strong><?php echo $p['loc']; ?></strong></span>
                    <input type="number" name="p_goles1" value="<?php echo $p['prediccion_goles1']; ?>" min="0" style="width: 60px; text-align: center;" <?php echo $is_expired ? 'disabled' : ''; ?> required>
                    <span>-</span>
                    <input type="number" name="p_goles2" value="<?php echo $p['prediccion_goles2']; ?>" min="0" style="width: 60px; text-align: center;" <?php echo $is_expired ? 'disabled' : ''; ?> required>
                    <span style="text-align: left; width: 120px;"><strong><?php echo $p['vis']; ?></strong></span>
                </div>

                <div>
                    <?php if($is_expired): ?>
                        <span style="color: #94a3b8; font-weight: bold;">🔒 Expirado</span>
                        <div style="font-size:12px; color:#10b981;">Puntos Ganados: <strong><?php echo $p['puntos_obtenidos'] ?? 0; ?></strong></div>
                    <?php else: ?>
                        <button type="submit" name="guardar_pronostico" style="width: auto; background: #10b981; padding: 8px 20px;">Guardar</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php include_once 'footer.php'; ?>