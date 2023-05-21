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

page_title ( "$type for $cat" );
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
	db_query("update tik set tik = now() where id_ = $tik_id");
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



	$tiks = db_list("select id_, name_, tik from tik where username = '$username' and category = '$cat' and type_ = '$type' order by tik asc");


page_top ();
?>

<div>
	<p>
		
		<a href="?cat=connect30">MEET</a>&nbsp;&nbsp;
		<a href="?cat=maintain&type=countdown">MAINTAIN</a>&nbsp;&nbsp;
		<a href="?cat=wishlist">WISH</a>&nbsp;&nbsp;
		<a href="?cat=luna&type=luna">LUNA</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=FFA">FFA</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=TRADE">TRADE</a>&nbsp;&nbsp;
	</p>
	<p>
		<a href="?cat=MATTER-RUSH">!!!</a>&nbsp;&nbsp;
		<a href="?cat=MATTER-DELAY">!!!DELAY</a>&nbsp;&nbsp;
		<a href="?cat=RUSH-only">RUSH</a>&nbsp;&nbsp;
		<a href="?cat=NONSENSE">SUCK</a>&nbsp;&nbsp;
		<a href="?cat=todo">2do</a>&nbsp;&nbsp;
	</p>	
	<p>
		<a href="?cat=KMS&days=1">KMS</a>&nbsp;&nbsp;
		<a href="?cat=gym&days=5">GYM</a>&nbsp;&nbsp;
		<a href="?cat=BOXING&days=3">BOXING</a>&nbsp;&nbsp;
		<a href="?cat=LEARN&days=2">LEARN</a>&nbsp;&nbsp;
	</p>
	<p>
		<a href="?cat=f1d">TASK</a>&nbsp;
		<a href="?cat=f1d_care">CARE</a>&nbsp;
		<a href="?cat=f3d">F3</a>&nbsp;
		<a href="?cat=f7d">F7</a>&nbsp;
		<a href="?cat=f14d">F14</a>&nbsp;
		<a href="?cat=f30d">F30</a>&nbsp;
		<a href="?cat=f60d">F60</a>&nbsp;
		<a href="?cat=f90d">F90</a>&nbsp;
		<a href="?cat=f180d">F180</a>&nbsp;
	</p>
	<p>
		<a href="?cat=f30d_15th">Monthly_15th</a>&nbsp;&nbsp;
		<a href="?cat=f30d_payment">Monthly_Payment</a>&nbsp;&nbsp;
	</p>
</div>

<div style="float:right; ">
	<form 
		action='/logout.php'
		onSubmit="return confirm('thoát nha anh hai');"
	>
		<input type="submit" value=">logout" />
	</form>
</div>




<h1><?=escape($cat)?>
<?php 
	if ($type == "countdown") {
		echo "(countdown)";
	} else if ($type== "luna") {
		echo "(luna)";
	}
	
	if (isset($tik_color_day)) {
		echo " <$tik_color_day day(s)>";
	}
?>
</h1>

<div>
	<?php if ($type == "tik") { ?>
	<form  method='post'>
		<input required="true" name="name_" placeholder="Cần 1 cái tên ..."></input>
		<input type="submit" value="Thêm"></input>
	</form>
	<?php } else if ($type == "countdown") { ?>
		<form  method='post'>
		<input required="true" name="countdown_name" placeholder="Cần 1 cái tên ..."></input>
		<input required="true" name="countdown_tik" placeholder="yyyy mm dd [hh mi]"></input>
		<input type="submit" value="Thêm"></input>
	</form>
	<?php } else if ($type == "luna"){ ?>
		<form  method='post'>
		<input required="true" name="luna_name" placeholder="Tên sự kiện âm lịch..."></input>
		<input required="true" name="luna_tik" placeholder="mm dd (Âm lịch)"></input>
		<input type="submit" value="Thêm"></input>
	</form>
	<?php } ?>
</div>

<table class="table table-striped">

<!-- header -->
<tr>
	<th>Item</th>

	<?php if ($type == "tik") { ?>
		<th>Tik</th>
		<th>#</th>
		<th>!!!</th>
	<?php } else { ?>
		<th>Boom!!! on</th>
		<th>!!!</th>
	<?php } ?>


<tr>

<!-- body -->
<?php foreach($tiks as $tik ) {?>
<tr>
	<td><?=escape($tik['name_'])?></td>

	<?php if ($type == "tik") { ?>
		<td><?=ago2($tik['tik'], false, $tik_color_day)?></td>
		<td>
			<form 
				method='post'
				onSubmit="return confirm('chắc chưa đại vương? <?=escape($tik['name_'])?>');"
			>
				<input type="hidden" name="tik_id" value="<?=$tik['id_']?>" />
				<input type="submit" value="Tik" />
			</form>
		</td>
		
		
	<?php } else if ($type == 'countdown') { 
		$tmp_date = str_replace(" 00:00:00", "", $tik['tik']);
	?>
		<td><?=ago2($tik['tik'], true)?> (<?=$tmp_date?>)</td>
		
		
	<?php } else if ($type == 'luna') {
		
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
	
	?>
		<td><?=ago2("$sola_year-$sola_month-$sola_day 12:00:00", false, 30, "luna")?> | <?="$sola_year-$sola_month-$sola_day | Nhằm ngày $luna_day tháng $luna_month âm"?></td>
	<?php } ?>


	<td>
		<form 
			method='post'
			onSubmit="return confirm('XÓA LUÔN ĐÓ ANH? <?=escape($tik['name_'])?>');"
		>
			<input type="hidden" name="rm_id" value="<?=$tik['id_']?>" />
			<input type="submit" value="Xóa" />
		</form>
	</td>
</tr>
<?php }?>

</table>

<?php
page_bot ();
db_close ();