# TechConnect: Tienda Electrónica

## Descripción del Proyecto

Aplicación web de comercio electrónico desarrollada para una empresa local de venta de dispositivos y accesorios electrónicos.

### Herramientas y Tecnologías
* **Backend:** PHP (scripts para lógica de negocio y base de datos)
* **Base de Datos:** MySQL (TechConnect_DB)
* **Frontend:** HTML5, CSS3 (con estilos básicos incluidos en `css/style.css`)
* **Entorno de Desarrollo:** Visual Studio Code
* **Servidor:** XAMPP, WAMP o Laragon (Apache)

## Estructura de Archivos

| Archivo/Carpeta | Propósito |
| :--- | :--- |
| `index.php` | Página principal y productos destacados. |
| `registro.php` | Maneja el registro de nuevos usuarios y el inicio de sesión. |
| `logout.php` | Cierra la sesión activa. |
| `includes/db_conexion.php` | Conexión con la base de datos MySQL. |
| `pages/catalogo.php` | Vista principal del listado de productos. |
| `pages/carrito.php` | Gestión y visualización del carrito de compras. |
| `pages/checkout.php` | **PROCESO CRÍTICO:** Finaliza la compra, crea el pedido y actualiza el stock (Debe ser añadido manualmente). |
| `css/style.css` | Hoja de estilos principal. |

## Instrucciones de Instalación

1.  **Configurar Servidor:** Instalar y ejecutar Apache y MySQL (usando XAMPP, WAMP, etc.).
2.  **Copiar Archivos:** Colocar la carpeta `TechConnect` dentro del directorio `htdocs` (o `www`) del servidor.
3.  **Configurar DB:** Crear la base de datos `TechConnect_DB` en phpMyAdmin y ejecutar el script SQL completo que contiene la creación de las 7 tablas y la inserción de los 100 productos.
4.  **Acceder:** Abrir el navegador en `http://localhost/TechConnect/`.

## Tablas Clave de MySQL

* `Clientes`
* `Productos`
* `Pedidos`
* `DetallePedido`
* `Carrito`
* `Categorias`