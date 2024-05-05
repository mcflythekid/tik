<?php
require_once ("_core.php");

if (!isset($_GET["token"]) || $_GET["token"] !== "dkmmAdutaoso") {
    echo "Not found\n";
    exit;
}


db_open ();
function update_price($code) {
	$price_txt = file_get_contents("/home/mc/app/matrix/price-$code");
	db_query("INSERT INTO portfolio_price (code, price, order_) VALUES ('$code', $price_txt, 'zz') ON DUPLICATE KEY UPDATE price = $price_txt ");
}

update_price("FIRO");
update_price("PAXG");
echo "Updated\n";
