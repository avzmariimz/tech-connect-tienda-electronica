<?php
// registro.php
include 'includes/db_conexion.php'; 
session_start(); 

$mensaje = "";

// Array de los 32 estados de México (Necesario para el registro)
$estados_mexico = [
    "Aguascalientes", "Baja California", "Baja California Sur", "Campeche", "Chiapas", "Chihuahua",
    "Ciudad de México", "Coahuila", "Colima", "Durango", "Guanajuato", "Guerrero", "Hidalgo", 
    "Jalisco", "México", "Michoacán", "Morelos", "Nayarit", "Nuevo León", "Oaxaca", 
    "Puebla", "Querétaro", "Quintana Roo", "San Luis Potosí", "Sinaloa", "Sonora", 
    "Tabasco", "Tamaulipas", "Tlaxcala", "Veracruz", "Yucatán", "Zacatecas"
];

// Lógica de recuperación de contraseña (se asume correcta)
if (isset($_GET['msg'])) {
    $mensaje = htmlspecialchars($_GET['msg']);
}

// --- LÓGICA PARA INICIO DE SESIÓN (CORREGIDA PARA APELLIDOS) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'login') {
    $email = $conn->real_escape_string($_POST['login_email']);
    $password = $_POST['login_password'];

    // Seleccionar 'apellidos' también
    $sql = "SELECT id_cliente, nombre, apellidos, password_hash FROM Clientes WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['id_cliente'] = $row['id_cliente'];
            // Guardar nombre y apellidos en sesión
            $_SESSION['nombre_usuario'] = trim($row['nombre'] . ' ' . $row['apellidos']); 
            header("Location: index.php"); 
            exit();
        } else {
            $mensaje = "<p class='error'>Contraseña incorrecta.</p>";
        }
    } else {
        $mensaje = "<p class='error'>Usuario no encontrado con ese email.</p>";
    }
}

// --- LÓGICA PARA REGISTRO DE NUEVO USUARIO (FINALIZADA) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'registro') {
    // CAPTURA DE TODOS LOS 8 CAMPOS
    $nombre = $conn->real_escape_string($_POST['reg_nombre'] ?? '');
    $apellidos = $conn->real_escape_string($_POST['reg_apellidos'] ?? ''); 
    $email = $conn->real_escape_string($_POST['reg_email'] ?? '');
    $password = $_POST['reg_password'] ?? '';
    $telefono = $conn->real_escape_string($_POST['reg_telefono'] ?? '');
    $direccion = $conn->real_escape_string($_POST['reg_direccion'] ?? '');
    $ciudad = $conn->real_escape_string($_POST['reg_ciudad'] ?? ''); 
    $codigo_postal = $conn->real_escape_string($_POST['reg_codigo_postal'] ?? ''); 

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // SENTENCIA INSERT CORREGIDA CON LOS 8 CAMPOS NECESARIOS
    $sql = "INSERT INTO Clientes (nombre, apellidos, email, telefono, password_hash, direccion, ciudad, codigo_postal) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", 
        $nombre, $apellidos, $email, $telefono, $password_hash, $direccion, $ciudad, $codigo_postal
    );

    if ($stmt->execute()) {
        $mensaje = "<p class='exito'>✔️ ¡Registro exitoso! Ya puedes iniciar sesión.</p>";
    } else {
        if ($conn->errno == 1062) {
             $mensaje = "<p class='error'>El email ya está registrado. Intenta iniciar sesión.</p>";
        } else {
            $mensaje = "<p class='error'>Error al registrar: " . $conn->error . "</p>";
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>TechConnect: Acceso de Clientes</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .contenedor-formulario { display: flex; justify-content: space-around; max-width: 800px; margin: 30px auto; gap: 20px; }
        .formulario-card { background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); flex: 1; }
        .formulario-card h2 { color: #007bff; margin-top: 0; }
        .formulario-card input[type="text"], .formulario-card input[type="email"],
        .formulario-card input[type="password"], .formulario-card textarea, .formulario-card select { 
            width: 95%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .formulario-card button { background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%; }
        .error { color: red; } .exito { color: green; }
        .nav-user { color: #ffd700; margin-left: 15px; } 
    </style>
</head>
<body>
    <header>
        <h1>TechConnect: Tienda Electrónica</h1>
        <nav>
            <a href="index.php">Inicio</a>
            <a href="pages/catalogo.php">Catálogo</a>
            <a href="pages/carrito.php">🛒 Carrito</a>
            <?php if (isset($_SESSION['id_cliente'])): ?>
                <a href="perfil.php" class="nav-user">Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></a>
                <a href="logout.php">Cerrar Sesión</a>
            <?php else: ?>
                <a href="registro.php">Iniciar Sesión / Registrarse</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <h2>Acceso a Clientes</h2>
        <?php echo $mensaje; ?>

        <div class="contenedor-formulario">
            
            <div class="formulario-card">
                <h2>Iniciar Sesión</h2>
                <form method="POST" action="registro.php">
                    <input type="hidden" name="accion" value="login">
                    <label for="login_email">Email:</label>
                    <input type="email" id="login_email" name="login_email" required>
                    <label for="login_password">Contraseña:</label>
                    <input type="password" id="login_password" name="login_password" required>
                    <button type="submit">Entrar</button>
                    
                    <p style="margin-top: 10px; text-align: right;">
                        <a href="olvido_password.php">¿Olvidaste tu Contraseña?</a>
                    </p>
                    
                </form>
            </div>

            <div class="formulario-card">
                <h2>Crear Cuenta</h2>
                <form method="POST" action="registro.php">
                    <input type="hidden" name="accion" value="registro">
                    
                    <label for="reg_nombre">Nombre:</label>
                    <input type="text" id="reg_nombre" name="reg_nombre" required>
                    
                    <label for="reg_apellidos">Apellidos:</label>
                    <input type="text" id="reg_apellidos" name="reg_apellidos" required>
                    
                    <label for="reg_email">Email:</label>
                    <input type="email" id="reg_email" name="reg_email" required>
                    
                    <label for="reg_password">Contraseña (mín. 6):</label>
                    <input type="password" id="reg_password" name="reg_password" required minlength="6">
                    
                    <label for="reg_telefono">Teléfono:</label>
                    <input type="text" id="reg_telefono" name="reg_telefono" placeholder="Ej: 5512345678">
                    
                    <hr>
                    
                    <label for="reg_direccion">Calle y Número:</label>
                    <input type="text" id="reg_direccion" name="reg_direccion" placeholder="Calle, Número y Colonia" required>
                    
                    <label for="reg_ciudad">Estado:</label>
                    <select id="reg_ciudad" name="reg_ciudad" required>
                        <option value="">Seleccione Estado</option>
                        <?php foreach ($estados_mexico as $estado): ?>
                            <option value="<?php echo htmlspecialchars($estado); ?>"><?php echo htmlspecialchars($estado); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="reg_codigo_postal">Código Postal:</label>
                    <input type="text" id="reg_codigo_postal" name="reg_codigo_postal" required>
                    
                    <button type="submit">Registrarme</button>
                </form>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 TechConnect: Tienda Electrónica. Todos los derechos reservados.</p>
    </footer>
</body>
</html>