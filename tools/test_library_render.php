<?php
session_start();
$_SESSION['user_id'] = 1;
ob_start();
include 'library.php';
$output = ob_get_clean();
echo "Length of output: " . strlen($output) . " bytes\n";
if (strpos($output, 'Fatal error') !== false || strpos($output, 'Parse error') !== false || strpos($output, 'Warning') !== false) {
    echo "ERROR DETECTED in output!\n";
    echo substr($output, 0, 500);
} else {
    echo "SUCCESS: library.php executed cleanly with no PHP errors!\n";
}

