<?php

chdir(dirname(__FILE__) . '/../../');

include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");

set_time_limit(3*60*60);

include_once("./load_settings.php");
include_once(DIR_MODULES . "optimizer/optimizer.class.php");

if (isset($_SERVER['REQUEST_METHOD'])) {
    header('X-Accel-Buffering: no');
    /*
    if (defined('PATH_TO_PHP'))
        $phpPath = PATH_TO_PHP;
    else
        $phpPath = IsWindowsOS() ? '..\server\php\php.exe' : 'php';
    safe_exec($phpPath.' '.dirname(__FILE__).'/optimize.php');
    echo "Scheduled";
    exit;
    */
}

$out=array();
$optimizer = new optimizer();
$optimizer->getConfig();
if ($optimizer->config['AUTO_OPTIMIZE']) {
    $optimizer->analyze($out, $optimizer->config['AUTO_OPTIMIZE'], 1);
}
$optimizer->optimizeAll();

