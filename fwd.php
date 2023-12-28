<?php
//
$chatid = "-4067902077";
$token = "5795192662:AAG3or0Ws_g7IxPJOy_6e1A2grV2Eu_DZGY";

//
$nameMap = array(
	'-'=>'VN-DCM',
	'whatapp'=>'WS'
);
$titleMap = array(
	'VN DCM Engineering'=>'VN-DCM',
	'DCM-'=>''
);

//
$p_name = $_POST["name"];
$p_pkg = $_POST["pkg"];
$p_title = $_POST["title"];
$p_text = $_POST["text"];
$p_subtext = $_POST["subtext"];
$p_bigtext = $_POST["bigtext"];
$p_infotext = $_POST["infotext"];


//
$p_name = empty($p_name) ? "" : "<strong>>$p_name</strong>\n";
$p_title = empty($p_title) ? "" : "<strong>>$p_title</strong>\n";

//
$p_text     = empty($p_text)     ? "" : ">$p_text\n";
$p_subtext  = empty($p_subtext)  ? "" : "$p_subtext\n";
$p_bigtext  = empty($p_bigtext)  ? "" : "$p_bigtext\n";
$p_infotext = empty($p_infotext) ? "" : $p_infotext;

//
$p_name  = reduceValues($p_name,  $nameMap);
$p_title = reduceValues($p_title, $titleMap);


//
$msg = "$p_name$p_title$p_text$p_subtext$p_bigtext$p_infotext";
$msg = locdautiengviet($msg);
$msg = removeNnMessages($msg); // vn dcm engineering (33 messages)

//
$data = [
    'text' => $msg,
    'chat_id' => $chatid,
    'parse_mode' => 'html'
];
file_get_contents("https://api.telegram.org/bot$token/sendMessage?" . http_build_query($data) );

echo $msg;


function reduceValues($msg, $map) {
	$output = $msg;
	foreach ($map as $key => $value) {
		$output = str_replace($key, $value, $output);
	}
	return $output;
}


function removeNnMessages($msg) {
	$string = 'April 15, 2003';
	$pattern = '/\s\((\d+) messages\)/i';
	$replacement = '';
    return preg_replace($pattern, $replacement, $msg).trim();
}


function locdautiengviet($str){
    //$str = strtolower($str); //chuyển chữ hoa thành chữ thường
    $unicode = array(
    'a'=>'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
    'd'=>'đ',
    'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
    'i'=>'í|ì|ỉ|ĩ|ị',
    'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
    'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
    'y'=>'ý|ỳ|ỷ|ỹ|ỵ',
    'A'=>'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
    'D'=>'Đ',
    'E'=>'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
    'I'=>'Í|Ì|Ỉ|Ĩ|Ị',
    'O'=>'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
    'U'=>'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
     'Y'=>'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
     );
     foreach($unicode as $nonUnicode=>$uni){
            $str = preg_replace("/($uni)/i", $nonUnicode, $str);
     }
     //$str = str_replace(' ','_',$str);
     return $str;
}