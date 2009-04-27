<?php
include_once 'settings.php';

$user_id = $facebook->get_loggedin_user();

$link = mysql_connect('localhost', 'root', '')
   or die('Could not connect: ' . mysql_error());
mysql_select_db('hanjadic') or die('Could not select database');

if($_GET['random']) {
  $results = fetch_all("SELECT * FROM hanja ORDER BY rand() LIMIT 1;"); 
  $search = $results[0]['hanja'];
}
if(!$search) $search = $_GET['search'];
if(!$search) $search = '字';

print '<fb:google-analytics uacct="UA-1741960-2" page="fb.php?search='. urlencode($search) .'" />';

if(array_key_exists('delete', $_GET)) {
  mysql_query(sprintf('DELETE FROM hanja_user WHERE uid = %d AND expression = "%s" AND rel = %d', $user_id, mysql_real_escape_string($search), $_GET['delete']));
}

if(array_key_exists('save', $_GET)) {
  mysql_query(sprintf('INSERT INTO hanja_user (uid, expression, rel) VALUES (%d, "%s", %d)', $user_id, mysql_real_escape_string($search), $_GET['save']));
}

if(array_key_exists('delete', $_GET) || array_key_exists('save', $_GET)) {
  $favorite_count = fetch_one(sprintf('SELECT COUNT(*) FROM hanja_user WHERE rel = 0 AND uid = %d', $user_id));
  $favorites = fetch_all(sprintf("SELECT * FROM hanja_user WHERE uid = %d AND rel = 0 ORDER BY rand() LIMIT 50", $user_id));
  $known_count = fetch_one(sprintf('SELECT COUNT(*) FROM hanja_user WHERE rel = 1 AND uid = %d', $user_id));
  $knowns = fetch_all(sprintf("SELECT * FROM hanja_user WHERE uid = %d AND rel = 1 ORDER BY rand() LIMIT 50", $user_id));
  
  $fbml = '';
  if ($known_count) {
    $fbml = '<fb:name uid="profileowner" useyou="false" firstnameonly="true" capitalize="true" /> knows '. $known_count . ': ';
    foreach ($knowns as $known) {
      $fbml .= ' <a href="http://apps.facebook.com/hanjadic/?search='. urlencode($known['expression']) .'">'. $known['expression'] .'</a>';
    }
  }
  if ($favorite_count) {
    $fbml .= '<br /><fb:name uid="profileowner" firstnameonly="true" useyou="false" capitalize="true" /> has '. $favorite_count .' favorites: ';
    foreach ($favorites as $favorite) {
      $fbml .= ' <a href="http://apps.facebook.com/hanjadic/?search='. urlencode($favorite['expression']) .'">'. $favorite['expression'] .'</a>';
    }
  }
  $result = $facebook->api_client->profile_setFBML($fbml. '<fb:ref handle="profile">');
}

if ($facebook->api_client->users_isAppAdded()) {
  $favorite = fetch_one(sprintf("SELECT COUNT(*) FROM hanja_user WHERE uid = %d AND expression = '%s' AND rel = 0", $user_id, mysql_real_escape_string($search)));
  $known = fetch_one(sprintf("SELECT COUNT(*) FROM hanja_user WHERE uid = %d AND expression = '%s' AND rel = 1", $user_id, mysql_real_escape_string($search)));
} else {
  $favorite = 0;
  $known = 0;
}
?>
<style>
#hanja-body  { font-family:sans-serif; color:black; font-size: 125%; }
table {font-size: 125%; }
input {font-size: 125%; }
a     {text-decoration: none; }
.hanja { font-size: 150%; }
.body {font-size: 120%; }
td {font-size: 120%; }
</style>

<div class="body">
<div>
<form method="get">
  漢字 玉篇<input name="search" value="<?php print $search ?>"> <a href="/hanjadic/?random=1">random</a>
  <fb:if-user-has-added-app>| <a href="/hanjadic/?summary=0">favorites</a> | <a href="/hanjadic/?summary=1">known</a> </fb:if-user-has-added-app> 
</form>
<?php
if(array_key_exists('summary', $_GET)) {
  $results = fetch_all(sprintf("SELECT * FROM hanja_user WHERE uid = %d AND rel = %d", $user_id, $_GET['summary']));
  foreach ($results as $result) {
    print '<a href="/hanjadic/?search='. urlencode($result['expression']) .'">'. $result['expression'] .'</a><br/>';
  }
  exit;
}

$results = fetch_all(sprintf('SELECT uid from hanja_user WHERE expression = "%s"', $search));
$get_uid = create_function('$i', 'return $i["uid"];');
$uids = array_map($get_uid, $results);
$query = 'SELECT first_name, uid, pic_square FROM user WHERE has_added_app = 1 AND uid IN (SELECT uid2 FROM friend WHERE uid1 = '. $user_id .' OR uid2 = '. $user_id .') AND uid IN ('. implode(',', $uids) .')';
$results = $facebook->api_client->fql_query($query);
if ($results) {
  foreach ($results as $result) {
    if ($result['pic_square']) {
      print '<img src="'. $result['pic_square'] .'" />';
    } else {
      print $result['first_name'];
    }
  }
}

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

function fetch_one($query) {
  $result = mysql_query($query) or die('Query failed: ' . mysql_error());
  $line = mysql_fetch_array($result);
  mysql_free_result($result);
  return $line[0];
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

if($facebook->api_client->users_isAppAdded()) { ?>
<?php if(!$favorite): ?>
  <a href="/hanjadic/?search=<?php print urlencode($search) ?>&save=0">save as a favorite</a>
<?php else: ?>
  <a href="/hanjadic/?search=<?php print urlencode($search) ?>&delete=0">remove from favorites</a>
<?php endif; ?>  
|
<?php if(!$known): ?>
  <a href="/hanjadic/?search=<?php print urlencode($search) ?>&save=1">save as known</a>
<?php else: ?>
  <a href="/hanjadic/?search=<?php print urlencode($search) ?>&delete=1">forgotten</a>
<?php endif; ?>
<?
} else {
  print '<a href="http://www.facebook.com/add.php?api_key=425661cce547d9c86990a8cd2fe11e07">Add this application to track which characters and phrases you know</a>';
}

display_results(korean_pronunciation($search), array('hanja' => 'linkify_pieces'));

display_results(hanja_definition($search), array('hanja' => 'linkify'));


print join(' ', array_map('linkify', radicals($search)));

display_results(search_all($search), array('hanja' => 'linkify'));

if (mb_strlen($search) > 1) {
  $hanja = fetch_all(sprintf("select hangul from hanja where hanja = '%s'", mysql_real_escape_string($search)));
  foreach (range(0, mb_strlen($search)) as $index) {
    $hangul_sound = mb_substr($hanja[0]['hangul'], $index, 1);
    display_results(hanja_definition(mb_substr($search, $index, 1), $hangul_sound), array('hanja' => 'linkify'));
  }
}

?> 
</div>
</div>
<?php
if ($facebook->api_client->users_isAppAdded()) {
  function get_stats($uid) {
    $favorite_count = fetch_one(sprintf('SELECT COUNT(*) FROM hanja_user WHERE rel = 0 AND uid = %d', $uid));
    $known_count = fetch_one(sprintf('SELECT COUNT(*) FROM hanja_user WHERE rel = 1 AND uid = %d', $uid));
    return array($favorite_count, $known_count);

  }

  $query = 'SELECT first_name, uid, pic_square FROM user WHERE has_added_app = 1 AND (uid = '. $user_id .' OR uid IN (SELECT uid2 FROM friend WHERE uid1 = '. $user_id .'))'; 
  $results = $facebook->api_client->fql_query($query);
  foreach ($results as $result) {
    if ($result['pic_square']) {
      print '<img src="'. $result['pic_square'] .'" />';
    } else {
      print $result['first_name'];
    }
  }
}

mysql_close($link);
?>
