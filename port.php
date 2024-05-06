<?php
session_start ();
require_once ("_core.php");
global $rate;
page_auth ();
db_open ();
$username = $_SESSION['username'];

$param_fund_type = $_GET["fund_type"];


function update_price($code) {
	$price_txt = file_get_contents("/home/mc/app/matrix/price-$code");
	db_query("INSERT INTO portfolio_price (code, price, order_) VALUES ('$code', $price_txt, 'zz') ON DUPLICATE KEY UPDATE price = $price_txt ");
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
update_price("PEPE");
update_price("PAXG");

// MEXC
update_price("PORK");
update_price("PEPEC");
update_price("TON");
update_price("MX");


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
	$coins = db_list("select id_, name_, coin_code, fund_type from portfolio where username = '$username' and fund_type NOT IN ('closed', 'FREEDOM') and name_ not like '%_Loan' order by name_");
}




$prices = db_list("select * from portfolio_price order by order_");
$price_data = array();
foreach($prices as $price ) {
	$price_data[$price["code"]] = $price["price"];
}

$paxg_price_now = getPAXG();
$paxg_price_now  = $paxg_price_now * GOLD_RATE;

$coin_data = array();
foreach($coins as $coin ) {
	$port_id = $coin["id_"];
	$coin_code = $coin["coin_code"];
	$price = $price_data[$coin_code];
	
	$tran_index = 0;
	$quantity = 0;
	//
	$sum_buy = 0;
	$sum_sell = 0;
	$sum_buy_G = 0;
	$sum_sell_G = 0;
	//
	$trans = db_list("select amount_coin, amount_usd, type, paxg from portfolio_trans where port_id = $port_id");
	
	$health_cost = 0;
	$health_cost_G = 0;

	$sum_cash_theorycal = 0;
	$sum_cash_avaiable = 0;

	foreach($trans as $tran) {
		$tran_quantity = $tran["amount_coin"];


		$tran_amount = $tran["amount_usd"];
		//
		if (isset($tran["paxg"])) {
			$tran_gold_price = $tran["paxg"] * GOLD_RATE;
		} else {
			$tran_gold_price = $paxg_price_now;
		}
		$tran_amount_G = $tran_amount / $tran_gold_price;
		
		// For quantity, sum buy/sell
		if ($tran["type"] == "sell") {
			$quantity -= $tran_quantity;
			//
			$sum_sell   += $tran_amount;
			$sum_sell_G += $tran_amount_G;
		} else if ($tran["type"] == "buy") { // buy
			$quantity += $tran_quantity;
			//
			$sum_buy   += $tran_amount;
			$sum_buy_G += $tran_amount_G;
		}
		
		// For on hand, health cost
		if ($tran_index++ > 0) {
			if ($tran["type"] == "spent") { // External
				reduceToZero($sum_cash_theorycal, $tran_amount);
				reduceToZero($sum_cash_avaiable,  $tran_amount);

			} else if ($tran["type"] == "move") { // Internal
				reduceToZero($sum_cash_avaiable,   $tran_amount);
				
			} else if ($tran["type"] == "sell") { // sell
				$sum_cash_theorycal += $tran_amount;
				$sum_cash_avaiable  += $tran_amount;
				
				
			} else { // buy
				if ($tran_amount > $sum_cash_avaiable) {
					$delta = $tran_amount - $sum_cash_avaiable;
					$health_cost += $delta;
					$health_cost_G += $delta / $tran_gold_price;
				}
				reduceToZero($sum_cash_theorycal, $tran_amount);
				reduceToZero($sum_cash_avaiable,  $tran_amount);
			}
			
			
		} else { // First BUY transaction
			$health_cost += $tran_amount;
			$health_cost_G += $tran_amount_G;
		}
	}
	


	// $data["per_recovered"] = 1 / $health_cost * 100.0;
	// Calculations
	$market_value =   $quantity * $price; // Market value of remaining coins
	$market_value_G = $market_value / $paxg_price_now;

	// Amount if rugpull
	$rugpull_value_avaiable  = $sum_cash_avaiable  + $market_value; // Real
	$rugpull_value_avaiable_G  = $rugpull_value_avaiable / $paxg_price_now;
	//
	$rugpull_value_theorycal = $sum_cash_theorycal + $market_value; // Virtual
	$rugpull_value_theorycal_G = $rugpull_value_theorycal / $paxg_price_now;

	//
	$recover_per_avaiable  = $sum_cash_avaiable  / $health_cost * 100.0;
	$recover_per_theorycal = $sum_cash_theorycal / $health_cost * 100.0;
	//
	$recover_per_avaiable_G  = $sum_cash_avaiable / $paxg_price_now  / $health_cost_G * 100.0;
	$recover_per_theorycal_G = $sum_cash_theorycal / $paxg_price_now / $health_cost_G * 100.0;

	// echo "recover_per_avaiable_G : $recover_per_avaiable_G <br>";
	// echo "recover_per_theorycal_G : $recover_per_theorycal_G <br>"; 
	// echo "";
	// echo "";
	// exit;

	// % Profit if rugpull
	$rugpull_per_avaiable  = $rugpull_value_avaiable  / $health_cost * 100.0;
	$rugpull_per_theorycal = $rugpull_value_theorycal / $health_cost * 100.0;
	//
	$rugpull_per_avaiable_G  = $rugpull_value_avaiable_G  / $health_cost_G * 100.0;
	$rugpull_per_theorycal_G = $rugpull_value_theorycal_G / $health_cost_G * 100.0;

	// Δ Profit if rugpull
	$rugpull_delta_avaiable      = $rugpull_value_avaiable  - $health_cost;
	$rugpull_delta_theorycal     = $rugpull_value_theorycal - $health_cost;
	//
	$rugpull_delta_avaiable_G      = $rugpull_value_avaiable_G  - $health_cost_G;
	$rugpull_delta_theorycal_G     = $rugpull_value_theorycal_G - $health_cost_G;
	//
	$rugpull_delta_avaiable_vnd  = $rugpull_delta_avaiable * $rate;
	$rugpull_delta_theorycal_vnd = $rugpull_delta_theorycal * $rate;

	// Assign data
	$data = array();
	//
	$data["quantity"]       = $quantity;
	$data["market_value"]   = $market_value;
	$data["market_value_G"] = $market_value_G;
	$data["health_cost"]    = $health_cost;
	$data["health_cost_G"]  = $health_cost_G;
	//
	$data["sum_cash_avaiable"]    = $sum_cash_avaiable;
	$data["sum_cash_avaiable_G"]  = $sum_cash_avaiable_G;
	$data["sum_cash_theorycal"]   = $sum_cash_theorycal;
	$data["sum_cash_theorycal_G"] = $sum_cash_theorycal_G;
	//
	$data["rugpull_value_avaiable"]    = $rugpull_value_avaiable;
	$data["rugpull_value_avaiable_G"]  = $rugpull_value_avaiable_G;
	$data["rugpull_value_theorycal"]   = $rugpull_value_theorycal;
	$data["rugpull_value_theorycal_G"] = $rugpull_value_theorycal_G;
	//
	$data["recover_per_avaiable"]    = $recover_per_avaiable;
	$data["recover_per_avaiable_G"]  = $recover_per_avaiable_G;
	$data["recover_per_theorycal"]   = $recover_per_theorycal;
	$data["recover_per_theorycal_G"] = $recover_per_theorycal_G;
	//
	$data["rugpull_per_avaiable"]    = $rugpull_per_avaiable;
	$data["rugpull_per_avaiable_G"]  = $rugpull_per_avaiable_G;
	$data["rugpull_per_theorycal"]   = $rugpull_per_theorycal;
	$data["rugpull_per_theorycal_G"] = $rugpull_per_theorycal_G;
	//
	$data["rugpull_delta_avaiable"]        = $rugpull_delta_avaiable; // troll
	$data["rugpull_delta_theorycal"]       = $rugpull_delta_theorycal;
	$data["rugpull_delta_theorycal_G"]     = $rugpull_delta_theorycal_G;

	$data["rugpull_delta_avaiable_vnd"]  = $rugpull_delta_avaiable_vnd; //troll
	$data["rugpull_delta_theorycal_vnd"] = $rugpull_delta_theorycal_vnd;
	
	$coin_data[$port_id] = $data;
}

if (isset($param_fund_type)) {
	page_title("[ " . $param_fund_type . ' ]');
} else {
	page_title("[ Portfolio ]");
}

page_top ();

$mode = $_GET["mode"];
if (!isset($mode)) {
	$mode = "all";
}
$modeGold = $mode === "gold";
$modeMoney = $mode === "money";
$modeAll = $mode === "all";

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
		<a href="/port.php?fund_type=FG1">FG1</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=MEGA_SH">_SH</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=MEGA_SC">_SC</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=MEGA_176">_176</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=MEGA_SSF">_SSF</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=MEGA_REV">_REV</a>&nbsp;&nbsp;
		
		<a href="/port.php?fund_type=FREEDOM">FREEDOM*</a>&nbsp;&nbsp;
		<a href="/port.php?fund_type=closed">CLOSED*</a>
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
	$ts_mx = file_get_contents("/home/mc/app/matrix/price-ts-mx");
	$ts_erc = file_get_contents("/home/mc/app/matrix/price-ts-erc");
?>
<p>
	<strong>CZ: <?=$ts_cz?></strong>&nbsp;&nbsp;&nbsp;&nbsp;
	<strong>MX: <?=$ts_mx?></strong>&nbsp;&nbsp;&nbsp;&nbsp;

	<?php if (isset($param_fund_type)) { ?>
		<strong><?= "type=$param_fund_type" ?></strong>&nbsp;&nbsp;&nbsp;&nbsp;
	<?php } ?>


	<strong><?= "Rate: ${rate}Đ" ?></strong>
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

<div style="margin-top: 30px; margin-bottom: 10px;">
    <a href="#" onclick="replaceURLParam('mode', 'money')"><strong>[MONEY-MODE]</strong></a>&nbsp;&nbsp;&nbsp;&nbsp;
    <a href="#" onclick="replaceURLParam('mode', 'gold')"><strong>[GOLD-MODE]</strong></a>&nbsp;&nbsp;&nbsp;&nbsp;
    <a href="#" onclick="replaceURLParam('mode', 'all')"><strong>[ALL-MODE]</strong></a>&nbsp;&nbsp;&nbsp;&nbsp;
	Mode = <?= $mode ?>
</div>

<table class="table table-striped" id="portz">
	<thead>
		<tr>
			<th>#ID</th>
			<th>Name</th>
			<th>Coin</th>


			<?php if ($modeAll): ?>
				<th>%Exit_Th</th>
				<th>%ExitG_Th</th>
				<th>ΔExit_Th</th>
				<th>ΔExitG_Th</th>
			<?php elseif ($modeGold): ?>
				<th>%ExitG_Th</th>
				<th>ΔExitG_Th</th>
			<?php elseif ($modeMoney): ?>
				<th>%Exit_Th</th>
				<th>ΔExit_Th</th>
			<?php endif; ?>


			<?php if ($modeAll): ?>
				<th>$Exit_A</th>
				<th>$ExitG_A</th>
			<?php elseif ($modeGold): ?>
				<th>$ExitG_A</th>
			<?php elseif ($modeMoney): ?>
				<th>$Exit_A</th>
			<?php endif; ?>


			<?php if (!isset($param_fund_type)) { ?><th>Fund</th><?php } ?>


			<th>Quantity</th>
			

			<?php if ($modeAll): ?>
				<th>Market</th>
				<th>MarketG</th>
				<th>Health</th>
				<th>HealthG</th>
			<?php elseif ($modeGold): ?>
				<th>MarketG</th>
				<th>HealthG</th>
			<?php elseif ($modeMoney): ?>
				<th>Market</th>
				<th>Health</th>
			<?php endif; ?>


			<th>Cash_A</th>


			<?php if ($modeAll): ?>
				<th>%Recover</th>
				<th>%RecoverG</th>
			<?php elseif ($modeGold): ?>
				<th>%RecoverG</th>
			<?php elseif ($modeMoney): ?>
				<th>%Recover</th>
			<?php endif; ?>


			<th>_</th>
			<th>_</th>
			<th>_</th>
		</tr>
	</thead>
<tbody>
<!-- body -->
<?php 
foreach($coins as $coin) {
	$coin_data_tmp = $coin_data[$coin["id_"]];
	$vnd_round = -4;
	$gold_round = 4;
?>
<tr>
	<td><?=escape($coin['id_'])?></td>
	<td><?=escape($coin['name_'])?></td>
	<td><a href="/port_history.php?port_id=<?=$coin["id_"]?>"><?=escape($coin['coin_code'])?></a></td>


	<?php if ($modeAll): ?>
		<td                 ><?=digit($coin_data_tmp["rugpull_per_theorycal"], 0)?>%</td>
		<td class="for_gold"><?=digit($coin_data_tmp["rugpull_per_theorycal_G"], 0)?>%</td>
		<td                ><?=money_color(digit($coin_data_tmp["rugpull_delta_theorycal"], 0), '$')?></td>
		<td class="for_gold"><?=money_color(digit($coin_data_tmp["rugpull_delta_theorycal_G"], $gold_round), '❖')?></td>
	<?php elseif ($modeGold): ?>
		<td class="for_gold"><?=digit($coin_data_tmp["rugpull_per_theorycal_G"], 0)?>%</td>
		<td class="for_gold"><?=money_color(digit($coin_data_tmp["rugpull_delta_theorycal_G"], $gold_round), '❖')?></td>
	<?php elseif ($modeMoney): ?>
		<td><?=digit($coin_data_tmp["rugpull_per_theorycal"], 0)?>%</td>
		<td><?=money_color(digit($coin_data_tmp["rugpull_delta_theorycal"], 0), '$')?></td>
	<?php endif; ?>



	<?php if ($modeAll): ?>
		<td class="value">$<?=digit($coin_data_tmp["rugpull_value_avaiable"] , 0)?></td>
		<td class="value">❖<?=round($coin_data_tmp["rugpull_value_avaiable_G"], $gold_round) ?></td>
	<?php elseif ($modeGold): ?>
		<td class="value">❖<?=round($coin_data_tmp["rugpull_value_avaiable_G"], $gold_round) ?></td>
	<?php elseif ($modeMoney): ?>
		<td class="value">$<?=digit($coin_data_tmp["rugpull_value_avaiable"], 0)?></td>
	<?php endif; ?>


	<?php if (!isset($param_fund_type)) { ?><td><?=escape($coin['fund_type'])?></td><?php } ?>


	<td class="quantity">  <?=digit($coin_data_tmp["quantity"], 5)?>   </td>


	<?php if ($modeAll): ?>
		<td class="market_value         ">$<?=digit($coin_data_tmp["market_value"], 0)?>   </td>
		<td class="market_value for_gold">❖<?=digit($coin_data_tmp["market_value_G"], $gold_round)?>   </td>
		<td class="health_cost          ">$<?=digit($coin_data_tmp["health_cost"], 0)?>    </td>
		<td class="health_cost  for_gold">❖<?=digit($coin_data_tmp["health_cost_G"], $gold_round)?>    </td>
	<?php elseif ($modeGold): ?>
		<td class="market_value for_gold">❖<?=digit($coin_data_tmp["market_value_G"], $gold_round)?>   </td>
		<td class="health_cost  for_gold">❖<?=digit($coin_data_tmp["health_cost_G"], $gold_round)?>    </td>
	<?php elseif ($modeMoney): ?>
		<td class="market_value">$<?=digit($coin_data_tmp["market_value"], 0)?>   </td>
		<td class="health_cost">$<?=digit($coin_data_tmp["health_cost"], 0)?>    </td>
	<?php endif; ?>

	
	<td class="normal_green"><?=$coin_data_tmp["sum_cash_avaiable"] > 0 ? "$" . digit($coin_data_tmp["sum_cash_avaiable"], 0) : ""?></td>


	<?php if ($modeAll): ?>
		<td class="normal_green         "><?=$coin_data_tmp["recover_per_theorycal"]   > 1 ? digit($coin_data_tmp["recover_per_theorycal"], 0)   . "%": ""?></td>
		<td class="normal_green for_gold"><?=$coin_data_tmp["recover_per_theorycal_G"] > 1 ? digit($coin_data_tmp["recover_per_theorycal_G"], 0) . "%": ""?></td>
	<?php elseif ($modeGold): ?>
		<td class="normal_green for_gold"><?=$coin_data_tmp["recover_per_theorycal_G"] > 1 ? digit($coin_data_tmp["recover_per_theorycal_G"], 0) . "%": ""?></td>
	<?php elseif ($modeMoney): ?>
		<td class="normal_green"><?=$coin_data_tmp["recover_per_theorycal"]   > 1 ? digit($coin_data_tmp["recover_per_theorycal"], 0)   . "%": ""?></td>
	<?php endif; ?>


	<?php if ($modeAll): ?>

	<?php elseif ($modeGold): ?>

	<?php elseif ($modeMoney): ?>

	<?php endif; ?>

	
	<td><?=ui_del($coin)?></td>
	
	<td id="act_<?=$coin["id_"]?>" style="display: none;">
		<div>
			<div><div><?=ui_rename($coin)?></div></div>
		</div>
	</td>
	<td><?=ui_toggle($coin)?></td>
</tr>
<?php } ?>
</tbody>
</table>

<style>
	.quantity { font-weight: bold; color: #000 }
	.market_value { font-weight: bold; color: #2e8499 }
	.value { font-style: normal; color: green }
	.normal_green { font-weight: normal; color: green }
	.health_cost { font-weight: bold; color: red }

	.for_gold {
		text-decoration: underline;
	}
</style>

<?php function ui_rename($coin) { ?>
	<form method='post' onSubmit="return confirm('Ghi nhận RENAME [<?=$coin['name_']?>] ?');">
		<input type="hidden" name="action_rename" value="xxx" />
		<input type="hidden" name="port_id" value="<?=$coin["id_"]?>" />
		<input required="true" size="<?=INPUT_SIZE_NAME?>" name="name" placeholder="<?=INPUT_HINT_NAME?>"  value='<?=escape($coin["name_"])?>' />
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

			<?php if ($modeAll): ?>
				"order": [[4, 'desc']]
			<?php elseif ($modeGold): ?>
				"order": [[3, 'desc']]
			<?php elseif ($modeMoney): ?>
				"order": [[3, 'desc']]
			<?php endif; ?>

			
		});
	});

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


<?php
page_bot ();
db_close ();

?>