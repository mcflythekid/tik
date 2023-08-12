<?php
session_start ();
require_once ("_core.php");
require_once ("luna.php");
page_auth ();

db_open ();
$cat = isset($_GET['cat']) ? $_GET['cat'] : "TODO";
$type = isset($_GET['type']) ? $_GET['type'] : "tik";

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
	db_query("update tik set tik = now(), counter = counter + 1 where id_ = $tik_id");
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

$order_by = 'tik asc';
if (in_array($cat, array("TODO", "TODO2", "cpg", "w"))) {
    $order_by = 'name_ asc';
}
$tiks = db_list("select id_, name_, tik, category, counter from tik where username = '$username' and category = '$cat' and type_ = '$type' order by $order_by");


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
	$tik["tik_out_line"] = $count > 0 ? "$name ⟳$count" : $name;
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

page_top ();
?>

<div>
	<?php function menu($type, $text, $href) { ?>
		<a href="<?=$href?>"><span class="badge badge-<?=$type?>"><?=$text?></span></a>&nbsp;&nbsp;
	<?php } ?>

	<p>
		<?=menu("primary", "⌛ 🔧", "?cat=maintain&type=countdown")?>
		<?=menu("primary", "⌛ EVT", "?cat=events&type=countdown")?>
		<?=menu("primary", "🌘", "?cat=luna&type=luna")?>
		<?=menu("primary", "₿", "/port.php?fund_type=FFA")?>
		<?=menu("primary", "📖", "https://lazylearn.com/deck.php")?>
		<?=menu("primary", "W", "?cat=w&days=1")?>
	</p>
	
	<p>
		<?=menu("primary", "x1", "?cat=TODO&days=1")?>
		<?=menu("primary", "x6", "?cat=TODO2&days=3")?>
		<?=menu("primary", "CPG", "?cat=cpg")?>
		<?=menu("primary", "W", "?cat=wishlist")?>
		
		<?=menu("info", "C4", "?cat=connect30_1st&days=4")?>
		<?=menu("info", "C21", "?cat=connect30_close&days=21")?>
		
		<?=menu("info", "P", "?cat=f30d_payment")?>
		<?=menu("info", "P15", "?cat=f30d_15th")?>
	</p>	
	
	<p>
		<?=menu("warning", "Adversary", "?cat=adversary")?>
		<?=menu("warning", "SG todo", "?cat=sg")?>
	</p>	
	
	<p>
		<?=menu("warning", "T", "?cat=f1task&days=1")?>
		<?=menu("warning", "T'", "?cat=f1task_b&days=1")?>
		
		<?=menu("warning", "C", "?cat=f1care&days=1")?>
		<?=menu("warning", "C'", "?cat=f1care_b&days=1")?>
		<?=menu("warning", "L", "?cat=f1learn&days=1")?>
		
		<?=menu("secondary", "GYM", "?cat=gym&days=6")?>
		<?=menu("secondary", "⚔️", "?cat=BOXING&days=3")?>
	</p>
	
	<p>
		<?=menu("info", "F3", "?cat=f3d")?>
		<?=menu("info", "F7", "?cat=f7d")?>
		<?=menu("info", "F14", "?cat=f14d")?>
		<?=menu("info", "F30", "?cat=f30d")?>
		<?=menu("info", "F60", "?cat=f60d")?>
		<?=menu("info", "F90", "?cat=f90d")?>
		<?=menu("info", "F180", "?cat=f180d")?>
	</p>
	
	<p>
		<?=menu("warning", "SS", "?cat=SS&days=1")?>
		<?=menu("warning", "S", "?cat=SS1&days=1")?>
		<?=menu("warning", "S'", "?cat=SS1_b&days=1")?>
		<?=menu("info", "S3", "?cat=SS_03&days=3")?>
		<?=menu("info", "S7", "?cat=SS_07&days=7")?>
		<?=menu("info", "S14", "?cat=SS_14&days=14")?>
		<?=menu("info", "S30", "?cat=SS_30&days=30")?>
	</p>
	

	
	<p>

	</p>
</div>

<div style="float:right; ">
	<form action='/logout.php' onSubmit="return confirm('thoát nha anh hai');">
		<input type="submit" class="btn btn-sm btn-danger" value=">logout" />
	</form>
</div>

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

<style>
div#adding button {
  border-radius: 0 !important;
}
</style>

<div id="adding">
	<?php if ($type == "tik") { ?>
		<form  method='post'>
			<div class="row">
				<div class="input-group col-lg-4 col-md-6 col-sm-8 col-xs-12">
					<input class="form-control" autofocus required="true" name="name_" placeholder="Cần 1 cái tên ..."></input>
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
					<input class="form-control" autofocus required="true" name="countdown_name" placeholder="Cần 1 cái tên ..."></input>
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
					<input class="form-control" autofocus required="true" name="luna_name" placeholder="Cần 1 cái tên ..."></input>
					<input class="form-control" required="true" name="luna_tik" placeholder="mm dd (ÂL)"></input>
					<span class="input-group-btn">
						<button class="btn btn-success" type="submit">Add</button>
					</span>
				</div>
			</div>				
		</form>
	<?php } ?>
</div>

<div style="margin-top: 15px;">
<table class="table table-striped">

<!-- header -->
<tr>
	<th>Item</th>

	<?php if ($type == "tik") { ?>
		<th>Tik</th>
		<th></th>
	<?php } else { ?>
		<th>Countdown</th>
	<?php } ?>

	<th></th>
	<th></th>
	<th></th>

<tr>

<!-- body -->
<?php foreach($tiks as $tik ) {?>
<tr>
	

	<?php if ($type == "tik") { ?>
		<td><?=escape($tik['tik_out_line'])?></td>
		<td><?=ago2($tik['tik'], false, $tik_color_day)?></td>
		<td>
			<form method='post' onSubmit="return confirm('chắc chưa đại vương? <?=escape($tik['name_'])?>');">
				<input type="hidden" name="tik_id" value="<?=$tik['id_']?>" />
				<input type="submit" classz="btn btn-success" value="Tik" />
			</form>
		</td>
		
		
	<?php } else if ($type == 'countdown') { 
		$tmp_date = str_replace(" 00:00:00", "", $tik['tik']);
	?>
		<td><?=escape($tik['name_'])?></td>
		<td><?=ago2($tik['tik'], true)?> (<?=$tmp_date?>)</td>
		
		
	<?php } else if ($type == 'luna') { ?>
		<td><?=escape($tik['name_'])?></td>
		<td><?=$tik["luna_out_line"]?></td>
	<?php } ?>

	<td><?=ui_del($tik)?></td>
	
	<td id="act_<?=$tik["id_"]?>" style="display: none;">
		<table>
			<tr><td><?=ui_edit($tik, $all_categories)?></td></tr>
		</table>
	</td>
	
	<td><?=ui_toggle($tik)?></td>
</tr>
<?php }?>

</table>
</div>


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
	<form method='post' onSubmit="return confirm('Ghi nhận SỬA <?=$tik_name?>?');">
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



<?php
page_bot ();
db_close ();