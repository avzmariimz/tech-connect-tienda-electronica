<?php
// logout.php
session_start();
session_unset();    // Elimina todas las variables de sesión
session_destroy();  // Destruye la sesión en el servidor
header("Location: index.php"); // Redirige al inicio
exit();
?>