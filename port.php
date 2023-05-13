<?php
session_start ();
require_once ("_core.php");
page_auth ();
db_open ();
$username = $_SESSION['username'];
$param_fund_type = get_httpget("fund_type", "FFA");
page_title("Portfolio");

function digit($in, $count = 3) {
	$nbr = number_format($in, $count, ".", ",");
	return strpos($nbr,'.')!==false ? rtrim(rtrim($nbr,'0'),'.') : $nbr;
}

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

//tier e
update_price("PEPE");
update_price("TATE");
update_price("BOBO");

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
	db_query("insert into portfolio_trans (username, port_id, type, amount_coin, amount_usd, note, ts)	values ('$username', '$req_port_id', 'buy', '$req_coin', '$req_usd', 'anynote', now())");
	header("Refresh:0");
	exit;
}

if (has_httppost("action_sell") == true) {
	$req_port_id = get_httppost("port_id");
	$req_coin = abs(get_httppost("coin"));
	$req_usd = abs(get_httppost("usd"));
	db_query("insert into portfolio_trans (username, port_id, type, amount_coin, amount_usd, note, ts)	values ('$username', '$req_port_id', 'sell', '$req_coin', '$req_usd', 'anynote', now())");
	header("Refresh:0");
	exit;
}

$coins = db_list("select id_, name_, coin_code from portfolio where username = '$username' and fund_type = '$param_fund_type'");



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
	
	$quantity = 0;
	$sum_buy = 0;
	$sum_sell = 0;
	$trans = db_list("select amount_coin, amount_usd, type from portfolio_trans where port_id = $port_id");
	foreach($trans as $tran) {
		if ($tran["type"] == "sell") {
			$quantity -= $tran["amount_coin"];
			$sum_sell += $tran["amount_usd"];
		} else { // buy
			$quantity += $tran["amount_coin"];
			$sum_buy += $tran["amount_usd"];
		}
	}
	$value = $quantity * $price;
	$per_onhand = ($sum_sell + 0) / $sum_buy * 100.0;
	$per_rug = ($sum_sell + $value) / $sum_buy * 100.0;
	$delta_rug = (  ($sum_sell + $value) - $sum_buy  )  *  23333;
	
	$data = array();
	$data["quantity"] = $quantity;
	$data["value"] = $value;
	$data["sum_buy"] = $sum_buy;
	$data["sum_sell"] = $sum_sell;
	$data["per_onhand"] = $per_onhand;
	$data["per_rug"] = $per_rug;
	$data["delta_rug"] = $delta_rug;
	
	//$data["per"] = ($sum_sell + $quantity * $price) / $sum_buy * 100;
	
	
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
		<a href="/index.php?cat=f1d">F1D</a>&nbsp;&nbsp;
	</p>
	<p>
		<a href="/port.php?fund_type=FFA">FFA</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=TRADE">TRADE</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=RISK">RISK</a>&nbsp;&nbsp;
	</p>
</div>

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
	<input required="true" name="coin_code" placeholder="coin_code"></input>
	<input required="true" name="name" placeholder="name"></input>
	<input type="submit" value="Thêm mã coin"></input>
</form>




<table class="table table-striped">

<!-- header -->
<tr>
	<th>Name</th>
	<th>Coin</th>
	<th>Quantity</th>
	<th>Value</th>
	<th>Σ_Buy</th>
	<th>Σ_Sell</th>
	<th>%_OnHand</th>
	<th>%_Rug</th>
	<th>Δ_RugVND</th>
	<th>#</th>
	<th>#</th>
	<th>#</th>
<tr>

<!-- body -->
<?php foreach($coins as $coin ) {?>
<tr>
	<td><?=escape($coin['name_'])?></td>
	<td><?=escape($coin['coin_code'])?></td>
	<td><?=digit($coin_data[$coin["id_"]]["quantity"], 5)?></td>
	<td><?=digit($coin_data[$coin["id_"]]["value"], 0)?></td>
	<td><?=digit($coin_data[$coin["id_"]]["sum_buy"], 0)?></td>
	<td><?=digit($coin_data[$coin["id_"]]["sum_sell"], 0)?></td>
	<td><?=digit($coin_data[$coin["id_"]]["per_onhand"], 0)?>%</td>
	<td><?=digit($coin_data[$coin["id_"]]["per_rug"], 0)?>%</td>
	<td><?=digit($coin_data[$coin["id_"]]["delta_rug"], 0)?></td>
	<td><?=ui_del($coin)?></td>
	<td><?=ui_toggle($coin)?></td>
	<td id="act_<?=$coin["id_"]?>" style="display: none;">
		<table>
			<tr><td><?=ui_buy($coin)?></td></tr>
			<tr><td><?=ui_sell($coin)?></td></tr>
		</table>
	</td>
</tr>
<?php }?>

</table>


<?php function ui_buy($coin) { ?>
	<form method='post' onSubmit="return confirm('chắc chưa đại vương?');">
		<input type="hidden" name="action_buy" value="xxx" />
		<input type="hidden" name="port_id" value="<?=$coin["id_"]?>" />
		<input required="true" size="10" name="coin" placeholder="coin"></input>
		<input required="true" size="10" name="usd" placeholder="usd"></input>
		<input type="submit" value="BUY" />
	</form>
<?php } ?>

<?php function ui_sell($coin) { ?>
	<form method='post' onSubmit="return confirm('chắc chưa đại vương?');">
		<input type="hidden" name="action_sell" value="xxx" />
		<input type="hidden" name="port_id" value="<?=$coin["id_"]?>" />
		<input required="true" size="10" name="coin" placeholder="coin"></input>
		<input required="true"" size="10" name="usd" placeholder="usd"></input>
		<input type="submit" value="SELL" />
	</form>
<?php } ?>

<?php function ui_del($coin) { ?>
	<form method='post' onSubmit="return confirm('chắc chưa đại vương?');">
		<input type="hidden" name="action_del" value="xxx" />
		<input type="hidden" name="port_id" value="<?=$coin["id_"]?>" />
		<input type="submit" value="DEL" />
	</form>
<?php } ?>


<?php function ui_toggle($coin) { ?>
	<button onclick="toggle_<?=$coin['id_']?>()">trans</button>
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


<?php
page_bot ();
db_close ();