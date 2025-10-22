<?php
// Script pour créer le hash bcrypt du mot de passe admin
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo "Hash bcrypt pour 'admin123':\n";
echo $hash . "\n";
?>
