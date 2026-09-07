<?php
// olvido_password.php - Flujo de recuperación de contraseña
session_start();
include 'includes/db_conexion.php';

$mensaje = '';
$etapa = 1; // 1: Identificación, 2: Código, 3: Restablecer
// Variable para almacenar el medio de identificación (Email o Teléfono)
$identificador_recuperacion = ''; 

// --- LÓGICA DE MANEJO DE ETAPAS ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ====================================================================
    // ETAPA 1: Identificación del Usuario (Solicitud de Código)
    // ====================================================================
    if (isset($_POST['accion']) && $_POST['accion'] === 'solicitar_codigo') {
        $tipo_identificador = $_POST['tipo_identificador'] ?? '';
        $identificador = $conn->real_escape_string($_POST['identificador'] ?? '');

        if (empty($identificador)) {
            $mensaje = "<p class='error'>Debe ingresar su email o número de teléfono.</p>";
            $etapa = 1;
        } else {
            // Determinar si buscamos por email o teléfono
            $campo_busqueda = ($tipo_identificador === 'email') ? 'email' : 'telefono';
            $sql = "SELECT id_cliente, email, telefono FROM Clientes WHERE $campo_busqueda = '$identificador'";
            $result = $conn->query($sql);

            if ($result && $result->num_rows === 1) {
                $cliente = $result->fetch_assoc();
                $id_cliente = $cliente['id_cliente'];
                
                // Formatear el medio de confirmación para el mensaje
                if ($tipo_identificador === 'telefono') {
                    $telefono_visible = substr($cliente['telefono'] ?? '**********', -4);
                    $medio_confirmacion = "SMS/WhatsApp (terminación $telefono_visible)";
                } else {
                    $medio_confirmacion = "Email ($cliente[email])";
                }
                
                // 1. Generar código de 6 dígitos y tiempo de expiración (5 minutos)
                $code = rand(100000, 999999);
                $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                // 2. Guardar el código y expiración en la DB
                $update_sql = "UPDATE Clientes SET recovery_code = '$code', recovery_expiry = '$expiry' WHERE id_cliente = $id_cliente";
                $conn->query($update_sql);

                // 3. Guardar variables de sesión para la Etapa 2
                $_SESSION['recovery_id'] = $id_cliente;
                $_SESSION['recovery_identifier'] = $identificador;
                $_SESSION['recovery_medium'] = $medio_confirmacion;
                $etapa = 2;
                $mensaje = "<p class='exito'>Código de seguridad generado ($code) y enviado a tu $medio_confirmacion. Expira en 5 minutos.</p>";

            } else {
                $mensaje = "<p class='error'>Identificador no encontrado en nuestro sistema.</p>";
                $etapa = 1;
            }
        }
    }
    
    // ====================================================================
    // ETAPA 2: Verificación del Código (Ingreso del OTP)
    // ====================================================================
    elseif (isset($_POST['accion']) && $_POST['accion'] === 'verificar_codigo' && isset($_SESSION['recovery_id'])) {
        $id_cliente = $_SESSION['recovery_id'];
        $codigo_ingresado = $conn->real_escape_string($_POST['codigo']);
        
        $sql = "SELECT recovery_code, recovery_expiry FROM Clientes WHERE id_cliente = $id_cliente";
        $result = $conn->query($sql);
        $cliente = $result->fetch_assoc();
        
        // Comprobar si el código es correcto Y si NO ha expirado
        if ($cliente['recovery_code'] === $codigo_ingresado && time() < strtotime($cliente['recovery_expiry'])) {
            $etapa = 3;
            $mensaje = "<p class='exito'>Código verificado. Ya puedes establecer una nueva contraseña.</p>";
            
        } else {
            $mensaje = "<p class='error'>Código de seguridad inválido o expirado. Vuelve a solicitar uno.</p>";
            unset($_SESSION['recovery_id']);
            unset($_SESSION['recovery_identifier']);
            unset($_SESSION['recovery_medium']);
            $etapa = 1; 
        }
    }
    
    // ====================================================================
    // ETAPA 3: Restablecer Contraseña (UPDATE)
    // ====================================================================
    elseif (isset($_POST['accion']) && $_POST['accion'] === 'restablecer' && isset($_SESSION['recovery_id'])) {
        $id_cliente = $_SESSION['recovery_id'];
        $nueva_password = $_POST['nueva_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($nueva_password !== $confirm_password || strlen($nueva_password) < 6) {
            $mensaje = "<p class='error'>Las contraseñas no coinciden o son demasiado cortas (mínimo 6 caracteres).</p>";
            $etapa = 3;
        } else {
            $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE Clientes SET password_hash = ?, recovery_code = NULL, recovery_expiry = NULL WHERE id_cliente = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $password_hash, $id_cliente);
            
            if ($stmt->execute()) {
                $mensaje = "<p class='exito'>¡Contraseña restablecida con éxito! Ya puedes iniciar sesión.</p>";
                unset($_SESSION['recovery_id']);
                unset($_SESSION['recovery_identifier']);
                unset($_SESSION['recovery_medium']);
                header("Location: registro.php?msg=" . urlencode($mensaje));
                exit();
            } else {
                $mensaje = "<p class='error'>Error al actualizar la contraseña.</p>";
                $etapa = 3;
            }
        }
    }
} else {
    // Si la página carga sin POST, pero la sesión de recuperación está activa, mantener la etapa 2.
    if (isset($_SESSION['recovery_id'])) {
        $etapa = 2;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Contraseña | TechConnect</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-container { max-width: 450px; margin: 50px auto; padding: 25px; background: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-container h2 { color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .form-container input[type="email"], .form-container input[type="text"], .form-container input[type="password"] { width: 95%; padding: 10px; margin-top: 5px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; }
        .form-container button { background-color: #ffc107; color: #333; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        .error { color: #dc3545; font-weight: bold; } .exito { color: green; font-weight: bold; }
        .radio-group label { display: inline-block; margin-right: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <h1>TechConnect: Tienda Electrónica</h1>
        <nav>
            <a href="index.php">Inicio</a>
            <a href="registro.php">Iniciar Sesión</a>
        </nav>
    </header>

    <main>
        <div class="form-container">
            <h2>Recuperar Contraseña</h2>
            <?php echo $mensaje; ?>

            <?php if ($etapa === 1): ?>
                <p>Elige tu método de recuperación e ingresa el dato asociado a tu cuenta.</p>
                <form method="POST">
                    <input type="hidden" name="accion" value="solicitar_codigo">
                    
                    <div class="radio-group" style="margin-bottom: 15px;">
                        <input type="radio" id="by_email" name="tipo_identificador" value="email" checked>
                        <label for="by_email">Correo Electrónico</label>
                        <input type="radio" id="by_phone" name="tipo_identificador" value="telefono">
                        <label for="by_phone">Teléfono / WhatsApp</label>
                    </div>

                    <label for="identificador">Email o Teléfono:</label>
                    <input type="text" id="identificador" name="identificador" required placeholder="ej. correo@ejemplo.com o 5512345678">
                    
                    <button type="submit">Enviar Código</button>
                    <p style="margin-top: 15px; text-align: center;"><a href="registro.php">Volver al Login</a></p>
                </form>

            <?php elseif ($etapa === 2): ?>
                <p>Ingresa el código de 6 dígitos que enviamos a: <strong style="color: #007bff;"><?php echo htmlspecialchars($_SESSION['recovery_medium']); ?></strong></p>
                <form method="POST">
                    <input type="hidden" name="accion" value="verificar_codigo">
                    <label for="codigo">Código de Seguridad:</label>
                    <input type="text" id="codigo" name="codigo" required maxlength="6">
                    <button type="submit">Verificar Código</button>
                    <p style="margin-top: 10px; text-align: center;"><a href="olvido_password.php">Reenviar / Cambiar método</a></p>
                </form>

            <?php elseif ($etapa === 3): ?>
                <p>Establece tu nueva contraseña segura.</p>
                <form method="POST">
                    <input type="hidden" name="accion" value="restablecer">
                    <label for="nueva_password">Nueva Contraseña:</label>
                    <input type="password" id="nueva_password" name="nueva_password" required minlength="6">
                    <label for="confirm_password">Confirmar Contraseña:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    <button type="submit">Cambiar Contraseña</button>
                </form>

            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 TechConnect: Tienda Electrónica. Recuperación de Acceso.</p>
    </footer>
</body>
</html>