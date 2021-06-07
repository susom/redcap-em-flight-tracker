<?php

use \Vanderbilt\CareerDevLibrary\Application;
use \Vanderbilt\CareerDevLibrary\REDCapManagement;
use \Vanderbilt\CareerDevLibrary\Download;
use \Vanderbilt\FlightTrackerExternalModule\CareerDev;

require_once(dirname(__FILE__)."/classes/Autoload.php");

$otherToken = $_POST['token'];
$otherServer = $_POST['server'];
if ($_GET['project_id'] && in_array($_GET['action'], ["setupSettings"])) {
    $pid = $_GET['project_id'];
    if (verifyToken($otherToken, $pid)) {
        try {
            $projectTitle = Download::projectTitle($otherToken, $otherServer);
            $eventId = REDCapManagement::getEventIdForClassical($pid);
            $module = Application::getModule();
            $module->enableModule($pid, CareerDev::getPrefix());
            $enabledModules = $module->getEnabledModules($pid);
            if (in_array(CareerDev::getPrefix(), array_keys($enabledModules))) {
                foreach ($_POST as $key => $value) {
                    if ($key == "pid") {
                        $value = $pid;
                    } else if ($key == "event_id") {
                        $value = $eventId;
                    }
                    CareerDev::saveSetting($key, $value, $pid);
                }
                echo "Project $pid successfully set up on server.";
            } else {
                echo "Not enabled.";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "Invalid token. ".REDCapManagement::json_encode_with_spaces($_POST);
    }
} else if ($otherServer && $otherToken && REDCapManagement::isValidToken($otherToken)) {
    require_once(dirname(__FILE__)."/small_base.php");
    if (!preg_match("/\/$/", $otherServer)) {
        $otherServer .= "/";
    }
    $otherServerAPI = $otherServer."api/";
    list($otherPid, $otherEventId) = \Vanderbilt\FlightTrackerExternalModule\copyProjectToNewServer($token, $server, $otherToken, $otherServerAPI);

    $otherREDCapVersion = Download::redcapVersion($otherToken, $otherServerAPI);
    $urlParams = "?prefix=".CareerDev::getPrefix()."&page=copyProject&NOAUTH&project_id=".$otherPid."&action=setupSettings";
    $url1 = $otherServer."redcap_v".$otherREDCapVersion."/Classes/ExternalModules/".$urlParams;
    $url2 = $otherServer."external_modules/".$urlParams;

    $allSettings = Application::getAllSettings($pid);
    $allSettings["token"] = $otherToken;
    $allSettings["server"] = $otherServerAPI;
    $allSettings["pid"] = $otherPid;
    $allSettings["supertoken"] = "";
    $allSettings["event_id"] = "";
    unset($allSettings["enabled"]);

    if (REDCapManagement::isGoodURL($url1)) {
        $url = $url1;
    } else {
        $url = $url2;
    }
    list($resp, $output) = REDCapManagement::downloadURLWithPOST($url, $allSettings, $pid);
    echo "allSettings: ".REDCapManagement::json_encode_with_spaces($allSettings)."<br>\n";
    echo $url."<br>\n";
    echo json_encode($output);
} else {
    require_once(dirname(__FILE__)."/charts/baseWeb.php");
?>

<h1>Copy <?= Application::getProgramName() ?> Project to Another Project</h1>

<form action="<?= Application::link("this") ?>" method="POST">
    <p class="centered">API Token for New Project:<br><input type="text" style="width: 500px;" id="token" name="token" value="<?= $otherToken ?>"></p>
    <p class="centered">Base Server URL (e.g., https://redcap.vanderbilt.edu/; note, <b>not</b> API URL):<br><input type="text" id="server" name="server" value="<?= $otherServer ?>" style="width: 500px;"></p>
    <p class="centered"><button onclick="copyProject($('#token').val(), $('#server').val()); return false;">Submit</button></p>
    <p class="centered" id="results"></p>
</form>

<?php

}

function verifyToken($token, $pid) {
    if (!is_numeric($pid)) {
        echo "Invalid pid $pid";
        return FALSE;
    }
    if (!REDCapManagement::isValidToken($token)) {
        echo "Invalid token $token";
        return FALSE;
    }

    # does NOT have to be present user
    $sql = "SELECT username FROM redcap_user_rights WHERE project_id = '".db_real_escape_string($pid)."' AND api_token = '".db_real_escape_string($token)."'";
    $q = db_query($sql);
    return (db_num_rows($q) > 0);
}