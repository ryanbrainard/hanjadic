<?php
if($_POST['search']) {
  $host = $_SERVER['HTTP_HOST'];
  $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
  header("Location: http://$host$uri/". $_POST['search']);
}
if(!$search) $search = $_GET['search'];
if(!$search) $search = '字';
?>
<? if(!$_GET['embed']) { ?>
<html><!-- new -->
<head>
<title><?php print $search ?></title>
<meta name="verify-v1" content="+UM6qgN3/CVGuWlHqgf9GBVKxYyz32j0cmRK+PxrE7s=" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> 
<meta name="verify-v1" content="+UM6qgN3/CVGuWlHqgf9GBVKxYyz32j0cmRK+PxrE7s=" />
<style>
#hanja-body  { font-family:sans-serif; color:black; font-size: 125%; }
table {font-size: 125%; }
input {font-size: 125%; }
a     {text-decoration: none; }
.hanja { font-size: 150%; }
</style>
</head>
<body id="hanja-body">
<div style="float:right; text-align:right">
<iframe src="http://rcm.amazon.com/e/cm?t=httpthebestbo-20&o=1&p=14&l=st1&mode=books&search=korean%20language&fc1=000000&lt1=&lc1=3366FF&bg1=FFFFFF&f=ifr" marginwidth="0" marginheight="0" width="160" height="600" border="0" frameborder="0" style="border:none;" scrolling="no"></iframe>
</div>
<!-- Piwik -->
<a href="http://piwik.org" title="Website analytics" onclick="window.open(this.href);return(false);">
<script type="text/javascript">
var pkBaseURL = (("https:" == document.location.protocol) ? "https://bravender.us/~dbravender/piwik/" : "http://bravender.us/~dbravender/piwik/");
document.write(unescape("%3Cscript src='" + pkBaseURL + "piwik.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
<!--
piwik_action_name = '';
piwik_idsite = 4;
piwik_url = pkBaseURL + "piwik.php";
piwik_log(piwik_action_name, piwik_idsite, piwik_url);
//-->
</script><object>
<noscript><p>Website analytics <img src="http://bravender.us/~dbravender/piwik/piwik.php" style="border:0" alt="piwik"/></p>
</noscript></object></a>
<!-- /Piwik -->
<div style="position:abslute;">
<form method="post">
  漢字 玉篇<input name="search" value="<?= $search ?>" />
</form>
<? } ?>
<?php
mb_internal_encoding("UTF-8");
$link = mysql_connect('localhost', 'root', '')
   or die('Could not connect: ' . mysql_error());
mysql_select_db('hanjadic') or die('Could not select database');

function fetch_all($query) {
  $result = mysql_query($query) or die('Query failed: ' . mysql_error());
  $return = array();
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $return[] = $line;
  }
  mysql_free_result($result);
  return $return;
}

function search_all($search) {
  $query = sprintf("SELECT hanja, hangul, english FROM hanja WHERE english LIKE '%%%s%%' or hanja LIKE '%%%s%%' or hangul LIKE '%%%s%%';",
    mysql_real_escape_string($search),
    mysql_real_escape_string($search),
    mysql_real_escape_string($search));
  
  return fetch_all($query);
}

function korean_pronunciation($search) {
  $query = sprintf("SELECT hangul, hanja FROM korean_pronunciation WHERE hangul = '%s';",
    mysql_real_escape_string($search),
    mysql_real_escape_string($search));
  
  return fetch_all($query);
}

function radicals($character) {
  $query = sprintf("SELECT * FROM radical WHERE hanja LIKE '%%%s%%';",
    mysql_real_escape_string($character));
    
  $return = array();
  
  foreach (fetch_all($query) as $result) {
    if (mb_strpos($result['hanja'], $character)) {
      $return[] = $result['radical'];
    }
  }

  return $return;
}

function hanja_definition($character, $match_sound=FALSE) {
  if ($match_sound) {
    $query = sprintf("SELECT hanja, definition FROM hanja_definition WHERE hanja = '%s' and definition like '%%%s%%';",
    mysql_real_escape_string($character), mysql_real_escape_string($match_sound));
  } else {
    $query = sprintf("SELECT hanja, definition FROM hanja_definition WHERE hanja = '%s';",
    mysql_real_escape_string($character));
  }

  $return = array();
  
  foreach (fetch_all($query) as $result) {
    if (mb_substr($result['hanja'], 0,1) == $character) {
      $return[] = array('hanja' => $result['hanja'], 'definition' => $result['definition']);
    }
  }

  if (!count($return)) {
    $return[]= array('hanja' => $character, 'definition' => $match_sound);
  }

  return $return;
}

function linkify_pieces($string) {
  $return = '';
  foreach (range(0, mb_strlen($string)) as $index) {
    $return .= linkify(mb_substr($string, $index, 1)) .' ';
  }
  
  return $return;
}

function linkify($string) {
  if ($string != $search) {
    return wrap_span('<a href="'. $string .'">'. $string .'</a>', 'hanja');
  }
  return $string;
}

function wrap_span($string, $type) {
  return '<span class="'. $type .'">'. $string .'</span>';
}

function decorate($array, $mappings) {
  $return = array();
  foreach ($array as $line) {
    foreach (array_keys($mappings) as $col) {
      $line[$col] = call_user_func($mappings[$col], $line[$col]);
    }
    $return[] = $line;
  }
  return $return;
}

function display_results($result, $mappings=array()) {
  $result = decorate($result, $mappings);
  echo "<table>\n";
  foreach ($result as $line) {
     echo "\t<tr>\n";
     
     foreach ($line as $col_value) {
         echo "\t\t<td>$col_value</td>\n";
     }
     echo "\t</tr>\n";
  }
  echo "</table>\n";
}

?><div style="font-size: 170px;"><?= $search ?></div><?

display_results(korean_pronunciation($search), array('hanja' => 'linkify_pieces'));

display_results(hanja_definition($search), array('hanja' => 'linkify'));


print join(' ', array_map('linkify', radicals($search)));

print '<table><tr><td valign="top"></td><td valign="top">';

display_results(search_all($search), array('hanja' => 'linkify'));

if (mb_strlen($search) > 1) {
  $hanja = fetch_all(sprintf("select hangul from hanja where hanja = '%s';", mysql_real_escape_string($search)));
  foreach (range(0, mb_strlen($search)) as $index) {
    $hangul_sound = mb_substr($hanja[0]['hangul'], $index, 1);
    display_results(hanja_definition(mb_substr($search, $index, 1), $hangul_sound), array('hanja' => 'linkify'));
  }
}

print '</td></tr></table>';

mysql_close($link);
?> 
</div>

<? if (!$_GET['embed']) { ?>
</body>
</html>
<? } ?>
