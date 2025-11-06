<?php
$host = 'localhost';
$port = 3307;
$username = 'root';
$password = '';
$database = 'rts';
$mydb = mysqli_connect($host, $username, $password, $database, $port);
if (!$mydb) {
    die("Connection failed: " . mysqli_connect_error());
}
$mysqli = new mysqli($host, $username, $password, $database, $port);
?>