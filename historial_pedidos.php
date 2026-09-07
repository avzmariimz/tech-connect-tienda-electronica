<?php
// historial_pedidos.php - Historial de Compras del Cliente
session_start();
// Conexión principal (se abre al inicio)
$conn = new mysqli("localhost", "root", "", "TechConnect_DB"); 
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Array de razones de cancelación para el formulario
$razones_cancelacion = [
    'Ya no necesito el producto',
    'El tiempo de envío es demasiado largo',
    'Encontré un precio mejor en otro lugar',
    'Error al ordenar el producto/cantidad',
    'Quiero cambiar el método de pago',
    'Otros motivos'
];

// 1. VERIFICACIÓN DE SESIÓN
if (!isset($_SESSION['id_cliente'])) { header("Location: registro.php"); exit(); }

$id_cliente = $_SESSION['id_cliente'];
$mensaje = '';

// 2. LÓGICA DE PROCESAMIENTO DE ACCIONES (Comentarios/Cancelación)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    // --- LÓGICA DE AGREGAR COMENTARIO Y CALIFICACIÓN (MODIFICADA) ---
    if ($_POST['accion'] === 'agregar_comentario') {
        $id_pedido = (int)$_POST['id_pedido'];
        $id_producto = (int)$_POST['id_producto']; // ID del producto a reseñar
        $comentario = $conn->real_escape_string($_POST['comentario'] ?? '');
        $calificacion = (int)$_POST['calificacion'] ?? 0;

        if (!empty($comentario) && $calificacion >= 1 && $calificacion <= 5) {
            
            // BUSCAR SI YA EXISTE UNA RESEÑA PARA ESTE PRODUCTO/CLIENTE
            $sql_check = "SELECT id_review FROM Reviews WHERE id_cliente = $id_cliente AND id_producto = $id_producto";
            if ($conn->query($sql_check)->num_rows > 0) {
                 $mensaje = "Error: Ya existe una reseña para este producto (#$id_producto).";
            } else {
                // INSERCIÓN REAL EN LA TABLA REVIEWS
                $sql_insert_review = "INSERT INTO Reviews (id_cliente, id_producto, calificacion, comentario) VALUES (?, ?, ?, ?)";
                $stmt_review = $conn->prepare($sql_insert_review);
                $stmt_review->bind_param("iiis", $id_cliente, $id_producto, $calificacion, $comentario);
                
                if ($stmt_review->execute()) {
                    $mensaje = "✔️ Reseña de $calificacion estrellas guardada con éxito.";
                } else {
                    $mensaje = "Error al guardar reseña: " . $conn->error;
                }
            }
        } else {
            $mensaje = "Error: Debe proporcionar una calificación de 1 a 5 estrellas y un comentario.";
        }
    }
    
    // LÓGICA DE CANCELACIÓN (Mantenida)
    if ($_POST['accion'] === 'cancelar_pedido_final' && isset($_POST['id_pedido']) && isset($_POST['motivo_cancelacion'])) {
        $id_pedido = (int)$_POST['id_pedido'];
        $motivo = $conn->real_escape_string($_POST['motivo_cancelacion']);

        $sql_cancel = "UPDATE Pedidos SET estado = 'Cancelado', datos_pago_adicionales = CONCAT('CANCELADO. Motivo: ', ?) 
                       WHERE id_pedido = ? AND id_cliente = ?";
        
        $stmt = $conn->prepare($sql_cancel);
        $stmt->bind_param("sii", $motivo, $id_pedido, $id_cliente);

        if ($stmt->execute()) {
            $mensaje = "❌ Pedido #$id_pedido cancelado con éxito. Motivo: $motivo";
        } else {
            $mensaje = "Error al cancelar el pedido.";
        }
    }

    // Redirigir para mostrar el mensaje sin reenvío POST
    header("Location: historial_pedidos.php?msg=" . urlencode($mensaje));
    exit();
}

// 3. CONSULTA PRINCIPAL: Obtener la cabecera de todos los pedidos del cliente
$sql_pedidos = "SELECT id_pedido, fecha_pedido, total_pedido, estado, metodo_pago 
                FROM Pedidos 
                WHERE id_cliente = $id_cliente
                ORDER BY fecha_pedido DESC";
$result_pedidos = $conn->query($sql_pedidos);

// La variable $mensaje se carga si viene de un POST/GET
if (isset($_GET['msg'])) {
    $mensaje = htmlspecialchars($_GET['msg']);
}

// Cerramos la conexión principal AHORA
$conn->close(); 

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Pedidos | TechConnect</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .historial-container { max-width: 1000px; margin: 40px auto; padding: 20px; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .pedido-card { border: 1px solid #ddd; border-radius: 8px; margin-bottom: 25px; padding: 15px; background-color: #f9f9f9; }
        .pedido-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 15px; }
        .pedido-header h4 { margin: 0; }
        .pedido-actions a, .pedido-actions button { margin-left: 10px; text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 0.9em; border: none; cursor: pointer; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; margin-left: 10px; }
        
        /* Colores de estados: */
        .status-badge.Pendiente { background-color: #f0ad4e; color: white; } 
        .status-badge.Procesando { background-color: #ffc107; color: #333; } 
        .status-badge.Enviado { background-color: #007bff; color: white; }
        .status-badge.Entregado { background-color: #28a54c; color: white; } 
        .status-badge.Cancelado { background-color: #dc3545; color: white; }
        
        .detalle-row { font-size: 0.9em; color: #555; }
        .message-success { color: green; font-weight: bold; text-align: center; margin-bottom: 20px;}
        
        /* Modal estilos */
        .comment-modal { position: fixed; top: 20%; left: 50%; transform: translate(-50%, 0); background: white; padding: 25px; border: 1px solid #ccc; box-shadow: 0 4px 8px rgba(0,0,0,0.2); z-index: 100; min-width: 350px; display: none;}
        .comment-modal select, .comment-modal input[type="text"] { width: 95%; padding: 8px; margin-bottom: 10px; }
        .comment-modal button { padding: 5px 15px; }
        .nav-user { color: #ffd700; margin-left: 15px; }
        
        /* ESTILOS CLAVE PARA LAS ESTRELLAS */
        .rating-stars { display: inline-block; font-size: 24px; direction: rtl; unicode-bidi: bidi-override; }
        .rating-stars > input { display: none; }
        .rating-stars > label { color: #ccc; float: right; cursor: pointer; }
        .rating-stars > input:checked ~ label, 
        .rating-stars:not(:checked) > label:hover, 
        .rating-stars:not(:checked) > label:hover ~ label { color: #ffd700; }
        .rating-status { color: #28a54c; font-weight: bold; margin-left: 10px; } /* Nuevo estilo para status de reseña */
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
        <div class="historial-container">
            <h2>Mis Pedidos Realizados</h2>
            <?php if ($mensaje): ?>
                <p class="message-success"><?php echo $mensaje; ?></p>
            <?php endif; ?>
            
            <?php if ($result_pedidos && $result_pedidos->num_rows > 0): ?>
                <?php
                // RECONEXIÓN para consultar detalles
                $conn_details = new mysqli("localhost", "root", "", "TechConnect_DB"); 

                // Mover el puntero al inicio del resultado
                if (method_exists($result_pedidos, 'data_seek')) {
                    $result_pedidos->data_seek(0); 
                }

                while($pedido = $result_pedidos->fetch_assoc()): 
                    $id_pedido = $pedido['id_pedido'];
                    
                    // CONSULTA DE DETALLES DENTRO DEL BUCLE (Incluye el estado de reseña)
                    $sql_detalles = "
                        SELECT 
                            D.cantidad, D.precio_unitario, P.nombre, P.id_producto, R.calificacion
                        FROM 
                            DetallePedido D 
                        JOIN 
                            Productos P ON D.id_producto = P.id_producto
                        LEFT JOIN 
                            Reviews R ON R.id_producto = P.id_producto AND R.id_cliente = $id_cliente
                        WHERE D.id_pedido = $id_pedido
                    ";
                    $result_detalles = $conn_details->query($sql_detalles);
                ?>
                <div class="pedido-card">
                    <div class="pedido-header">
                        <h4>Pedido #<?php echo htmlspecialchars($pedido['id_pedido']); ?></h4>
                        <div>
                            Estado: 
                            <span class="status-badge <?php echo htmlspecialchars($pedido['estado']); ?>">
                                <?php 
                                if ($pedido['estado'] === 'Pendiente') echo 'NO PAGADO / PENDIENTE';
                                else if ($pedido['estado'] === 'Procesando') echo 'PAGO CONFIRMADO';
                                else if ($pedido['estado'] === 'Cancelado') echo 'CANCELADO';
                                else echo htmlspecialchars(strtoupper($pedido['estado']));
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <p style="font-size: 0.9em; margin-bottom: 5px;">Fecha: <?php echo date("d/m/Y", strtotime($pedido['fecha_pedido'])); ?> | Pago: <?php echo htmlspecialchars($pedido['metodo_pago']); ?></p>

                    <table class="pedido-detail-table">
                        <thead>
                            <tr><th>Producto</th><th>Cant.</th><th>Precio Unitario</th><th>Subtotal</th><th>Reseñar</th></tr>
                        </thead>
                        <tbody>
                            <?php while($detalle = $result_detalles->fetch_assoc()): ?>
                            <tr class="detalle-row">
                                <td><?php echo htmlspecialchars($detalle['nombre']); ?></td>
                                <td><?php echo (int)$detalle['cantidad']; ?></td>
                                <td>$<?php echo number_format($detalle['precio_unitario'], 2); ?> MXN</td>
                                <td>$<?php echo number_format($detalle['cantidad'] * $detalle['precio_unitario'], 2); ?> MXN</td>
                                
                                <td>
                                    <?php if ($detalle['calificacion'] > 0): ?>
                                        <span class="rating-status">★ <?php echo $detalle['calificacion']; ?> Calificado</span>
                                    <?php elseif ($pedido['estado'] === 'Entregado'): ?>
                                        <a href="javascript:void(0)" 
                                           onclick="showCommentModal(<?php echo $id_pedido; ?>, <?php echo $detalle['id_producto']; ?>)" 
                                           style="background-color: #007bff; color: white;">Opinar</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <div style="text-align: right; margin-top: 15px;">
                        <h3 style="color: #000;">TOTAL: <span style="color: #dc3545;">$<?php echo number_format($pedido['total_pedido'], 2); ?> MXN</span></h3>
                    </div>

                    <div class="pedido-actions" style="border-top: 1px solid #eee; padding-top: 10px;">
                        
                        <?php if ($pedido['estado'] === 'Entregado'): ?>
                            <a href="#" style="background-color: #f0ad4e; color: white;">Solicitar Devolución</a>
                        <?php endif; ?>

                        <?php if ($pedido['estado'] !== 'Cancelado' && $pedido['estado'] !== 'Entregado'): ?>
                            <button type="button" onclick="showCancelModal(<?php echo $id_pedido; ?>)" style="background-color: #dc3545; color: white;">Cancelar Pedido</button>
                        <?php endif; ?>
                        
                        <a href="pages/checkout.php?view_pedido=<?php echo $id_pedido; ?>" style="background-color: #5bc0de; color: white;">Ver Detalle</a>
                    </div>
                </div>
                <?php endwhile; 
                // Cerrar la conexión de detalles después del bucle
                $conn_details->close(); 
                ?>
            
            <?php else: ?>
                <p>Aún no has realizado ningún pedido. ¡Empieza a comprar en nuestro <a href="pages/catalogo.php">catálogo</a>!</p>
            <?php endif; ?>
        </div>
    </main>

    <div id="commentModal" class="comment-modal">
        <h4>Comentar Pedido #<span id="modalCommentPedidoId"></span></h4>
        <form method="POST">
            <input type="hidden" name="accion" value="agregar_comentario">
            <input type="hidden" name="id_pedido" id="modalInputCommentId">
            <input type="hidden" name="id_producto" id="modalInputProductId"> <label style="margin-top: 10px;">Tu Calificación:</label>
            <div class="rating-stars" style="direction: rtl; unicode-bidi: bidi-override;">
                <input id="rating5" type="radio" name="calificacion" value="5" required><label for="rating5">★</label>
                <input id="rating4" type="radio" name="calificacion" value="4"><label for="rating4">★</label>
                <input id="rating3" type="radio" name="calificacion" value="3"><label for="rating3">★</label>
                <input id="rating2" type="radio" name="calificacion" value="2"><label for="rating2">★</label>
                <input id="rating1" type="radio" name="calificacion" value="1"><label for="rating1">★</label>
            </div>

            <label for="comentario_input" style="margin-top: 15px;">Tu Comentario:</label>
            <input type="text" id="comentario_input" name="comentario" required>
            
            <button type="submit" class="btn" style="background-color: #28a54c;">Enviar</button>
            <button type="button" onclick="document.getElementById('commentModal').style.display='none'" class="btn" style="background-color: #ccc;">Cerrar</button>
        </form>
    </div>

    <div id="cancelModal" class="comment-modal">
        <h4>Cancelar Pedido #<span id="modalCancelPedidoId"></span></h4>
        <form method="POST">
            <input type="hidden" name="accion" value="cancelar_pedido_final">
            <input type="hidden" name="id_pedido" id="modalInputCancelId">

            <label for="motivo_cancelacion_select" style="margin-top: 10px;">Razón de la cancelación:</label>
            <select id="motivo_cancelacion_select" name="motivo_cancelacion" required>
                <option value="">Seleccione un motivo</option>
                <?php foreach ($razones_cancelacion as $razon): ?>
                    <option value="<?php echo htmlspecialchars($razon); ?>"><?php echo htmlspecialchars($razon); ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn" style="background-color: #dc3545;">Confirmar Cancelación</button>
            <button type="button" onclick="document.getElementById('cancelModal').style.display='none'" class="btn" style="background-color: #ccc;">Cerrar</button>
        </form>
    </div>

    <footer>
        <p>&copy; 2025 TechConnect: Tienda Electrónica. Todos los derechos reservados.</p>
    </footer>

    <script>
        // Función para mostrar el modal de comentarios (ACTUALIZADA para aceptar Product ID)
        function showCommentModal(pedidoId, productId) {
            document.getElementById('modalCommentPedidoId').textContent = pedidoId;
            document.getElementById('modalInputCommentId').value = pedidoId;
            document.getElementById('modalInputProductId').value = productId; // Establecer el ID del producto
            document.getElementById('commentModal').style.display = 'block';
        }

        // NUEVA FUNCIÓN: Muestra el modal de cancelación (Mantenida)
        function showCancelModal(pedidoId) {
            document.getElementById('modalCancelPedidoId').textContent = pedidoId;
            document.getElementById('modalInputCancelId').value = pedidoId;
            document.getElementById('cancelModal').style.display = 'block';
        }
        
        // Función para actualizar el contador del carrito (Mantenida)
        function updateCartCount() {
            fetch('includes/contar_carrito.php')
                .then(response => response.json())
                .then(data => {
                    const cartCountElement = document.querySelector('a[href="pages/carrito.php"]');
                    if (cartCountElement) {
                        const span = document.querySelector('a[href="pages/carrito.php"] span');
                        if (span) { span.textContent = data.count; }
                    }
                })
                .catch(error => console.error('Error al obtener el contador del carrito:', error));
        }
        document.addEventListener('DOMContentLoaded', updateCartCount);
    </script>
</body>
</html>