<?php 
include_once 'header.php';

// Validar que sea Administrador
if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] == false) {
    die("<p class='error'>Acceso denegado. Se requieren permisos de administrador.</p>");
}

// Guardar nuevo Equipo
if (isset($_POST['guardar_equipo'])) {
    $id_equipo = strtoupper($_POST['id_equipo']);
    $nombre = $_POST['nombre'];
    $pais = $_POST['pais'];
    $id_grupo = strtoupper($_POST['id_grupo']);
    
    // Tratamiento del archivo binario de la imagen (Bandera)
    $bandera_contenido = null;
    if (!empty($_FILES['bandera']['tmp_name'])) {
        $bandera_contenido = file_get_contents($_FILES['bandera']['tmp_name']);
    }

    try {
        // Asegurarse de que el grupo exista primero
        $chkGroup = $db->prepare("INSERT INTO Grupo (id_grupo, nombre_grupo) VALUES (?, ?) ON CONFLICT DO NOTHING");
        $chkGroup->execute([$id_grupo, "Grupo " . $id_grupo]);

        $stmt = $db->prepare("INSERT INTO Equipo (id_equipo, nombre, pais, id_grupo, bandera, puntos_obtenidos, goles_dif) VALUES (?, ?, ?, ?, ?, 0, 0)");
        
        // Atar los parámetros incluyendo el binario LOB de Postgres
        $stmt->bindParam(1, $id_equipo);
        $stmt->bindParam(2, $nombre);
        $stmt->bindParam(3, $pais);
        $stmt->bindParam(4, $id_grupo);
        $stmt->bindParam(5, $bandera_contenido, PDO::PARAM_LOB);
        
        $stmt->execute();
        echo "<p class='success'>¡Equipo guardado con éxito!</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>Error al guardar equipo: " . $e->getMessage() . "</p>";
    }
}

// Obtener la lista de equipos registrados
$equipos = $db->query("SELECT * FROM Equipo ORDER BY id_grupo, nombre")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Administración de Equipos Participantes (CRUD)</h2>

<div class="auth-flex">
    <div class="auth-form" style="max-width: 400px;">
        <h3>Registrar Selección</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="id_equipo" placeholder="Código (ej: GUA, ARG, BRA)" maxlength="5" required>
            <input type="text" name="nombre" placeholder="Nombre de la Selección" required>
            <input type="text" name="pais" placeholder="País" required>
            <input type="text" name="id_grupo" placeholder="Letra del Grupo (ej: A, B, C)" maxlength="1" required>
            <label style="font-size: 13px; color: #666;">Bandera (Puntos Extra - Guarda en BD):</label>
            <input type="file" name="bandera" accept="image/*">
            <button type="submit" name="guardar_equipo">Guardar Equipo</button>
        </form>
    </div>

    <div style="flex: 2;">
        <h3>Equipos Guardados</h3>
        <table border="1" cellpadding="10" style="width:100%; border-collapse: collapse; text-align: left;">
            <tr style="background: #f1f5f9;">
                <th>Código</th>
                <th>Selección</th>
                <th>Grupo</th>
                <th>Bandera</th>
            </tr>
            <?php foreach($equipos as $eq): ?>
            <tr>
                <td><strong><?php echo $eq['id_equipo']; ?></strong></td>
                <td><?php echo $eq['nombre']; ?></td>
                <td>Grupo <?php echo $eq['id_grupo']; ?></td>
                <td>
                    <?php if ($eq['bandera']): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode(stream_get_contents($eq['bandera'])); ?>" width="40" alt="Bandera">
                    <?php else: ?>
                        ❌ Sin imagen
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<?php include_once 'footer.php'; ?>