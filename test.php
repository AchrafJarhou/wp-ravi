<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/plain');
echo "PHP VERSION: " . phpversion() . "\n";
echo "SCRIPT: " . __FILE__ . "\n";
echo "CWD: " . getcwd() . "\n";
echo "TEST OK\n";

// Try database connection
if (function_exists('mysqli_connect')) {
    echo "MySQLi: AVAILABLE\n";
} else {
    echo "MySQLi: NOT AVAILABLE\n";
}
?>
