<?php

use \Vanderbilt\CareerDevLibrary\Publications;
use \Vanderbilt\CareerDevLibrary\Citation;
use \Vanderbilt\CareerDevLibrary\CitationCollection;
use \Vanderbilt\CareerDevLibrary\Download;
use \Vanderbilt\CareerDevLibrary\Application;
use \Vanderbilt\CareerDevLibrary\Altmetric;
use \Vanderbilt\CareerDevLibrary\REDCapManagement;
use \Vanderbilt\CareerDevLibrary\Cohorts;
use \Vanderbilt\FlightTrackerExternalModule\CareerDev;

require_once(dirname(__FILE__)."/../Application.php");
require_once(dirname(__FILE__)."/../CareerDev.php");
require_once(dirname(__FILE__)."/../small_base.php");
require_once(dirname(__FILE__)."/../classes/Publications.php");
require_once(dirname(__FILE__)."/../classes/Citation.php");
require_once(dirname(__FILE__)."/../classes/Download.php");
require_once(dirname(__FILE__)."/../classes/Altmetric.php");
require_once(dirname(__FILE__)."/../classes/REDCapManagement.php");
require_once(dirname(__FILE__)."/../classes/Cohorts.php");

if ($_GET['record']) {
    if ($_GET['record'] == "all") {
        if ($_GET['cohort']) {
            $records = Download::cohortRecordIds($token, $server, Application::getModule(), $_GET['cohort']);
        } else {
            $records = Download::recordIds($token, $server);
        }
    } else {
        $records = array($_GET['record']);
    }
}

$names = Download::names($token, $server);
if (isset($_GET['download']) && $records) {
    list($citations, $dates) = getCitationsForRecords($records, $token, $server, $metadata);
    $html = makePublicationListHTML($citations, $names, $dates);
    Application::writeHTMLToDoc($html, "Publications ".date("Y-m-d").".docx");
    exit;
}
if (isset($_GET['grantCounts'])) {
    $metadata = Download::metadata($token, $server);
    if (empty($records)) {
        $records = Download::records($token, $server);
    }
    $citationFields = ["record_id", "citation_pmid", "citation_include", "citation_grants"];
    $grantCounts = [];
    $redcapData = Download::fieldsForRecords($token, $server, $citationFields, $records);
    foreach ($redcapData as $row) {
        $grantStr = preg_replace("/\s+/", "", $row['citation_grants']);
        if ($row['citation_pmid'] && $grantStr && ($row['citation_include'] == "1")) {
            $awardNumbers = preg_split("/;/", $grantStr);
            foreach ($awardNumbers as $awardNo) {
                if ($awardNo) {
                    if (!isset($grantCounts[$awardNo])) {
                        $grantCounts[$awardNo] = 0;
                    }
                    $grantCounts[$awardNo]++;
                }
            }
        }
    }

        # slow
        // $pubs = new Publications($token, $server, $metadata);
        // $pubs->setRows($redcapData);
        // $recordGrantCounts = $pubs->getAllGrantCounts("Included");
        // foreach ($recordGrantCounts as $awardNo => $count) {
            // if (!isset($grantCounts[$awardNo])) {
                // $grantCounts[$awardNo] = 0;
            // }
            // $grantCounts[$awardNo] += $count;
        //}
    arsort($grantCounts);

    $fullURL = Application::link("publications/view.php").makeExtraURLParams(["trainingPeriodPlusDays", "begin", "end"]);
    list($url, $trainingPeriodParams) = REDCapManagement::splitURL($fullURL);

    $html = "";
    $html .= "<form method='GET' action='$url'>";
    $html .= REDCapManagement::makeHiddenInputs($trainingPeriodParams);
    $html .= "<h4>Pubs Associated With a Grant</h4>";
    $allSelected = "";
    if (!$_GET['grant'] || $_GET['grant'] == "all") {
        $allSelected = " selected";
    }
    $html .= "<p class='centered'><select name='grant'><option value='all'$allSelected>---ALL---</option>";
    foreach ($grantCounts as $awardNo => $count) {
        $selected = "";
        if ($_GET['grant'] == $awardNo) {
            $selected = " selected";
        }
        if ($_GET['record']) {
            $phrase = "citations";
            if ($count == 1) {
                $phrase = "citation";
            }
        } else {
            $phrase = "names in citations";
            if ($count == 1) {
                $phrase = "name in citations";
            }
        }
        $maxLen = 15;
        if (strlen($awardNo) > $maxLen) {
            $shownAwardNo = substr($awardNo, 0, $maxLen)."...";
        } else {
            $shownAwardNo = $awardNo;
        }
        $html .= "<option value='$awardNo'$selected>$shownAwardNo ($count $phrase)</option>";
    }
    $html .= "</select>";
    $html .= "<br><button>Re-Configure</button></p>";
    $html .= "</form>";
    echo $html;
    exit;
}

require_once(dirname(__FILE__)."/../charts/baseWeb.php");

$metadata = Download::metadata($token, $server);
$link = Application::link("publications/view.php")."&download".makeExtraURLParams();
echo "<h1>View Publications</h1>\n";
if (Application::hasComposer() && CareerDev::isVanderbilt()) {
    echo "<p class='centered'><a href='$link'>Download as MS Word doc</a></p>\n";
}
echo makeCustomizeTable($token, $server, $metadata);
if ($records) {
    list($citations, $dates) = getCitationsForRecords($records, $token, $server, $metadata);
    echo makePublicationSearch($names);
    echo makePublicationListHTML($citations, $names, $dates);
} else {
	echo makePublicationSearch($names);
}

function getCitationsForRecords($records, $token, $server, $metadata) {
    $trainingStarts = Download::oneField($token, $server, "summary_training_start");
    $trainingEnds = Download::oneField($token, $server, "summary_training_end");
    $confirmed = "Confirmed Publications";
    if ($_GET['grant'] && $_GET['grant'] != "all") {
        $confirmed .= " for Grant ".$_GET['grant'];
    }
    $notConfirmed = "Publications Yet to be Confirmed";
    $citations = [$confirmed => [], $notConfirmed => []];
    $dates = [];
    $lastNames = Download::lastnames($token, $server);
    $firstNames = Download::firstnames($token, $server);
    foreach ($records as $record) {
        $redcapData = Download::fieldsForRecords($token, $server, Application::getCitationFields($metadata), [$record]);
        $pubs = new Publications($token, $server, $metadata);
        $pubs->setRows($redcapData);
        if (isset($_GET['test'])) {
            Application::log($pubs->getCitationCount("Included")." citations included");
        }
        $notDone = $pubs->getCitationCollection("Not Done");

        if ($_GET['grant'] && ($_GET['grant'] != 'all')) {
            $included = new CitationCollection($record, $token, $server, "Filtered", [], $metadata, $lastNames, $firstNames);
            $recordCitations = $pubs->getCitationsForGrants($_GET['grant'], "Included");
            foreach ($recordCitations as $citation) {
                $included->addCitation($citation);
            }
            if (isset($_GET['test'])) {
                Application::log("Record $record has filtered ".$included->getCount()." records");
            }
        } else {
            $included = $pubs->getCitationCollection("Included");
            if (isset($_GET['test'])) {
                Application::log("Record $record has downloaded ".$included->getCount()." records");
            }
        }

        if ($_GET['trainingPeriodPlusDays']) {
            $trainingStart = $trainingStarts[$record];
            $trainingEnd = $trainingEnds[$record];
            if ($trainingStart) {
                $startTs = strtotime($trainingStart);
                if ($trainingEnd) {
                    $endTs = strtotime($trainingEnd);
                    $daysTimespan = $_GET['trainingPeriodPlusDays'] * 24 * 3600;
                    $endTs += $daysTimespan;
                } else {
                    # currently training or do not have end date?
                    $endTs = time();
                }
                $included->filterForTimespan($startTs, $endTs);
                $notDone->filterForTimespan($startTs, $endTs);
                $dates[$record] = date("m-d-Y", $startTs)." - ".date("m-d-Y", $endTs);
            } else {
                # do not filter
                $dates[$record] = "Training period not recorded";
            }
        } else if ($_GET['begin']) {
            $startTs = strtotime($_GET['begin']);
            if ($_GET['end']) {
                $endTs = strtotime($_GET['end']);
            } else {
                $endTs = time();
            }
            if ($startTs && $endTs) {
                $included->filterForTimespan($startTs, $endTs);
                $notDone->filterForTimespan($startTs, $endTs);
                $dates[$record] = date("m-d-Y", $startTs)." - ".date("m-d-Y", $endTs);
            }
        }

        if (isset($_GET['test'])) {
            Application::log("Record $record has ".$included->getCount()." records");
        }

        $citations[$confirmed][$record] = $included;
        $citations[$notConfirmed][$record] = $notDone;
    }
    return [$citations, $dates];
}

function totalCitationColls($citColls) {
    $total = 0;
    foreach ($citColls as $citColl) {
        $total += $citColl->getCount();
    }
    return $total;
}

function makePublicationListHTML($citations, $names, $dates) {
    $html = "";
    foreach ($citations as $header => $citColls) {
        $total = totalCitationColls($citColls);
        $html .= "<h2>$header (" . REDCapManagement::pretty($total) . ")</h2>\n";
        $html .= "<div class='centered' style='max-width: 800px;'>\n";
        if ($total == 0) {
            $html .= "<p class='centered'>No citations.</p>\n";
        } else {
            foreach ($citColls as $record => $citColl) {
                $name = $names[$record];
                $date = $dates[$record];
                $header = "<p class='centered'>$name (".$citColl->getCount().")";
                if ($date) {
                    $header .= "<br>$date";
                }
                $header .= "</p>\n";
                if ($citColl->getCount() > 0) {
                    $html .= $header;
                }

                $citations = $citColl->getCitations();
                foreach ($citations as $citation) {
                    $html .= "<p style='text-align: left;'>";
                    if (isset($_GET['altmetrics'])) {
                        $html .= $citation->getImage("left");
                    }
                    $html .= $citation->getCitationWithLink(TRUE, TRUE);
                    $html .= "</p>\n";
                }
            }
        }
        $html .= "</div>\n";
    }
    $html .= "<br><br><br>";
    return $html;
}

function makeExtraURLParams($exclude = []) {
    $additionalParams = "";
    $expected = ["record", "altmetrics", "trainingPeriodPlusDays", "grant", "begin", "end", "cohort"];
    foreach ($_GET as $key => $value) {
        if (isset($_GET[$key]) && in_array($key, $expected) && !in_array($key, $exclude)) {
            if ($value === "") {
                $additionalParams .= "&".$key;
            } else {
                $additionalParams .= "&$key=".urlencode($value);
            }
        }
    }
    return $additionalParams;
}

function makeCustomizeTable($token, $server, $metadata) {
    $cohorts = new Cohorts($token, $server, Application::getModule());
    $html = "";
    $style = "style='width: 250px; padding: 15px; vertical-align: top;'";
    $defaultDays = "";
    if (isset($_GET['trainingPeriodPlusDays']) && is_numeric($_GET['trainingPeriodPlusDays'])) {
        $defaultDays = $_GET['trainingPeriodPlusDays'];
    }
    $fullURL = Application::link("publications/view.php").makeExtraURLParams(["trainingPeriodPlusDays", "begin", "end"]);
    list($url, $trainingPeriodParams) = REDCapManagement::splitURL($fullURL);
    $fullURLMinusCohort = preg_replace("/&cohort=[^\&]+/", "", $fullURL);
    $begin = $_GET['begin'];
    $end = $_GET['end'];

    $html .= "<table class='centered'>\n";
    $html .= "<tr>\n";
    $html .= "<td colspan='2' $style><h2 class='nomargin'>Customize</h2></td>\n";
    $html .= "</tr>\n";
    $html .= "<tr>\n";
    $html .= "<td $style>".Altmetric::makeClickText()."</td>\n";
    $html .= "<td $style>";
    $html .= "<h4>Show Pubs During Training</h4>";
    $html .= "<form action='$url' method='GET'>";
    $html .= REDCapManagement::makeHiddenInputs($trainingPeriodParams);
    $html .= "<p class='centered'>Additional Days After Training: <input type='number' name='trainingPeriodPlusDays' style='width: 60px;' value='$defaultDays'><br><button>Re-Configure</button></p>";
    $html .= "</form>";
    $html .= "</td>";
    $html .= "</tr>";
    $html .= "<tr>";
    $html .= "<td>";
    $html .= $cohorts->makeCohortSelect($_GET['cohort'], "location.href=\"$fullURLMinusCohort\"+\"&cohort=\"+encodeURIComponent($(this).val());");
    $html .= "</td>";
    $html .= "<td>";
    $html .= "<h4>Show Pubs During Timespan</h4>";
    $html .= "<form action='$url' method='GET'>";
    $html .= REDCapManagement::makeHiddenInputs($trainingPeriodParams);
    $html .= "<p class='centered'>Start Date: <input type='date' name='begin' style='width: 150px;' value='$begin'><br>";
    $html .= "End Date: <input type='date' name='end' value='$end' style='width: 150px;'><br>";
    $html .= "<button>Re-Configure</button></p>";
    $html .= "</form>";
    $html .= "<div id='grantCounts'>";
    $grantCountsFetchUrl = Application::link("publications/view.php").makeExtraURLParams(["trainingPeriodPlusDays", "begin", "end"])."&grantCounts";
    if ($_GET['grant'] && ($_GET['grant'] != "all")) {
        $html .= "<script>$(document).ready(function() { downloadUrlIntoPage(\"$grantCountsFetchUrl\", \"#grantCounts\"); });</script>";
    } else {
        $html .= "<p class='centered'><a href='javascript:;' onclick='downloadUrlIntoPage(\"$grantCountsFetchUrl\", \"#grantCounts\");'>Get Counts to Select a Grant</a><br>(Computationally Expensive)</p>";
    }
    $html .= "</div>";
    $html .= "</td>\n";
    $html .= "</tr>\n";
    $html .= "</table>\n";

    return $html;
}

function makePublicationSearch($names) {
	$html = "";
	$html .= "<h2>View a Scholar's Publications</h2>\n";
	$html .= "<p class='centered'><a href='".Application::link("publications/view.php")."&record=all".makeExtraURLParams(["record"])."'>View All Scholars' Publications</a></p>\n";
	$html .= "<p class='centered'><select onchange='window.location.href = \"".Application::link("publications/view.php").makeExtraURLParams(["record"])."&record=\" + $(this).val();'><option value=''>---SELECT---</option>\n";
	foreach ($names as $recordId => $name) {
		$html .= "<option value='$recordId'";
		if ($_GET['record'] && ($_GET['record'] == $recordId)) {
			$html .= " selected";
		}
		$html .= ">$name</option>\n";
	}
	$html .= "</select></p>\n";
	return $html;
}
