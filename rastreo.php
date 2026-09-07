<?php
// rastreo.php - Seguimiento y Estatus del Pedido
session_start();
include 'includes/db_conexion.php'; 

// 1. VERIFICACIÓN INICIAL Y PREPARACIÓN
$id_pedido = (int)($_GET['id_pedido'] ?? 0);
$id_cliente = $_SESSION['id_cliente'] ?? 0;
$pedido_info = null;
$mensaje = '';
$status_entrega = ''; // Variable para la estimación de entrega

// Redirigir si no hay cliente logueado o ID de pedido
if ($id_cliente === 0 || $id_pedido === 0) {
    header("Location: registro.php"); 
    exit();
}

if ($id_pedido > 0) {
    // CONSULTA: Obtiene el estado actual y el dato de pago adicional (para el motivo de cancelación)
    $sql_rastreo = "SELECT id_pedido, fecha_pedido, total_pedido, estado, metodo_pago, datos_pago_adicionales 
                    FROM Pedidos 
                    WHERE id_pedido = $id_pedido AND id_cliente = $id_cliente";
    
    $result = $conn->query($sql_rastreo);
    
    if ($result && $result->num_rows == 1) {
        $pedido_info = $result->fetch_assoc();
        $current_state = $pedido_info['estado'];
        
        // --- CÁLCULO DE FECHA ESTIMADA DE ENTREGA (SIMULACIÓN) ---
        $fecha_compra = new DateTime($pedido_info['fecha_pedido']);
        
        if ($current_state === 'Entregado' || $current_state === 'Cancelado') {
             $status_entrega = ($current_state === 'Entregado') ? "¡ENTREGADO!" : "CANCELADO";
        } else {
             // Simulación: Entrega en 5 días hábiles
             // Nota: Necesitas PHP 5.3+ para DateTime y modify('+X weekday')
             $fecha_estimada = (clone $fecha_compra)->modify('+5 weekday')->format('d/m/Y');
             $status_entrega = "Llega Aprox. el $fecha_estimada";
        }
        
        // --- SIMULACIÓN DE LA LÍNEA DE TIEMPO Y DESCRIPCIONES ---
        $timeline_states = [
            'Pendiente' => 'Pago pendiente: Esperando confirmación de transferencia/efectivo.',
            'Procesando' => 'PAGO CONFIRMADO: Pedido en preparación y empaquetado.',
            'Enviado' => 'EN CAMINO: El paquete salió de nuestro centro de distribución.',
            'Entregado' => 'ENTREGADO: Paquete recibido en la dirección final.'
        ];
        
    } else {
        $mensaje = "Error: Pedido #$id_pedido no encontrado o no te pertenece. Inténtalo de nuevo.";
    }
} else {
    // Si llegamos aquí y no hay ID en la URL, el mensaje se mantiene
    $mensaje = "Ingrese un número de pedido válido."; 
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rastreo de Pedido #<?php echo htmlspecialchars($id_pedido); ?> | TechConnect</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .rastreo-container { max-width: 800px; margin: 40px auto; padding: 25px; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-radius: 8px;}
        .rastreo-form input[type="text"] { padding: 10px; width: 65%; margin-right: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .timeline { list-style: none; padding-left: 20px; margin-top: 30px; }
        .timeline li { border-left: 3px solid #ddd; padding: 0 0 20px 20px; position: relative; color: #666; }
        
        /* Estilos de la línea de tiempo */
        .timeline li:before { 
            content: ' '; 
            background: #ddd; 
            border: 3px solid #ddd;
            border-radius: 50%;
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-left: -28px;
            position: absolute;
            left: 0;
            top: 0;
            z-index: 10;
        }
        .timeline li.done { color: #333; }
        .timeline li.done:before { background: #28a54c; border-color: #28a54c; } /* Estado completado */
        .timeline li.current:before { background: #007bff; border-color: #007bff; animation: pulse 1s infinite alternate; } /* Estado actual */
        
        .timeline li h4 { margin: 0 0 5px 0; color: #333; }
        
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; margin-left: 10px; background-color: #ffc107; color: #333;}
        .status-badge.Entregado { background-color: #28a54c; color: white;}
        .status-badge.Cancelado { background-color: #dc3545; color: white;}
        
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.4); } 100% { box-shadow: 0 0 0 8px rgba(0, 123, 255, 0); } }
    </style>
</head>
<body>
    <header>
        <h1>TechConnect: Rastreo de Pedido</h1>
        <nav>
            <a href="index.php">Inicio</a>
            <a href="pages/catalogo.php">Catálogo</a>
            <a href="perfil.php">Mi Perfil</a>
            <a href="historial_pedidos.php">Historial</a>
            <a href="logout.php">Cerrar Sesión</a>
        </nav>
    </header>

    <main>
        <div class="rastreo-container">
            <h2>Seguimiento de Pedido #<?php echo htmlspecialchars($id_pedido); ?></h2>

            <?php if ($mensaje && !$pedido_info): ?>
                <p class="error" style="color: red;"><?php echo $mensaje; ?></p>
            <?php endif; ?>

            <?php if ($pedido_info): ?>
                <div style="border-top: 1px solid #ccc; padding-top: 20px; margin-top: 20px;">
                    
                    <p style="font-size: 1.1em; margin-bottom: 5px;">
                        **ESTADO ACTUAL:** <span class="status-badge <?php echo htmlspecialchars($pedido_info['estado']); ?>">
                             <?php echo htmlspecialchars(strtoupper($pedido_info['estado'])); ?>
                        </span>
                    </p>
                    
                    <h3 style="color: <?php echo ($current_state === 'Entregado') ? '#28a54c' : (($current_state === 'Cancelado' ? '#dc3545' : '#007bff')); ?>;">
                        Estimación: <?php echo $status_entrega; ?>
                    </h3>
                    
                    <p style="font-size: 0.9em; color: #777;">
                        Fecha de Compra: <?php echo date("d/m/Y H:i", strtotime($pedido_info['fecha_pedido'])); ?>
                    </p>
                </div>
                
                <h4>Progreso del Envío</h4>
                <ul class="timeline">
                    <?php 
                    $current_state_found = false;
                    
                    // Definimos el orden de la línea de tiempo (De más antiguo a más reciente)
                    $states = ['Pendiente', 'Procesando', 'Enviado', 'Entregado'];
                    
                    // Iterar sobre los estados de la línea de tiempo
                    foreach ($states as $state) {
                        $is_current = ($state === $current_state);
                        $class = '';
                        
                        // Si ya encontramos el estado actual, el resto son futuros y no deben ser 'done'
                        if ($is_current_found) {
                            $class = ''; 
                        } elseif ($is_current) {
                            $class = 'done current'; // Estado actual: animado y marcado
                            $is_current_found = true; 
                        } else {
                            $class = 'done'; // Estado pasado: marcado como completado
                        }
                        
                        // Si el pedido está Cancelado, salimos del flujo normal de estados
                        if ($current_state === 'Cancelado') {
                            if ($state === 'Pendiente') { // Solo muestra el punto de inicio
                                echo "<li class='done'><h4>PEDIDO CREADO</h4><p>El pedido fue iniciado.</p></li>";
                            }
                            continue;
                        }

                        // Solo mostramos este estado si ya se alcanzó (es done) o es el estado actual.
                        if ($class === 'done' || $class === 'done current' || $state === 'Pendiente') {
                            echo "<li class='{$class}'>";
                            echo "<h4>" . htmlspecialchars(strtoupper($state)) . "</h4>";
                            echo "<p>" . htmlspecialchars($timeline_states[$state] ?? 'Actualización del sistema.') . "</p>";
                            echo "</li>";
                        }
                        
                        if ($is_current) break; // Detener la línea de tiempo después del estado actual
                    }
                    
                    // Caso específico Cancelado
                    if ($current_state === 'Cancelado') {
                         echo "<li class='done current' style='border-left-color: #dc3545;'>"; 
                         echo "<h4>CANCELADO</h4>";
                         // Muestra el motivo si está disponible
                         echo "<p>El pedido fue cancelado. Motivo: " . htmlspecialchars($pedido_info['datos_pago_adicionales'] ?? 'Verifique el historial.') . "</p>"; 
                         echo "</li>";
                    }
                    ?>
                </ul>

            <?php else: ?>
                <p>Por favor, usa el Historial de Pedidos para obtener el ID de tu orden para rastrearlo.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 TechConnect: Tienda Electrónica. Todos los derechos reservados.</p>
    </footer>
</body>
</html>