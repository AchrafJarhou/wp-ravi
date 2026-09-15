<?php
echo "Hello World";
echo "\n\nHTTP_ORIGIN: " . ($_SERVER['HTTP_ORIGIN'] ?? 'NOT SET');
echo "\nREQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'];
?>