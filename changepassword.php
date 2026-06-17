<?php

$password = "1234";

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Password:</h3>";
echo $password;

echo "<h3>Hash:</h3>";
echo $hash;

?>