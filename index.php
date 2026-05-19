<?php 
include_once 'header.php';

// Registrar Usuario
if (isset($_POST['registrar'])) {
    $username = $_POST['username'];
    $nombre = $_POST['nombre'];
    $pass_hash = password_hash($_POST['pass'], PASSWORD_BCRYPT);

    try {
        $stmt = $db->prepare("INSERT INTO Usuario (Username, nombre, pass) VALUES (?, ?, ?)");
        $stmt->execute([$username, $nombre, $pass_hash]);
        echo "<p class='success'>¡Registro exitoso! Ya puedes iniciar sesión.</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>El nombre de usuario ya se encuentra registrado.</p>";
    }
}

// Iniciar Sesión
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $pass = $_POST['pass'];

    $stmt = $db->prepare("SELECT * FROM Usuario WHERE Username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pass, $user['pass'])) {
        $_SESSION['id_usuario'] = $user['id_usuario'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['es_admin'] = $user['es_admin'];
        header("Location: index.php");
        exit();
    } else {
        echo "<p class='error'>Credenciales incorrectas, por favor intenta de nuevo.</p>";
    }
}
?>

<div style="text-align: center; margin-bottom: 30px;">
    <h1>Sistema de Quinielas - Copa Mundial 2026</h1>
    <p>Cumpliendo con los requerimientos técnicos de Ciencias de la Computación V</p>
</div>

<?php if(!isset($_SESSION['id_usuario'])): ?>
<div class="auth-flex">
    <div class="auth-form">
        <h2>Ingresar al Sistema</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Nombre de Usuario" required>
            <input type="password" name="pass" placeholder="Contraseña" required>
            <button type="submit" name="login">Iniciar Sesión</button>
        </form>
    </div>

    <div class="auth-form">
        <h2>Crear Cuenta Nueva</h2>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre Completo" required>
            <input type="text" name="username" placeholder="Elige un Username" required>
            <input type="password" name="pass" placeholder="Contraseña Segura" required>
            <button type="submit" name="registrar" class="btn-alt">Registrarse</button>
        </form>
    </div>
</div>
<?php else: ?>
    <div style="background: #eff6ff; padding: 20px; border-radius: 6px; text-align: center;">
        <h2>¡Hola de nuevo, <?php echo $_SESSION['username']; ?>!</h2>
        <p>Has ingresado correctamente al entorno. Selecciona una acción en el menú de navegación superior.</p>
    </div>
<?php endif; ?>

<?php include_once 'footer.php'; ?>