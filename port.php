<?php
session_start ();
require_once ("_core.php");
global $rate;
page_auth ();
db_open ();
$username = $_SESSION['username'];

$param_fund_type = $_GET["fund_type"];

page_title("Portfolio");
function update_price($code) {
	$price_txt = file_get_contents("/home/mc/app/matrix/price-$code");
	db_query("update portfolio_price set price = $price_txt where code = '$code'");
}

//tier a
update_price("ETH");
update_price("BTC");

//tier b
update_price("NEO");
update_price("GAS");
update_price("WING");
update_price("FIRO");
update_price("LTC");
update_price("FLM");
update_price("ARB");

//tier e
update_price("PEPE");
//update_price("TATE");
//update_price("BOBO");

if (has_httppost("action_create_coin") == true) {
	$req_name = get_httppost("name");
	$req_coin_code = get_httppost("coin_code");
	$req_fund_type = get_httppost("fund_type");
	db_query("insert into portfolio (username, fund_type, coin_code, name_) values ('$username', '$param_fund_type', '$req_coin_code', '$req_name')");
	header("Refresh:0");
	exit;
}

if (has_httppost("action_del") == true) {
	$req_port_id = get_httppost("port_id");
	db_query("delete from portfolio where id_ = $req_port_id");
	db_query("delete from portfolio_trans where port_id = $req_port_id");
	header("Refresh:0");
	exit;
}


if (has_httppost("action_buy") == true) {
	$req_port_id = get_httppost("port_id");
	$req_coin = abs(get_httppost("coin"));
	$req_usd = abs(get_httppost("usd"));
	$req_note = get_httppost("note");
	$req_ts = get_httppost("ts");
	$cal_ts = ts_or_now($req_ts);
	db_query("insert into portfolio_trans (username, port_id, type, amount_coin, amount_usd, note, ts)	values ('$username', '$req_port_id', 'buy', '$req_coin', '$req_usd', '$req_note', FROM_UNIXTIME($cal_ts))");
	header("Refresh:0");
	exit;
}

if (has_httppost("action_sell") == true) {
	$cal_type = get_httppost("coin") == "spent" ? "spent" : "sell";
	
	$req_port_id = get_httppost("port_id");
	$req_coin = abs(get_httppost("coin"));
	$req_usd = abs(get_httppost("usd"));
	$req_note = get_httppost("note");
	$req_ts = get_httppost("ts");
	$cal_ts = ts_or_now($req_ts);
	
	
	db_query("insert into portfolio_trans (username, port_id, type, amount_coin, amount_usd, note, ts)	values ('$username', '$req_port_id', '$cal_type', '$req_coin', '$req_usd', '$req_usd', FROM_UNIXTIME($cal_ts))");
	header("Refresh:0");
	exit;
}

if (has_httppost("action_rename") == true) {
	$req_port_id = get_httppost("port_id");
	$req_name = get_httppost("name");
	db_query("update portfolio set name_ = '$req_name' where id_ = '$req_port_id'");
	header("Refresh:0");
	exit;
}

if (isset($param_fund_type)) {
	$coins = db_list("select id_, name_, coin_code, fund_type from portfolio where username = '$username' and fund_type = '$param_fund_type' order by name_");
} else {
	$coins = db_list("select id_, name_, coin_code, fund_type from portfolio where username = '$username' and fund_type <> 'closed' order by name_");
}




$prices = db_list("select * from portfolio_price order by order_");
$price_data = array();
foreach($prices as $price ) {
	$price_data[$price["code"]] = $price["price"];
}



$coin_data = array();
foreach($coins as $coin ) {
	$port_id = $coin["id_"];
	$coin_code = $coin["coin_code"];
	$price = $price_data[$coin_code];
	
	$tran_index = 0;
	$quantity = 0;
	$sum_buy = 0;
	$sum_sell = 0;
	$trans = db_list("select amount_coin, amount_usd, type from portfolio_trans where port_id = $port_id");
	
	$health_cost = 0;

	$sum_cash_theorycal = 0;
	$sum_cash_avaiable = 0;
	
	foreach($trans as $tran) {
		$tran_amount = $tran["amount_usd"];
		$tran_quantity = $tran["amount_coin"];
		
		// For quantity, sum buy/sell
		if ($tran["type"] == "sell") {
			$quantity -= $tran_quantity;
			$sum_sell += $tran_amount;
		} else if ($tran["type"] == "buy") { // buy
			$quantity += $tran_quantity;
			$sum_buy += $tran_amount;
		}
		
		// For on hand, health cost
		if ($tran_index++ > 0) {
			if ($tran["type"] == "spent") { // External
				reduceToZero($sum_cash_theorycal, $tran_amount);
				reduceToZero($sum_cash_avaiable, $tran_amount);

			} else if ($tran["type"] == "move") { // Internal
				reduceToZero($sum_cash_avaiable, $tran_amount);
				
			} else if ($tran["type"] == "sell") { // sell
				$sum_cash_theorycal += $tran_amount;
				$sum_cash_avaiable += $tran_amount;
				
				
			} else { // buy
				if ($tran_amount > $sum_cash_avaiable) {
					$health_cost += $tran_amount - $sum_cash_avaiable;
				}
	
				reduceToZero($sum_cash_theorycal, $tran_amount);
				reduceToZero($sum_cash_avaiable, $tran_amount);
			}
			
			
		} else { // First BUY transaction
			$health_cost += $tran_amount;
		}
	}
	


	// $data["per_recovered"] = 1 / $health_cost * 100.0;
	// Calculations
	$market_value = $quantity * $price; // Market value of remaining coins
	// Amount if rugpull
	$rugpull_value_avaiable  = $sum_cash_avaiable  + $market_value; // Real
	$rugpull_value_theorycal = $sum_cash_theorycal + $market_value; // Virtual
	//
	$recover_per_avaiable  = $sum_cash_avaiable  / $health_cost * 100.0;
	$recover_per_theorycal = $sum_cash_theorycal / $health_cost * 100.0;
	// % Profit if rugpull
	$rugpull_per_avaiable  = $rugpull_value_avaiable  / $health_cost * 100.0;
	$rugpull_per_theorycal = $rugpull_value_theorycal / $health_cost * 100.0;
	// Δ Profit if rugpull
	$rugpull_delta_avaiable      = $rugpull_value_avaiable  - $health_cost;
	$rugpull_delta_theorycal     = $rugpull_value_theorycal - $health_cost;
	$rugpull_delta_avaiable_vnd  = $rugpull_delta_avaiable * $rate;
	$rugpull_delta_theorycal_vnd = $rugpull_delta_theorycal * $rate;

	// Assign data
	$data = array();
	//
	$data["quantity"]     = $quantity;
	$data["market_value"] = $market_value;
	$data["health_cost"]  = $health_cost;
	//
	$data["sum_cash_avaiable"]  = $sum_cash_avaiable;
	$data["sum_cash_theorycal"] = $sum_cash_theorycal;
	//
	$data["rugpull_value_avaiable"]  = $rugpull_value_avaiable;
	$data["rugpull_value_theorycal"] = $rugpull_value_theorycal;
	//
	$data["recover_per_avaiable"] = $recover_per_avaiable;
	$data["recover_per_theorycal"] = $recover_per_theorycal;
	//
	$data["rugpull_per_avaiable"]  = $rugpull_per_avaiable;
	$data["rugpull_per_theorycal"] = $rugpull_per_theorycal;
	//
	$data["rugpull_delta_avaiable"]      = $rugpull_delta_avaiable; // troll
	$data["rugpull_delta_theorycal"]     = $rugpull_delta_theorycal;
	$data["rugpull_delta_avaiable_vnd"]  = $rugpull_delta_avaiable_vnd; //troll
	$data["rugpull_delta_theorycal_vnd"] = $rugpull_delta_theorycal_vnd;
	
	$coin_data[$port_id] = $data;
}


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
	<p>
		<a href="/index.php?cat=TODO&days=1">Dashboard</a>&nbsp;&nbsp;
	</p>
	<p>
		<a href="/port.php">ALL</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=MEGA">MEGA</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=FG1">FG1</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=FG2">FG2</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=HELL">HELL</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=closed">CLOSED</a>
	</p>
	<!-- <p>
		<a href="/port0.php?fund_type=MEGA">MEGA</a>&nbsp;&nbsp;
		<a href="/port0.php?fund_type=FG1">FG1</a>&nbsp;&nbsp;
		<a href="/port0.php?fund_type=FG2">FG2</a>&nbsp;&nbsp;
		<a href="/port0.php?fund_type=HELL">HELL</a>&nbsp;&nbsp;
		<a href="/port0.php?fund_type=closed">CLOSED</a>
	</p> -->
</div>

<?php
	$ts_cz = file_get_contents("/home/mc/app/matrix/price-ts-cz");
	$ts_erc = file_get_contents("/home/mc/app/matrix/price-ts-erc");
?>
<p>
	<strong>Binance: <?=$ts_cz?></strong>&nbsp;&nbsp;&nbsp;&nbsp;

	<?php if (isset($param_fund_type)) { ?>
		<strong><?= "type=$param_fund_type" ?></strong>&nbsp;&nbsp;&nbsp;&nbsp;
	<?php } ?>


	<strong><?= "1 USDT=$rate VND" ?></strong>
</p>

<p>
<?php foreach($prices as $price ) {?>
	<span><?=$price["code"]?>=<?=digit($price["price"], 10)?></span>&nbsp;&nbsp;
<?php } ?>
</p>
<hr/>

<?php if (isset($param_fund_type)) { ?>
<div style="margin-bottom: 20px;">
	<form  method='post'>
		<input type="hidden" name="action_create_coin" value="xxx"></input>
		<input required="true" name="fund_type" placeholder="fund_type" value=<?=$param_fund_type?>></input>
		<input required="true" name="coin_code" placeholder="Code" size="<?=INPUT_SIZE_COIN_CODE?>"></input>
		<input required="true" size="<?=INPUT_SIZE_W_NAME?>" name="name"  placeholder="<?=INPUT_HINT_NAME?>"></input>
		<input type="submit" value="Thêm mã coin"></input>
	</form>
</div>
<?php } ?>

<table class="table table-striped" id="portz">
	<thead>
		<tr>
			<!-- <th>#</th> -->
			<th>Name</th>
			<th>Coin</th>

			<th>%Exit T</th>
			<th>ΔExit T</th>
			<th>$Exit A</th>

			<?php if (!isset($param_fund_type)) { ?><th>Fund</th><?php } ?>

			<th>Quantity</th>
			<th>Market</th>
			<th>Health</th>
	
			<th>Cash A</th>
			<th>%Recover</th>
			
			<th>#</th>
			<th>#</th>
			<th>#</th>
			<th>#</th>
		</tr>
	</thead>
<tbody>
<!-- body -->
<?php 
foreach($coins as $coin) {
	$coin_data_tmp = $coin_data[$coin["id_"]];
?>
<tr>
	<!-- <td><?=escape($coin['id_'])?></td> -->
	<td><?=escape($coin['name_'])?></td>
	<td><a href="/port_history.php?port_id=<?=$coin["id_"]?>"><?=escape($coin['coin_code'])?></a></td>

	<td><?=digit($coin_data_tmp["rugpull_per_theorycal"], 0)?>%</td>
	<td><?=money_color(digit($coin_data_tmp["rugpull_delta_theorycal_vnd"], 0))?></td>
	<td class="value"><?=digit($coin_data_tmp["rugpull_value_avaiable"] * $rate, 0)?></td>

	<?php if (!isset($param_fund_type)) { ?><td><?=escape($coin['fund_type'])?></td><?php } ?>

	<td class="quantity">  <?=digit($coin_data_tmp["quantity"], 5)?>   </td>
	<td class="market_value">$<?=digit($coin_data_tmp["market_value"], 0)?>   </td>
	<td class="health_cost">$<?=digit($coin_data_tmp["health_cost"], 0)?>    </td>
	
	<td class="normal_green"><?=$coin_data_tmp["sum_cash_avaiable"] > 0 ? "$" . digit($coin_data_tmp["sum_cash_avaiable"], 0) : ""?></td>
	<td class="normal_green"><?=$coin_data_tmp["recover_per_theorycal"] > 1 ? digit($coin_data_tmp["recover_per_theorycal"], 0) . "%": ""?></td>

	
	<td><a href="/port_history.php?port_id=<?=$coin["id_"]?>">History</a></td>
	<td><?=ui_del($coin)?></td>
	
	<td id="act_<?=$coin["id_"]?>" style="display: none;">
		<div>
			<div><div><?=ui_buy($coin)?></div></div>
			<div><div><?=ui_sell($coin)?></div></div>
			<div><div><?=ui_rename($coin)?></div></div>
		</div>
	</td>
	<td><?=ui_toggle($coin)?></td>
</tr>
<?php } ?>
</tbody>
</table>

<style>
	.quantity { font-weight: bold; color: #fcba03 }
	.market_value { font-weight: bold; color: #fcba03 }
	.value { font-style: normal; color: green }
	.normal_green { font-weight: normal; color: green }
	.health_cost { font-weight: bold; color: red }
</style>

<?php function ui_buy($coin) { ?>
	<form method='post' onSubmit="return confirm('Ghi nhận MUA [<?=$coin['coin_code']?>] ?');">
		<input type="hidden" name="action_buy" value="xxx" />
		<input type="hidden" name="port_id" value="<?=$coin["id_"]?>" />
		<input required="true" size="<?=INPUT_SIZE_COUNTING?>" name="coin" placeholder="<?=INPUT_HINT_QUANTITY?>"></input>
		<input required="true" size="<?=INPUT_SIZE_COUNTING?>" name="usd" placeholder="<?=INPUT_HINT_MONEY?>"></input>
		<textarea required="true" rows="<?=INPUT_SIZE_H_NOTE?>" cols="<?=INPUT_SIZE_W_NOTE?>" name="note"  placeholder="<?=INPUT_HINT_NOTE?>"></textarea>
		<input size="<?=INPUT_SIZE_DATETIME?>" name="ts" placeholder="<?=INPUT_HINT_DATETIME?>"></input>
		<input type="submit" value="BUY" />
	</form>
<?php } ?>

<?php function ui_sell($coin) { ?>
	<form method='post' onSubmit="return confirm('Ghi nhận BÁN [<?=$coin['coin_code']?>] ?');">
		<input type="hidden" name="action_sell" value="xxx" />
		<input type="hidden" name="port_id" value="<?=$coin["id_"]?>" />
		<input required="true" size="<?=INPUT_SIZE_COUNTING?>" name="coin" placeholder="<?=INPUT_HINT_QUANTITY?>"></input>
		<input required="true"" size="<?=INPUT_SIZE_COUNTING?>" name="usd" placeholder="<?=INPUT_HINT_MONEY?>"></input>
		<textarea required="true" rows="<?=INPUT_SIZE_H_NOTE?>" cols="<?=INPUT_SIZE_W_NOTE?>" name="note"  placeholder="<?=INPUT_HINT_NOTE?>"></textarea>
		<input size="<?=INPUT_SIZE_DATETIME?>" name="ts" placeholder="<?=INPUT_HINT_DATETIME?>"></input>
		<input type="submit" value="SELL" />
	</form>
<?php } ?>

<?php function ui_rename($coin) { ?>
	<form method='post' onSubmit="return confirm('Ghi nhận RENAME [<?=$coin['name_']?>] ?');">
		<input type="hidden" name="action_rename" value="xxx" />
		<input type="hidden" name="port_id" value="<?=$coin["id_"]?>" />
		<textarea required="true" rows="<?=INPUT_SIZE_H_NAME?>" cols="<?=INPUT_SIZE_W_NAME?>" name="name"  placeholder="<?=INPUT_HINT_NAME?>"><?=escape($coin["name_"])?></textarea>
		<input type="submit" value="RENAME" />
	</form>
<?php } ?>

<?php function ui_del($coin) { ?>
	<form method='post' onSubmit="return confirm('Xác nhận XÓA [<?=$coin['coin_code']?>] ?');">
		<input type="hidden" name="action_del" value="xxx" />
		<input type="hidden" name="port_id" value="<?=$coin["id_"]?>" />
		<input type="submit" value="DEL" />
	</form>
<?php } ?>


<?php function ui_toggle($coin) { ?>
	<button onclick="toggle_<?=$coin['id_']?>()">[...]</button>
	<script>
		function toggle_<?=$coin['id_']?>() {
			var x = document.getElementById("act_<?=$coin['id_']?>");
			if (x.style.display === "none") {
				x.style.display = "block";
			} else {
				x.style.display = "none";
			}
		}
	</script>
<?php } ?>



<script>
	$(document).ready(function() {
		$('#portz').DataTable({
			"pageLength": 50,
			"order": [[3, 'desc']]
		});
	});
</script>


<?php
page_bot ();
db_close ();

?>