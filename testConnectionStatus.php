<?php

use \Vanderbilt\CareerDevLibrary\ConnectionStatus;
use \Vanderbilt\CareerDevLibrary\REDCapManagement;
use \Vanderbilt\CareerDevLibrary\Application;

require_once(dirname(__FILE__)."/classes/Autoload.php");
require_once(dirname(__FILE__)."/small_base.php");

$name = REDCapManagement::sanitize($_POST['name']);
$server = REDCapManagement::sanitize($_POST['server']);

if ($name && $server) {
    $connStatus = new ConnectionStatus($name, $server, $pid);
    $results = $connStatus->test();
    foreach ($results as $key => $result) {
        if (preg_match("/error/i", $result) && !Application::isLocalhost()) {
            Application::log("$server: $key - ".$result);
        }
    }
    echo json_encode($results);
} else {
    echo "[]";
}