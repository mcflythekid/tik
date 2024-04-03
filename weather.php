<?php
require_once ("_core.php");
global $con;
db_open();

$temp = requireNonBlank($_GET['temp']);
$hum = requireNonBlank($_GET['hum']);
$id = requireNonBlank($_GET['id']);

db_query("update weather set temp = $temp, hum = $hum, ts = now() where id = '$id'");
echo "Success";

function requireNonBlank($data) {
    global $con;
    if (!isset($data) || empty($data)) {
        exit("Data is required");
    }
    return mysqli_real_escape_string($con, $data);
}