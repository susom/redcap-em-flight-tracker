<?php

use \Vanderbilt\CareerDevLibrary\Application;
use \Vanderbilt\CareerDevLibrary\Download;
use \Vanderbilt\CareerDevLibrary\NameMatcher;
use \Vanderbilt\CareerDevLibrary\Cohorts;
use \Vanderbilt\CareerDevLibrary\REDCapManagement;
use \Vanderbilt\CareerDevLibrary\Stats;
use \Vanderbilt\CareerDevLibrary\BarChart;
use \Vanderbilt\CareerDevLibrary\SocialNetworkChart;
use \Vanderbilt\CareerDevLibrary\Citation;
use \Vanderbilt\FlightTrackerExternalModule\CareerDev;

require_once(dirname(__FILE__)."/../classes/Download.php");
require_once(dirname(__FILE__)."/../classes/NameMatcher.php");
require_once(dirname(__FILE__)."/../classes/Cohorts.php");
require_once(dirname(__FILE__)."/../classes/REDCapManagement.php");
require_once(dirname(__FILE__)."/../classes/Stats.php");
require_once(dirname(__FILE__)."/../classes/BarChart.php");
require_once(dirname(__FILE__)."/../classes/SocialNetworkChart.php");
require_once(dirname(__FILE__)."/../classes/Citation.php");
require_once(dirname(__FILE__)."/../Application.php");
require_once(dirname(__FILE__)."/../CareerDev.php");
require_once(dirname(__FILE__)."/../charts/baseWeb.php");
if ($_GET['record']) {
    $highlightedRecord = $_GET['record'];
}

define('PUBYEAR_SELECT', '---pub_year---');
define('START_YEAR', 2010);

$metadata = Download::metadata($token, $server);
$metadataFields = REDCapManagement::getFieldsFromMetadata($metadata);
$choices = REDCapManagement::getChoices($metadata);
$metadataLabels = REDCapManagement::getLabels($metadata);
if ($_GET['cohort'] && ($_GET['cohort'] != "all")) {
    $records = Download::cohortRecordIds($token, $server, $metadata, $_GET['cohort']);
} else if ($_GET['cohort'] == "all") {
    $records = Download::recordIds($token, $server);
} else {
    $records = [];
}
$networkChartName = "chartdiv";
$includeHeaders = !isset($_GET['headers']) || ($_GET['headers'] != "false");
$possibleFields = ["record_id", "summary_primary_dept", "summary_gender", "summary_urm", "summary_degrees", "summary_current_division"];
if (CareerDev::isVanderbilt() && in_array("identifier_grant_type", $metadataFields)) {
    $possibleFields[] = "identifier_grant_type";
}
if ($_GET['field'] && in_array($_GET['field'], $possibleFields)) {
    $indexByField = $_GET['field'];
} else if ($_GET['field'] == PUBYEAR_SELECT) {
    $indexByField = PUBYEAR_SELECT;
} else {
    $indexByField = "record_id";
}
$includeMentors = ($_GET['mentors'] == "on") && isForIndividualScholars($indexByField);
$otherMentorsOnly = ($_GET['other_mentors'] == "on") && isForIndividualScholars($indexByField);

$cohorts = new Cohorts($token, $server, $metadata);

if ($includeHeaders) {
    echo "<h1>Publishing Collaborations Among Scholars</h1>\n";
    list($url, $params) = REDCapManagement::splitURL(Application::link("socialNetwork/coauthorship.php"));
    echo "<form method='GET' action='$url'>\n";
    foreach ($params as $param => $value) {
        echo "<input type='hidden' name='$param' value='$value'>";
    }
    echo "<p class='centered'><a href='" . Application::link("cohorts/addCohort.php") . "'>Make a Cohort</a> to View a Sub-Group<br>";
    echo $cohorts->makeCohortSelect($_GET['cohort'], "", TRUE) . "<br>";
    echo makeFieldSelect($indexByField, $possibleFields, $metadataLabels) . "<br>";
    $style = "";
    if (!isForIndividualScholars($indexByField)) {
        $style = " style='display: none;'";
    }
    $checked = [];
    foreach (['mentors', 'other_mentors'] as $key) {
        if ($_GET[$key] == "on") {
            $checked[$key] = " checked";
        } else {
            $checked[$key] = "";
        }
    }

    echo "<span class='mentorCheckbox'$style><input type='checkbox' name='mentors'{$checked['mentors']}> Include Mentors' Collaborations with Scholars<br></span>";
    echo "<span class='mentorCheckbox'$style><input type='checkbox' name='other_mentors'{$checked['other_mentors']}> Show Only Collaborations with Multiple Mentors<br></span>";
    echo "<button>Go!</button></p></form>";
}

if (isset($_GET['cohort']) && !empty($records)) {
    $inlineDefinitions = [
        "given" => "Citations <u>to</u> others in network",
        "received" => "Citations <u>by</u> others in network",
    ];
    $topDefinitions = [
        "Scholar" => "The person whose publication is being examined.",
        "Collaborator" => "A different person who has co-authored a paper with the Scholar.",
        "Connection" => "A paper published with a Scholar and a Collaborator. (One paper may have more than one connection.)",
    ];

    $names = Download::names($token, $server);
    if ($otherMentorsOnly) {
        $possibleLastNames = [];
        $possibleFirstNames = [];
    } else {
        $possibleLastNames = getExplodedLastNames($token, $server);
        $possibleFirstNames = getExplodedFirstNames($token, $server);
    }
    $mentors = [];
    if ($includeMentors || $otherMentorsOnly) {
        $mentors = Download::primaryMentors($token, $server);
        addMentorNamesForRecords($possibleFirstNames, $possibleLastNames, $records, $mentors, $highlightedRecord);
    }
    $fields = ["record_id", "citation_include", "citation_authors", "citation_year", "citation_month", "citation_day"];
    if (!in_array($indexByField, $fields)) {
        $fields[] = $indexByField;
    }

    $matches = [];
    $pubs = [];
    $index = [];
    if ($highlightedRecord) {
        foreach ($records as $fromRecordId) {
            if ($fromRecordId == $highlightedRecord) {
                $matches[$highlightedRecord] = findMatchesForRecord($index, $pubs, $token, $server, $fields, $highlightedRecord, $possibleFirstNames, $possibleLastNames, $indexByField, $records);
            } else {
                $matches[$fromRecordId] = [];
            }
        }
    } else {
        foreach ($records as $fromRecordId) {
            $fromMatches = findMatchesForRecord($index, $pubs, $token, $server, $fields, $fromRecordId, $possibleFirstNames, $possibleLastNames, $indexByField, $records);
            if ($otherMentorsOnly) {
                if (count($fromMatches) > 1) {
                    $matches[$fromRecordId] = $fromMatches;
                }
            } else {
                $matches[$fromRecordId] = $fromMatches;
            }
        }
    }

    list($connections, $chartData, $uniqueNames) = makeEdges($matches, $indexByField, $names, $choices, $index, $pubs);
    if ($includeMentors) {
        $mentorConnections = getAvgMentorConnections($matches);
    }
    list($stats, $maxConnections, $maxNames, $maxCollaborators, $maxCollabNames, $totalCollaborators) = makeSummaryStats($connections, $names);

    if ($includeHeaders) {
        echo "<table style='margin: 30px auto; max-width: 800px;' class='bordered'>\n";
        echo "<tr><td colspan='2'><h4 class='nomargin'>Definitions</h4>";
        $lines = [];
        foreach ($topDefinitions as $term => $def) {
            $lines[] = "<b>$term</b> - $def";
        }
        echo "<div class='centered'>".implode("<br>", $lines)."</div>";
        foreach (array_keys($connections) as $type) {
            echo "<tr><td colspan='2' class='centered green'><h4 class='nomargin'>".ucfirst($type)."</h4>{$inlineDefinitions[$type]}</td></tr>";
            if ($stats) {
                echo "<tr><th>Total Connections</th><td>".REDCapManagement::pretty(array_sum($stats[$type]->getValues()))."</td></tr>\n";
                echo "<tr><th>Number of Scholars with Connections</th><td>n = ".REDCapManagement::pretty($stats[$type]->getN())."</td></tr>\n";
                echo "<tr><th>Average Number of Collaborators with at least one Connection</th><td>".REDCapManagement::pretty($totalCollaborators[$type] / $stats[$type]->getN(), 1)."</td></tr>\n";
                echo "<tr><th>Mean of Connections</th><td>&mu; = ".REDCapManagement::pretty($stats[$type]->mean(), 1)."</td></tr>\n";
                echo "<tr><th>Average Connections with a Collaborator</th><td>".REDCapManagement::pretty($stats[$type]->sum() / $totalCollaborators[$type], 1)."</td></tr>\n";
                echo "<tr><th>Median of Connections</th><td>".REDCapManagement::pretty($stats[$type]->median(), 1)."</td></tr>\n";
                echo "<tr><th>Mode of Connections</th><td>".implode(", ", $stats[$type]->mode())."</td></tr>\n";
                echo "<tr><th>Standard Deviation</th><td>&sigma; = ".REDCapManagement::pretty($stats[$type]->getSigma(), 1)."</td></tr>\n";
                echo "<tr><th>Maximum Connections</th><td>max = ".REDCapManagement::pretty($maxConnections[$type])." (".REDCapManagement::makeConjunction($maxNames[$type]).")</td></tr>\n";
                echo "<tr><th>Maximum Number of Collaborators</th><td>max = ".REDCapManagement::pretty($maxCollaborators[$type])." (".REDCapManagement::makeConjunction($maxCollabNames[$type]).")</td></tr>\n";
                if ($includeMentors && ($type == "given")) {
                    echo "<tr><th>Average Connections per Mentor with All Scholars</th><td>".REDCapManagement::pretty($mentorConnections, 1)."</td></tr>\n";
                }
            }
        }
        echo "</table>\n";
    }

    echo makeLegendHTML($indexByField);
    $socialNetwork = new SocialNetworkChart($networkChartName, $chartData);
    $socialNetwork->setNonRibbon(count($uniqueNames) > 100);
    echo $socialNetwork->getImportHTML();
    echo $socialNetwork->getHTML(900, 700);

    if ($includeHeaders) {
        echo "<br><br>";
        list($barChartCols, $barChartLabels) = makePublicationColsAndLabels($pubs);
        $chart = new BarChart($barChartCols, $barChartLabels, "barChart");
        $chart->setXAxisLabel("Year");
        $chart->setYAxisLabel("Number of Connections");
        echo $chart->getImportHTML();
        echo $chart->getHTML(500, 300);
    }
}




function getCitationTimestamp($row) {
    $year = Citation::transformYear($row['citation_year']);
    $month = $row['citation_month'];
    $day = $row['citation_day'];
    return Citation::transformDateToTimestamp(Citation::transformIntoDate($year, $month, $day));
}

function makePublicationColsAndLabels($pubs) {
    $data = [];
    foreach ($pubs as $loc => $ts) {
        $year = date("Y", $ts);
        if (!isset($data[$year])) {
            $data[$year] = 0;
        }
        $data[$year]++;
    }
    ksort($data);
    for ($year = min(array_keys($data)); $year <= max(array_keys($data)); $year++) {
        if (!isset($data[$year])) {
            $data[$year] = 0;
        }
    }
    ksort($data);

    $labels = array_keys($data);
    $cols = array_values($data);
    return [$cols, $labels];
}

function makeFieldSelect($selectedField, $fields, $metadataLabels) {
    $html = "";
    $pubyearSelect = PUBYEAR_SELECT;
    $html .= "Index by Field: <select name='field' onchange='if (($(this).val() == \"record_id\") || ($(this).val() == \"$pubyearSelect\")) { $(\".mentorCheckbox\").show(); } else { $(\".mentorCheckbox\").hide(); }'>";
    foreach ($fields as $field) {
        $selected = "";
        if ($field == $selectedField) {
            $selected = " selected";
        }
        $html .= "<option value='$field'$selected>".$metadataLabels[$field]."</option>";
    }
    $selected = "";
    if ($selectedField == PUBYEAR_SELECT) {
        $selected = " selected";
    }
    $html .= "<option value='".PUBYEAR_SELECT."'$selected>Publication Year</option>";
    $html .= "</select>";
    return $html;
}

function getAvgMentorConnections($matches) {
    $mentorConnections = 0;
    $mentorCollaborators = [];
    foreach (array_keys($matches) as $fromRecordId) {
        foreach ($matches[$fromRecordId] as $toRecordId => $fromInstances) {
            if (preg_match("/^Mentor/", $toRecordId)) {
                $mentor = $toRecordId;
                $mentorConnections += count($fromInstances);
                if (!in_array($mentor, $mentorCollaborators)) {
                    $mentorCollaborators[] = $mentor;
                }
            }
        }
    }
    return $mentorConnections / count($mentorCollaborators);
}

function generateColorWheel($numColors, $startYear, $endYear) {
    # from https://learnui.design/tools/data-color-picker.html#palette
    $colors = [
        "#003f5c",
        "#2f4b7c",
        "#665191",
        "#a05195",
        "#d45087",
        "#f95d6a",
        "#ff7c43",
        "#ffa600",
    ];
    $unknownColor = '#000000';

    if ($numColors > count($colors)) {
        throw new \Exception("Requested $numColors colors; maximum is ".count($colors));
    }

    $yearspan = $endYear - $startYear + 1;
    $yearsPerColor = 1;
    while ($yearspan > $yearsPerColor * count($colors)) {
        $yearsPerColor++;
    }

    $colorWheel = ["Unknown" => $unknownColor];
    $i = 0;
    for ($year_i = $startYear; $year_i <= $endYear; $year_i += $yearsPerColor) {
        for ($year = $year_i; $year < $year_i + $yearsPerColor; $year++) {
            $colorWheel[$year] = $colors[$i];
        }
        $i++;
    }
    return $colorWheel;
}

function collapseColorWheel($colorWheel) {
    $newWheel = [];
    $reverseWheel = [];
    ksort($colorWheel);
    foreach ($colorWheel as $label => $hex) {
        if (is_numeric($label)) {
            $year = $label;
            if (!isset($reverseWheel[$hex])) {
                $reverseWheel[$hex] = ["start" => $year, "end" => $year];
            }
            $reverseWheel[$hex]["end"] = $year;
        } else {
            # unknown
            $newWheel[$label] = $hex;
        }
    }
    foreach ($reverseWheel as $hex => $ary) {
        if ($ary["start"] == $ary["end"]) {
            $yearspan = $ary["start"];
        } else {
            $yearspan = $ary["start"]."-".$ary["end"];
        }
        $newWheel[$yearspan] = $hex;
    }
    return $newWheel;
}

function makeLegendHTML($indexByField) {
    if ($indexByField == PUBYEAR_SELECT) {
        $colorWheel = generateColorWheel(8, START_YEAR, date("Y"));
        $colorWheel = collapseColorWheel($colorWheel);

        $lines = [];
        foreach ($colorWheel as $label => $hex) {
            $lines[] = "<span style='background-color: $hex'>&nbsp;&nbsp;&nbsp;</span> $label";
        }
        return "<div style='background-color: white;' class='smaller centered max-width'>".implode("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", $lines)."</div>";
    }
    return "";
}

function makeEdges($matches, $indexByField, $names, $choices, $index, $pubs) {
    $colorWheel = generateColorWheel(8, START_YEAR, date("Y"));
    $combine = ["VCTRS", "VPSD", "VFRS"];
    $connections = ["given" => [], "received" => [], ];
    $chartData = [];
    $uniqueNames = [];
    foreach (array_keys($matches) as $fromRecordId) {
        $connections["given"][$fromRecordId] = [];
        foreach ($matches[$fromRecordId] as $toRecordId => $fromInstances) {
            if (isForIndividualScholars($indexByField)) {
                if ($names[$fromRecordId]) {
                    $from = $fromRecordId.": ".$names[$fromRecordId];
                } else {
                    # mentor
                    $from = $fromRecordId;
                }
                if ($names[$toRecordId]) {
                    $to = $toRecordId . ": " . $names[$toRecordId];
                } else {
                    # mentor
                    $to = $toRecordId;
                }
            } else if ($choices[$indexByField]) {
                $from = $choices[$indexByField][$index[$fromRecordId]];
                $to = $choices[$indexByField][$index[$toRecordId]];
            } else {
                $from = $index[$fromRecordId];
                $to = $index[$toRecordId];
            }
            foreach ([$to, $from] as $name) {
                if (!in_array($name, $uniqueNames)) {
                    $uniqueNames[] = $name;
                }
            }
            if (in_array($from, $combine)) {
                $from = implode(" / ", $combine);
            }
            if (in_array($to, $combine)) {
                $to = implode(" / ", $combine);
            }
            $numItems = count($fromInstances);
            $chartRow = ["from" => $from, "to" => $to];
            if ($indexByField == PUBYEAR_SELECT) {
                $dates = [];
                foreach ($pubs as $key => $ts) {
                    $nodes = preg_split("/:/", $key);
                    if ($ts && ($fromRecordId == $nodes[0]) && ($toRecordId == $nodes[1])) {
                        $year = date("Y", $ts);
                        if (!isset($dates[$year])) {
                            $dates[$year] = 0;
                        }
                        $dates[$year]++;
                    }
                }
                if (count($dates) > 0) {
                    foreach ($dates as $year => $count) {
                        $newRow = $chartRow;
                        $newRow['nodeColor'] = $colorWheel[$year];
                        $newRow['value'] = $count;
                        $chartData[] = $newRow;
                    }
                } else {
                    $chartRow['nodeColor'] = $colorWheel['Unknown'];
                    $chartRow['value'] = $numItems;
                    $chartData[] = $chartRow;
               }
            } else {
                $chartRow['value'] = $numItems;
                $chartData[] = $chartRow;
            }
            $connections["given"][$fromRecordId][$toRecordId] = $numItems;

            if (!isset($connections["received"][$toRecordId])) {
                $connections["received"][$toRecordId] = [];
            }
            $connections["received"][$toRecordId][$fromRecordId] = $numItems;
            $totalConnections += $numItems;
        }
    }
    return [$connections, $chartData, $uniqueNames];
}

function findMatchesForRecord(&$index, &$pubs, $token, $server, $fields, $fromRecordId, $possibleFirstNames, $possibleLastNames, $indexByField, $records) {
    $redcapData = Download::fieldsForRecords($token, $server, $fields, [$fromRecordId]);
    $matches = [];
    foreach ($redcapData as $row) {
        if ($row['redcap_repeat_instrument'] == "") {
            $index[$fromRecordId] = $row[$indexByField];
        }
        if (($row['redcap_repeat_instrument'] == "citation") && ($row['citation_include'] == '1')) {
            $authors = preg_split("/,\s*/", $row['citation_authors']);
            foreach ($authors as $author) {
                list($authorLast, $authorInitials) = NameMatcher::splitName($author);
                foreach (array_keys($possibleFirstNames) as $toRecordId) {
                    if ($toRecordId != $fromRecordId && in_array($toRecordId, $records)) {
                        foreach ($possibleFirstNames[$toRecordId] as $firstName) {
                            foreach ($possibleLastNames[$toRecordId] as $lastName) {
                                if (NameMatcher::matchByInitials($authorLast, $authorInitials, $lastName, $firstName)) {
                                    // echo "Matched $fromRecordId $authorLast, $authorInitials to $toRecordId $lastName, $firstName<br>";
                                    if (!isset($matches[$toRecordId])) {
                                        $matches[$toRecordId] = [];
                                    }
                                    $matches[$toRecordId][] = $row['redcap_repeat_instrument'];
                                    $ts = getCitationTimestamp($row);
                                    if ($ts) {
                                        $pubs["$fromRecordId:$toRecordId:".$row['redcap_repeat_instance']] = $ts;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    return $matches;
}

function getExplodedFirstNames($token, $server) {
    $firstnames = Download::firstnames($token, $server);
    $possibilities = [];
    foreach (array_keys($firstnames) as $recordId) {
        $possibilities[$recordId] = NameMatcher::explodeFirstName($firstnames[$recordId]);
    }
    return $possibilities;
}

function getExplodedLastNames($token, $server) {
    $lastnames = Download::lastnames($token, $server);
    $possibilities = [];
    foreach (array_keys($lastnames) as $recordId) {
        $possibilities[$recordId] = NameMatcher::explodeLastName($lastnames[$recordId]);
    }
    return $possibilities;
}

function makeSummaryStats($connections, $names) {
    $stats = [];
    $maxConnections = [];
    $maxNames = [];
    $maxCollaborators = [];
    $maxCollabNames = [];
    $totalCollaborators = [];
    foreach ($connections as $type => $typeConnections) {
        $dataValues = [];
        $maxCollaborators[$type] = 0;
        $totalCollaborators[$type] = 0;
        foreach ($typeConnections as $recordId => $indivConnections) {
            $numConnections = array_sum(array_values($indivConnections));
            if ($numConnections > 0) {
                $dataValues[] = $numConnections;
                $numCollaborators = count($indivConnections);
                $totalCollaborators[$type] += $numCollaborators;
                if ($numCollaborators > $maxCollaborators[$type]) {
                    $maxCollaborators[$type] = $numCollaborators;
                }
            }
        }
        $stats[$type] = new Stats($dataValues);
        $maxConnections[$type] = max($dataValues);
        $maxNames[$type] = [];
        $maxCollabNames[$type] = [];
        foreach ($typeConnections as $recordId => $indivConnections) {
            $numConnections = array_sum(array_values($indivConnections));
            $numCollaborators = count($indivConnections);
            if ($numConnections == $maxConnections[$type]) {
                if ($names[$recordId]) {
                    $maxNames[$type][] = $names[$recordId];
                } else {
                    # mentor
                    $maxNames[$type][] = $recordId;
                }
            }
            if ($numCollaborators == $maxCollaborators[$type]) {
                if ($names[$recordId]) {
                    $maxCollabNames[$type][] = $names[$recordId];
                } else {
                    # mentor
                    $maxCollabNames[$type][] = $recordId;
                }
            }
        }
    }
    return [$stats, $maxConnections, $maxNames, $maxCollaborators, $maxCollabNames, $totalCollaborators];
}

function addMentorNamesForRecords(&$firstNames, &$lastNames, &$records, $mentors, $highlightedRecord = FALSE) {
    foreach ($mentors as $recordId => $mentorList) {
        $useThisRecord = !$highlightedRecord || ($highlightedRecord == $recordId);
        if (in_array($recordId, $records) && $useThisRecord) {
            $i = 1;
            foreach ($mentorList as $mentor) {
                list($first, $last) = NameMatcher::splitName($mentor);
                $alreadyPresent = FALSE;
                foreach ($firstNames as $currRecordId => $currFirst) {
                    $currLast = $lastNames[$currRecordId];
                    if (NameMatcher::matchByInitials($last, $first, $currLast, $currFirst)) {
                        $alreadyPresent = TRUE;
                        break;
                    }
                }
                if (!$alreadyPresent) {
                    $key = "Mentor $mentor";
                    $records[] = $key;
                    $firstNames[$key] = NameMatcher::explodeFirstName($first);
                    $lastNames[$key] = NameMatcher::explodeLastName($last);
                }
                $i++;
            }
        }
    }
}

function isForIndividualScholars($indexByField) {
    return in_array($indexByField, ["record_id", PUBYEAR_SELECT]);
}