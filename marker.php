<?php
require_once ("_core.php");



if (isset($_GET['create'])) {
    $marker = $_GET['create'];
    if (!isAlphanumeric($marker)) {
        echo "Internal Server Errors";
        exit;
    }

    db_open();
	db_query("insert into marker (marker) values ('$marker')   ");
    echo "CREATED";
    exit;
}

if (isset($_GET['check'])) {
    $marker = $_GET['check'];
    if (!isAlphanumeric($marker)) {
        echo "Internal Server Errors";
        exit;
    }

    db_open ();

    $count = db_count("select count(*) from marker where marker = '$marker' ");
    if ($count == 0) {
        echo "WIP";
        exit;
    }

    $count = db_count("delete from marker where marker = '$marker' ");
    echo "DONE";
    exit;
}

echo "Internal Server Error";

function isAlphanumeric($string) {
    // Check if the string contains only a-z, A-Z, and 0-9
    return preg_match('/^[a-zA-Z0-9]+$/', $string);
}