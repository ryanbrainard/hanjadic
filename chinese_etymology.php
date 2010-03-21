<?php
header('Content-Type: text/javascript');
$search = $_GET['search'];
mb_internal_encoding("UTF-8");
$link = mysql_connect('localhost', 'root', '')
   or die('Could not connect: ' . mysql_error());
mysql_select_db('hanjadic') or die('Could not select database');

function output($string) {
    echo "document.write('". trim($string) ."');";
}

function fetch_all($query) {
  $result = mysql_query($query) or die('Query failed: ' . mysql_error());
  $return = array();
  while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $return[] = $line;
  }
  mysql_free_result($result);
  return $return;
}

function hanja_definition($character, $match_sound=FALSE) {
  $items = array();
  $query = sprintf("SELECT hanja, definition FROM hanja_definition WHERE hanja = '%s';",
  mysql_real_escape_string($character));
  $items = fetch_all($query);

  $return = array();
  
  $definition = '';
  
  foreach ($items as $result) {
    $definition = $result['definition'] .' '. $definition;
  }

  return array(array('hanja' => $character, 'definition' => $definition));
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
    return wrap_span('<a href="http://hanjadic.bravender.us/'. $string .'">'. $string .'</a>', 'hanja');
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
      $line[$col] = call_user_func($mappings[$col], $line[$col], $line);
    }
    $return[] = $line;
  }
  return $return;
}

function display_results($result, $mappings=array()) {
  $result = decorate($result, $mappings);
  output("<table>\n");
  foreach ($result as $line) {
     output("\t<tr>\n");
     
     foreach ($line as $col_value) {
         output("\t\t<td>$col_value</td>\n");
     }
     output("\t</tr>\n");
  }
  output("</table>\n");
}

function conjugate($result, $other_fields) {
    if (strstr($result, 'v') && mb_substr($other_fields['hangul'], -1, 1) == '다') {
        return '<a class="conjugate" href="http://dongsa.net/?infinitive='. urlencode($other_fields['hangul']) .'">conjugate verb</a>';
    } else {
        return '';
    }
}


$verb = fetch_all(sprintf("select hanja from hanja where hangul = '%s'", mysql_real_escape_string($search)));

if (!isset($verb[0]['hanja'])) {
    if(mb_substr($search, -2, 2) == '하다') {
        $verb = fetch_all(sprintf("select hanja from hanja where hangul = '%s'", mysql_real_escape_string(mb_substr($search, 0, -2))));
    }
}

if (!isset($verb[0]['hanja'])) {
    if(mb_substr($search, -3, 3) == '적이다') {
        $verb = fetch_all(sprintf("select hanja from hanja where hangul = '%s'", mysql_real_escape_string(mb_substr($search, 0, -2).'인')));
    } 
}

if (!isset($verb[0]['hanja'])) {
    exit;
}

$chinese = $verb[0]['hanja'];

if (mb_strlen($chinese) > 1) {
  $hanja = fetch_all(sprintf("select hangul from hanja where hanja = '%s';", mysql_real_escape_string($chinese)));
  foreach (range(0, mb_strlen($chinese)-1) as $index) {
    $hangul_sound = mb_substr($hanja[0]['hangul'], $index, 1);
    display_results(hanja_definition(mb_substr($chinese, $index, 1), $hangul_sound), array('hanja' => 'linkify'));
  }
}

output('</td></tr></table>');

mysql_close($link);
?> 
