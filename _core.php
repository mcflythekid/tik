<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

define("INPUT_SIZE_COUNTING", 10);
define("INPUT_SIZE_COIN_CODE", 4);
define("INPUT_SIZE_DATETIME", 18);
define("INPUT_HINT_DATETIME", "1999-12-31 23:59:59");
define("INPUT_HINT_QUANTITY", "Quantity");
define("INPUT_HINT_MONEY", "Money");
define("INPUT_HINT_NOTE", "Note");
define("INPUT_HINT_NAME", "Name");
define("INPUT_SIZE_H_NOTE", 2);
define("INPUT_SIZE_H_NAME", 2);
define("INPUT_SIZE_W_NOTE", 45);
define("INPUT_SIZE_W_NAME", 45);

include_once("_conf.php");
date_default_timezone_set($param_timezone);

$page_title = "page_title(x)";


function page_title($var){
	global $page_title;
	$page_title = $var;
}

function page_auth(){
	if (!isset($_SESSION["username"])){
		header("Location:/login.php");
		exit;
	}
}
function page_top(){
	global $page_title;
	include_once("_top.php");
}
function page_bot(){
	include_once("_bot.php");
}
/************/




/*                     DB                         */
$con = 0;
function db_open(){
	global $con;
	global $param_db_server;
	global $param_db_username;
	global $param_db_password;
	global $param_db_db;
	global $param_db_port;
	global $param_timezone;
	$con = mysqli_connect($param_db_server, $param_db_username, $param_db_password, $param_db_db, $param_db_port);
	if (!$con) {
		core_log("Connection failed: " . mysqli_connect_error());
		exit;
	}
	mysqli_set_charset($con,"utf8");
	mysqli_query($con, "SET timezone = '$param_timezone'");
	//exit;
}
function db_object($sql){
	global $con;
	$rs = mysqli_query($con, $sql);
	if (mysqli_num_rows($rs) > 0) {
		$obj = mysqli_fetch_assoc($rs); 
		return $obj;
	}
	return false;
}
function db_count($sql){
	global $con;
	$rs = mysqli_query($con, $sql);
	if (mysqli_num_rows($rs) > 0) {
		$obj = mysqli_fetch_assoc($rs);
		return $obj["count(*)"];
	}
	return false;
}
function db_list($sql){
	global $con;
	$rs = mysqli_query($con, $sql);
	if (mysqli_num_rows($rs) > 0) {
		
		$rows = array();
		while($row = mysqli_fetch_assoc($rs)) {
			$rows[] = $row;
		}
		return $rows;
	}
	return array();
}
function db_query($sql){
	global $con;
	return mysqli_query($con, $sql);
}
function db_insert_id(){
	global $con;
	return mysqli_insert_id($con);
}
function db_close(){
	global $con;
	mysqli_close($con);
}








/*                     form                         */
function form_param($post_or_get, $name){
	if (!isset($post_or_get[$name])){
		return null;
	}
	global $con;
	$str = mysqli_real_escape_string($con, $post_or_get[$name]);
	return $str;
}
function form_param_not_empty($post_or_get, $name){
	if (!isset($post_or_get[$name])){
		return null;
	}
	global $con;
	$str = mysqli_real_escape_string($con, $post_or_get[$name]);
	if ($str === "" ) return null;
	return $str;
}
function form_param_not_empty_html($post_or_get, $name){
	if (!isset($post_or_get[$name])){
		return null;
	}
	$str =  $post_or_get[$name];
	if ($str === "" ) return null;
	return $str;
}


/*             param           */
function param_get($key){
	global $con;
	return db_object("select `value` from param where `key` = '$key'")["value"];
}
function param_set($key, $value){
	global $con;
	return db_query("update param set `value` = '$value' where `key` = '$key'");
}
function param_get_all(){
	global $con;
	$arr = db_list("select * from param");
	$out_arr = [];
	foreach($arr as $obj){
		$out_arr[$obj["key"]] = $obj["value"];
	}
	return $out_arr;
}

/*        echo         */
function escape($s){
	echo htmlspecialchars($s);
}


// Return the value of time different in "xx times ago" format
function ago($date_string, $color = false)
{
    $today = new DateTime("now");
    $thatDay = new DateTime($date_string);
    $dt = $today->diff($thatDay);

    if ($dt->y > 0){
        $number = $dt->y;
        $unit = "year";
    } else if ($dt->m > 0) {
        $number = $dt->m;
        $unit = "month";
    } else if ($dt->d > 0) {
        $number = $dt->d;
        $unit = "day";
    } else if ($dt->h > 0) {
        $number = $dt->h;
        $unit = "hour";
    } else if ($dt->i > 0) {
        $number = $dt->i;
        $unit = "minute";
    } else if ($dt->s > 0) {
        $number = $dt->s;
        $unit = "second";
    } else {
		return "just now";
	}
    
    $unit .= $number  > 1 ? "s" : "";
 
	$ret = $number . " " . $unit;

	if ($color) {
		if ($thatDay > $today) {
			return "<span style='color:green'>Next $ret</span>";
		} else {
			return "<span style='color:red'>$ret ago</span>";
		}
	} else {
		if ($thatDay > $today) {
			return "Next $ret";
		} else {
			return "$ret ago";
		}
	}
}


function get_tik_color_day($cat) {
    preg_match_all('!\d+!', $cat, $matches);
    return $matches[0][0];
}


function ago2_unit($value1, $unit1, $value2, $unit2) {
	$suffix1 = $value1 > 1 ? $unit1 . "s" : $unit1;
	$ret1 = "$value1 $suffix1";
	
	if ($value2 == 0) {
		return $ret1;
	}
	
	$suffix2 = $value2 > 1 ? $unit2 . "s" : $unit2;
	$ret2 = "$value2 $suffix2";
	
	return "$ret1 $ret2";
}
function ago2_date_diff($d1, $d2) {
	$difference = $d1 - $d2;
	return abs($difference/(60 * 60)/24);
}
function ago2($date_string, $color = false, $tik_color_day = 0, $tik_color_type = "tik")
{
    $today = new DateTime("now");
    $thatDay = new DateTime($date_string);
    $dt = $today->diff($thatDay);
	
	$x_year = $dt->y;
	$x_month = $dt->m;
	$x_day = $dt->d;
	$x_hour = $dt->h;
	$x_minute = $dt->i;
	$x_second = $dt->s;

    if ($dt->y > 0){
        $ret = ago2_unit($x_year, "year", $x_month, "month");
    } else if ($dt->m > 0) {
        $ret = ago2_unit($x_month, "month", $x_day, "day");
    } else if ($dt->d > 0) {
        $ret = ago2_unit($x_day, "day", $x_hour, "hour");
    } else if ($dt->h > 0) {
        $ret = ago2_unit($x_hour, "hour", $x_minute, "minute");
    } else if ($dt->i > 0) {
        $ret = ago2_unit($x_minute, "minute", $x_second, "second");
    } else if ($dt->s > 0) {
        $ret = ago2_unit($x_second, "second", 0, "");
    } else {
		return "just now";
	}

	if ($color) { // For maintain
		if ($thatDay > $today) {
			return "<span style='color:green'>Next $ret</span>";
		} else {
			return "<span style='color:red'>$ret ago</span>";
		}
		
	} else if ($tik_color_day > 0) { // For tik day
		$diff = date_diff($today, $thatDay)->days;
		if ($tik_color_type == "tik") {
			if ($diff >= $tik_color_day) {
				return "<strong style='color:green'>$ret ago</strong>";
			} else {
				return "<span style='color:black'>$ret ago</span>";
			}
		} else if ($tik_color_type == "luna") {
			if ($diff <= $tik_color_day) {
				return "<strong style='color:green'>Next $ret</strong>";
			} else {
				return "<span style='color:black'>Next $ret ago</span>";
			}
		} 
		
	} else {
		if ($thatDay > $today) {
			return "Next $ret";
		} else {
			return "$ret ago";
		}
	}
}

function has_httppost($param) {
	return isset($_POST[$param]);
}

function get_httpget($param, $default = "") {
	return isset($_GET[$param]) ? $_GET[$param] : $default;
}

function get_httppost($param, $default = "") {
	return isset($_POST[$param]) ? $_POST[$param] : $default;
}



function ts_or_now($datetime_str) {
	if (empty($datetime_str)) {
		return time();
	}
	return strtotime($datetime_str);
}


function digit($in, $count = 3) {
	$nbr = number_format($in, $count, ".", ",");
	return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}

function money_color($num) {
	if ($num <= 0) {
		return $num;
	}
	return "<strong style='color: green;'>$num</strong>";
}