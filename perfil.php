<?php
// perfil.php - Gestión del Perfil del Cliente
session_start();
include 'includes/db_conexion.php';

// Array de los 32 estados de México
$estados_mexico = [
    "Aguascalientes", "Baja California", "Baja California Sur", "Campeche", "Chiapas", "Chihuahua",
    "Ciudad de México", "Coahuila", "Colima", "Durango", "Guanajuato", "Guerrero", "Hidalgo", 
    "Jalisco", "México", "Michoacán", "Morelos", "Nayarit", "Nuevo León", "Oaxaca", 
    "Puebla", "Querétaro", "Quintana Roo", "San Luis Potosí", "Sinaloa", "Sonora", 
    "Tabasco", "Tamaulipas", "Tlaxcala", "Veracruz", "Yucatán", "Zacatecas"
];

// 1. VERIFICACIÓN DE SESIÓN
if (!isset($_SESSION['id_cliente'])) {
    header("Location: registro.php");
    exit();
}

$id_cliente = $_SESSION['id_cliente'];
$mensaje = '';

// --- LÓGICA DE PROCESAMIENTO DE FORMS ---

// 2. PROCESAR ACTUALIZACIÓN DE DATOS PERSONALES Y DIRECCIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_perfil') {
    
    // Capturar y sanear los campos de Nombre, Apellidos y Dirección
    $nombre = $conn->real_escape_string($_POST['nombre'] ?? '');
    $apellidos = $conn->real_escape_string($_POST['apellidos'] ?? ''); 
    $telefono = $conn->real_escape_string($_POST['telefono'] ?? '');
    $direccion = $conn->real_escape_string($_POST['direccion'] ?? '');
    
    // El valor del Estado (Viene del selector de Estado)
    $estado_seleccionado = $conn->real_escape_string($_POST['estado_republica'] ?? ''); 
    
    // El valor del Municipio (Viene del input de texto)
    $municipio_ingresado = $conn->real_escape_string($_POST['municipio_text'] ?? ''); 
    
    // LÓGICA UNIFICADA DE CIUDAD: Concatenar Municipio (si es necesario) y Estado
    $ciudad_a_guardar = $estado_seleccionado;
    if (!empty($municipio_ingresado)) { // Ya no restringimos a 'México', guardamos si existe texto
        $ciudad_a_guardar = $municipio_ingresado . ' (' . $estado_seleccionado . ')';
    }

    $codigo_postal = $conn->real_escape_string($_POST['codigo_postal'] ?? '');
    $email_placeholder = $conn->real_escape_string($_POST['email_placeholder'] ?? ''); 
    
    // Sentencia UPDATE preparada: Se asume que el email en la DB es el valor del placeholder si no hay proceso de cambio
    $sql_update = "UPDATE Clientes SET 
                   nombre = ?, apellidos = ?, email = ?, telefono = ?, 
                   direccion = ?, ciudad = ?, codigo_postal = ? 
                   WHERE id_cliente = ?";
                   
    $stmt = $conn->prepare($sql_update);
    // Bind: s (nombre), s (apellidos), s (email_placeholder), s (telefono), s (direccion), s (ciudad_a_guardar), s (cp), i (id)
    $stmt->bind_param("sssssssi", $nombre, $apellidos, $email_placeholder, $telefono, $direccion, $ciudad_a_guardar, $codigo_postal, $id_cliente);

    if ($stmt->execute()) {
        $_SESSION['nombre_usuario'] = $nombre . ' ' . $apellidos;
        $mensaje = "✔️ Datos actualizados con éxito.";
        header("Location: perfil.php?msg=" . urlencode($mensaje));
        exit();
    } else {
        $mensaje = "Error al actualizar datos: " . $conn->error;
    }
}

// 4. PROCESAR SOLICITUD DE CÓDIGO PARA CAMBIO DE EMAIL (Simulación) - Mantenida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'solicitar_cambio_email') {
    $nuevo_email = $conn->real_escape_string($_POST['nuevo_email'] ?? '');
    $email_actual = $conn->real_escape_string($_POST['email_actual'] ?? '');
    
    if (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "<p class='error'>Ingrese un nuevo email válido.</p>";
    } else {
        $code = rand(1000, 9999);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $update_sql = "UPDATE Clientes SET recovery_code = '$code', recovery_expiry = '$expiry' WHERE id_cliente = $id_cliente";
        $conn->query($update_sql);
        
        $_SESSION['new_email_temp'] = $nuevo_email;
        $mensaje = "<p class='exito'>Código de verificación ($code) enviado a tu email actual ($email_actual). Ingrésalo para confirmar el cambio.</p>";
    }
}

// 5. PROCESAR VERIFICACIÓN DE CÓDIGO Y CAMBIO DE EMAIL FINAL (Mantenida)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'confirmar_cambio_email') {
    $codigo_ingresado = $conn->real_escape_string($_POST['codigo_email'] ?? '');
    $nuevo_email = $_SESSION['new_email_temp'] ?? null;

    $sql = "SELECT recovery_code, recovery_expiry FROM Clientes WHERE id_cliente = $id_cliente";
    $result = $conn->query($sql);
    $cliente = $result->fetch_assoc();

    if ($cliente['recovery_code'] === $codigo_ingresado && time() < strtotime($cliente['recovery_expiry'])) {
        $update_sql = "UPDATE Clientes SET email = ?, recovery_code = NULL, recovery_expiry = NULL WHERE id_cliente = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $nuevo_email, $id_cliente);
        
        if ($stmt->execute()) {
            $mensaje = "<p class='exito'>✔️ Correo electrónico actualizado con éxito a $nuevo_email.</p>";
            unset($_SESSION['new_email_temp']);
            header("Location: perfil.php?msg=" . urlencode($mensaje));
            exit();
        } else {
            $mensaje = "<p class='error'>Error al actualizar el correo: " . $conn->error . "</p>";
        }
    } else {
        $mensaje = "<p class='error'>El código de verificación es inválido o ha expirado.</p>";
    }
}


// 6. OBTENER DATOS ACTUALES DEL CLIENTE (READ)
$sql_select = "SELECT nombre, apellidos, email, telefono, direccion, ciudad, codigo_postal FROM Clientes WHERE id_cliente = $id_cliente";
$result = $conn->query($sql_select);
$cliente_data = $result->fetch_assoc();

// Extracción de datos para prellenar formularios
$estado_actual_db = $cliente_data['ciudad'];
$municipio_actual = ''; 
$estado_display = $estado_actual_db; 

// Lógica para preseleccionar el ESTADO y extraer el MUNICIPIO guardado
if (strpos($estado_actual_db, ' (') !== false) {
    list($municipio_actual, $estado_parentesis) = explode(' (', $estado_actual_db, 2);
    $estado_display = trim(str_replace(')', '', $estado_parentesis));
}

if (isset($_GET['msg'])) {
    $mensaje = htmlspecialchars($_GET['msg']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil | TechConnect</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-container { max-width: 700px; margin: 40px auto; padding: 30px; background: #fff; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .profile-container h2 { color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .profile-form label { display: block; margin-top: 15px; font-weight: bold; }
        .profile-form input[type="text"], .profile-form input[type="email"], .profile-form select { width: 95%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .profile-form button { background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; font-size: 1em; }
        .message-success { color: green; font-weight: bold; }
        .message-error { color: red; font-weight: bold; }
        .nav-user { color: #ffd700; margin-left: 15px; }
        #municipio-div { margin-top: 15px; }
        .email-update-section { border: 1px solid #007bff; padding: 15px; margin-top: 20px; border-radius: 5px; }
        .email-update-section h4 { margin-top: 0; }
        .btn-history { background-color: #007bff; color: white; padding: 10px 20px; border-radius: 5px; display: block; text-align: center; margin-top: 15px; text-decoration: none; }
    </style>
</head>
<body>
    <header>
        <h1>TechConnect: Tienda Electrónica</h1>
        <nav>
            <a href="index.php">Inicio</a>
            <a href="pages/catalogo.php">Catálogo</a>
            <a href="pages/carrito.php">🛒 Carrito</a>
            <a href="perfil.php">Mi Perfil</a>
            <a href="historial_pedidos.php">Historial</a>
            <a href="logout.php">Cerrar Sesión</a>
        </nav>
    </header>

    <main>
        <div class="profile-container">
            <h2>Mi Perfil y Datos Personales</h2>
            <?php if (isset($_GET['msg']) && strpos($_GET['msg'], '✔️') !== false): ?>
                <p class="message-success"><?php echo htmlspecialchars($_GET['msg']); ?></p>
            <?php elseif (isset($mensaje) && strpos($mensaje, 'Error') !== false): ?>
                <p class="message-error"><?php echo $mensaje; ?></p>
            <?php endif; ?>

            <a href="historial_pedidos.php" class="btn-history">
                📦 Ver Historial de Compras
            </a>

            <form method="POST" class="profile-form">
                <input type="hidden" name="accion" value="actualizar_perfil">

                <h3>Datos de Contacto</h3>
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($cliente_data['nombre']); ?>" required>

                <label for="apellidos">Apellidos:</label>
                <input type="text" id="apellidos" name="apellidos" value="<?php echo htmlspecialchars($cliente_data['apellidos'] ?? ''); ?>" required> 

                <label for="telefono">Teléfono:</label>
                <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($cliente_data['telefono'] ?? ''); ?>" placeholder="Ej: 5512345678">

                <hr style="margin: 30px 0;">

                <h3>Dirección de Envío</h3>
                <label for="direccion">Calle y Número:</label>
                <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($cliente_data['direccion'] ?? ''); ?>" placeholder="Calle, Número y Colonia" required>

                <label for="ciudad">Estado de la República:</label>
                <select id="ciudad" name="estado_republica" onchange="checkState(this.value)" required>
                    <option value="">Seleccione un Estado</option>
                    <?php foreach ($estados_mexico as $estado): ?>
                        <option value="<?php echo htmlspecialchars($estado); ?>" <?php echo ($estado_display === $estado) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($estado); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <div id="municipio-div" style="display: none;">
                    <label for="municipio-input">Municipio/Alcaldía:</label>
                    <input type="text" id="municipio-input" name="municipio_text" 
                           value="<?php echo htmlspecialchars($municipio_actual); ?>" placeholder="Escriba el municipio/alcaldía">
                </div>
                
                <label for="codigo_postal">Código Postal:</label>
                <input type="text" id="codigo_postal" name="codigo_postal" value="<?php echo htmlspecialchars($cliente_data['codigo_postal'] ?? ''); ?>" required>

                <button type="submit">Guardar Cambios</button>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <div class="email-update-section">
                <h3>Cambiar Correo Electrónico (Verificación Requerida)</h3>
                
                <p><strong>Email Actual:</strong> <?php echo htmlspecialchars($cliente_data['email']); ?></p>

                <?php if (isset($_SESSION['new_email_temp'])): ?>
                    <form method="POST">
                        <input type="hidden" name="accion" value="confirmar_cambio_email">
                        <label for="codigo_email">Código de Verificación (4 dígitos):</label>
                        <input type="text" id="codigo_email" name="codigo_email" maxlength="4" required>
                        <button type="submit" style="background-color: #ffc107; color: #333;">Confirmar Cambio</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="accion" value="solicitar_cambio_email">
                        <input type="hidden" name="email_actual" value="<?php echo htmlspecialchars($cliente_data['email']); ?>">

                        <label for="nuevo_email">Nuevo Correo Electrónico:</label>
                        <input type="email" id="nuevo_email" name="nuevo_email" required>
                        <button type="submit" style="background-color: #007bff;">Enviar Código al Correo Actual</button>
                        <small style="display: block; margin-top: 5px;">Se enviará un código al correo **<?php echo htmlspecialchars($cliente_data['email']); ?>** para verificar que eres tú.</small>
                    </form>
                <?php endif; ?>
            </div>
            </div>
    </main>
    
    <footer>
        <p>&copy; 2025 TechConnect: Tienda Electrónica. Perfil.</p>
    </footer>
    
    <script>
    // Función que se ejecuta al cambiar el Estado y al cargar la página
    function checkState(selectedState) {
        const municipioDiv = document.getElementById('municipio-div');
        const municipioInput = document.getElementById('municipio-input');
        
        // Muestra el input de municipio solo si el estado es "México" o "Ciudad de México"
        if (selectedState === 'México' || selectedState === 'Ciudad de México') {
            municipioDiv.style.display = 'block';
            municipioInput.setAttribute('required', 'required');
        } else {
            municipioDiv.style.display = 'none';
            municipioInput.removeAttribute('required');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Ejecutar al inicio para manejar la selección previa de la DB
        const initialCitySelect = document.getElementById('ciudad');
        checkState(initialCitySelect.value); 
        
        // Lógica de validación de formulario principal (para dirección y datos)
        const form = document.querySelector('.profile-form');
        form.addEventListener('submit', function(e) {
            const estado = document.getElementById('ciudad').value;
            const municipioInput = document.getElementById('municipio-input');
            
            // Si el estado es México/CDMX y el campo está visible pero vacío, forzar validación.
            if ((estado === 'México' || estado === 'Ciudad de México') && municipioInput.style.display === 'block' && municipioInput.value.trim() === '') {
                e.preventDefault();
                alert('Por favor, ingresa el Municipio o Alcaldía.');
                municipioInput.focus();
            }
        });
    });
    </script>
</body>
</html>