<?php

namespace Vanderbilt\CareerDevLibrary;

use \Vanderbilt\FlightTrackerExternalModule\CareerDev;


# This class handles links within the code. It puts in the appropriate prefix to relative links as well.
# All methods are static. No need to instatiate this class.

require_once(dirname(__FILE__)."/../../../redcap_connect.php");
require_once(dirname(__FILE__)."/../Application.php");

class Links {
	private static $server;

	# returns WITHOUT trailing "/"
	public static function getServer() {
		if (!self::$server) {
			if (preg_match("/^https?:\/\/[^\/]+/i", APP_PATH_WEBROOT_FULL, $matches)) {
				self::$server = $matches[0];
			}
		}
		return self::$server;
	}

	public function getServer_test($tester) {
		$server = self::getServer();
		$tester->assertEqual($server, substr(APP_PATH_WEBROOT_FULL, 0, strlen($server)));
	}

	public static function makeLink($url, $text, $launchNewWindow = FALSE, $linkClass = "") {
		if (!preg_match("/^https?:/i", $url)) {
			$server = self::getServer();
			if (preg_match("/^\//", $url)) {
				$url = $server.$url;
			} else {
				$url = $server."/".$url;
			}
		}

		$html = "";
		$html .= "<a href='$url'";
		if ($launchNewWindow) {
			$html .= " target='_blank'";
		}
		if ($linkClass) {
			$html .= " class='$linkClass'";
		}
		$html .= ">$text</a>";
		return $html;
	}

	public function makeLink_test($tester) {
		$url = "https://redcap.vanderbilt.edu/plugins/career_dev/index.php";
		$text = "Test Link";
		$link = self::makeLink($url, $text);
		$tester->assertMatch("/$text/", $link);
		$tester->assertMatch("/<a /", $link);
		$linkUrl = "";
		if (preg_match("/href\s*=\s*'[^']+'/i", $link, $matches)) {
			$linkUrl = preg_replace("/^href\s*=\s*'/i", "", $matches[0]);
			$linkUrl = preg_replace("/'$/", "", $linkUrl);
		}
		$tester->assertEqual($url, $linkUrl);
	}

	private static function link($relativeUrl) {
		$module = Application::getModule();
                $relativeUrl = preg_replace("/^\//", "", $relativeUrl);

                if ($module) {
                        return $module->getUrl($relativeUrl);
                }
                return "";
	}

	public static function makeProfileLink($pid, $text, $recordId = "", $markAsNew = FALSE, $linkClass = "") {
		$url = self::link("profile.php");
		if ($recordId) {
			$url .= "&record=".$recordId;
		}
		return self::makeLink($url, $text, FALSE, $linkClass);
	}

	# synonym to makeDataWranglingLink
	public static function makeSelectLink($pid, $text, $recordId = "", $markAsNew = FALSE, $linkClass = "") {
		return self::makeDataWranglingLink($pid, $text, $recordId, $markAsNew, $linkClass);
	}

	# synonym to makeSelectLink
	public static function makeDataWranglingLink($pid, $text, $recordId = "", $markAsNew = FALSE, $linkClass = "") {
		$url = self::link("wrangler/index.php");
		if ($recordId) {
			$url = $url."&record=".$recordId;
		}
		if ($markAsNew) {
			$url = $url."&new";
		}
		return self::makeLink($url, $text, FALSE, $linkClass);
	}

	public static function makeEmailManagementLink($pid, $text, $markAsNew = FALSE, $linkClass = "") {
		return self::makeEmailMgmtLink($pid, $text, $markAsNew, $linkClass);
	}

	public static function makeEmailMgmtLink($pid, $text, $markAsNew = FALSE, $linkClass = "") {
		$url = self::link("emailMgmt/index.php");
		if ($markAsNew) {
			$url = $url."&new";
		}
		return self::makeLink($url, $text, FALSE, $linkClass);
	}

	public static function makePubWranglingLink($pid, $text, $recordId = "", $markAsNew = FALSE, $linkClass = "") {
		$url = self::link("wrangler/pubs.php");
		if ($recordId) {
			$url = $url."&record=".$recordId;
		}
		if ($markAsNew) {
			$url = $url."&new";
		}
		return self::makeLink($url, $text, FALSE, $linkClass);
	}

	public static function makeReportLink($pid, $reportId, $text) {
		$url = APP_PATH_WEBROOT."DataExport/index.php?pid=".$pid."&report_id=".$reportId;
		return self::makeLink($url, $text);
	}

	public static function makeRecordHomeLink($pid, $recordId, $text, $armno = 1) {
		$url = APP_PATH_WEBROOT."DataEntry/record_home.php?pid=".$pid."&arm=".$armno."&id=".$recordId;
		return self::makeLink($url, $text);
	}

	public static function makePubLink($pid, $recordId, $event_id, $text) {
		return self::makeFormLink($pid, $recordId, $event_id, $text, "publications");
	}

	public static function makeIdentifiersLink($pid, $recordId, $event_id, $text, $linkClass = "") {
		return self::makeFormLink($pid, $recordId, $event_id, $text, "identifiers", 1, $linkClass);
	}

	public static function makeSummaryLink($pid, $recordId, $event_id, $text, $linkClass = "") {
		return self::makeFormLink($pid, $recordId, $event_id, $text, "summary", 1, $linkClass);
	}

	public static function makeSummaryGrantsLink($pid, $recordId, $event_id, $text, $instance = 1, $linkClass = "") {
		return self::makeRepeatingFormLink($pid, $recordId, $event_id, $text, "summary_grants", $instance, $linkClass);
	}

	public static function makeCustomGrantLink($pid, $recordId, $event_id, $text, $instance = 1, $linkClass = "") {
		return self::makeRepeatingFormLink($pid, $recordId, $event_id, $text, "custom_grant", $instance, $linkClass);
	}

	public static function makeRePORTERLink($pid, $recordId, $event_id, $text, $instance = 1) {
		return self::makeRepeatingFormLink($pid, $recordId, $event_id, $text, "reporter", $instance);
	}

	public static function makeExPORTERLink($pid, $recordId, $event_id, $text, $instance = 1) {
		return self::makeRepeatingFormLink($pid, $recordId, $event_id, $text, "exporter", $instance);
	}

	public static function makeCOEUSLink($pid, $recordId, $event_id, $text, $instance = 1) {
		return self::makeRepeatingFormLink($pid, $recordId, $event_id, $text, "coeus", $instance);
	}

	public static function makePublicationsLink($pid, $recordId, $event_id, $text, $instance = 1) {
		return self::makeFormLink($pid, $recordId, $event_id, $text, "citation", $instance);
	}

	public static function makeRepeatingFormLink($pid, $recordId, $event_id, $text, $form, $instance = 1, $linkClass = "") {
		$url = APP_PATH_WEBROOT."DataEntry/index.php?pid=".$pid."&id=".$recordId."&event_id=".$event_id."&page=".$form."&instance=".$instance;
		return self::makeLink($url, $text, FALSE, $linkClass);
	}

	public static function makeFormUrl($pid, $recordId, $event_id, $form, $instance = 1) {
		$url = APP_PATH_WEBROOT."DataEntry/index.php?pid=".$pid."&id=".$recordId."&event_id=".$event_id."&page=".$form;
		if ($instance != 1) {
			$url .= "&instance=".$instance;
		}
		return $url;
	}

	public static function makeFormLink($pid, $recordId, $event_id, $text, $form, $instance = 1, $linkClass = "") {
		$url = self::makeFormUrl($pid, $recordId, $event_id, $form, $instance);
		return self::makeLink($url, $text, FALSE, $linkClass);
	}
}
