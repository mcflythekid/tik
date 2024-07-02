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
if (isset($_POST['name_'])) {
	$new_name = $_POST['name_'];
	db_query("insert into tik (username, category, type_, name_, tik) values ('$username', '$cat', 'tik', '$new_name', now() - interval 10 year)");
	header("Refresh:0");
	exit;
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
if (isset($_POST['tik_id'])) {
	$tik_id = $_POST['tik_id'];
	db_query("update tik set tik = now(), counter = counter + 1, skip = NULL where id_ = $tik_id");
	header("Refresh:0");
	exit;
}

// removal
if (isset($_POST['rm_id'])) {
	$rm_id = $_POST['rm_id'];
	db_query("delete from tik where id_ = $rm_id");
	header("Refresh:0");
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
if (has_httppost("action_skip") == true) {
	$req_id = get_httppost("id");

	$endString = date('Y-m-d 23:59:59', time());
	$endMillis = strtotime($endString);
	
	db_query("update tik set skip = FROM_UNIXTIME($endMillis) where id_ = '$req_id'");
	header("Refresh:0");
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

$order_by = 'tik asc';
if (in_array($cat, array("TODO", "TODO2", "cpg", "w"))) { // No tik
    $order_by = 'name_ asc';
}
$tiks = db_list("select id_, name_, tik, category, counter, skip from tik where username = '$username' and category = '$cat' and type_ = '$type' order by $order_by");


$all_categories = db_list("select distinct category from tik order by category");

foreach ($tiks as &$tik) {
	if ($type == "luna"){
		handle_luna($tik);
	} else if ($type == "tik"){
		handle_normal($tik);
	}
} 
unset($tik); // https://stackoverflow.com/questions/7158741/why-php-iteration-by-reference-returns-a-duplicate-last-record
// https://bugs.php.net/bug.php?id=29992


function handle_normal(&$tik) {
	$name = $tik["name_"];
	$count = $tik["counter"];
	$tik["tik_out_line"] = $count > 0 ? "$name <strong style='color: orange;'>⟳$count</strong>" : $name;
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
		
		$tmp_luna_obj = $luna->convertSolar2Lunar(idate("d"),idate("m"),idate("Y") + 1,7);
		$tmp_luna_leap = $tmp_luna_obj[3];
		
		$sola_obj = $luna->convertLunar2Solar($luna_day, $luna_month, $now_luna_year + 1,$tmp_luna_leap,7);
		$sola_year = $sola_obj[2];
		$sola_month = $sola_obj[1];
		$sola_day = $sola_obj[0];
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
			if (strpos($line, "green") !== false) {
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
// var_dump($weather);
// exit;

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

</style>

<div class="menu">
	<?php function menu($type, $text, $href) { ?>
		<a class="menu-item" href="<?=$href?>">
			<span class="hed badge badge-<?=$type?>"><?=$text?></span>
			<span style="vertical-align: super; color: red;"></span>
		</a>
	<?php } ?>
	
	<div style="float:right; ">
		<form action='/logout.php' onSubmit="return confirm('thoát nha anh hai');">
			<input type="submit" class="btn btn-sm btn-warning" value="🌚" />
		</form>
	</div>
	
	
	<hr />
	<!-- <p>
		<span class="line-head"></span>
		<?=menu("info", $weather["temp"] . "℃", "#")?>
		<?=menu("info",  ($weather["hum"] - 0) . "%", "#")?>
		<?=menu("info",  date("H:i:s", strtotime($weather["ts"])), "#")?>
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
		<?=menu("success", "D3", "?cat=care_f003d")?>
		<?=menu("success", "D7", "?cat=care_f007d")?>
		<?=menu("success", "D14", "?cat=care_f014d")?>
		<?=menu("secondary", "GYM", "?cat=gym&days=7")?>
		<?=menu("secondary", "FGT", "?cat=BOXING&days=6")?>
		<?=menu("secondary", "GOD", "?cat=LEARN&days=1")?>
		<span style="floatz:right; "><?=menu("warning", "⚔Pay", "?cat=f30d_payment")?></span>
		
		<!-- <span style="floatz:right; "><?=menu("danger", "🌞 End", "?cat=daily_end&days=1")?></span> -->
	</p>



	<p>
		<?=menu("secondary", "🌘", "?cat=luna&type=luna")?>	
		<?=menu("secondary", "🔧", "?cat=maintain&type=countdown")?>
		<?=menu("secondary", "⌛", "?cat=events&type=countdown")?>
		<?=menu("secondary", "CPG", "?cat=cpg")?>
		<?=menu("purple", "CONN", "?cat=connect_all&days=21")?>
		<?=menu("purple", "DEBT", "?cat=DEBT")?>
		<span style="floatz:right; "><?=menu("coin", "> ₿^ALL", "/port.php")?></span>
	</p>
	<hr/>
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
		  <?=escape($cat)?>
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
	<?php if ($type == "tik") { ?>
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
		?>
			<tr>
				<?php if ($type == "tik") { ?>
					<td><?=($tik['tik_out_line'])?></td>
					<td class="td-min"><?=ago2($tik['tik'], false, $tik_color_day, "tik", $skipMillis)?></td>
					<td class="td-min">
						<form method='post' onSubmitz="return confirm('chắc chưa đại vương? <?=escape($tik['name_'])?>');">
							<input type="hidden" name="tik_id" value="<?=$tik['id_']?>" />
							<input type="submit" classz="btn btn-success" value="Tik" />
						</form>

						<form method='post'>
							<input type="hidden" name="action_skip" value="xxx" />
							<input type="hidden" name="id" value="<?=$tik['id_']?>" />
							<input type="submit" classz="btn btn-warning" value="Skip" />
						</form>
					</td>
		
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
	
				<td class="td-min">
					<?=ui_del($tik)?>
					<?=ui_toggle($tik)?>
				</td>
			</tr>
		<?php }?>
		</tbody>

	</table>
</div>

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
	<form  method='post' onSubmit="return confirm('XÓA LUÔN ĐÓ ANH? <?=escape($tik['name_'])?>');" >
		<input type="hidden" name="rm_id" value="<?=$tik['id_']?>" />
		<input type="submit" value="Xóa" classz="btn btn-danger"/>
	</form>
<?php } ?>


<?php function ui_edit($tik, $all_categories) { 
	$tik_name = $tik["name_"];
	$tik_cat = $tik["category"];
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
		
		
		<input size="<?=INPUT_SIZE_DATETIME?>" name="ts" placeholder="<?=INPUT_HINT_DATETIME?>" value="<?=$tik['tik']?>"></input>
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
	$(".hed").each(function(index) {
		var ref = $(this).parent().attr("href");
		if (ref.includes("http")) {
			return;
		}
		
		var el = $(this);
		$.ajax(`./${ref}&kind=xhr`).done(function(res) {
			var badge = res.badge;
			if (badge) {
				el.next().html(`${badge}`);
			}
		});
		
	});
</script>