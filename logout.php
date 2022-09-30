<?php
session_start();
require_once("_core.php");
session_unset();
session_destroy();
header("Location:/login.php");