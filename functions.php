<?php
$host = 'localhost';
$username = 'rts_user';
$password = 'sumet4631022';
$database = 'rts_db';
$mydb = mysqli_connect($host, $username, $password, $database);
if (!$mydb) {
    die("Connection failed: " . mysqli_connect_error());
}
$mysqli = new mysqli($host, $username, $password, $database);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
} else {
    echo "Connected successfully";
}
?>