<?php

use \Vanderbilt\CareerDevLibrary\Application;
use \Vanderbilt\CareerDevLibrary\REDCapManagement;
use \Vanderbilt\CareerDevLibrary\ReactNIHTables;
use \Vanderbilt\CareerDevLibrary\Sanitizer;

require_once(dirname(__FILE__)."/../small_base.php");
require_once(dirname(__FILE__)."/../classes/Autoload.php");
Application::increaseProcessingMax(1);

$module = Application::getModule();
$userid = Application::getUsername();
$reactHandler = new ReactNIHTables($pid, $token, $server);

echo Application::makeIcon();
if (isset($_GET['email'])) {
    $email = Sanitizer::sanitize($_GET['email'] ?? "No email");
    $requestedHash = Sanitizer::sanitize($_GET['hash']);
    echo $reactHandler->getTable1_4Header();
    if ($reactHandler->verify($requestedHash, $email)) {
        list($tables, $emailHash) = $reactHandler->getInformation($requestedHash, $email);
        list($userids, $name) = $reactHandler->getUseridsAndNameAssociatedWithEmail($email);
        if (ReactNIHTables::isAuthorized($userid, $userids)) {
            echo $reactHandler->saveNotes($_POST, $email, $tables);
        } else {
            echo "Could not match user-id.";
        }
    } else {
        echo "Invalid request.";
    }
} else if (isset($_GET['revise']) && REDCapManagement::isEmail($_GET['revise'])) {
    $email = Sanitizer::sanitize($_GET['revise'] ?? "No email");
    $requestedHash = Sanitizer::sanitize($_GET['hash']);
    $dateOfReport = Sanitizer::sanitize($_GET['date'] ?? date("Y-m-d"));
    $savedName = Sanitizer::sanitize($_GET['savedName'] ?? "");
    echo $reactHandler->getTable1_4Header();
    if ($reactHandler->verify($requestedHash, $email)) {
        list($tables, $emailHash) = $reactHandler->getInformation($requestedHash, $email);
        list($userids, $name) = $reactHandler->getUseridsAndNameAssociatedWithEmail($email);
        if (ReactNIHTables::isAuthorized($userid, $userids)) {
            echo $reactHandler->makeHTMLForNIHTableEdits($dateOfReport, $name, $email, $emailHash, $tables, $savedName);
        } else {
            echo "Could not match user-id.";
        }
    } else {
        echo "Invalid request.";
    }
} else if (isset($_GET['confirm']) && REDCapManagement::isEmail($_GET['confirm'])) {
    $email = Sanitizer::sanitize($_GET['confirm'] ?? "No email");
    $requestedHash = Sanitizer::sanitize($_GET['hash']);
    echo $reactHandler->getTable1_4Header();
    if ($reactHandler->verify($requestedHash, $email)) {
        list($tables, $emailHash) = $reactHandler->getInformation($requestedHash, $email);
        list($userids, $name) = $reactHandler->getUseridsAndNameAssociatedWithEmail($email);
        if (ReactNIHTables::isAuthorized($userid, $userids)) {
            $reactHandler->saveConfirmationTimestamp($email, $tables);
            echo "Data saved. Thank you!";
        } else {
            echo "Could not match user-id.";
        }
    } else {
        echo "Invalid request.";
    }
}
