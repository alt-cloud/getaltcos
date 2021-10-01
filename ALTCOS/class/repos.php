<?php
class repos {

  static $OSs = ['altcos' => 'ALT Container OS'];

  static function listOSs() {
    $ret = array_keys(repos::$OSs);
    return $ret;
  }

  static function getOSName($os) {
    $ret = repos::$OSs[$os];
    return $ret;
  }

  static function listArchs($os='altcos') {
    $fd = opendir($_SERVER['DOCUMENT_ROOT'] . "/ALTCOS/streams/$os/");
    $ret = [];
    while ($entry=readdir($fd)) {
      if (substr($entry,0,1) == '.') continue;
      $ret[] = $entry;
    }
    return $ret;
  }

  static function listStreams($os='altcos', $arch='x86_64') {
    $archDir = $_SERVER['DOCUMENT_ROOT'] . "/ALTCOS/streams/$os/$arch";
//     echo "<pre>archDir=$archDir</pre>\n";
    $fd = opendir($archDir);
    $ret = [];
    while ($entry=readdir($fd)) {
      if (substr($entry,0,1) == '.') continue;
      $ret[] = $entry;
    }
    return $ret;
  }

  static function repoTypes() {
    $ret = ['bare', 'archive'];
    return $ret;
  }

  /*
   * Возвращает true, усли ref базовый: altcos/x86_64/sisyphus
   */
  static  function isBaseRef($ref) {
    $ret = count(explode('/', $ref)) == 3;
    return $ret;
  }

    /*
   * Формирует имя подветки
   * переводя в верхний регистр первую букву текущей ветки и
   * добавляя через / имя подветки
   * subRef('altcos/x86_64/sisyphus', 'apache') => altcos/x86_64/Sisyphus/apache
   */
  static function subRef($ref, $subName) {
    $path = explode('/', $ref);
    $lastN = count($path) - 1;
    $path[$lastN] = ucfirst($path[$lastN]);
    $path[] = $subName;
    $ret = implode('/', $path);
    return $ret;
  }


  /**
   * Возвращает тропу, где находятся данные ветки (vars, roots, ALTCOSfile, ...)
   * altcos/x86_64/sisyphus -> altcos/x86_64/sisyphus
   * altcos/x86_64/Sisyphus/apache -> altcos/x86_64/sisyphus/apache
   */
  static function refToDir($ref) {
    $ret = strtolower($ref);
    return $ret;
  }

  /**
   * Возвращает тропу, где находятся данные ветки (vars, roots, ALTCOSfile, ...)
   * altcos/x86_64/sisyphus -> altcos/x86_64/sisyphus
   * altcos/x86_64/Sisyphus/apache -> altcos/x86_64/sisyphus/apache
   */
  static function dirToRef($ref) {
    $path = explode('/', $ref);
    $ret = implode('/', array_slice($path, 0, 2));
    for ($i = 2; $i < count($path)-1; $i++) {
      $ret .= "/" . ucfirst($path[$i]);
    }
    $ret .= "/" . ucfirst($path[$i]);
    return $ret;
  }


  /**
   * Возвращает тропу, где находятся репозитории bare, archive
   * altcos/x86_64/sisyphus -> altcos/x86_64/sisyphus
   * altcos/x86_64/Sisyphus/apache -> altcos/x86_64/sisyphus
   */
  static function refRepoDir($ref) {
    $path = array_slice(explode('/', $ref), 0, 3);
    $path[2] = strtolower($path[2]);
    $ret = implode('/', $path);
    return $ret;
  }

  /*
   * Возвращает имя поддиректория варианта в каталоге /vars
   * sisyphus.20210914.0.0 => 20210914/0/0
   * sisyphus_apache.20210914.0.0 => apache/20210914/0/0
   */
  static function versionVarSubDir($version) {
    $path = explode('.', strtolower($version));
    $date = $path[1];
    $major = $path[2];
    $minor = $path[3];
    $ret = "$date/$major/$minor";
    return $ret;
  }

  function fullCommitId($refDir, $shortCommitId) {
    $varsDir = $_SERVER['DOCUMENT_ROOT'] . "/ALTCOS/streams/$refDir/vars";
    $fd = opendir($varsDir);
    $commitIds = [];
    $len =  strlen($shortCommitId);
    while ($entry=readdir($fd)) {
      $file = "$varsDir/$entry";
      if (!is_link($file) || strlen($entry) != 64 || substr($entry, 0, $len) != $shortCommitId) continue;
      $commitIds[] = $entry;
    }
    if (count($commitIds) == 0) {
        echo "Коммит $shortCommitId отсутствует";
        return false;
    }
    if (count($commitIds) > 1) {
      echo "Коммит $shortCommitId неоднозначен. Ему соответствуют несколько коммитов: " . implode(', ', $commitIds);
      return false;
    }
    $ret = $commitIds[0];
    return $ret;
  }

  function lastCommitId($refDir) {
    $varsDir = $_SERVER['DOCUMENT_ROOT'] . "/ALTCOS/streams/altcos/$refDir/vars";
    $fd = opendir($varsDir);
    $commitIds = [];
    $len =  strlen($shortCommitId);
    while ($entry=readdir($fd)) {
      $file = "$varsDir/$entry";
      if (!is_link($file) || strlen($entry) != 64) continue;
      $stat = stat($file);
      $mtime = $stat['mtime'];
      $commitIds[$mtime] = $entry;
    }
    ksort($commitIds, SORT_NUMERIC);
    $commitIds = array_reverse(array_values($commitIds));
    $ret = $commitIds[0];
    return $ret;
  }

  /*
   * Возвращает вариант ветки по $ref и $commitId
   * altcos/x86_64/Sisyphus/apache -> sisyphus_apache.$date.$major.$minor
   */
  static function refVersion($ref, $commitId=false) {
    if (!$commitId) {
      $date = strftime("%Y%m%d");
      $major = 0;
      $minor = 0;
    } else {
      $fullCommitId = repos::fullCommitId($ref, $commitId);
      $varsDir = $_SERVER['DOCUMENT_ROOT'] . "/ALTCOS/streams/altcos/$refDir/vars";
      $commitLink = "$varsDir/$fullCommitId";
      $dir = readlink($commitLink);
      $path = explode($dir);
      $date = $path[0];
      $major = $path[1];
      $minor = $path[2];
    }
    $path = explode('/', strtolower($ref));
    $stream = implode('_', array_slice($path, 2));
    $ret = "$stream.$date.$major.$minor";
    return $ret;
  }

  static function fullRPMNameToShort($fullName) {
    $path = explode('-', $fullName);
    $nPath = count($path);
    $ret = implode('-', array_slice($path, 0, $nPath-2));
    return $ret;
  }

}