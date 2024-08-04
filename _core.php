<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

$rate = 26000;
define("GOLD_RATE", 1.0 / 0.829426);

define("INPUT_SIZE_COUNTING", 10);
define("INPUT_SIZE_COIN_CODE", 4);
define("INPUT_SIZE_DATETIME", 18);
define("INPUT_SIZE_NOTE", 30);
define("INPUT_SIZE_PAXG", 5);
define("INPUT_SIZE_NAME", 30);
//
define("INPUT_SIZE_H_NAME", 2);
define("INPUT_SIZE_W_NAME", 30);

define("INPUT_HINT_DATETIME", "1999-12-31 23:59:59");
define("INPUT_HINT_QUANTITY", "Quantity");
define("INPUT_HINT_MONEY", "Money");
define("INPUT_HINT_NOTE", "Note");
define("INPUT_HINT_NAME", "Name");
define("INPUT_HINT_PAXG", "PAXG");
//
define("INPUT_HINT_QUANTITY_WITHDRAW", "N|spent|move");

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
	global $param_is_prod;
	//
	$con = mysqli_connect($param_db_server, $param_db_username, $param_db_password, $param_db_db, $param_db_port);
	if (!$con) {
		core_log("Connection failed: " . mysqli_connect_error());
		exit;
	}
	mysqli_set_charset($con,"utf8");
	if ($param_is_prod) {
		mysqli_query($con, "SET timezone = '$param_timezone'");
	}
	//exit;
}
function db_object($sql){
	global $con;
	$rs = mysqli_query($con, $sql);
	if (!$rs) {
		return false;
	}
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
	if (is_array($matches) && is_array($matches[0]) && sizeof($matches[0]) > 0) {
		return $matches[0][0];
	}
    return null;
}


function ago2_unit($value1, $unit1, $value2, $unit2) {
	$suffix1 = $value1 > 1 ? $unit1 . "" : $unit1; // S suffix
	$ret1 = "$value1$suffix1";
	
	if ($value2 == 0) {
		return $ret1;
	}
	
	$suffix2 = $value2 > 1 ? $unit2 . "" : $unit2; // S suffix
	$ret2 = "$value2$suffix2";
	
	return "$ret1$ret2";
}
function ago2_date_diff($d1, $d2) {
	$difference = $d1 - $d2;
	return abs($difference/(60 * 60)/24);
}
function ago2($date_string, $color = false, $tik_color_day = 0, $tik_color_type = "tik", $skipMillis = -1)
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
        $ret = ago2_unit($x_year, "y", $x_month, "m");
    } else if ($dt->m > 0) {
        $ret = ago2_unit($x_month, "m", $x_day, "d");
    } else if ($dt->d > 0) {
        $ret = ago2_unit($x_day, "d", $x_hour, "h");
    } else if ($dt->h > 0) {
        $ret = ago2_unit($x_hour, "h", $x_minute, "mi");
    } else if ($dt->i > 0) {
        $ret = ago2_unit($x_minute, "mi", $x_second, "s");
    } else if ($dt->s > 0) {
        $ret = ago2_unit($x_second, "s", 0, "");
    } else {
		return $tik_color_day == 1 ? "DONE" : "now";
	}

	$next = "→";
	$ago = "⏎";

	if ($color) { // For maintain
		if ($skipMillis > 0 && $skipMillis > time()) { // Skipped
			return "<span style='color:pink'>$ret$ago</span>";
		}		
		if ($thatDay > $today) {
			return "<span style='color:green'>$next$ret</span>";
		} else {
			return "<span style='color:red'>$ret$ago</span>";
		}
		
	} else if ($tik_color_day > 0) { // For tik day
		$diff = date_diff($today, $thatDay)->days;
		if ($tik_color_type == "tik") {
			if ($skipMillis > 0 && $skipMillis > time()) { // Skipped
				return "<span style='color:pink'>$ret$ago</span>";
			}
			if ($tik_color_day == 1) { // For tik daily
				$foo = $today->format('Y-m-d');
				$bar = $thatDay->format('Y-m-d');
				return $foo == $bar ?
					"DONE" :
					"<strong style='color:green'>$ret$ago</strong>";
			} else { // For tik more than daily
				if ($diff >= $tik_color_day) {
					return "<strong style='color:green'>$ret$ago</strong>";
				} else {
					return "<span style='color:black'>$ret$ago</span>";
				}
			}
		} else if ($tik_color_type == "luna") {
			if ($diff <= $tik_color_day) {
				return "<strong style='color:green'>$next$ret</strong>";
			} else {
				return "<span style='color:black'>$next$ret</span>";
			}
		} 
		
	} else {
		if ($thatDay > $today) {
			return "$next$ret";
		} else {
			return "$ret$ago";
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

function money_color($num, $prefix = '') {
	if ($num <= 0) {
		return "$prefix$num";
	}
	return "<strong style='color: green;'>$prefix$num</strong>";
}

function startsWith($string, $substring) {
    // Using substr() to get the substring from the start of the string
    // and comparing it with the given substring
    return strpos($string, $substring) === 0;
}

function reduceToZero(&$target, $deduction) {
	$target -= $deduction;
	if ($target < 0) {
		$target = 0;
	}
}

function contains($str, $feed) {
    return strpos($str, $feed) !== false;
}


///////////
function getPAXG() {
	$row = db_object("select * from portfolio_price where code = 'PAXG' ");
	return $row["price"];
}




// TODO related
function getTodoGroupMinOfId($id) {
	$data = db_object("SELECT MIN(todo_order) AS min FROM tik WHERE category = (SELECT category FROM tik WHERE id_ = ${id}) ");
	return $data['min'] != null ? $data['min'] : 0;
}
function getTodoGroupMaxOfId($id) {
	$data = db_object("SELECT MAX(todo_order) AS max FROM tik WHERE category = (SELECT category FROM tik WHERE id_ = ${id}) ");
	return $data['max'] != null ? $data['max'] : 0;
}
function getTodoGroupMinOfCat($cat) {
	$data = db_object("SELECT MIN(todo_order) AS min FROM tik WHERE category = '${cat}' ");
	return $data['min'] != null ? $data['min'] : 0;
}
function getTodoGroupMaxOfCat($cat) {
	$data = db_object("SELECT MAX(todo_order) AS max FROM tik WHERE category = '${cat}' ");
	return $data['max'] != null ? $data['max'] : 0;
}
function todo_create($cat, $name) {
	$username = $_SESSION['username'];
	// $position = getTodoGroupMinOfCat($cat) - 1;
	$position = getTodoGroupMaxOfCat($cat) + 1;
	db_query("INSERT INTO tik (username, category, type_, name_, todo_order) values ('$username', '$cat', 'todo', '$name', $position)");
}
function todo_move_top($id) {
	$position = getTodoGroupMaxOfId($id) + 1;
	db_query("UPDATE tik SET todo_order = $position WHERE id_ = $id");
}
function todo_move_bottom($id) {
	$position = getTodoGroupMinOfId($id) - 1;
	db_query("UPDATE tik SET todo_order = $position WHERE id_ = $id");
}
function todo_move_down($id) {
    $current = db_object("SELECT id_, category, todo_order FROM tik WHERE id_ = ${id}");
    $currentOrder = $current['todo_order'];
    $category = $current['category'];

    // Get the item just above the current item
    $above = db_object("SELECT id_, todo_order FROM tik WHERE category = '${category}' AND todo_order < ${currentOrder} ORDER BY todo_order DESC LIMIT 1");

    if ($above) {
        $aboveId = $above['id_'];
        $aboveOrder = $above['todo_order'];

        // Swap the order values
        db_query("UPDATE tik SET todo_order = ${currentOrder} WHERE id_ = ${aboveId}");
        db_query("UPDATE tik SET todo_order = ${aboveOrder} WHERE id_ = ${id}");
    }
}
function todo_move_up($id) {
    $current = db_object("SELECT id_, category, todo_order FROM tik WHERE id_ = ${id}");
    $currentOrder = $current['todo_order'];
    $category = $current['category'];

    // Get the item just below the current item
    $below = db_object("SELECT id_, todo_order FROM tik WHERE category = '${category}' AND todo_order > ${currentOrder} ORDER BY todo_order ASC LIMIT 1");

    if ($below) {
        $belowId = $below['id_'];
        $belowOrder = $below['todo_order'];

        // Swap the order values
        db_query("UPDATE tik SET todo_order = ${currentOrder} WHERE id_ = ${belowId}");
        db_query("UPDATE tik SET todo_order = ${belowOrder} WHERE id_ = ${id}");
    }
}


function simpleAction($text, $array, $class = 'btn btn-warning') {
	$html = "<form method='post'>";

	foreach ($array as $key => $value):
		$html .= "<input type='hidden' name='$key' value='$value' />";
	endforeach;
	
	$html .= "<input type='submit' class='$class' value='$text' />";
	$html .= "</form>";
	return $html;
}