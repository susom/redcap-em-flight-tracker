<?php

use \Vanderbilt\FlightTrackerExternalModule\CareerDev;
use \Vanderbilt\CareerDevLibrary\Download;
use \Vanderbilt\CareerDevLibrary\Upload;
use \Vanderbilt\CareerDevLibrary\REDCapManagement;
use \Vanderbilt\CareerDevLibrary\Application;
use \Vanderbilt\CareerDevLibrary\DataDictionaryManagement;
use \Vanderbilt\CareerDevLibrary\FeatureSwitches;
use \Vanderbilt\CareerDevLibrary\URLManagement;
use \Vanderbilt\CareerDevLibrary\Sanitizer;

require_once(dirname(__FILE__)."/small_base.php");
require_once(dirname(__FILE__)."/classes/Autoload.php");

$files = Application::getMetadataFiles();
$lastCheckField = "prior_metadata_ts";
$deletionRegEx = DataDictionaryManagement::getDeletionRegEx();

if ($_POST['process'] == "check") {
    $ts = $_POST['timestamp'];
    $lastCheckTs = CareerDev::getSetting($lastCheckField);
    if (!$lastCheckTs) {
        $lastCheckTs = 0;
    }

    # check a maximum of once every 30 seconds
    if ($ts > $lastCheckTs + 30) {
        $metadata = Download::metadata($token, $server);
        $switches = new FeatureSwitches($token, $server, $pid);
        list ($missing, $additions, $changed) = DataDictionaryManagement::findChangedFieldsInMetadata($metadata, $files, $deletionRegEx, CareerDev::getRelevantChoices(), $switches->getFormsToExclude());
        CareerDev::setSetting($lastCheckField, time(), $pid);
        if (count($additions) + count($changed) > 0) {
            if (Application::isSuperUser()) {
                $module = Application::getModule();
                $pids = $module->getPids();
                $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
                echo "<div id='metadataWarning' class='install-metadata-box install-metadata-box-danger'>
                <i class='fa fa-exclamation-circle' aria-hidden='true'></i> <a href='javascript:;' onclick='installMetadataForProjects(" . json_encode($pids) . ");'>Click here to install for all " . Application::getProgramName() . " projects (REDCap SuperUsers only).</a>
                </div>";
            }
            echo "<script>const missing = " . json_encode($missing) . ";</script>\n";
            echo "<div id='metadataWarning' class='install-metadata-box install-metadata-box-danger'>
                <i class='fa fa-exclamation-circle' aria-hidden='true'></i> An upgrade in your Data Dictionary exists. <a href='javascript:;' onclick='installMetadata(missing);'>Click here to install.</a>
                <p>The following fields will be added: " . (empty($additions) ? "<i>None</i>" : "<strong>" . implode(", ", $additions) . "</strong>") . "</p>
                <p>The following fields will be changed: " . (empty($changed) ? "<i>None</i>" : "<strong>" . implode(", ", $changed) . "</strong>") . "</p>
            </div>";
        }
    }
} else if (in_array($_POST['process'], ["install", "install_all"])) {
    Application::increaseProcessingMax(2);
    if (isset($_POST['pids'])) {
        $pidsToRun = [];
        $requestedPids = Sanitizer::sanitizeArray($_POST['pids']);
        $pids = Application::getPids();
        foreach ($requestedPids as $requestedPid) {
            if (REDCapManagement::isActiveProject($requestedPid) && in_array($requestedPid, $pids)) {
                $pidsToRun[] = $requestedPid;
            }
        }
    } else {
        $pidsToRun = [$pid];
    }
    $returnData = [];
    foreach ($pidsToRun as $currPid) {
        $pidToken = Application::getSetting("token", $currPid);
        $pidServer = Application::getSetting("server", $currPid);
        $switches = new FeatureSwitches($pidToken, $pidServer, $currPid);
        $pidGrantClass = Application::getSetting("grant_class", $currPid);
        $pidEventId = Application::getSetting("event_id", $currPid);
        if ($pidToken && $pidServer && $pidEventId) {
            Application::log("Installing metadata", $currPid);
            $returnData[$currPid] = DataDictionaryManagement::installMetadataFromFiles($files, $pidToken, $pidServer, $currPid, $pidEventId, $pidGrantClass, CareerDev::getRelevantChoices(), $deletionRegEx, $switches->getFormsToExclude());
        }
    }
    echo json_encode($returnData);
}