<?php
// index.php
session_start();
include 'includes/db_conexion.php'; 

// --- 1. Obtener todas las categorías para la sección de inicio y filtros ---
// CORRECCIÓN: Prefijamos 'id_categoria' y 'nombre_categoria' con 'c.'
$sql_categorias = "SELECT c.id_categoria, c.nombre_categoria, c.icono, COUNT(p.id_producto) AS total_productos 
                   FROM Categorias c
                   LEFT JOIN Productos p ON c.id_categoria = p.id_categoria
                   GROUP BY c.id_categoria, c.nombre_categoria, c.icono
                   ORDER BY c.nombre_categoria";
$result_categorias = $conn->query($sql_categorias);

// --- Lógica de Búsqueda para enviar al catálogo (Se mantiene) ---
$termino_busqueda = $conn->real_escape_string($_GET['search'] ?? '');
$filtro_categoria_id = (int)($_GET['category'] ?? 0);
$ordenamiento = $_GET['order'] ?? '';

// Cierre de la conexión principal
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>TechConnect: Tienda Electrónica - Inicio</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Estilos de Búsqueda y Categorías en Inicio */
        .search-bar-home { 
            display: flex; 
            gap: 10px; 
            margin: 30px auto; 
            padding: 20px; 
            background: #e9ecef; 
            border-radius: 8px; 
            max-width: 800px;
        }
        .search-bar-home input[type="text"], .search-bar-home select, .search-bar-home button { 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            box-sizing: border-box;
        }
        .search-bar-home input[type="text"] { flex-grow: 1; }
        .search-bar-home button { background-color: #007bff; color: white; cursor: pointer; border: none; }
        
        .search-container { position: relative; flex-grow: 1; }
        #suggestions-list {
            position: absolute;
            z-index: 100;
            width: 100%;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        #suggestions-list div { padding: 10px; cursor: pointer; }
        #suggestions-list div:hover { background-color: #e9e9e9; }


        /* Estilos de la Sección de Categorías */
        .categories-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            padding: 20px 0;
        }
        .category-card { 
            background: #fff; 
            padding: 30px 20px; /* Aumentamos el padding para el icono */
            border-radius: 8px; 
            text-align: center; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            transition: transform 0.2s;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .category-icon {
            font-size: 3em; /* Hace el icono grande */
            display: block;
            margin-bottom: 10px;
        }
        .category-card h4 { margin-top: 0; color: #007bff; }
        
        .nav-user { color: #ffd700; margin-left: 15px; }
        /* Estilos para el contador del carrito */
        #cart-count { background-color: #ffc107; color: black; padding: 2px 6px; border-radius: 50%; font-size: 0.8em; margin-left: 5px; }
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
                <a href="perfil.php" class="nav-user">Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Cliente'); ?></a>
                <a href="historial_pedidos.php">Historial</a>
                <a href="logout.php">Cerrar Sesión</a>
            <?php else: ?>
                <a href="registro.php">Iniciar Sesión / Registrarse</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <section class="hero">
            <h2>Tecnología de Vanguardia al Mejor Precio</h2>
            <p>Encuentra tu próximo gadget con nuestros filtros avanzados.</p>
        </section>

        <form method="GET" action="pages/catalogo.php" class="search-bar-home">
            <div class="search-container">
                <input type="text" id="search-input" name="search" placeholder="Busca productos por nombre (Ej: Laptop, Audífonos)..." 
                       value="<?php echo htmlspecialchars($termino_busqueda); ?>" onkeyup="fetchSuggestions(this.value)">
                <div id="suggestions-list"></div>
            </div>
            
            <button type="submit">Buscar</button>
        </form>

        <section class="content-section" style="max-width: 1000px; margin: 30px auto;">
            <h2>Explora por Categoría</h2>
            <div class="categories-grid">
                <?php 
                if ($result_categorias && $result_categorias->num_rows > 0) {
                    while($cat = $result_categorias->fetch_assoc()):
                ?>
                    <a href="pages/catalogo.php?category=<?php echo $cat['id_categoria']; ?>" class="category-card">
                        <?php if (!empty($cat['icono'])): ?>
                            <span class="category-icon"><?php echo htmlspecialchars($cat['icono']); ?></span>
                        <?php else: ?>
                            <span class="category-icon">🏷️</span> 
                        <?php endif; ?>
                        
                        <h4><?php echo htmlspecialchars($cat['nombre_categoria']); ?></h4>
                        <p><?php echo $cat['total_productos']; ?> productos</p>
                    </a>
                <?php 
                    endwhile;
                } else {
                    echo "<p>No hay categorías disponibles.</p>";
                }
                ?>
            </div>
        </section>
        
        <section class="content-section">
             </section>
    </main>

    <footer>
        <p>&copy; 2025 TechConnect: Tienda Electrónica. Todos los derechos reservados.</p>
    </footer>

    <script>
        // Función AJAX para la barra de búsqueda dinámica (Necesita fetch_suggestions.php)
        function fetchSuggestions(query) {
            const suggestionsList = document.getElementById('suggestions-list');
            if (query.length < 2) {
                suggestionsList.innerHTML = '';
                return;
            }

            // NOTA: La ruta es 'includes/fetch_suggestions.php' porque index.php está en la raíz
            fetch(`includes/fetch_suggestions.php?q=${query}`)
                .then(response => response.json())
                .then(suggestions => {
                    suggestionsList.innerHTML = '';
                    if (suggestions.length > 0) {
                        suggestions.forEach(suggestion => {
                            const div = document.createElement('div');
                            div.textContent = suggestion;
                            div.onclick = function() {
                                // Al hacer clic, rellena el campo de búsqueda y envía el formulario al catálogo
                                document.getElementById('search-input').value = suggestion;
                                suggestionsList.innerHTML = '';
                                document.querySelector('.search-bar-home').submit();
                            };
                            suggestionsList.appendChild(div);
                        });
                    } else {
                        suggestionsList.innerHTML = '<div style="padding: 10px; color: #666;">No se encontraron resultados.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error al obtener sugerencias:', error);
                    suggestionsList.innerHTML = '<div style="padding: 10px; color: red;">Error de conexión.</div>';
                });
        }
        
        // Función para actualizar el contador del carrito (AJAX - Mantenido)
        function updateCartCount() {
            fetch('includes/contar_carrito.php')
                .then(response => response.json())
                .then(data => {
                    const cartCountElement = document.querySelector('a[href="pages/carrito.php"] span');
                    if (cartCountElement) {
                        cartCountElement.textContent = data.count;
                    }
                })
                .catch(error => console.error('Error al obtener el contador del carrito:', error));
        }
        document.addEventListener('DOMContentLoaded', updateCartCount);
    </script>
</body>
</html>