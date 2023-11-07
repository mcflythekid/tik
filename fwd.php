<?php
//
$chatid = "-4067902077";
$token = "5795192662:AAG3or0Ws_g7IxPJOy_6e1A2grV2Eu_DZGY";

//
$p_name = $_POST["name"];
$p_pkg = $_POST["pkg"];
$p_title = $_POST["title"];
$p_text = $_POST["text"];
$p_subtext = $_POST["subtext"];
$p_bigtext = $_POST["bigtext"];
$p_infotext = $_POST["infotext"];

//
$msg = 
"<strong>$p_name >> $p_title</strong>
	$p_text
	$p_subtext
	$p_bigtext
	$p_infotext
";

//
$data = [
    'text' => $msg,
    'chat_id' => $chatid,
    'parse_mode' => 'html'
];
file_get_contents("https://api.telegram.org/bot$token/sendMessage?" . http_build_query($data) );