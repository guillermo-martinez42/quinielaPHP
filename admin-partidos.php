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

// Aseguramos que existan las Fases necesarias en el catálogo (FK requerida por Partido)
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

// =====================================================
// Estructura del bracket de eliminación (Dieciseisavos -> Final)
// Cada partido conoce a quién alimenta y en qué slot (1 = local, 2 = visitante).
// ids en el rango 8901..9301 para no colisionar con los de groups.json (~400021xxx).
// =====================================================
$bracket_estructura = [
    'F2' => [ // Dieciseisavos de Final (32 -> 16)
        8901 => ['siguiente' => 9001, 'slot' => 1],
        8902 => ['siguiente' => 9001, 'slot' => 2],
        8903 => ['siguiente' => 9002, 'slot' => 1],
        8904 => ['siguiente' => 9002, 'slot' => 2],
        8905 => ['siguiente' => 9003, 'slot' => 1],
        8906 => ['siguiente' => 9003, 'slot' => 2],
        8907 => ['siguiente' => 9004, 'slot' => 1],
        8908 => ['siguiente' => 9004, 'slot' => 2],
        8909 => ['siguiente' => 9005, 'slot' => 1],
        8910 => ['siguiente' => 9005, 'slot' => 2],
        8911 => ['siguiente' => 9006, 'slot' => 1],
        8912 => ['siguiente' => 9006, 'slot' => 2],
        8913 => ['siguiente' => 9007, 'slot' => 1],
        8914 => ['siguiente' => 9007, 'slot' => 2],
        8915 => ['siguiente' => 9008, 'slot' => 1],
        8916 => ['siguiente' => 9008, 'slot' => 2],
    ],
    'F3' => [ // Octavos de Final
        9001 => ['siguiente' => 9101, 'slot' => 1],
        9002 => ['siguiente' => 9101, 'slot' => 2],
        9003 => ['siguiente' => 9102, 'slot' => 1],
        9004 => ['siguiente' => 9102, 'slot' => 2],
        9005 => ['siguiente' => 9103, 'slot' => 1],
        9006 => ['siguiente' => 9103, 'slot' => 2],
        9007 => ['siguiente' => 9104, 'slot' => 1],
        9008 => ['siguiente' => 9104, 'slot' => 2],
    ],
    'F4' => [ // Cuartos de Final
        9101 => ['siguiente' => 9201, 'slot' => 1],
        9102 => ['siguiente' => 9201, 'slot' => 2],
        9103 => ['siguiente' => 9202, 'slot' => 1],
        9104 => ['siguiente' => 9202, 'slot' => 2],
    ],
    'F5' => [ // Semifinales
        9201 => ['siguiente' => 9301, 'slot' => 1],
        9202 => ['siguiente' => 9301, 'slot' => 2],
    ],
    'F7' => [ // Final
        9301 => ['siguiente' => null, 'slot' => null],
    ],
];

// Inicializar (una sola vez) las filas vacías del bracket en la tabla Partido
try {
    $chkB = $db->prepare("SELECT 1 FROM Partido WHERE id_partido = ?");
    $insB = $db->prepare("INSERT INTO Partido (id_partido, id_fase, estado) VALUES (?, ?, 'Pendiente')");
    foreach ($bracket_estructura as $fase => $matches) {
        foreach (array_keys($matches) as $mid) {
            $chkB->execute([$mid]);
            if (!$chkB->fetchColumn()) {
                $insB->execute([$mid, $fase]);
            }
        }
    }
} catch (PDOException $e) { /* ignorar */ }

// =====================================================
// Mapa de posiciones de Fase de Grupos a slots fijos de Dieciseisavos.
// 16 partidos: 8 "1° vs 3°", 4 "1° vs 2°" cruzados (H<->J, I<->L),
// y 4 "2° vs 2°" (A-B, C-D, E-F, G-K).
// Cada posición ("1A", "2A", ...) va a un (match_id, slot) específico.
// Los slots "3rd" se llenan después con los 8 mejores terceros.
// =====================================================
$r32_slot_map = [
    // 1° de cada grupo (lado izquierdo de los 8 "1° vs 3°" y de los 4 "1° vs 2°")
    '1A' => ['match' => 8901, 'slot' => 1],   // 1A vs 3rd
    '1B' => ['match' => 8903, 'slot' => 1],   // 1B vs 3rd
    '1C' => ['match' => 8905, 'slot' => 1],   // 1C vs 3rd
    '1D' => ['match' => 8907, 'slot' => 1],   // 1D vs 3rd
    '1E' => ['match' => 8909, 'slot' => 1],   // 1E vs 3rd
    '1F' => ['match' => 8911, 'slot' => 1],   // 1F vs 3rd
    '1G' => ['match' => 8913, 'slot' => 1],   // 1G vs 3rd
    '1K' => ['match' => 8915, 'slot' => 1],   // 1K vs 3rd
    '1H' => ['match' => 8910, 'slot' => 1],   // 1H vs 2J
    '1I' => ['match' => 8912, 'slot' => 1],   // 1I vs 2L
    '1J' => ['match' => 8914, 'slot' => 1],   // 1J vs 2H
    '1L' => ['match' => 8916, 'slot' => 1],   // 1L vs 2I

    // 2° de cada grupo
    '2A' => ['match' => 8906, 'slot' => 1],   // 2A vs 2B
    '2B' => ['match' => 8906, 'slot' => 2],
    '2C' => ['match' => 8902, 'slot' => 1],   // 2C vs 2D
    '2D' => ['match' => 8902, 'slot' => 2],
    '2E' => ['match' => 8904, 'slot' => 1],   // 2E vs 2F
    '2F' => ['match' => 8904, 'slot' => 2],
    '2G' => ['match' => 8908, 'slot' => 1],   // 2G vs 2K
    '2K' => ['match' => 8908, 'slot' => 2],
    '2H' => ['match' => 8914, 'slot' => 2],   // 1J vs 2H
    '2I' => ['match' => 8916, 'slot' => 2],   // 1L vs 2I
    '2J' => ['match' => 8910, 'slot' => 2],   // 1H vs 2J
    '2L' => ['match' => 8912, 'slot' => 2],   // 1I vs 2L
];

// Los 8 partidos donde encaja un tercer lugar (slot 2), junto con el grupo del 1° que les toca
$r32_slots_de_terceros = [
    8901 => 'A',
    8903 => 'B',
    8905 => 'C',
    8907 => 'D',
    8909 => 'E',
    8911 => 'F',
    8913 => 'G',
    8915 => 'K',
];

/**
 * Calcula la tabla ordenada de un grupo (1° -> 4°).
 * Criterios: puntos DESC, gol diferencia DESC, goles a favor DESC.
 */
function tabla_grupo(PDO $db, string $letra): array {
    $q = "SELECT id_equipo, id_grupo, puntos_obtenidos, goles_dif,
          COALESCE((SELECT SUM(goles_equipo1) FROM Partido WHERE id_equipo1 = id_equipo AND estado='Finalizado' AND id_fase='F1'), 0) +
          COALESCE((SELECT SUM(goles_equipo2) FROM Partido WHERE id_equipo2 = id_equipo AND estado='Finalizado' AND id_fase='F1'), 0) AS gf
          FROM Equipo
          WHERE id_grupo = ?
          ORDER BY puntos_obtenidos DESC, goles_dif DESC, gf DESC";
    $st = $db->prepare($q);
    $st->execute([$letra]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Después de cada save de Fase de Grupos:
 *  - Para cada grupo con sus 6 partidos finalizados, coloca 1° y 2° en sus slots fijos de R32.
 *  - Cuando los 12 grupos están listos, calcula los 8 mejores terceros y los asigna
 *    a los 8 slots "vs 3°" evitando emparejamientos del mismo grupo.
 */
function avanzar_grupos_a_dieciseisavos(PDO $db, array $r32_slot_map, array $r32_slots_de_terceros): void {
    // 1) ¿Qué grupos tienen sus 6 partidos finalizados?
    $cnt = $db->query("SELECT grupo_partido, COUNT(*) AS c
                       FROM Partido
                       WHERE id_fase='F1' AND estado='Finalizado' AND grupo_partido IS NOT NULL
                       GROUP BY grupo_partido")->fetchAll(PDO::FETCH_ASSOC);
    $grupos_listos = [];
    foreach ($cnt as $r) {
        if ((int)$r['c'] >= 6) $grupos_listos[] = $r['grupo_partido'];
    }

    // 2) Para cada grupo completo, escribir 1° y 2° en sus slots
    foreach ($grupos_listos as $letra) {
        $std = tabla_grupo($db, $letra);
        if (count($std) < 2) continue;
        $first  = $std[0]['id_equipo'];
        $second = $std[1]['id_equipo'];

        foreach (["1$letra" => $first, "2$letra" => $second] as $pos => $eq) {
            if (!isset($r32_slot_map[$pos])) continue;
            $m   = $r32_slot_map[$pos]['match'];
            $col = $r32_slot_map[$pos]['slot'] == 1 ? 'id_equipo1' : 'id_equipo2';
            $u = $db->prepare("UPDATE Partido SET $col = ? WHERE id_partido = ?");
            $u->execute([$eq, $m]);
        }
    }

    // 3) ¿Están listos los 12 grupos? Entonces calculamos los 8 mejores terceros
    if (count($grupos_listos) >= 12) {
        $terceros = [];
        foreach (['A','B','C','D','E','F','G','H','I','J','K','L'] as $letra) {
            $std = tabla_grupo($db, $letra);
            if (count($std) >= 3) $terceros[] = $std[2];
        }
        // Ordenar terceros: puntos DESC, gol diferencia DESC, goles a favor DESC
        usort($terceros, function($a, $b) {
            if ($b['puntos_obtenidos'] != $a['puntos_obtenidos']) return $b['puntos_obtenidos'] <=> $a['puntos_obtenidos'];
            if ($b['goles_dif']        != $a['goles_dif'])        return $b['goles_dif']        <=> $a['goles_dif'];
            return $b['gf'] <=> $a['gf'];
        });
        $top8 = array_slice($terceros, 0, 8);

        // Limpiar primero todos los slots de 3° por si una corrida anterior dejó algo
        foreach (array_keys($r32_slots_de_terceros) as $mid) {
            $db->prepare("UPDATE Partido SET id_equipo2 = NULL WHERE id_partido = ?")->execute([$mid]);
        }

        // Asignar greedy evitando que un tercero juegue contra el 1° de su propio grupo
        $usados = [];
        foreach ($r32_slots_de_terceros as $mid => $grupo_del_primero) {
            foreach ($top8 as $i => $t) {
                if (in_array($i, $usados, true)) continue;
                if ($t['id_grupo'] === $grupo_del_primero) continue;
                $db->prepare("UPDATE Partido SET id_equipo2 = ? WHERE id_partido = ?")
                   ->execute([$t['id_equipo'], $mid]);
                $usados[] = $i;
                break;
            }
        }
    }
}

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
                                     AND id_fase = 'F1'
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

            // 3.b) Avanzar 1° y 2° (y mejores 8 terceros si los 12 grupos están listos) a Dieciseisavos
            avanzar_grupos_a_dieciseisavos($db, $r32_slot_map, $r32_slots_de_terceros);

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

// =====================================================
// Guardar resultado de un partido del bracket de eliminación
// Reglas:
//   - Ambos equipos deben estar definidos antes de poder guardar
//   - No se permiten empates (es eliminación directa)
//   - Al guardar, el ganador avanza al slot correspondiente del siguiente partido
// =====================================================
if (isset($_POST['guardar_eliminacion'])) {
    $id_partido = intval($_POST['id_partido']);
    $g1 = intval($_POST['goles_equipo1']);
    $g2 = intval($_POST['goles_equipo2']);

    // Identificar la configuración bracket del partido
    $config = null;
    foreach ($bracket_estructura as $fase => $matches) {
        if (isset($matches[$id_partido])) { $config = $matches[$id_partido]; break; }
    }

    if (!$config) {
        echo "<p class='error'>Partido de eliminación inválido ($id_partido).</p>";
    } elseif ($g1 == $g2) {
        echo "<p class='error'>En la fase de eliminación no se permiten empates. Define un ganador.</p>";
    } else {
        try {
            $db->beginTransaction();

            $cur = $db->prepare("SELECT id_equipo1, id_equipo2 FROM Partido WHERE id_partido = ?");
            $cur->execute([$id_partido]);
            $p = $cur->fetch(PDO::FETCH_ASSOC);

            if (!$p || !$p['id_equipo1'] || !$p['id_equipo2']) {
                $db->rollBack();
                echo "<p class='error'>Este partido todavía no tiene ambos equipos definidos.</p>";
            } else {
                $ganador = ($g1 > $g2) ? $p['id_equipo1'] : $p['id_equipo2'];

                // Guardar el resultado del partido
                $upd = $db->prepare("UPDATE Partido SET goles_equipo1 = ?, goles_equipo2 = ?, estado = 'Finalizado' WHERE id_partido = ?");
                $upd->execute([$g1, $g2, $id_partido]);

                // Avanzar el ganador al siguiente partido (si lo hay)
                if ($config['siguiente'] && $config['slot']) {
                    $col = ($config['slot'] == 1) ? 'id_equipo1' : 'id_equipo2';
                    $adv = $db->prepare("UPDATE Partido SET $col = ? WHERE id_partido = ?");
                    $adv->execute([$ganador, $config['siguiente']]);
                }

                // Recalcular puntos de quinielas para este partido (mismas reglas: 3 por resultado, 6 si exacto)
                $real_signo = ($g1 > $g2) ? 'W1' : 'W2';
                $q_stmt = $db->prepare("SELECT id_quiniela, prediccion_goles1, prediccion_goles2 FROM Quiniela WHERE id_partido = ?");
                $q_stmt->execute([$id_partido]);
                $up_q = $db->prepare("UPDATE Quiniela SET puntos_obtenidos = ? WHERE id_quiniela = ?");
                foreach ($q_stmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
                    $puntos = 0;
                    $pred_signo = ($q['prediccion_goles1'] > $q['prediccion_goles2']) ? 'W1'
                                : (($q['prediccion_goles1'] < $q['prediccion_goles2']) ? 'W2' : 'X');
                    if ($real_signo === $pred_signo) $puntos += 3;
                    if ($g1 == $q['prediccion_goles1'] && $g2 == $q['prediccion_goles2']) $puntos += 3;
                    $up_q->execute([$puntos, $q['id_quiniela']]);
                }

                $db->commit();
                echo "<p class='success'>¡Resultado guardado! El ganador avanza automáticamente a la siguiente ronda.</p>";
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo "<p class='error'>Error al procesar resultado de eliminación: " . $e->getMessage() . "</p>";
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

<?php else:
    // Cargar las filas del bracket desde Partido (incluyen los equipos que ya fueron asignados)
    $bracket_partidos = [];
    try {
        $rsB = $db->query("SELECT id_partido, id_fase, id_equipo1, id_equipo2, goles_equipo1, goles_equipo2, estado
                           FROM Partido
                           WHERE id_partido BETWEEN 8901 AND 9301");
        foreach ($rsB->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $bracket_partidos[(int)$r['id_partido']] = $r;
        }
    } catch (PDOException $e) { /* ignorar */ }

    $rondas = [
        ['titulo' => 'Dieciseisavos',    'ids' => [8901,8902,8903,8904,8905,8906,8907,8908,8909,8910,8911,8912,8913,8914,8915,8916], 'color' => '#0ea5e9'],
        ['titulo' => 'Octavos de Final', 'ids' => [9001,9002,9003,9004,9005,9006,9007,9008], 'color' => '#3b82f6'],
        ['titulo' => 'Cuartos de Final', 'ids' => [9101,9102,9103,9104],                     'color' => '#8b5cf6'],
        ['titulo' => 'Semifinales',      'ids' => [9201,9202],                               'color' => '#ec4899'],
        ['titulo' => 'Final',            'ids' => [9301],                                    'color' => '#f59e0b'],
    ];
?>
    <h3 style="color:#1e3a8a; margin-top:0;">Bracket de Eliminación</h3>
    <p style="color:#475569;">A medida que se guarden los resultados, el ganador de cada partido avanzará automáticamente al slot correspondiente de la siguiente ronda. No se permiten empates en esta fase.</p>

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
                        $g1v = $bp['goles_equipo1'] ?? '';
                        $g2v = $bp['goles_equipo2'] ?? '';
                        $finalizado = isset($bp['estado']) && $bp['estado'] === 'Finalizado';
                        $listo      = $e1 && $e2;
                        $home_label = ($e1 && isset($by_code[$e1])) ? $by_code[$e1]['nombre'] : ($e1 ?: 'Por definir');
                        $away_label = ($e2 && isset($by_code[$e2])) ? $by_code[$e2]['nombre'] : ($e2 ?: 'Por definir');
                        $home_flag  = ($e1 && isset($by_code[$e1])) ? $by_code[$e1]['bandera'] : '';
                        $away_flag  = ($e2 && isset($by_code[$e2])) ? $by_code[$e2]['bandera'] : '';

                        // Determinar el ganador (para resaltarlo si ya está finalizado)
                        $ganador_e1 = $finalizado && intval($g1v) > intval($g2v);
                        $ganador_e2 = $finalizado && intval($g2v) > intval($g1v);
                    ?>
                        <div style="background: <?php echo $finalizado ? '#ecfdf5' : ($listo ? '#ffffff' : '#f8fafc'); ?>;
                                    padding: 10px;
                                    border-radius: 8px;
                                    border: 1px solid <?php echo $finalizado ? '#10b981' : ($listo ? '#cbd5e1' : '#e2e8f0'); ?>;">
                            <div style="font-size:10px; color:#94a3b8; text-align:center; margin-bottom:6px; letter-spacing:0.5px;">Partido #<?php echo $mid; ?></div>
                            <form method="POST" action="?fase=eliminacion">
                                <input type="hidden" name="id_partido" value="<?php echo $mid; ?>">

                                <div style="display:flex; align-items:center; gap:6px; padding:5px 4px; border-radius:4px; background: <?php echo $ganador_e1 ? '#bbf7d0' : 'transparent'; ?>;">
                                    <span style="font-size:16px;"><?php echo $home_flag ?: '🏳️'; ?></span>
                                    <span style="flex:1; font-size:13px; font-weight: <?php echo $ganador_e1 ? 'bold' : 'normal'; ?>; color: <?php echo $e1 ? '#1e293b' : '#94a3b8'; ?>;">
                                        <?php echo htmlspecialchars($home_label); ?>
                                    </span>
                                    <input type="number" name="goles_equipo1" value="<?php echo $g1v; ?>" min="0"
                                           style="width: 42px; text-align: center; padding:3px;"
                                           <?php echo $listo ? '' : 'disabled'; ?>>
                                </div>

                                <div style="display:flex; align-items:center; gap:6px; padding:5px 4px; margin-top:3px; border-radius:4px; background: <?php echo $ganador_e2 ? '#bbf7d0' : 'transparent'; ?>;">
                                    <span style="font-size:16px;"><?php echo $away_flag ?: '🏳️'; ?></span>
                                    <span style="flex:1; font-size:13px; font-weight: <?php echo $ganador_e2 ? 'bold' : 'normal'; ?>; color: <?php echo $e2 ? '#1e293b' : '#94a3b8'; ?>;">
                                        <?php echo htmlspecialchars($away_label); ?>
                                    </span>
                                    <input type="number" name="goles_equipo2" value="<?php echo $g2v; ?>" min="0"
                                           style="width: 42px; text-align: center; padding:3px;"
                                           <?php echo $listo ? '' : 'disabled'; ?>>
                                </div>

                                <button type="submit" name="guardar_eliminacion"
                                        style="width:100%; margin-top:8px; padding:6px 0; font-size:12px; font-weight:bold;
                                               background: <?php echo $listo ? $ronda['color'] : '#cbd5e1'; ?>;
                                               color:#fff; border:none; border-radius:4px;
                                               cursor: <?php echo $listo ? 'pointer' : 'not-allowed'; ?>;"
                                        <?php echo $listo ? '' : 'disabled'; ?>>
                                    <?php echo $finalizado ? 'Actualizar' : 'Guardar'; ?>
                                </button>
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
