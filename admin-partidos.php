<?php
include_once 'header.php';

if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] == false) {
    die("<p class='error'>Acceso denegado. Se requieren permisos de administrador.</p>");
}

// Fase activa de la administración (grupos | eliminacion)
$fase_activa = $_GET['fase'] ?? 'grupos';
if (!in_array($fase_activa, ['grupos', 'eliminacion'])) {
    $fase_activa = 'grupos';
}

// Cargar el catálogo de Equipos directamente desde la base de datos.
// Construimos dos mapas:
//   - $by_code:  id_equipo (FIFA)  -> { nombre, bandera, id_grupo }
//   - $by_name:  nombre del equipo -> id_equipo (FIFA)
$by_code = [];
$by_name = [];
try {
    foreach ($db->query("SELECT id_equipo, nombre, bandera, id_grupo FROM Equipo") as $e) {
        $by_code[$e['id_equipo']] = $e;
        $by_name[$e['nombre']]    = $e['id_equipo'];
    }
} catch (PDOException $e) { /* la tabla Equipo aún no se carga */ }

// Aseguramos que exista la Fase de Grupos en el catálogo (FK requerida por Partido)
try {
    $db->exec("INSERT INTO Fase (id_fase, nombre_fase, orden) VALUES ('F1', 'Fase de Grupos', 1) ON CONFLICT (id_fase) DO NOTHING");
} catch (PDOException $e) { /* ignorar */ }

// =====================================================
// Guardar resultado oficial del partido (acción de Admin)
// El formulario envía DIRECTAMENTE los códigos FIFA en id_eq1 / id_eq2,
// resueltos contra la BD al renderizar la lista, así no dependemos de
// comparar nombres en este punto.
// =====================================================
if (isset($_POST['guardar_resultado'])) {
    $id_partido = intval($_POST['id_partido']);
    $g1     = intval($_POST['goles_equipo1']);
    $g2     = intval($_POST['goles_equipo2']);
    $fecha  = $_POST['fecha'];
    $hora   = $_POST['hora'];
    $id_eq1 = $_POST['id_eq1'] ?? '';
    $id_eq2 = $_POST['id_eq2'] ?? '';
    $grupo  = $_POST['grupo'];

    // Validar que ambos códigos existan en la BD
    if (!isset($by_code[$id_eq1]) || !isset($by_code[$id_eq2])) {
        echo "<p class='error'>Error: códigos de equipo inválidos ($id_eq1 / $id_eq2). Verifica que la tabla Equipo esté cargada.</p>";
    } else {
        try {
            $db->beginTransaction();

            // 1) Asegurar que el Partido exista con los códigos FIFA correctos
            $chk = $db->prepare("SELECT id_equipo1, id_equipo2 FROM Partido WHERE id_partido = ?");
            $chk->execute([$id_partido]);
            $partido_prev = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$partido_prev) {
                $insP = $db->prepare("INSERT INTO Partido (id_partido, fecha, hora, estado, id_fase, id_equipo1, id_equipo2, grupo_partido)
                                      VALUES (?, ?, ?, 'Pendiente', 'F1', ?, ?, ?)");
                $insP->execute([$id_partido, $fecha, $hora, $id_eq1, $id_eq2, $grupo]);
            } elseif ($partido_prev['id_equipo1'] !== $id_eq1 || $partido_prev['id_equipo2'] !== $id_eq2) {
                // Auto-corrección: si un Partido anterior se había guardado con el nombre del equipo, sustituirlo por el código FIFA
                $fix = $db->prepare("UPDATE Partido SET id_equipo1 = ?, id_equipo2 = ? WHERE id_partido = ?");
                $fix->execute([$id_eq1, $id_eq2, $id_partido]);
            }

            // 2) Guardar el resultado final del partido
            $upd = $db->prepare("UPDATE Partido SET goles_equipo1 = ?, goles_equipo2 = ?, estado = 'Finalizado' WHERE id_partido = ?");
            $upd->execute([$g1, $g2, $id_partido]);

            // 3) Reconstruir la tabla de posiciones desde cero a partir de TODOS los partidos finalizados.
            //    Esto se autorrepara aunque haya datos inconsistentes anteriores.
            $db->exec("UPDATE Equipo SET puntos_obtenidos = 0, goles_dif = 0");

            $finStmt = $db->query("SELECT id_equipo1, id_equipo2, goles_equipo1, goles_equipo2
                                   FROM Partido
                                   WHERE estado = 'Finalizado'
                                     AND goles_equipo1 IS NOT NULL
                                     AND goles_equipo2 IS NOT NULL");
            $addPts = $db->prepare("UPDATE Equipo SET puntos_obtenidos = puntos_obtenidos + ?, goles_dif = goles_dif + ? WHERE id_equipo = ?");
            foreach ($finStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rg1 = intval($r['goles_equipo1']);
                $rg2 = intval($r['goles_equipo2']);
                $rdif1 = $rg1 - $rg2;
                $rdif2 = $rg2 - $rg1;
                $rpts1 = ($rg1 > $rg2) ? 3 : (($rg1 == $rg2) ? 1 : 0);
                $rpts2 = ($rg2 > $rg1) ? 3 : (($rg1 == $rg2) ? 1 : 0);
                $addPts->execute([$rpts1, $rdif1, $r['id_equipo1']]);
                $addPts->execute([$rpts2, $rdif2, $r['id_equipo2']]);
            }

            // 4) Recalcular puntos de las quinielas para este partido
            //    - 3 puntos si atinó el resultado (gana local / gana visitante / empate)
            //    - 6 puntos en total si además atinó el marcador exacto (3 + 3)
            $real_signo = ($g1 > $g2) ? 'W1' : (($g1 < $g2) ? 'W2' : 'X');
            $q_stmt = $db->prepare("SELECT id_quiniela, prediccion_goles1, prediccion_goles2 FROM Quiniela WHERE id_partido = ?");
            $q_stmt->execute([$id_partido]);
            $up_q = $db->prepare("UPDATE Quiniela SET puntos_obtenidos = ? WHERE id_quiniela = ?");
            foreach ($q_stmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
                $puntos = 0;
                $pred_signo = ($q['prediccion_goles1'] > $q['prediccion_goles2']) ? 'W1'
                            : (($q['prediccion_goles1'] < $q['prediccion_goles2']) ? 'W2' : 'X');

                if ($real_signo === $pred_signo)         $puntos += 3; // atinó ganador / empate
                if ($g1 == $q['prediccion_goles1']
                    && $g2 == $q['prediccion_goles2'])   $puntos += 3; // atinó marcador exacto -> total 6

                $up_q->execute([$puntos, $q['id_quiniela']]);
            }

            $db->commit();
            echo "<p class='success'>¡Resultado guardado, tabla de posiciones y quinielas actualizadas!</p>";
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo "<p class='error'>Error al procesar resultado: " . $e->getMessage() . "</p>";
        }
    }
}

// Cargar partidos ya registrados en BD, indexados por id_partido (para pre-llenar marcadores)
$partidos_db = [];
$rsP = $db->query("SELECT id_partido, goles_equipo1, goles_equipo2, estado FROM Partido");
if ($rsP) {
    foreach ($rsP->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $partidos_db[$r['id_partido']] = $r;
    }
}

// Cargar estructura de grupos y partidos desde groups.json (orden A -> L)
$grupos = [];
if (file_exists('groups.json')) {
    $data = json_decode(file_get_contents('groups.json'), true);
    $grupos = $data['groups'] ?? [];
}

// Helper: dado el nombre que viene en groups.json, devolver el id_equipo (código FIFA) de la BD.
// Si el nombre exacto no aparece en Equipo, prueba alias comunes (acentos, & vs and, etc.)
function resolver_codigo(string $nombre_json, array $by_name): ?string {
    if (isset($by_name[$nombre_json])) return $by_name[$nombre_json];

    $alias = [
        'Bosnia & Herzegovina' => 'Bosnia and Herzegovina',
        'Cote d\'Ivoire'       => 'Côte d\'Ivoire',
        "Cote d'Ivoire"        => "Côte d'Ivoire",
        'Curacao'              => 'Curaçao',
        'Turkiye'              => 'Türkiye',
        'Cape Verde'           => 'Cabo Verde',
        'DR Congo'             => 'Congo DR',
        'South Korea'          => 'Korea Republic',
        'Iran'                 => 'IR Iran',
        'Czech Republic'       => 'Czechia',
        'United States'        => 'USA',
        'Turkey'               => 'Türkiye',
        'Ivory Coast'          => 'Côte d\'Ivoire',
    ];
    if (isset($alias[$nombre_json]) && isset($by_name[$alias[$nombre_json]])) {
        return $by_name[$alias[$nombre_json]];
    }
    return null;
}
?>

<h2>Administración de Resultados - Mundial 2026</h2>
<p>Como administrador, ingresa el marcador oficial de cada partido. Al guardar, se actualizan automáticamente la <strong>tabla de posiciones</strong> y los <strong>puntos de las quinielas</strong> de todos los participantes.</p>

<?php if (empty($by_code)): ?>
    <p class="error">⚠️ La tabla <strong>Equipo</strong> está vacía. Ejecuta <code>adding_teams.sql</code> antes de usar esta pantalla.</p>
<?php endif; ?>

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
        <h3 style="margin-top: 25px; color: #1e3a8a; border-left: 5px solid #3b82f6; padding-left: 10px;">Grupo <?php echo htmlspecialchars($g['group']); ?></h3>

        <?php foreach ($g['matches'] as $m):
            $mid = intval($m['match_id']);
            $row_db = $partidos_db[$mid] ?? null;
            $g1_val = $row_db['goles_equipo1'] ?? '';
            $g2_val = $row_db['goles_equipo2'] ?? '';
            $finalizado = isset($row_db['estado']) && $row_db['estado'] === 'Finalizado';

            // Resolver los códigos FIFA de los equipos directamente desde la BD
            $id_eq1 = resolver_codigo($m['home_team'], $by_name);
            $id_eq2 = resolver_codigo($m['away_team'], $by_name);

            // Si los códigos existen, usamos el nombre/bandera de la BD; si no, caemos al nombre del JSON
            $home_label = $id_eq1 ? $by_code[$id_eq1]['nombre'] : $m['home_team'];
            $away_label = $id_eq2 ? $by_code[$id_eq2]['nombre'] : $m['away_team'];
            $home_flag  = $id_eq1 ? $by_code[$id_eq1]['bandera'] : '';
            $away_flag  = $id_eq2 ? $by_code[$id_eq2]['bandera'] : '';

            $missing = (!$id_eq1 || !$id_eq2);
        ?>
            <div style="background: <?php echo $missing ? '#fef2f2' : ($finalizado ? '#ecfdf5' : '#ffffff'); ?>; padding: 20px; margin-bottom: 15px; border-radius: 8px; border: 1px solid <?php echo $missing ? '#fca5a5' : ($finalizado ? '#10b981' : '#e2e8f0'); ?>;">
                <?php if ($missing): ?>
                    <p style="margin:0; color:#b91c1c;"><strong>⚠️ No se pudo emparejar:</strong>
                       <?php if (!$id_eq1) echo "'" . htmlspecialchars($m['home_team']) . "' "; ?>
                       <?php if (!$id_eq2) echo "'" . htmlspecialchars($m['away_team']) . "' "; ?>
                       con la tabla Equipo. Revisa que estos equipos existan en la BD.
                    </p>
                <?php else: ?>
                    <form method="POST" action="?fase=grupos" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                        <input type="hidden" name="id_partido" value="<?php echo $mid; ?>">
                        <input type="hidden" name="fecha"      value="<?php echo htmlspecialchars($m['date']); ?>">
                        <input type="hidden" name="hora"       value="<?php echo htmlspecialchars($m['time']); ?>">
                        <input type="hidden" name="id_eq1"     value="<?php echo htmlspecialchars($id_eq1); ?>">
                        <input type="hidden" name="id_eq2"     value="<?php echo htmlspecialchars($id_eq2); ?>">
                        <input type="hidden" name="grupo"      value="<?php echo htmlspecialchars($g['group']); ?>">

                        <div>
                            <span style="display:block; font-size:12px; color:#64748b;">Grupo <?php echo htmlspecialchars($g['group']); ?> · <?php echo htmlspecialchars($m['stadium']); ?></span>
                            <strong><?php echo date('d/m/Y H:i', strtotime($m['date'] . ' ' . $m['time'])); ?></strong>
                            <?php if ($finalizado): ?>
                                <span style="display:inline-block; margin-left:6px; font-size:11px; background:#10b981; color:#fff; padding:2px 6px; border-radius:4px;">FINALIZADO</span>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; align-items: center; gap: 10px; justify-content: center; flex: 1;">
                            <span style="text-align: right; width: 160px;">
                                <strong><?php echo htmlspecialchars($home_label); ?></strong>
                                <span style="margin-left:4px;"><?php echo $home_flag; ?></span>
                                <span style="display:block; font-size:11px; color:#94a3b8;"><?php echo htmlspecialchars($id_eq1); ?></span>
                            </span>
                            <input type="number" name="goles_equipo1" value="<?php echo $g1_val; ?>" min="0" style="width: 60px; text-align: center;" required>
                            <span>-</span>
                            <input type="number" name="goles_equipo2" value="<?php echo $g2_val; ?>" min="0" style="width: 60px; text-align: center;" required>
                            <span style="text-align: left; width: 160px;">
                                <span style="margin-right:4px;"><?php echo $away_flag; ?></span>
                                <strong><?php echo htmlspecialchars($away_label); ?></strong>
                                <span style="display:block; font-size:11px; color:#94a3b8;"><?php echo htmlspecialchars($id_eq2); ?></span>
                            </span>
                        </div>

                        <div>
                            <button type="submit" name="guardar_resultado" style="width: auto; background: #3b82f6; padding: 8px 20px;">
                                <?php echo $finalizado ? 'Actualizar' : 'Guardar'; ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
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
