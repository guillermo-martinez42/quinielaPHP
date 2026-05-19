<?php
include_once 'conexion.php';

// Aumentar el tiempo de ejecución por si el servidor local es lento
set_time_limit(120);

try {
    // 1. Validar que existan los archivos JSON en la carpeta
    if (!file_exists('worldcup.teams_meta.json') || !file_exists('worldcup.json')) {
        throw new Exception("Faltan los archivos 'worldcup.teams_meta.json' o 'worldcup.json' en el directorio.");
    }

    // 2. Leer y decodificar los archivos
    $teams_json = json_decode(file_get_contents('worldcup.teams_meta.json'), true);
    $matches_json = json_decode(file_get_contents('worldcup.json'), true);

    // Iniciar una transacción para que si algo falla, no se guarde nada a medias
    $db->beginTransaction();

    echo "<h4>Iniciando procesamiento de datos del Mundial 2026...</h4>";

    // ==========================================
    // PASO A: INSERTAR GRUPOS Y EQUIPOS
    // ==========================================
    $stmt_grupo = $db->prepare("INSERT INTO Grupo (id_grupo, nombre_grupo) VALUES (?, ?) ON CONFLICT (id_grupo) DO NOTHING");
    $stmt_equipo = $db->prepare("INSERT INTO Equipo (id_equipo, nombre, id_grupo) VALUES (?, ?, ?) ON CONFLICT (id_equipo) DO NOTHING");

    foreach ($teams_json as $team) {
        $letra_grupo = strtoupper($team['group']); // Captura "A", "B", "C", etc.
        $fifa_code = $team['fifa_code'];          // Código de 3 letras (MEX, GHA, etc.)
        $nombre_pais = $team['name'];             // Nombre del país

        // Insertar el grupo dinámicamente si no existe
        $stmt_grupo->execute([$letra_grupo, "Grupo " . $letra_grupo]);

        // Insertar el equipo mapeado a su grupo
        $stmt_equipo->execute([$fifa_code, $nombre_pais, $letra_grupo]);
    }
    echo "Equipos y Grupos de la A a la L insertados exitosamente.<br>";

    // ==========================================
    // PASO B: INSERTAR LAS FASES DEL TORNEO
    // ==========================================
    $fases = [
        'F1' => 'Fase de Grupos',
        'F2' => 'Dieciseisavos de Final',
        'F3' => 'Octavos de Final',
        'F4' => 'Cuartos de Final',
        'F5' => 'Semifinales',
        'F6' => 'Tercer Lugar',
        'F7' => 'Final'
    ];

    $stmt_fase = $db->prepare("INSERT INTO Fase (id_fase, nombre_fase) VALUES (?, ?) ON CONFLICT (id_fase) DO NOTHING");
    foreach ($fases as $id => $nombre) {
        $stmt_fase->execute([$id, $nombre]);
    }
    echo " Catálogo de Fases (F1 a F7) cargado.<br>";

    // ==========================================
    // PASO C: INSERTAR PARTIDOS Y VALIDAR FASES
    // ==========================================
    $stmt_partido = $db->prepare("
        INSERT INTO Partido (fecha, hora, estado, id_fase, team1, team2, grupo_partido) 
        VALUES (?, ?, 'Pendiente', ?, ?, ?, ?)
    ");

    $partidos = $matches_json['matches'] ?? [];
    $contador_partidos = 0;

    foreach ($partidos as $match) {
        // 1. Mapear el nombre de la fase del JSON al ID de tu base de datos
        $round = $match['round'];
        $id_fase = 'F1'; // Por defecto Fase de Grupos
        
        if ($round === 'Round of 32') $id_fase = 'F2';
        elseif ($round === 'Round of 16') $id_fase = 'F3';
        elseif ($round === 'Quarter-final') $id_fase = 'F4';
        elseif ($round === 'Semi-final') $id_fase = 'F5';
        elseif ($round === 'Match for third place') $id_fase = 'F6';
        elseif ($round === 'Final') $id_fase = 'F7';

        // 2. Extraer los nombres de los contrincantes
        // En fase de grupos vendrá el nombre ("Mexico"), en fases directas vendrá la clave ("2A")
        $t1 = $match['team1'];
        $t2 = $match['team2'];

        // Si es fase de grupos, intentemos cambiar el nombre completo por su código FIFA (opcional pero más ordenado)
        if ($id_fase === 'F1') {
            foreach ($teams_json as $t_meta) {
                if ($t_meta['name'] === $t1) $t1 = $t_meta['fifa_code'];
                if ($t_meta['name'] === $t2) $t2 = $t_meta['fifa_code'];
            }
        }

        // 3. Limpiar Datos de fecha, hora y grupo
        $fecha = $match['date'];
        $hora  = $match['time']; // Almacena el string completo con zona horaria o sepáralo si usas tipo TIME
        $grupo_origen = isset($match['group']) ? str_replace('Group ', '', $match['group']) : null;

        // 4. Ejecutar la inserción del partido
        $stmt_partido->execute([$fecha, $hora, $id_fase, $t1, $t2, $grupo_origen]);
        $contador_partidos++;
    }

    // Si todo salió bien, guardamos definitivamente en la base de datos
    $db->commit();
    echo " Se han registrado exitosamente <strong>$contador_partidos</strong> partidos en el calendario.<br>";
    echo "<h3 style='color:green;'>¡Estructura e información cargadas con éxito! Ya puedes ir a tu Calendario.</h3>";

} catch (Exception $e) {
    // Si algo falla, revertimos todo para evitar datos duplicados o corruptos
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<h3 style='color:red;'>Error al procesar los archivos JSON: " . $e->getMessage() . "</h3>";
}
?>