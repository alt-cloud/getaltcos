<?php
$rootdir = $_SERVER['DOCUMENT_ROOT'];
ini_set('include_path', "$rootdir/class");
require_once('repo.php');

$ref = $_REQUEST['ref'];
$commitId1 = $_REQUEST['commitId1'];
$commitId2 = $_REQUEST['commitId2'];
$rootsDir = "$rootdir/ALTCOS/streams/${ref}/roots/";
$repo = new repo($ref, 'bare');
$repo->getCommits();
$repo->checkout($commitId1, true);
$repo->checkout($commitId2, true);
$version1 = $repo->getCommitVersion($commitId1);
$version2 = $repo->getCommitVersion($commitId2);

$cmd = "cd $rootsDir; sudo diff -r $commitId2 $commitId1  2>&1";
// echo "CMD = $cmd<br>\n";
$output = [];
exec($cmd, $output);

$added = [];
$deleted = [];
$binaryChanged = [];
$diff = [];
$lost = [];

$tail = [];
foreach ($output as $line) {
  if (substr($line, 0, 8) == 'Only in ') {
    $path1 = explode(' ', substr($line,8));
    $file = $path1[1];
    $path2 = explode('/', $path1[0]);
    $commitId = $path2[0];
    $dir = '/' . substr(implode('/', array_slice($path2, 1)), 0, -1);
//     echo "<pre>commitId=$commitId</pre>";
    if ($commitId == $commitId2) {
      $deleted[$die][] = $file;
    } else {
      $added[$dir][] = $file;
    }
    $diffFile = false;
    continue;
  }
  if (substr($line, 0, 13) == 'Binary files ') {
    $path1 = explode(' ', substr($line,13));
    $file1 = implode('/', array_slice(explode('/', $path1[0]), 1));
    $last = trim(array_pop($path1));
    if ($last == 'differ') {
      $binaryChanged[] = $file1;
      $diffFile = false;
      continue;
    }
  }
  if (substr($line, 0, 5) == 'diff ') {
    $path1 = explode(' ', substr($line, 5), 3);
    if ($path1[0] == '-r') {
      $diffFile = implode('/', array_slice(explode('/', $path1[1]), 1));
      $diff[$diffFile] = [];
      continue;
    }
  }
  if (substr($line, 0, 5) == 'diff:') {
    $path1 = explode(' ', substr($line, 5), 3);
//     echo "PATH1=".print_r($path1,1);
    $path2 = explode('/', $path1[1], 2);
//     echo "PATH2=".print_r($path2,1);
    $commitId = $path2[0];
    $path = "/" . substr($path2[1], 0, -1);
    if ($commitId == $commitId2) {
      $lost[$path][] = 'Old';
    } else {
      $lost[$path][] = 'New';
    }
    $diffFile = false;
    continue;
  }
  if ($diffFile) {
    $diff[$diffFile][] = $line;
  } else {
    $tail[] = $line;
  }
}

?>
<center>
<h1>?????????????????? ???????????????? ???????????? ????????????<br><?= $version2?> ?? <?= $version1?><br>?????????? <?= $ref?></h1>
</center>
<ul>
  <li><a href='#binaryAdded'>?????????????????????? ???????????????? ??????????</a></li>
  <li><a href='#binaryDeleted'>?????????????????? ???????????????? ??????????</a></li>
  <li><a href='#notFound'>?????????????????????? ?????????? ?? ????????????????????</a></li>
  <li><a href='#changed'>???????????????????? ?????????????????? ??????????</a></li>
  <li><a href='#binaryChanged'>???????????????????? ???????????????? ??????????</a></li>
</ul>

<h2><a name='binaryAdded'>?????????????????????? ???????????????? ??????????</a></h2>
<?php
foreach ($added as $dir => $files) {
?>
<b><?= $dir?></b><ul>
<?php
  foreach ($files as $file) {
?>
  <li><?= $file?></li>
<?php
  }
?>
</ul>
<?php
}
?>
<h2><a name='binaryDeleted'>?????????????????? ???????????????? ??????????</a></h2>
<?php
foreach ($deleted as $dir => $files) {
?>
<b><?= $dir?></b><ul>
<?php
  foreach ($files as $file) {
?>
  <li><?= $file?></li>
<?php
  }
?>
</ul>
<?php
}
?>
<h2><a name='notFound'>?????????????????????? ?????????? ?? ????????????????????</a></h2>
<table border='1'>
  <tr>
    <th>????????/????????????????????</th>
    <th>Old</th>
    <th>New</th>
  </tr>
 <?php
 foreach ($lost as $file => $list) {
 ?>
  <tr>
    <th align='left'><?= $file?></th>
    <th><?php $s = in_array('Old', $list)?'X':'&nbsp;'; echo $s;?></th>
    <th><?php $s = in_array('New', $list)?'X':'&nbsp;'; echo $s;?></th>
  </tr>
 <?php
 }
 ?>
</table>

<h2><a name='changed'>???????????????????? ?????????????????? ??????????</a></h2>
<ul>
<?php
foreach ($diff as $file => $lines) {
?>
  <li>
    <b><?= $file?></b><br><?= implode('<br>', $lines)?>
  </li>
<?php
}
?>
</ul>

<h2><a name='binaryChanged'>???????????????????? ???????????????? ??????????</a></h2>
<ul>
<?php
foreach ($binaryChanged as $file) {
?>
   <li><?= $file?></li>
<?php
}
?>
</ul>

<?php
// echo "ADDED=<pre>". print_r($added, 1) . "</pre>\n";
// echo "DELETED=<pre>". print_r($deleted, 1) . "</pre>";
// echo "LOST=<pre>". print_r($lost, 1) . "</pre>";
// echo "DIFF=<pre>". print_r($diff, 1) . "</pre>";
// echo "BINARYCHANGED=<pre>". print_r($binaryChanged, 1) . "</pre>";
// echo implode("<br>\n", $tail);



