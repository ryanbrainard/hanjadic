<?php
if ($_GET['user'] != 'admin' && $_GET['password'] != 'what') {
  exit;
}
mb_internal_encoding("UTF-8");

$link = mysql_connect('127.0.0.1', 'root', '')
   or die('Could not connect: ' . mysql_error());
mysql_select_db('hanjadic') or die('Could not select database');

function save($hangul, $hanja, $english, $sajasongoh) {
  $query = sprintf("insert into hanja (hangul, hanja, english, sajasongoh) values ('%s', '%s', '%s', %d)",
            mysql_real_escape_string($hangul),
            mysql_real_escape_string($hanja),
            mysql_real_escape_string($english),
            $sajasongoh ? 1 : 0);
  mysql_query($query) or die('Query failed: ' . mysql_error());
}

if($_POST['save']) {
  save($_POST['hangul'], $_POST['hanja'], $_POST['english'], $_POST['sajasongoh']);
}

mysql_close($link);
?>
<html>
<head>
<meta name="verify-v1" content="+UM6qgN3/CVGuWlHqgf9GBVKxYyz32j0cmRK+PxrE7s=" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> 
</head>
<body>

<form action="./admin.php?user=admin&amp;password=what" method="post" accept-charset="utf-8">
<p><label for="hangul">hangul</label><input type="text" name="hangul" value=""></p>
<p><label for="hanja">hanja</label><input type="text" name="hanja" value=""></p>
<p><label for="english">english</label><input type="text" name="english" value=""></p>
<p><label for="sajasongoh"><input type="checkbox" name="sajasongoh" value="on"/></p>
<p><input type="submit" name="save" value="Continue &rarr;"></p>
</form>
</body>
</html>
