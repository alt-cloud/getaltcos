<?php
//phpinfo();
$rootdir = $_SERVER['DOCUMENT_ROOT'];
ini_set('include_path', "$rootdir/class");
require_once('repo.php');
require_once('repos.php');

$url = key_exists('url', $_REQUEST) ? $_REQUEST['url'] : 'https://altcos.altlinux.org/ALTCOS/streams';


$streams = key_exists('streams', $_REQUEST) ? $_REQUEST['streams'] : 'altcos/x86_64/sisyphus,altcos/x86_64/p10';
$streams = explode(',', $streams);

$result = repos::mirror($url, $streams);
