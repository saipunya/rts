<?php
$host = 'localhost';
$username = 'rts_user';
$password = 'sumetchoorat4631022';
$database = 'rts_db';
$mydb = mysqli_connect($host, $username, $password, $database);
if (!$mydb) {
    die("Connection failed: " . mysqli_connect_error());
}
$mysqli = new mysqli($host, $username, $password, $database);
?>