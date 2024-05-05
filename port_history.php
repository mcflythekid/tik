<?php


// ##
session_start ();
require_once ("_core.php");
page_auth ();
db_open();


// ##
$session_username = $_SESSION['username'];
$param_port_id = get_httpget("port_id", "FFA");


$fund_types = db_list("select distinct(fund_type) from portfolio");
if (isset($_POST["fund_type"])) {
	$fund_type_param = $_POST["fund_type"];
	db_query("update portfolio set fund_type = '$fund_type_param' where id_ = '$param_port_id'");
	header("refresh: 0");
	exit;
}

// ##
$port = db_object("select name_, coin_code, fund_type from portfolio where id_ = '$param_port_id'");
$port_name = $port["name_"];
$port_coin_code = $port["coin_code"];
$port_fund_type = $port["fund_type"];
page_title("History | $port_coin_code | $port_name");


// ##
if (has_httppost("action_del") == true) {
	$req_trans_id = get_httppost("tran_id");
	db_query("delete from portfolio_trans where id_ = $req_trans_id");
	header("Refresh:0");
	exit;
}

if (has_httppost("action_buy") == true) {
	$req_coin = abs(get_httppost("coin"));
	$req_usd = abs(get_httppost("usd"));
	$req_note = get_httppost("note");
	$req_ts = get_httppost("ts");
	$cal_ts = ts_or_now($req_ts);
	
	db_query("insert into portfolio_trans (username, port_id, type, amount_coin, amount_usd, note, ts)	values ('$session_username', '$param_port_id', 'buy', '$req_coin', '$req_usd', '$req_note', FROM_UNIXTIME($cal_ts))");
	header("Refresh:0");
	exit;
}

if (has_httppost("action_sell") == true) {
	$cal_type = "sell";
	if (get_httppost("coin") == "spent") {
		$cal_type = "spent";
	} else if (get_httppost("coin") == "move") {
		$cal_type = "move";
	}
	
	$req_coin = abs(get_httppost("coin"));
	$req_usd = abs(get_httppost("usd"));
	$req_note = get_httppost("note");
	$req_ts = get_httppost("ts");
	$cal_ts = ts_or_now($req_ts);
	
	db_query("insert into portfolio_trans (username, port_id, type, amount_coin, amount_usd, note, ts)	values ('$session_username', '$param_port_id', '$cal_type', '$req_coin', '$req_usd', '$req_note', FROM_UNIXTIME($cal_ts))");
	header("Refresh:0");
	exit;
}

if (has_httppost("action_edit") == true) {
	$req_trans_id = get_httppost("tran_id");
	$req_coin = abs(get_httppost("coin"));
	$req_usd = abs(get_httppost("usd"));
	$req_note = get_httppost("note");
	$req_ts = get_httppost("ts");
	$cal_ts = ts_or_now($req_ts);
	
	db_query("update portfolio_trans set amount_coin = '$req_coin', amount_usd = '$req_usd', note = '$req_note', ts = FROM_UNIXTIME($cal_ts) where id_ = '$req_trans_id'");
	header("Refresh:0");
	exit;
}

$trans = db_list("select id_, type, amount_coin, amount_usd, note, ts, (amount_usd / amount_coin) as price from portfolio_trans where username = '$session_username' and port_id = '$param_port_id' order by ts asc");




$quantity = 0;
//
$sum_sell = 0;
$sum_buy = 0;
//
$sum_cash_theorycal = 0;
$sum_cash_avaiable = 0;
//
$health_cost = 0;
$tran_index = 0;
//
foreach($trans as $key => &$tran) {
	$tran_amount = $tran["amount_usd"];
	$tran_quantity = $tran["amount_coin"];

	// For quantity, sum buy/sell
	if ($tran["type"] == "sell") {
		$quantity -= $tran_quantity;
		$sum_sell += $tran_amount;
	} else if ($tran["type"] == "buy") {
		$quantity += $tran_quantity;
		$sum_buy += $tran_amount;
	}
	
	// For on hand, health cost
	if ($tran_index++ > 0) {
		if ($tran["type"] == "spent") { // Spending (move external. e.g. buying a car)
			reduceToZero($sum_cash_theorycal, $tran_amount);
			reduceToZero($sum_cash_avaiable, $tran_amount);
			
		} else if ($tran["type"] == "move") { // Moving funds internal
			reduceToZero($sum_cash_avaiable, $tran_amount);

		} else if ($tran["type"] == "sell") { // Sell
			$sum_cash_theorycal += $tran_amount;
			$sum_cash_avaiable += $tran_amount;
			
			
		} else { // Buy
			if ($tran_amount > $sum_cash_avaiable) {
				$health_cost += $tran_amount - $sum_cash_avaiable;
			}

			reduceToZero($sum_cash_theorycal, $tran_amount);
			reduceToZero($sum_cash_avaiable, $tran_amount);
		}
		
		
	} else { // First BUY transaction
		$health_cost += $tran_amount;
	}
	
	$tran["sum_cash_theorycal"] = $sum_cash_theorycal + 0;
	$tran["sum_cash_avaiable"] = $sum_cash_avaiable + 0;
}
unset($tran);

function cmpx($a, $b) {
    return $b["id_"] > $a["id_"];
}
usort($trans, "cmpx");

page_top ();
?>


<div style="float:right; ">
	<form 
		action='/logout.php'
		onSubmit="return confirm('thoát nha anh hai');"
	>
		<input type="submit" value=">logout" />
	</form>
</div>

<div>
	<form method="post">
		<select name="fund_type">
			<?php
			var_dump($fund_types);
			foreach ($fund_types as $fund_type) {
				$item = $fund_type["fund_type"];
				echo "<option value=\"$item\">$item</option>";
			}
			?>
		</select>
		<button type="submit">CHange category</button>
	</form>
</div>

<p>
	UUID: <?=$param_port_id?><br>
	Name: <?=$port_name?><br>
	COIN: <?=$port_coin_code?><br>
	Fund Type: <?=$port_fund_type?>
</p>

<p>
<strong>Quantity:</strong> <?=$quantity?>&nbsp;&nbsp;
<!-- on_hand: <?=$sum_cash_theorycal?>&nbsp;&nbsp; -->
<strong>HealthCost:</strong> <?=$health_cost?>&nbsp;&nbsp;

<!-- sum_sell: <?=$sum_sell?>&nbsp;&nbsp; -->
<!-- sum_buy: <?=$sum_buy?>&nbsp;&nbsp; -->
</p>


<table>
<tr>
	<td>
		<form method='post' onSubmit="return confirm('Ghi nhận MUA?');">
			<input type="hidden" name="action_buy" value="xxx" />
			<input type="hidden" name="port_id" value="<?=$param_port_id?>" />
			<input required="true" size="<?=INPUT_SIZE_COUNTING?>" name="coin" placeholder="<?=INPUT_HINT_QUANTITY?>"></input>
			<input required="true" size="<?=INPUT_SIZE_COUNTING?>" name="usd" placeholder="<?=INPUT_HINT_MONEY?>"  <?php if ($port_coin_code == "USD") : ?> value="0" <?php endif; ?> ></input>
			<input required="true" size="<?=INPUT_SIZE_W_NOTE?>"   name="note" placeholder="<?=INPUT_HINT_NOTE?>"  <?php if ($port_coin_code == "USD") : ?> value="--" <?php endif; ?> ></input>
			<input size="<?=INPUT_SIZE_DATETIME?>" name="ts" placeholder="<?=INPUT_HINT_DATETIME?>"></input>
			<input type="submit" value="BUY" />
		</form>
	</td>
	<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
	<td>
		<form method='post' onSubmit="return confirm('Ghi nhận BÁN/Rút?');">
			<input type="hidden" name="action_sell" value="xxx" />
			<input type="hidden" name="port_id" value="<?=$param_port_id?>" />
			<input required="true" size="<?=INPUT_SIZE_COUNTING?>" name="coin" placeholder="<?=INPUT_HINT_QUANTITY_WITHDRAW?>"></input>
			<input required="true" size="<?=INPUT_SIZE_COUNTING?>" name="usd" placeholder="<?=INPUT_HINT_MONEY?>"  <?php if ($port_coin_code == "USD") : ?> value="0" <?php endif; ?> ></input>
			<input required="true" size="<?=INPUT_SIZE_W_NOTE?>"   name="note" placeholder="<?=INPUT_HINT_NOTE?>"  <?php if ($port_coin_code == "USD") : ?> value="--" <?php endif; ?> ></input>
			<input size="<?=INPUT_SIZE_DATETIME?>" name="ts" placeholder="<?=INPUT_HINT_DATETIME?>"></input>
			<input type="submit" value="SELL" />
		</form>
	</td>
</tr>
</table>
<br>

<p>
	<a target="_self" href="/port.php?fund_type=<?=$port_fund_type?>">Back to Portfolio</a>
</p>

<table class="table table-striped">

<!-- header -->
<tr>
	<th>Type</th>
	<th>Quantity</th>
	<th>USD</th>
	<th>~Price</th>
	<th>Σ $Theorycal</th>
	<th>Σ $Avaiable</th>
	<th>Note</th>
	<th>Time</th>
	<th>#</th>
	<th>#</th>
	<th>#</th>
<tr>

<!-- body -->
<?php foreach($trans as $tran ) {?>
<tr>
	<td>
		<?php 
			$type = $tran['type'];

			if ($type == "buy") {
				echo '<strong style="color: red;">Buy</strong>';
			} else if ($type == "sell") {
				echo '<strong style="color: Green;">Sell</strong>';
			} else {
				echo $type;
			}
		?>
	</td>

	<td><?=digit($tran['amount_coin'], 9)?></td>
	<td><?=digit($tran['amount_usd'])?></td>
	<td><?=digit($tran['price'], 9)?></td>
	<td><?=digit($tran['sum_cash_theorycal'], 9)?></td>
	<td><?=digit($tran['sum_cash_avaiable'], 9)?></td>
	<td><?=escape($tran['note'])?></td>
	<td><?=escape($tran['ts'])?></td>
	<td><?=ui_del($tran)?></td>
	
	<td id="act_<?=$tran["id_"]?>" style="display: none;">
		<table>
			<tr><td><?=ui_edit($tran)?></td></tr>
		</table>
	</td>
	<td><?=ui_toggle($tran)?></td>
</tr>
<?php }?>

</table>


<?php function ui_edit($tran) { ?>
	<form method='post' onSubmit="return confirm('Ghi nhận SỬA?');">
		<input type="hidden" name="action_edit" value="xxx" />
		<input type="hidden" name="tran_id" value="<?=$tran["id_"]?>" />
		<input required="true" size="<?=INPUT_SIZE_COUNTING?>" name="coin" placeholder="<?=INPUT_HINT_QUANTITY?>" value="<?=$tran['amount_coin']?>"></input>
		<input required="true"" size="<?=INPUT_SIZE_COUNTING?>" name="usd" placeholder="<?=INPUT_HINT_MONEY?>" value="<?=$tran['amount_usd']?>"></input>
		<textarea required="true" rows="<?=INPUT_SIZE_H_NOTE?>" cols="<?=INPUT_SIZE_W_NOTE?>" name="note"  placeholder="<?=INPUT_HINT_NOTE?>"><?=escape($tran['note'])?></textarea>
		<input size="<?=INPUT_SIZE_DATETIME?>" name="ts" placeholder="<?=INPUT_HINT_DATETIME?>" value="<?=$tran['ts']?>"></input>
		<input type="submit" value="Submit" />
	</form>
<?php } ?>

<?php function ui_del($tran) { ?>
	<form method='post' onSubmit="return confirm('Xác nhận XÓA?');">
		<input type="hidden" name="action_del" value="xxx" />
		<input type="hidden" name="tran_id" value="<?=$tran["id_"]?>" />
		<input type="submit" value="DELETE" />
	</form>
<?php } ?>


<?php function ui_toggle($tran) { ?>
	<button onclick="toggle_<?=$tran['id_']?>()">[...]</button>
	<script>
		function toggle_<?=$tran['id_']?>() {
			var x = document.getElementById("act_<?=$tran['id_']?>");
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