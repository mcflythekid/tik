<?php
session_start ();
require_once ("_core.php");
require_once ("luna.php");
page_auth ();

db_open ();
$cat = isset($_GET['cat']) ? $_GET['cat'] : "TODO";
$type = isset($_GET['type']) ? $_GET['type'] : "tik";
$kind = isset($_GET['kind']) ? $_GET['kind'] : "html";

if ($type == 'luna') {
	$luna = new luna();
}

page_title(strtoupper("$cat"));
$username = $_SESSION['username'];

$tik_color_day = isset($_GET['days']) ? $_GET['days'] : get_tik_color_day($cat);

// create for tik
if (isset($_POST['name_']) && $type == 'tik') {
	$new_name = $_POST['name_'];
	db_query("insert into tik (username, category, type_, name_, tik) values ('$username', '$cat', 'tik', '$new_name', now() - interval 10 year)");
	header("Refresh:0");
	exit;
}

// TODO
if ($type == 'todo') {
	if (isset($_POST['name_'])) {
		todo_create($cat, $_POST['name_']);
		header("Refresh:0");
		exit;
	}
	if (isset($_POST['todo_move_top'])) {
		todo_move_top($_POST['todo_move_top']);
		header("Refresh:0");
		exit;
	}
	if (isset($_POST['todo_move_bottom'])) {
		todo_move_bottom($_POST['todo_move_bottom']);
		header("Refresh:0");
		exit;
	}
	if (isset($_POST['todo_move_up'])) {
		todo_move_up($_POST['todo_move_up']);
		header("Refresh:0");
		exit;
	}
	if (isset($_POST['todo_move_down'])) {
		todo_move_down($_POST['todo_move_down']);
		header("Refresh:0");
		exit;
	}
}


// create for countdown
if (isset($_POST['countdown_name']) && isset($_POST['countdown_tik'])) {
	$countdown_name = $_POST['countdown_name'];
	$countdown_tik  = $_POST['countdown_tik'];
	$tik_format = strlen($countdown_tik) == 10 ? '%Y %m %d' : '%Y %m %d %H %i';
	db_query("insert into tik (username, category, type_, name_, tik) values ('$username', '$cat', 'countdown', '$countdown_name', STR_TO_DATE('$countdown_tik', '$tik_format'))");
	header("Refresh:0");
	exit;
}

// create for luna
if (isset($_POST['luna_name']) && isset($_POST['luna_tik'])) {
	$luna_name = $_POST['luna_name'];
	$luna_tik  = $_POST['luna_tik'];
	$tik_format = '%Y %m %d';
	db_query("insert into tik (username, category, type_, name_, tik) values ('$username', '$cat', 'luna', '$luna_name', STR_TO_DATE('2000 $luna_tik', '$tik_format'))");
	header("Refresh:0");
	exit;
}

// do the tik
if (isset($_POST['tik_id_async'])) {
	$tik_id = $_POST['tik_id_async'];
	db_query("update tik set tik = now(), counter = counter + 1, skip = NULL where id_ = $tik_id");
	echo "OK";
	exit;
}

// removal
if (isset($_POST['rm_id_async'])) {
	$rm_id = $_POST['rm_id_async'];
	db_query("delete from tik where id_ = $rm_id");
	echo "OK";
	exit;
}

// edit
if (has_httppost("action_edit") == true) {
	$req_id = get_httppost("id");
	$req_cat = get_httppost("category");
	$req_name = get_httppost("name");
	$req_ts = get_httppost("ts");
	$cal_ts = ts_or_now($req_ts);
		
	db_query("update tik set category = '$req_cat', name_ = '$req_name', tik = FROM_UNIXTIME($cal_ts) where id_ = '$req_id'");
	header("Refresh:0");
	exit;
}

// skip
if (has_httppost("action_skip_async_id") == true) {
	$req_id = get_httppost("action_skip_async_id");

	$endString = date('Y-m-d 23:59:59', time());
	$endMillis = strtotime($endString);
	
	db_query("update tik set skip = FROM_UNIXTIME($endMillis) where id_ = '$req_id'");
	echo "OK";
	exit;
}

// skipAll
if (has_httppost("action_skipAll") == true) {
	$req_category = get_httppost("category");

	$endString = date('Y-m-d 23:59:59', time());
	$endMillis = strtotime($endString);
	
	db_query("update tik set skip = FROM_UNIXTIME($endMillis) where category = '$req_category'");
	header("Refresh:0");
	exit;
}
if (has_httppost("action_skipAllGroup") == true) {
	$req_category_prefix = get_httppost("category_prefix");

	$endString = date('Y-m-d 23:59:59', time());
	$endMillis = strtotime($endString);
	
	db_query("update tik set skip = FROM_UNIXTIME($endMillis) where category LIKE '$req_category_prefix%'");
	header("Refresh:0");
	exit;
}

// Reset Skip
if (has_httppost("action_skipReset") == true) {
	db_query("update tik set skip = null");
	header("Refresh:0");
	exit;
}

$order_by = 'tik asc';
if (in_array($cat, array("TODO", "TODO2", "cpg", "w"))) { // No tik
    $order_by = 'name_ asc';
}
if ($type == 'todo') {
	$order_by = 'todo_order DESC';
}

$tiks = db_list("select id_, name_, tik, category, counter, skip from tik where username = '$username' and category = '$cat' and type_ = '$type' order by $order_by");


$all_categories = db_list("select distinct category from tik order by category");


// Type gym
$gym_mode = "simple"; // and "complex"
if (isset($_GET["gym_mode"]) && isNotBlank($_GET["gym_mode"])) {
    $gym_mode = $_GET["gym_mode"];
}
//
$gym_only_muscle_group = ""; // Filter gym
if (isset($_GET["gym_only_muscle_group"]) && isNotBlank($_GET["gym_only_muscle_group"])) {
    $gym_only_muscle_group = $_GET["gym_only_muscle_group"];
}
//
$gym_records = array();
//
$gym_musble_group_avaiable_items = array(); // Count gym items
function add_gym_musble_group_avaiable_items($muscleGroup) {
	global $gym_musble_group_avaiable_items;
	if (!isset($gym_musble_group_avaiable_items[$muscleGroup])) {
		$gym_musble_group_avaiable_items[$muscleGroup] = 0;
	}
	$gym_musble_group_avaiable_items[$muscleGroup] += 1;
}
function get_gym_musble_group_avaiable_items($muscleGroup) {
	global $gym_musble_group_avaiable_items;
	if (!isset($gym_musble_group_avaiable_items[$muscleGroup])) {
		return 0;
	}
	return $gym_musble_group_avaiable_items[$muscleGroup];
}
//

// Pre-process
foreach ($tiks as &$tik) {
	if ($type == 'tik' && $cat == 'gym') {
		handle_gym_preprocess($tik);
	}
}
// Process
foreach ($tiks as &$tik) {
	if ($type == "luna"){
		handle_luna($tik);
	} else if ($type == "tik"){
		handle_normal($tik);
	} 
	
	// Extra processor for gymmer
	if ($type == 'tik' && $cat == 'gym') {
		handle_gym($tik);
	}
} 
unset($tik); // https://stackoverflow.com/questions/7158741/why-php-iteration-by-reference-returns-a-duplicate-last-record
// https://bugs.php.net/bug.php?id=29992




function handle_normal(&$tik) {
	$name = $tik["name_"];
	$count = $tik["counter"];
	$tik["tik_out_line"] = $count > 0 ? "$name <strong style='color: orange;'>⟳$count</strong>" : $name;
}


function handle_gym_preprocess(&$tik) {
	// prepare data for GYM first
	global $gym_records;
	//
	$name = $tik["name_"];
	$tikDate = $tik['tik'];
	$skipDate = $tik['skip'];
	//
	$muscleGroups = extractAllStringsInBrackets($name);
	foreach ($muscleGroups as $muscleGroup) {
		addGymRecord($gym_records, $muscleGroup, $tikDate);
	}

	// Custom sort key
	$tik_epox = strtotime($tikDate);
	if ($skipDate != null) {
		$skip_epox = strtotime($skipDate);
		if ($skip_epox > time()) {
			$tik_epox += 50 * 3600 * 24 * 365;
			$tik['gym_sort_IS_SKIPPED'] = 1;
		}
	}
	$tik['gym_sort'] = $tik_epox;
}
function handle_gym(&$tik) {
	global $gym_records, $gym_mode;
	

	$name = $tik["name_"];
	$muscleGroups = extractAllStringsInBrackets($name);

	// Recovery
	$MAX_GYM_STREAK = 4;
	if (count($muscleGroups) == 1 && $muscleGroups[0] == "Recover") {
		$recoverDate = epocToHanoiYYYYMMDD(strtotime($tik['tik']));
		$nowDate = epocToHanoiYYYYMMDD(time());
		$interval = (new DateTime($recoverDate))->diff(new DateTime($nowDate));
		$intervalDays = $interval->days;
		$streak = $intervalDays - 1;
		if ($streak < 0) {
			$streak = 0;
		}

		if ($streak > $MAX_GYM_STREAK) {
			$tik["tik_out_line"] .= " <span class='color_red'>!! Rest Today Please Haha !! $streak/$MAX_GYM_STREAK streak reached</span>";
			$tik['gym_sort'] = $tik['gym_sort'] - 300 * 3600 * 24 * 365;
			
		} else {
			$tik["tik_out_line"] .= " <span class='color_green'>$streak/$MAX_GYM_STREAK streak</span>";
		}

		return;
	}
	// \\Recovery

	$error = "";
	foreach ($muscleGroups as $muscleGroup) { // Loop on all muscle group of this $tik

		$hourLimit = getGymLimitHour($muscleGroup);
		//
		$hourPassedGroup = getGymHourPassed($gym_records, $muscleGroup);
		$hourPassedGroupRnd = round($hourPassedGroup, 2);
		//
		$hourPassedItem = getGymHourPassedOfItem($tik);
		$hourPassedItemRnd = round($hourPassedItem, 2);
		//
		$isFoundGroupLimit = false;
		if ($hourPassedGroup > GYM_SESSION_HOURS && $hourPassedGroup <= $hourLimit) {
			$error .= "G-${muscleGroup};${hourPassedGroupRnd}/${hourLimit};";
			$isFoundGroupLimit = true;
		}
		//
		if (!$isFoundGroupLimit && $hourPassedItem <= $hourLimit) { // Check NOT "isFoundGroupLimit" to save UI spaces
			$error .= "I-${muscleGroup};${hourPassedItemRnd}/${hourLimit};";
		}
		//
		$skipText = "SKIP;";
		if (isTikSkipped($tik) && !contains($error, $skipText)) {
			$error .= $skipText;
		}
	}
	if (isNotBlank($error)) {
		$error = trim($error);
		$error = rtrim($error, ';');

		$tik["tik_out_line"] .= "<br><span class='color_red'>[${error}]</span>";
		//
		if (!isset($tik['gym_sort_IS_SKIPPED']) || $tik['gym_sort_IS_SKIPPED'] != 1) { // Add 30days for skipped items
			$tik['gym_sort'] = $tik['gym_sort'] + 30 * 3600 * 24 * 365;
		}
		$tik["gym_item_recovering"] = 1; // Recovering (And sesion end by GYM_SESSION_HOURS)

	} else {
		add_gym_musble_group_avaiable_items($muscleGroup);
	}
}

function handle_luna(&$tik) {
	global $luna;
	$now_luna_obj = $luna->convertSolar2Lunar(idate("d"),idate("m"),idate("Y"),7);
	$now_luna_year = $now_luna_obj[2];
	$now_luna_leap = $now_luna_obj[3];
	
	$thatDayInLuna2000 = strtotime($tik['tik']);
	$luna_day = date("d", $thatDayInLuna2000);
	$luna_month = date("m", $thatDayInLuna2000);
	
	$sola_obj = $luna->convertLunar2Solar($luna_day, $luna_month, $now_luna_year,$now_luna_leap,7);
	$sola_year = $sola_obj[2];
	$sola_month = $sola_obj[1];
	$sola_day = $sola_obj[0];
	
	$today = new DateTime("now");
	
	$haha = new DateTime("$sola_year-$sola_month-$sola_day 12:00:00");

	if ($today > $haha) {
		
		//$tmp_luna_obj = $luna->convertSolar2Lunar(idate("d"),idate("m"),idate("Y") + 1,7);
		//$tmp_luna_leap = $tmp_luna_obj[3];
		
		$sola_obj = $luna->convertLunar2Solar($luna_day, $luna_month, $now_luna_year + 1, null ,7);
		//$sola_obj = $luna->convertLunar2Solar($luna_day, $luna_month, $now_luna_year + 1,$tmp_luna_leap,7);
		$sola_year = $sola_obj[2];
		$sola_month = $sola_obj[1];
		$sola_day = $sola_obj[0];

		//echo "<br>";
		//var_dump($sola_obj);
	}
	
	$weekday = date('D', strtotime("$sola_year-$sola_month-$sola_day"));
	//
	$luna_out_ts = "$sola_year-$sola_month-$sola_day";
	$luna_out_ago = ago2("$sola_year-$sola_month-$sola_day 12:00:00", false, 30, "luna");
	$luna_out_display = "$weekday, $sola_year-$sola_month-$sola_day ($luna_day/$luna_month ÂL)";
	//
	$tik["luna_out_ts"] = strtotime($luna_out_ts);
	$tik["luna_out_line"] = $luna_out_ago . " | " . $luna_out_display;
	
}

if ($type == "luna"){
	$collumnToSort = array_column($tiks, 'luna_out_ts');
	array_multisort($collumnToSort, SORT_ASC, $tiks);
}

if ($cat == "gym"){
	$collumnToSort = array_column($tiks, 'gym_sort');
	array_multisort($collumnToSort, SORT_ASC, $tiks);
}


if ($kind == "xhr") {
	
	$green = 0;
	foreach($tiks as $tik ) {
		$skipMillis = strtotime($tik["skip"]);

		if ($type == "tik") {
			$line = ago2($tik['tik'], false, $tik_color_day, "tik", $skipMillis);
			if (strpos($line, "green") !== false) {
				$green += 1;
			}
			
		} else if ($type == 'countdown') { 
			$line = ago2($tik['tik'], true, 0, "xx", $skipMillis);
			if (strpos($line, "red") !== false) {
				$green += 1;
			}
			
		} else if ($type == 'luna') {
			if (isset($line) && strpos($line, "green") !== false) {
				$green += 1;
			}
			$line = $tik["luna_out_line"];
		}
	}


	$data = array("badge" => $green);
	header("Content-Type: application/json");
	echo json_encode($data);
	exit();
}

$weather = db_object("select * from weather where id = 'main'");


page_top ();
?>



<style>
	a:hover { text-decoration: none; }
	.menu-item { margin-left: 1px; }
	.td-min { width: 1%; padding: 0px !important; }
	div.menu > p { margin-bottom: 1px; }
	div.menu > hr { margin-bottom: 1px; margin-top: 1px;
	
	
			border: none;
			height: 1px;
			/* Set the hr color */
			color: #333;  /* old IE */
			background-color: #333;  /* Modern Browsers */


	}
	div.menu > h5 { margin-bottom: 1px;}
</style>

<style>
span.line-head {
    font-weight: bold;
    position: relative;
	color: #4CAF50; /* Change the color value as needed */
	/* fixed width */
	width: 40px; /* Adjust width as needed */
    display: inline-block;
}
span.line-head-skipable {
	font-style: italic;
	text-decoration: underline;
}
.color_red {
	color: red;
	font-weight: bold;
}
.color_green {
	color: green;
}
</style>

<div class="menu">
	<?php function menu($type, $text, $href) { ?>
		<a class="menu-item" href="<?=$href?>">
			<span class="hed badge badge-<?=$type?>"><?=$text?></span>
			<span style="vertical-align: super; color: red;"></span>
		</a>
	<?php } ?>
	
	<div style="float:right; ">
		<form action='/logout.php' onSubmit="return confirm('thoát nha anh hai');" style="display:inline;">
			<input type="submit" class="btn btn-sm btn-warning" value="🌚" />
		</form>
		<form method='post' style="display:inline;">
			<input type="hidden" name="action_skipReset" value="xxx" />
			<input type="submit" class="btn btn-sm btn-info =" value="Rv" />
		</form>
	</div>
	

	<!-- <p>
		<span class="line-head"></span>
		<?php if (is_array($weather)): ?>
			<?=menu("info", $weather["temp"] . "℃", "#")?>
			<?=menu("info",  ($weather["hum"] - 0) . "%", "#")?>
			<?=menu("info",  date("H:i:s", strtotime($weather["ts"])), "#")?>
		<?php endif; ?>
	</p> -->

	<!-- WEEKEND -->
	<p>
		<form method='post' id="skip-all-t7cn-form">
			<input type="hidden" name="action_skipAllGroup" value="xxx" />
			<input type="hidden" name="category_prefix" value="WEEKEND_" />
		</form>
		<span class="line-head line-head-skipable" onclick="document.getElementById('skip-all-t7cn-form').submit();">SUN</span>
	    <?=menu("info", "1W", "?cat=WEEKEND_007d")?>
	    <?=menu("info", "1M", "?cat=WEEKEND_030d")?>
	    <?=menu("info", "2M", "?cat=WEEKEND_060d")?>
	    <?=menu("info", "6M", "?cat=WEEKEND_180d")?>
	    <?=menu("info", "1Y", "?cat=WEEKEND_360d")?>
	</p>
	<hr style="margin-bottom: 6px;"/>


	<p>
		<?=menu("success", "D1", "?cat=daily_begin&days=1")?>
		<span style="floatz:right; "><?=menu("danger", "🌞 D1", "?cat=daily_end&days=1")?></span>

		<?=menu("success", "D3", "?cat=care_f003d")?>
		<?=menu("success", "D7", "?cat=care_f007d")?>
		<?=menu("success", "D14", "?cat=care_f014d")?>
		<?=menu("secondary", "GYM", "?cat=gym&days=8")?>
		<?=menu("secondary", "FGT", "?cat=BOXING&days=6")?>
		
		
		
		
	</p>

	<p>
		<?=menu("warning", "STEP_BY_STEP", "?cat=STEP_BY_STEP&days=1")?>
		<?=menu("success", "KT: PePe", "?cat=GOD_HAND&days=1")?>
		<?=menu("success", "KT: PePe X2", "?cat=GOD_HANDx2&days=2")?>
		<?=menu("success", "KT: SS", "?cat=GOD_SS&days=1")?>
		<?=menu("warning", "☣ AVOID", "?cat=AVOID&days=1")?>
		<?=menu("purple", "TMP", "?cat=TMP")?>
	</p>



	<p>
		
		<?=menu("secondary", "🌘", "?cat=luna&type=luna")?>	
		<?=menu("secondary", "🔧", "?cat=maintain&type=countdown")?>
		<?=menu("secondary", "⌛", "?cat=events&type=countdown")?>
		<?=menu("purple", "CONN", "?cat=connect_all&days=21")?>
		<?=menu("purple", "DEBT", "?cat=DEBT")?>
		<span style="floatz:right; "><?=menu("coin", "> ₿^ALL", "https://tik.lazylearn.com/port.php")?></span>
		<span style="floatz:right; "><?=menu("warning", "⚔Pay", "?cat=f30d_payment")?></span>
	</p>

	<p>	
		
		<?=menu("success", " ☣ Fast ☣ ", "?type=todo&cat=TD_FAST")?>
		<?=menu("success", " ☣ TechDebt ☣ ", "?type=todo&cat=TD_TECHDEBT")?>

		<?=menu("warning", "PePe", "?type=todo&cat=todo_pepe")?>
		


		<?=menu("warning", "- Tốn Sức", "?type=todo&cat=TD_COST_TIME")?>
		<?=menu("warning", "- Tốn Tiền", "?type=todo&cat=TD_COST_MONEY")?>
	</p>

	<hr/>

	<?php if ($cat == "gym") : ?>
		<div style="margin-top: 1px; margin-bottom: 1px;">
			<a onclick="replaceURLParam('gym_mode', 'simple')"><span class="<?= $gym_mode === 'simple' ? 'color_red' : ''?>">[Không Dùng Não]</span></a>&nbsp;&nbsp;&nbsp;&nbsp;
			<a onclick="replaceURLParam('gym_mode', 'complex')"><span class="<?= $gym_mode === 'complex' ? 'color_red' : ''?>">[Bậc Thầy]</span></a>&nbsp;&nbsp;&nbsp;&nbsp;
		</div>


		<?php 
		$expired_muscle_groups = array();
		foreach ($gym_records as $muscleGroup => $val) {
			$passed = getGymHourPassed($gym_records, $muscleGroup);
			$limit = getGymLimitHour($muscleGroup);
			$expired = $passed - $limit;

			if ($muscleGroup == "Recover") {
				continue;
			}
			$expired_muscle_groups[$muscleGroup] = $expired;
		}
		arsort($expired_muscle_groups);
		//
		echo "<p>";
		foreach ($expired_muscle_groups as $muscleGroup => $expiredHours) {
			$ago = gymAgo($expiredHours);
			if (isNotBlank($ago)) {
				$ago = "exp:$ago";
			}

			$htmlAction = "replaceURLParam(\"gym_only_muscle_group\", \"$muscleGroup\")";

			// Size
			if ($expiredHours < 0) {
				$passed = getGymHourPassed($gym_records, $muscleGroup);
				$limit = getGymLimitHour($muscleGroup);
				$percent = round($passed / $limit * 100);
				$hoursLeft = gymAgo(- $expiredHours);
				$name = "${muscleGroup};♥$percent%;nxt:$hoursLeft";
			} else {
				$name = "<strong>$muscleGroup;</strong>";
			}

			// Color
			if ($gym_only_muscle_group == $muscleGroup) {
				$marker = "class='color_red'";
			} else {
				$marker = "";
			}

			$count = get_gym_musble_group_avaiable_items($muscleGroup);
			if ($count == 0 && $gym_mode === 'simple') {
				// Drop muscle group that has no items
			} else {
				$countMark = $count > 0 ? "x$count " : "";
				echo "<a $marker onclick='$htmlAction'>[$countMark$name$ago]</a> &nbsp;&nbsp;";
			}
			
		}

		// Filter remobal
		if (isNotBlank($gym_only_muscle_group)) {
			$htmlAction = "replaceURLParam(\"gym_only_muscle_group\", \"\")";
			echo "<a onclick='$htmlAction'><strong class='color_red'>[rm-filter]</strong></a> &nbsp;&nbsp;";
		}

		echo "</p>";
		?>

	<?php endif; ?>

	<br>
</div>

<style>
.badge-purple {
    color: #000;
    background-color: pink;
}
.badge-coin {
    color: #fff;
    background-color: #a80717;
}
</style>

<div class="row">
	<div class="col-9">
		<div class="alert alert-primary" role="alert">
		  <?=escape($cat)?> [<?= sizeof($tiks) ?>]
		  <?php 
			if (isset($tik_color_day)) {
				$suffix = $tik_color_day > 1 ? "days" : "day";
				echo " [$tik_color_day $suffix]";
			}
			if ($type == "countdown") {
				echo " ⌛";
			} else if ($type== "luna") {
				echo " 🌘";
			}
		  ?>
		</div>
	</div>
	<div class="col-3">
		<?php if ($type == "tik") { ?>
		<div id="skipAll">
			<form method='post'>
				<input type="hidden" name="action_skipAll" value="xxx" />
				<input type="hidden" name="category" value="<?=$cat?>" />
				<input type="submit" class="btn btn-sm btn-warning" value="Skip All" />
			</form>
		</div>
		<?php } ?>
	</div>
</div>





<style>
div#adding button {
  border-radius: 0 !important;
}
div#skipAll {
	margin-bottom: 10px;
}
</style>

<div id="adding">
	<?php if ($type == "tik" || $type == 'todo') { ?>
		<form  method='post'>
			<div class="row">
				<div class="input-group col-lg-4 col-md-6 col-sm-8 col-xs-12">
					<input class="form-control" autofocusz required="true" name="name_" placeholder="Cần 1 cái tên ..."></input>
					<span class="input-group-btn">
						<button class="btn btn-success" type="submit">Add</button>
					</span>
				</div>
			</div>	
		</form>
	<?php } else if ($type == "countdown") { ?>
		<form  method='post'>
			<div class="row">
				<div class="input-group col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<input class="form-control" autofocusz required="true" name="countdown_name" placeholder="Cần 1 cái tên ..."></input>
					<input class="form-control" required="true" name="countdown_tik" placeholder="yyyy mm dd [hh mi]"></input>
					<span class="input-group-btn">
						<button class="btn btn-success" type="submit">Add</button>
					</span>
				</div>
			</div>			
		</form>
	<?php } else if ($type == "luna"){ ?>
		<form  method='post'>
			<div class="row">
				<div class="input-group col-lg-6 col-md-6 col-sm-12 col-xs-12">
					<input class="form-control" autofocusz required="true" name="luna_name" placeholder="Cần 1 cái tên ..."></input>
					<input class="form-control" required="true" name="luna_tik" placeholder="mm dd (ÂL)"></input>
					<span class="input-group-btn">
						<button class="btn btn-success" type="submit">Add</button>
					</span>
				</div>
			</div>				
		</form>
	<?php } ?>
</div>

<div style="margin-top: 15px;" class="col-lg-6">
	<table class="table table-striped table-bordered" id="dataz">
		
		<thead>
			<tr>
				<th>Item</th>

				<?php if ($type == "tik") { ?>
					<th>Tik</th>
				<?php } else if ($type == "todo") { ?>
					<th></th>
				<?php } else { ?>
					<th>Countdown</th>
				<?php } ?>

				<th>&nbsp;</th>
				<th>&nbsp;</th>
				<th style="display: none;"></th>
			</tr>
		</thead>

		<tbody>
		<?php foreach($tiks as $tik ) {
			$skipMillis = strtotime($tik['skip']);
			$tik_id = $tik["id_"];

			// Skip recovery gym item
			if ($cat == 'gym' && $gym_mode == "simple") {
				if (isset($tik['gym_item_recovering']) && $tik['gym_item_recovering'] == 1) {
					continue; // Skip recovering item
				}
			}

			// Filter by muscle-group
			if ($cat == 'gym' && isNotBlank($gym_only_muscle_group)) {
				$focusGroup = $gym_only_muscle_group;
				$search = "[$focusGroup]";
				if (!contains($tik["tik_out_line"], $search)) {
					continue;
				}
			}
		?>
			<tr>
				<?php if ($type == "tik") { ?>
					<td><?=($tik['tik_out_line'])?></td>
					<td class="td-min tik_async_prefix" data-id="<?=$tik['id_']?>"><?=ago2($tik['tik'], false, $tik_color_day, "tik", $skipMillis)?></td>
					<td class="td-min">

						<!-- do tik -->
						<button class="btn btn-sm btn-warning tik_async" data-id="<?=$tik['id_']?>">Tik</button>
						<button class="btn btn-sm btn-info tikskip_async" data-id="<?=$tik['id_']?>">Skip</button>
					</td>


				<?php } else if ($type == 'todo') { 
					
				?>
					<td><?=($tik['name_'])?></td>


				<?php } else if ($type == 'countdown') { 
					$tmp_date = str_replace(" 00:00:00", "", $tik['tik']);
				?>
					<td><?=($tik['name_'])?></td>
					<td class="td-min"><?=ago2($tik['tik'], true, 0, "xx", $skipMillis)?> (<?=$tmp_date?>)</td>
					
					<td class="td-min">
						<form method='post'>
							<input type="hidden" name="action_skip" value="xxx" />
							<input type="hidden" name="id" value="<?=$tik['id_']?>" />
							<input type="submit" classz="btn btn-warning" value="Skip" />
						</form>
					</td>
		
				<?php } else if ($type == 'luna') { ?>
					<td><?=escape($tik['name_'])?></td>
					<td class="td-min"><?=$tik["luna_out_line"]?></td>
				<?php } ?>

				<td class="td-min" id="act_<?=$tik["id_"]?>" style="display: none;">
					<table>
						<tr><td><?=ui_edit($tik, $all_categories)?></td></tr>
					</table>
				</td>

				<?php if ($type == 'todo') : ?>
					<td >
						<div class="button-container">
							
							<?= simpleAction("TOP", ["todo_move_top" => $tik_id], '') ?>
							
							<?= simpleAction("BOT", ["todo_move_bottom" => $tik_id], '') ?>
							<?= simpleAction("UP", ["todo_move_up" => $tik_id], '') ?>
							<?= simpleAction("DOWN", ["todo_move_down" => $tik_id], '') ?>
							<?=ui_del($tik)?>
							<?=ui_toggle($tik)?>
						</div>
					</td>
				<?php endif; ?>
	
				<?php if ($type !== 'todo') : ?>
					<td class="td-min">
						<?=ui_del($tik)?>
						<?=ui_toggle($tik)?>
					</td>
				<?php endif; ?>
			</tr>
		<?php }?>
		</tbody>

	</table>
</div>

<style>
.button-container {
	display: flex;
    /* text-align: center; */
}

.button-container input {
    display: inline-block;
    /* margin: 0 10px;  */
}
</style>

<?php if (startsWith($cat, "connect_")) { ?>
	<script>
		$(document).ready(function() {
			$.extend($.fn.dataTable.defaults, {
				searching: {
					caseInsensitive: true
				}
			});
			
			$("#dataz").DataTable({
				"pageLength": 100,
				"ordering": false
			});
		});
	</script>
<?php } ?>


<?php function ui_del($tik) { ?>
	<button class="btn btn-sm btn-danger tik_delete_btn" data-id="<?=$tik['id_']?>" data-name="<?= $tik['name_'] ?>">Xóa</button>
<?php } ?>


<?php function ui_edit($tik, $all_categories) { 
	$tik_name = $tik["name_"];
	$tik_cat = $tik["category"];
	global $type;
?>
	<form method='post' onSubmitz="return confirm('Ghi nhận SỬA <?=$tik_name?>?');">
		<input type="hidden" name="action_edit" value="xxx" />
		<input type="hidden" name="id" value="<?=$tik["id_"]?>" />
		
		<textarea required="true" rows="<?=INPUT_SIZE_H_NAME?>" cols="<?=INPUT_SIZE_W_NAME?>" name="name"  placeholder="<?=INPUT_HINT_NAME?>"><?=escape($tik_name)?></textarea>
		
		<select name="category" id="ca">
			<?php foreach($all_categories as $category ) {
				$cat = $category["category"];
			?>
				<option <?=$tik_cat == $cat ? "selected " : " "?> value="<?=$cat?>"><?=$cat?></option>
			<?php } ?>
		</select>
		
		
		<input <?= $type=='todo' ? ' type="hidden" ' : '' ?> size="<?=INPUT_SIZE_DATETIME?>" name="ts" placeholder="<?=INPUT_HINT_DATETIME?>" value="<?=$tik['tik']?>"></input>

		<input type="submit" value="Submit" />
	</form>
<?php } ?>


<?php function ui_toggle($tik) { ?>
	<button classz="btn btn-warning" onclick="toggle_<?=$tik['id_']?>()">[...]</button>
	<script>
		function toggle_<?=$tik['id_']?>() {
			var x = document.getElementById("act_<?=$tik['id_']?>");
			if (x.style.display === "none") {
				x.style.display = "block";
			} else {
				x.style.display = "none";
			}
		}
	</script>
<?php } ?>


<br>
<br>
<br>
<br>
<br>
<?php
page_bot ();
db_close ();
?>


<script>
	$(document).on('click', ".tik_async" , function() {
		blockui();

		var tikId = $(this).data('id');
		const formData = new FormData();
		formData.append('tik_id_async', tikId);

		axios.post('index.php', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
			.then(response => {
				if (response.data != "OK") {
					alert("Failed to TIK");
					return;
				}
				//
				$('td.tik_async_prefix[data-id="' + tikId + '"]').text('now');
				//
				var button = $('button.tik_async[data-id="' + tikId + '"]');
				button.removeClass('btn-warning').addClass('btn-default');
				//
				<?php if ($cat == 'gym'): ?>
					location.reload();
				<?php else: ?>
					reloadHeaders('current');
				<?php endif; ?>
				
			})
			.catch(error => {
				alert("Failed to TIK");
			})
			.finally(() => {
				unblockui();
			});
	});

	$(document).on('click', ".tikskip_async" , function() {
		blockui();

		var tikId = $(this).data('id');
		const formData = new FormData();
		formData.append('action_skip_async_id', tikId);

		axios.post('index.php', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
			.then(response => {
				if (response.data != "OK") {
					alert("Failed to Skip");
					return;
				}
				//
				var prefix = $('td.tik_async_prefix[data-id="' + tikId + '"] strong');
				prefix.css('color', 'pink');
				//
				var button = $('button.tikskip_async[data-id="' + tikId + '"]');
				button.removeClass('btn-info').addClass('btn-default');
				//
				<?php if ($cat == 'gym'): ?>
					location.reload();
				<?php else: ?>
					reloadHeaders('current');
				<?php endif; ?>
			})
			.catch(error => {
				alert("Failed to Skip");
			})
			.finally(() => {
				unblockui();
			});
	});

	$(document).on('click', ".tik_delete_btn" , function() {
		blockui();

		var tikId = $(this).data('id');
		var tikName = $(this).data('name');
		
		const isConfirmed = confirm("XÓA LUÔN ĐÓ ANH? >> " + tikName);
		if (!isConfirmed) {
			return;
		}
		
		const formData = new FormData();
		formData.append('rm_id_async', tikId);

		axios.post('index.php', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
			.then(response => {
				if (response.data != "OK") {
					alert("Failed to Delete");
					return;
				}
				//
				var button = $('button.tik_delete_btn[data-id="' + tikId + '"]');
				const tr = button.closest('tr');
				if (tr) {
					tr.remove();
				}
				//
				reloadHeaders('current');
			})
			.catch(error => {
				alert("Failed to Delete");
			})
			.finally(() => {
				unblockui();
			});
	});
</script>

<script>
	function reloadHeaders(type) {

		$(".hed").each(function(index) {
			var ref = $(this).parent().attr("href");
			if (ref.includes("http")) {
				return;
			}

			if (type == 'current') {
				var currentRef = window.location.search;
				if (ref != currentRef) {
					return;
				}
			}
			
			var el = $(this);
			$.ajax(`./${ref}&kind=xhr`).done(function(res) {
				var badge = res.badge;
				if (badge) {
					el.next().html(`${badge}`);
				} else {
					el.next().html("");
				}
			});
			
		});
	}
	reloadHeaders('all');





	function replaceURLParam(paramName, paramNewValue) {
		// Get the current URL
		var currentURL = window.location.href;
			
		// Regular expression to find and replace the parameter value
		var regex = new RegExp('(' + paramName + '=)([^&]*)');
		
		// Check if the parameter exists in the current URL
		if (currentURL.match(regex)) {
			// If the parameter exists, replace its value
			var newURL = currentURL.replace(regex, '$1' + paramNewValue);
		} else {
			// If the parameter doesn't exist, add it to the URL
			var separator = currentURL.includes('?') ? '&' : '?';
			var newURL = currentURL + separator + paramName + '=' + paramNewValue;
		}
			
		// Update the URL
		window.history.replaceState({}, '', newURL);
		console.log('Updated URL:', newURL);

		window.location.href = newURL;
	}
</script>