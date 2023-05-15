<?php
session_start ();
require_once ("_core.php");
page_auth ();

db_open ();
$cat = isset($_GET['cat']) ? $_GET['cat'] : "TODO";
$type = isset($_GET['type']) ? $_GET['type'] : "tik";
if ($type != "tik") {
	$type = "countdown";
}

page_title ( "$type for $cat" );
$username = $_SESSION['username'];




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
		
		<a href="?cat=connect">CONNECT</a>&nbsp;&nbsp;
		<a href="?cat=maintain&type=countdown">MAINTAIN</a>&nbsp;&nbsp;
		<a href="?cat=wishlist">WISHLIST</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=FFA">PORTFOLIO</a>&nbsp;&nbsp;
	</p>
	<p>
		<a href="?cat=MATTER-RUSH">MATTER-RUSH</a>&nbsp;&nbsp;
		<a href="?cat=MATTER-DELAY">MATTER-DELAY</a>&nbsp;&nbsp;
		<a href="?cat=RUSH-only">RUSH only</a>&nbsp;&nbsp;
		<a href="?cat=NONSENSE">NONSENSE</a>&nbsp;&nbsp;
		<a href="?cat=todo">2do</a>&nbsp;&nbsp;
	</p>	
	<p>
		<a href="?cat=KMS">KMS</a>&nbsp;&nbsp;
		<a href="?cat=gym">GYM</a>&nbsp;&nbsp;
		<a href="?cat=BOXING">BOXING</a>&nbsp;&nbsp;
		<a href="?cat=LEARN">LEARN</a>&nbsp;&nbsp;
	</p>
	<p>
		<a href="?cat=f1d">F1</a>&nbsp;&nbsp;
		<a href="?cat=f7d">F7</a>&nbsp;&nbsp;
		<a href="?cat=f14d">F14</a>&nbsp;&nbsp;
		<a href="?cat=f30d">F30</a>&nbsp;&nbsp;
		<a href="?cat=f180d">F180</a>&nbsp;&nbsp;
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




<h1><?=escape($cat)?> <?= $type != "tik" ? "(countdown)" : "" ?></h1>

<div>
	<?php if ($type == "tik") { ?>
	<form  method='post'>
		<input required="true" name="name_" placeholder="Cần 1 cái tên ..."></input>
		<input type="submit" value="Thêm"></input>
	</form>
	<?php } else { ?>
		<form  method='post'>
		<input required="true" name="countdown_name" placeholder="Cần 1 cái tên ..."></input>
		<input required="true" name="countdown_tik" placeholder="yyyy mm dd [hh mi]"></input>
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
		<td><?=ago($tik['tik'])?></td>
		<td>
			<form 
				method='post'
				onSubmit="return confirm('chắc chưa đại vương? <?=escape($tik['name_'])?>');"
			>
				<input type="hidden" name="tik_id" value="<?=$tik['id_']?>" />
				<input type="submit" value="Tik" />
			</form>
		</td>
	<?php } else { 
		$tmp_date = str_replace(" 00:00:00", "", $tik['tik']);
	?>
		<td><?=ago($tik['tik'], true)?> (<?=$tmp_date?>)</td>
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