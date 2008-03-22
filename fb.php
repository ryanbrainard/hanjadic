<?php
$link = mysql_connect('localhost', 'root', '')
   or die('Could not connect: ' . mysql_error());
mysql_select_db('hanjadic') or die('Could not select database');

if($_GET['random']) {
  $results = fetch_all("SELECT * FROM hanja ORDER BY rand() LIMIT 1;"); 
  $search = $results[0]['hanja'];
}
if(!$search) $search = $_GET['search'];
if(!$search) $search = '字';
?>
<style>
#hanja-body  { font-family:sans-serif; color:black; font-size: 125%; }
table {font-size: 125%; }
input {font-size: 125%; }
a     {text-decoration: none; }
.hanja { font-size: 150%; }
</style>

<div>
<form method="get">
  漢字 玉篇<input name="search" value="<?php print $search ?>"> <a href="/hanjadic/?random=1">random</a>
</form>
<?php
mb_internal_encoding("UTF-8");

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

function hanja_definition($character) {
  $query = sprintf("SELECT hanja, definition FROM hanja_definition WHERE hanja = '%s';",
    mysql_real_escape_string($character));

  $return = array();
  
  foreach (fetch_all($query) as $result) {
    if (mb_substr($result['hanja'], 0,1) == $character) {
      $return[] = array('hanja' => $result['hanja'], 'definition' => $result['definition']);
    }
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
    return wrap_span('<a href="/hanjadic/?search='. urlencode($string) .'">'. $string .'</a>', 'hanja');
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

?><div style="font-size: 140px;"><?= $search ?></div><?

display_results(korean_pronunciation($search), array('hanja' => 'linkify_pieces'));

display_results(hanja_definition($search), array('hanja' => 'linkify'));


print join(' ', array_map('linkify', radicals($search)));

display_results(search_all($search), array('hanja' => 'linkify'));

if (mb_strlen($search) > 1) {
  foreach (range(0, mb_strlen($search)) as $index) {
    display_results(hanja_definition(mb_substr($search, $index, 1)), array('hanja' => 'linkify'));
  }
}

mysql_close($link);
?> 
</div>
