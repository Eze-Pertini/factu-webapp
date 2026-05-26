<?php
session_start();
echo "Nombre en sesión: " . $_SESSION['usuario_nombre'];
echo "<br>Email en sesión: " . $_SESSION['usuario_email'];