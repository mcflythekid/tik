<?php
session_start ();
$_SESSION ["username"] = "marty";
header("Location:/index.php");
exit;




require_once ("_core.php");
if (isset ( $_SESSION ["username"] )) { // go home if logged in
  header("Location:/index.php");
  exit;
}
db_open ();

$username = form_param ( $_POST, "username" ); // get username
$username = "marty"; // Force username
$password = form_param ( $_POST, "password" );
if (isset ( $username )) { // have username
	$user = db_object ( "select * from user_ where username = '$username' and password = '$password';" ); // select user
	if ($user != null) { // authenticated
		$_SESSION ["username"] = $username;
		db_close ();
		header("Location:/index.php");
		exit;
	} else { // fuck
		//
	}
}
db_close ();
page_title("đăng nhập đi");
page_top();
?>


<style>
body{
  font-family: 'Open Sans', sans-serif;
  background:#3498db;
  margin: 0 auto 0 auto;  
  width:100%; 
  text-align:center;
  margin: 20px 0px 20px 0px;   
}

p{
  font-size:12px;
  text-decoration: none;
  color:#ffffff;
}

h1{
  font-size:1.5em;
  color:#525252;
}

.box{
  background:white;
  width:300px;
  border-radius:6px;
  margin: 0 auto 0 auto;
  padding:0px 0px 70px 0px;
  border: #2980b9 4px solid; 
}

.email{
  background:#ecf0f1;
  border: #ccc 1px solid;
  border-bottom: #ccc 2px solid;
  padding: 8px;
  width:250px;
  color:#AAAAAA;
  margin-top:10px;
  font-size:1em;
  border-radius:4px;
}

.password{
  border-radius:4px;
  background:#ecf0f1;
  border: #ccc 1px solid;
  padding: 8px;
  width:250px;
  font-size:1em;
}

.btn{
  background:#2ecc71;
  width:265px;;
  padding-top:5px;
  padding-bottom:5px;
  color:white;
  border-radius:4px;
  border: #27ae60 1px solid;
  
  margin-top:20px;
  margin-bottom:20px;
  float:left;
  margin-left:16px;
  font-weight:800;
  font-size:0.8em;
}

.btn:hover{
  background:#2CC06B; 
}

#btn2{
  float:left;
  background:#3498db;
  width:125px;  padding-top:5px;
  padding-bottom:5px;
  color:white;
  border-radius:4px;
  border: #2980b9 1px solid;
  
  margin-top:20px;
  margin-bottom:20px;
  margin-left:10px;
  font-weight:800;
  font-size:0.8em;
}

#btn2:hover{ 
background:#3594D2; 
}
</style>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js" type="text/javascript"></script>






<script>


//Fade in dashboard box
$(document).ready(function(){
  $('.box').hide().fadeIn(1000);
  $('#password').focus();
});


</script>



<link href='http://fonts.googleapis.com/css?family=Open+Sans:700,600' rel='stylesheet' type='text/css'>
</head>
<body>


<form method="post" id="myForm">
<div class="box">
<h1>Tik login for my master</h1>

<!-- <input autocapitalize="off" type="text" placeholder="Tên đăng nhập" id="username" name="username" value=""  class="email" /> -->
  
<input placeholder="Mật khẩu" id="password" name="password" type="password"  class="email" />

  
<input type="submit" value="Đăng Nhập" class="btn"/>


  
</div> <!-- End Box -->
  
</form>

<p>Quên mật khẩu? Chết mẹ mày đi</p>

<?php
page_bot();
