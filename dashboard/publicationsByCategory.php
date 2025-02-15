<?php

use \Vanderbilt\CareerDevLibrary\Publications;
use \Vanderbilt\CareerDevLibrary\Citation;
use \Vanderbilt\CareerDevLibrary\Download;
use \Vanderbilt\CareerDevLibrary\Measurement;
use \Vanderbilt\FlightTrackerExternalModule\CareerDev;
use \Vanderbilt\CareerDevLibrary\REDCapManagement;
use \Vanderbilt\CareerDevLibrary\Application;
use \Vanderbilt\CareerDevLibrary\Dashboard;

require_once(dirname(__FILE__)."/../small_base.php");
require_once(dirname(__FILE__)."/base.php");
require_once(dirname(__FILE__)."/../classes/Autoload.php");
$dashboard = new Dashboard($pid);
require_once(dirname(__FILE__)."/".$dashboard->getTarget().".php");

$headers = [];
$measurements = [];

$headers[] = "Publications by Category";
if (isset($_GET['cohort'])) {
    $cohort = REDCapManagement::sanitizeCohort($_GET['cohort']);
    $headers[] = "For Cohort " . $cohort;
} else {
    $cohort = "";
}
$headers[] = Publications::makeLimitButton();

$indexedRedcapData = Download::getIndexedRedcapData($token, $server, CareerDev::$smallCitationFields, $cohort, Application::getModule());

$numConfirmedPubs = 0;
$numUnconfirmedPubs = 0;
$notDoneRecords = 0;
$numForCategory = [];
$numForYear = [];
$ts = time();
foreach ($indexedRedcapData as $recordId => $rows) {
	$pubs = new Publications($token, $server, []);
	$pubs->setRows($rows);
	$goodCitations = $pubs->getCitationCollection("Included");
	if ($goodCitations) {
		$confirmedCount = $goodCitations->getCount();
		$numConfirmedPubs += $confirmedCount;
		foreach ($goodCitations->getCitations() as $citation) {
			$cat = $citation->getCategory();
	
			if (!isset($numForCategory[$cat])) {
				$numForCategory[$cat] = 0;
			}
	
			$numForCategory[$cat]++;
		}
	}

	$notDoneCitations = $pubs->getCitationCollection("Not done");
	if ($notDoneCitations) {
		$notDoneCount = $notDoneCitations->getCount();
		$numUnconfirmedPubs += $notDoneCount;
		if ($notDoneCount > 0) {
			$notDoneRecords++;
		}
	}
}

$measurements["Number of Confirmed Publications"] = new Measurement($numConfirmedPubs);
$measurements["Number of Unconfirmed Publications"] = new Measurement($numUnconfirmedPubs);
$measurements["Records with Unconfirmed Publications"] = new Measurement($notDoneRecords);

$categories = Citation::getCategories();
$label = "";
foreach ($categories as $cat) {
	if (!$label) {
		$label = "[BLANK]";
	}
	if ($numForCategory[$cat]) {
		$measurements["Confirmed:<br>".$cat] = new Measurement($numForCategory[$cat], $numConfirmedPubs);
	}
}

echo $dashboard->makeHTML($headers, $measurements, [], $cohort);
