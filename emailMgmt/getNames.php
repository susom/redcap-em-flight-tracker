<?php

use \Vanderbilt\FlightTrackerExternalModule\CareerDev;
use \Vanderbilt\CareerDevLibrary\EmailManager;

require_once(dirname(__FILE__)."/../small_base.php");
require_once(dirname(__FILE__)."/../CareerDev.php");
require_once(dirname(__FILE__)."/../classes/EmailManager.php");

$who = $_POST;

$mgr = new EmailManager($token, $server, $pid, CareerDev::getModule());
$names = $mgr->getNames($who);
if (empty($names)) {
	echo "No names match your description.";
} else {
	if ($who['recipient'] && ($who['recipient'] == "individuals")) {
		$emails = $mgr->getEmails($who);

		$lines = array();
		foreach ($names as $recordId => $name) {
			$email = $emails[$recordId];
			array_push($lines, $name.";".$email);
		}
		echo implode("<br>\n", $lines);
	} else {
		echo implode("<br>\n", array_values($names));
	}
}
