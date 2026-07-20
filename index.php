<?php
// Required file - Redirect to main application
$uri = $_SERVER['REQUEST_URI'];

// Agar seedha root par request aayi to anmol folder mein bhejo
if ($uri == '/' || $uri == '') {
    header('Location: anmol/index.php');
    exit;
}

// Agar koi specific file/folder request hai to usse jaane do
return false;
?>
