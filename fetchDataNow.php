<?php

use \Vanderbilt\CareerDevLibrary\Download;
use \Vanderbilt\CareerDevLibrary\Sanitizer;
use \Vanderbilt\CareerDevLibrary\Application;
use \Vanderbilt\CareerDevLibrary\Upload;

require_once(dirname(__FILE__)."/small_base.php");
require_once(dirname(__FILE__)."/classes/Autoload.php");

$records = Download::recordIds($token, $server);
$recordId = Sanitizer::getSanitizedRecord($_POST['record'], $records);
$fetchType = Sanitizer::sanitize($_POST['fetchType']);
$action = Sanitizer::sanitize($_POST['action']);
if (!$recordId) {
    die("Error: Invalid Record-ID");
}

try {
    if ($action == "fetch") {
        if ($fetchType == "summary") {
            require_once(dirname(__FILE__) . "/drivers/6d_makeSummary.php");
            $metadata = Download::metadata($token, $server);
            \Vanderbilt\CareerDevLibrary\summarizeRecord($token, $server, $pid, $recordId, $metadata);
        } else if ($fetchType == "publications") {
            require_once(dirname(__FILE__) . "/publications/getAllPubs_func.php");
            \Vanderbilt\CareerDevLibrary\getPubs($token, $server, $pid, [$recordId]);
        } else if ($fetchType == "grants") {
            require_once(dirname(__FILE__) . "/drivers/20_nsf.php");
            \Vanderbilt\CareerDevLibrary\getNSFGrants($token, $server, $pid, [$recordId]);

            require_once(dirname(__FILE__) . "/drivers/2s_updateRePORTER.php");
            \Vanderbilt\CareerDevLibrary\updateNIHRePORTER($token, $server, $pid, [$recordId]);

            if (Application::isVanderbilt() && !Application::isLocalhost()) {
                require_once(dirname(__FILE__)."/drivers/19_updateNewCoeus.php");
                \Vanderbilt\CareerDevLibrary\updateCoeusGrants($token, $server, $pid, [$recordId]);
                \Vanderbilt\CareerDevLibrary\updateCoeusSubmissions($token, $server, $pid, [$recordId]);
            }
        } else if ($fetchType == "patents") {
            require_once(dirname(__FILE__) . "/drivers/18_getPatents.php");
            \Vanderbilt\CareerDevLibrary\getPatents($token, $server, $pid, [$recordId]);
        } else {
            throw new \Exception("Invalid fetchType $fetchType");
        }
    } else if ($action == "delete") {
        $prefixes = [];
        if ($fetchType == "publications") {
            $prefixes[] = "citation_";
        } else if ($fetchType == "grants") {
            $prefixes[] = "nih_";
            $prefixes[] = "reporter_";
            $prefixes[] = "nsf_";
            if (Application::isVanderbilt()) {
                $prefixes[] = "coeus_";
                $prefixes[] = "coeus2)";
                $prefixes[] = "coeussubmission_";
            }
        } else if ($fetchType == "patents") {
            $prefixes[] = "patent_";
        } else {
            throw new \Exception("Invalid fetchType $fetchType");
        }
        foreach ($prefixes as $prefix) {
            Upload::deleteForm($token, $server, $pid, $prefix, $recordId);
        }
    } else {
        throw new \Exception("Invalid action $action");
    }
    echo "Success.";
} catch (\Exception $e) {
    echo "Error: ".$e->getMessage();
}