<?php
session_start ();
require_once ("_core.php");
page_auth ();
db_open ();
$username = $_SESSION['username'];
$param_fund_type = get_httpget("fund_type", "FFA");
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
	$cal_type = get_httppost("coin") == "withdraw" ? "withdraw" : "sell";
	
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

$coins = db_list("select id_, name_, coin_code from portfolio where username = '$username' and fund_type = '$param_fund_type' order by name_");



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
	
	$on_hand = 0;
	$health_cost = 0;
	
	foreach($trans as $tran) {
		
		// For quantity, sum buy/sell
		if ($tran["type"] == "withdraw") {
			
		} else if ($tran["type"] == "sell") {
			$quantity -= $tran["amount_coin"];
			$sum_sell += $tran["amount_usd"];
		} else { // buy
			$quantity += $tran["amount_coin"];
			$sum_buy += $tran["amount_usd"];
		}
		
		// For on hand, health cost
		if ($tran_index++ > 0) {
			if ($tran["type"] == "withdraw") { // With draw
				$on_hand -= $tran["amount_usd"];
				$on_hand = $on_hand < 0 ? 0 : $on_hand;
				
			} else if ($tran["type"] == "sell") { // sell
				$on_hand += $tran["amount_usd"];
				
				
			} else { // buy
				$prev_on_hand = $on_hand;
				$on_hand -= $tran["amount_usd"];
				$on_hand = $on_hand < 0 ? 0 : $on_hand;
				
				if ($tran["amount_usd"] > $prev_on_hand) {
					$health_cost += $tran["amount_usd"] - $prev_on_hand;
				}
				
			}
			
			
		} else { // First BUY transaction
			$health_cost += $tran["amount_usd"];
		}
	}
	
	
	$value = $quantity * $price;
	//
	$usd_rug = $on_hand + $value;
	$per_rug = $usd_rug / $health_cost * 100.0;
	$delta_rug = ($usd_rug - $health_cost)  *  24950;
	
	$data = array();
	$data["quantity"] = $quantity;
	$data["value"] = $value;
	//
	$data["sum_buy"] = $sum_buy;
	$data["sum_sell"] = $sum_sell;
	$data["damages"] = $sum_buy - $sum_sell;
	//
	$data["on_hand"] = $on_hand;
	$data["health_cost"] = $health_cost;
	$data["per_recovered"] = $on_hand / $health_cost * 100.0;
	//
	$data["usd_rug"] = $usd_rug;
	$data["per_rug"] = $per_rug;

	$data["delta_rug"] = $delta_rug;
	
	
	
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
		<a href="/port.php?fund_type=FG1">FG1</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=FG2">FG2</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=closed">CLOSED</a>
	</p>
</div>

<?php
	$ts_cz = file_get_contents("/home/mc/app/matrix/price-ts-cz");
	$ts_erc = file_get_contents("/home/mc/app/matrix/price-ts-erc");
?>
<p>
	<strong>Binance: <?=$ts_cz?></strong>&nbsp;&nbsp;
	<!-- <strong>ERC20: <?=$ts_erc?></strong> -->
</p>
<hr/>

<p><strong><?= "param_fund_type=$param_fund_type" ?></strong></p>
<hr/>

<p>
<?php foreach($prices as $price ) {?>
	<span><?=$price["code"]?>=<?=digit($price["price"], 10)?></span>&nbsp;&nbsp;
<?php }?>
</p>
<hr/>


<form  method='post'>
	<input type="hidden" name="action_create_coin" value="xxx"></input>
	<input required="true" name="fund_type" placeholder="fund_type" value=<?=$param_fund_type?>></input>
	<input required="true" name="coin_code" placeholder="Code" size="<?=INPUT_SIZE_COIN_CODE?>"></input>
	<textarea required="true" rows="<?=INPUT_SIZE_H_NAME?>" cols="<?=INPUT_SIZE_W_NAME?>" name="name"  placeholder="<?=INPUT_HINT_NAME?>"></textarea>
	<input type="submit" value="Thêm mã coin"></input>
</form>

<style>
.quantity { font-weight: bold; color: #fcba03 }
.value { font-weight: bold; color: #fcba03 }
.sum { font-weight: bold; color: grey }
.damages { font-weight: normal; color: red }
.on_hand { font-weight: normal; color: green }
.health_cost { font-weight: bold; color: red }
.per_recovered { font-weight: normal; color: green }

</style>


<table class="table table-striped" id="portz">
	<thead>
		<tr>
			<th>Name</th>
			<th>Coin</th>

			
			
			
			<th>%Rug</th>
			<th>ΔRug</th>
			<th>Quantity</th>
			<th>$Rug</th>
			
			<th>MarketValue</th>
			
			<th>HealthCost</th>
			<th>OnHand</th>
			<th>%Recover</th>
			
			<th>ΣBuy</th>
			<th>ΣSell</th>
			<!-- <th>Damages</th> -->
			

			
			<th>#</th>
			<th>#</th>
			<th>#</th>
			<th>#</th>
		</tr>
	</thead>

<tbody>
<!-- body -->
<?php foreach($coins as $coin) {?>
<tr>
	<td><?=escape($coin['name_'])?></td>
	<td><?=escape($coin['coin_code'])?></td>
	
	
	
	<td><?=digit($coin_data[$coin["id_"]]["per_rug"], 0)?>%</td>
	<td><?=money_color(digit($coin_data[$coin["id_"]]["delta_rug"], 0))?></td>
	<td class="quantity"><?=digit($coin_data[$coin["id_"]]["quantity"], 5)?></td>
	<td><?=digit($coin_data[$coin["id_"]]["usd_rug"], 0)?></td>
	<td class="value">$<?=digit($coin_data[$coin["id_"]]["value"], 0)?></td>
	
	
	<?php
		$health_cost = $coin_data[$coin["id_"]]["health_cost"];
		$health_cost = "$" . digit($health_cost, 0);
	?>
	<td class="health_cost"><?=$health_cost?></td>
	<?php
		$on_hand = $coin_data[$coin["id_"]]["on_hand"];
		if ($on_hand > 0) {
			$on_hand = "$" . digit($on_hand, 1);
		} else {
			$on_hand = "";
		}
	?>
	<td class="on_hand"><?=$on_hand?></td>
	<?php
		$per_recovered = $coin_data[$coin["id_"]]["per_recovered"];
		if ($per_recovered > 1) {
			$per_recovered = digit($per_recovered, 0) . "%";
		} else {
			$per_recovered = "";
		}
	?>
	<td class="per_recovered"><?=$per_recovered?></td>
	
	
	
	<td class="sum">$<?=digit($coin_data[$coin["id_"]]["sum_buy"], 0)?></td>
	<?php
		$sum_sell = $coin_data[$coin["id_"]]["sum_sell"];
		if ($sum_sell > 0) {
			$sum_sell = "$" . digit($sum_sell, 0);
		} else {
			$sum_sell = "";
		}
	?>
	<td class="sum"><?=$sum_sell?></td>
	

	

	

	

	
	
	

	<td><a target="_blank" href="/port_history.php?port_id=<?=$coin["id_"]?>">History</a></td>
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
<?php }?>
</tbody>
</table>


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
			"pageLength": 50
		});
	});
</script>


<?php
page_bot ();
db_close ();