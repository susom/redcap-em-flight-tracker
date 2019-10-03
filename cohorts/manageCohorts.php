<?php

use \Vanderbilt\CareerDevLibrary\Cohorts;
use \Vanderbilt\CareerDevLibrary\CohortConfig;
use \Vanderbilt\CareerDevLibrary\Download;
use \Vanderbilt\CareerDevLibrary\Filter;
use \Vanderbilt\FlightTrackerExternalModule\CareerDev;

# rename, delete, reorder

require_once(dirname(__FILE__)."/../small_base.php");
require_once(dirname(__FILE__)."/../CareerDev.php");
require_once(dirname(__FILE__)."/../charts/baseWeb.php");
require_once(dirname(__FILE__)."/../wrangler/css.php");
require_once(dirname(__FILE__)."/../classes/Download.php");
require_once(dirname(__FILE__)."/../classes/Cohorts.php");
require_once(dirname(__FILE__)."/../classes/CohortConfig.php");
require_once(dirname(__FILE__)."/../classes/Filter.php");

echo \Vanderbilt\FlightTrackerExternalModule\getCohortHeaderHTML();

?>
<script>
function rename(selector, button = false) {
	if (button) {
		var showSelector = selector+"_div";
		var hideSelector = selector+"_title";
		var inputSelector = selector+"_input";
		$(showSelector).val($(hideSelector).html());
		$(showSelector).show();
		$(hideSelector).hide();
		$(inputSelector).focus();
	} else {
		var inputSelector = selector;
		var newVal = $(inputSelector).val();
		if (newVal) {
			if (!newVal.match(/[#'"]/)) {
				var showSelector = inputSelector.replace(/_input/, "_title");
				var hideSelector = inputSelector.replace(/_input/, "_div");
				var processingSelector = inputSelector.replace(/_input/, "_processing");
				var oldVal = $(showSelector).html(); 

				$(hideSelector).hide();
				$(showSelector).hide();  // initially, hide
				$(processingSelector).show();
				presentScreen('Processing...');
				$.post("<?= CareerDev::link("cohorts/renameCohort.php") ?>", { oldValue: oldVal, newValue: newVal }, function(data) {
					clearScreen();
					if (data.match(/success/)) {
						$(showSelector).html(newVal);
						$(hideSelector).val("");
						$(showSelector).show();
						$(processingSelector).hide();
					} else {
						alert("Rename failed: "+data);
					}
				}); 
			} else {
				alert("The title cannot contain #, ', and \".");
			}
		} else {
			alert("You must specify a title.");
		}
	}
}

function deleteCohort(cohort, selector) {
	var delSelector = selector+"_delete";
	var divSelector = selector+"_div";
	var titleSelector = selector+"_title";
	$(delSelector).show();
	$(divSelector).hide();
	$(titleSelector).hide();

	presentScreen('Deleting...');
	$.post("<?= CareerDev::link("cohorts/deleteCohort.php") ?>", { cohort: cohort }, function(data) {
		clearScreen();
		if (data.match(/success/)) {
			$(selector).remove();
			alert("Delete successful!");
		} else {
			$(delSelector).hide();
			$(titleSelector).show();
			alert("Delete failed: "+data);
		}
	});
}

function cancel(selector) {
	var divSelector = selector+"_div";
	var inputSelector = selector+"_input";
	var titleSelector = selector+"_title";
	$(divSelector).hide();
	$(inputSelector).val("");
	$(titleSelector).show();
}
</script>

<style>
.processing { color: red; text-align: center; font-style: italic; font-size: 16px; }
</style>
<div id='content'>
<h1>Manage Cohorts</h1>

<?php

$cohorts = new Cohorts($token, $server, CareerDev::getModule());
$cohortTitles = $cohorts->getCohortTitles();
if (count($cohortTitles) > 0) {
	echo "<table class='centered'>\n";
	echo "<tr class='paddedRow borderedRow whiteRow centeredRow'><td></td><th>Cohort Size</th><th>Delete</th><th>Rename</th></tr>\n";
	$metadata = Download::metadata($token, $server);
	foreach ($cohortTitles as $title) {
		$filter = new Filter($token, $server, $metadata);
		$config = $cohorts->getCohort($title);
		$records = $filter->getRecords($config);
		$htmlTitle = \Vanderbilt\FlightTrackerExternalModule\makeHTMLId($title);
		echo "<tr id='".$htmlTitle."' class='ui-widget-content paddedRow whiteRow borderedRow centeredRow'>\n";
		$colWidth = 150;
		echo "<th style='width: ".$colWidth."px;'><div id='".$htmlTitle."_title'>$title</div><div style='display: none;' id='".$htmlTitle."_div'><input style='width: ".$colWidth."px;' id='".$htmlTitle."_input'><br><button class='green' onclick='rename(\"#".$htmlTitle."_input\");'>&check;</button>&nbsp;<button class='red' onclick='cancel(\"#".$htmlTitle."\");'>X</button></div><div id='".$htmlTitle."_processing' class='processing' style='display: none;'>Processing...</div><div class='processing' style='display: none;' id='".$htmlTitle."_delete'>Deleting...</div></th>\n";
		echo "<td>".count($records)." Scholars</td>\n";
		echo "<td><button class='red biggerButton' onclick='deleteCohort(\"$title\", \"#$htmlTitle\");' style='font-weight: bold;'>X</button></td>\n";
		echo "<td><button class='biggerButton' onclick='rename(\"#$htmlTitle\", this);'>Rename</button></td>\n";
		echo "</tr>\n";
	}
	echo "</table>\n";
} else {
	echo "<p class='centered'>No Cohorts Available</p>\n";
}
echo "</div>\n";
