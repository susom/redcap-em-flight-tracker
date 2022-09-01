<?php

namespace Vanderbilt\CareerDevLibrary;

require_once(__DIR__ . '/ClassLoader.php');

class REDCapLookup {
    public function __construct($firstName, $lastName) {
        $this->firstName = NameMatcher::clearOfHonorifics(NameMatcher::clearOfDegrees($firstName));
        $this->lastName = NameMatcher::clearOfHonorifics(NameMatcher::clearOfDegrees($lastName));
    }

    public static function getUserInfo($uid) {
        if ($uid) {
            $sql = "SELECT * FROM redcap_user_information WHERE username = '".db_real_escape_string($uid)."'";
            $results = db_query($sql);
            if ($error = db_error()) {
                throw new \Exception($error.": ".$sql);
            }
            if ($row = db_fetch_assoc($results)) {
                return $row;
            }
        }
        return [];
    }

    public function getName() {
        return $this->firstName." ".$this->lastName;
    }

    public function getUidsAndNames($showEmails = FALSE) {
        $uids = [];
        if ($showEmails) {
            $sqlField = ", user_email";
        } else {
            $sqlField = "";
        }

        if (!$this->firstName || !$this->lastName) {
            if (!$this->firstName) {
                $name = $this->lastName;
            } else {
                $name = $this->firstName;
            }
            $sql = "SELECT username, user_firstname, user_lastname $sqlField FROM redcap_user_information WHERE lower(user_firstname) = '".db_real_escape_string(strtolower($name))."' OR lower(user_lastname) = '".db_real_escape_string(strtolower($name))."'";
        } else {
            $firstNames = NameMatcher::explodeFirstName($this->firstName);
            if (count($firstNames) > 1) {
                foreach ($firstNames as $firstName) {
                    if ($firstName && !NameMatcher::isInitial($firstName)) {
                        $sql = "SELECT username, user_firstname, user_lastname $sqlField FROM redcap_user_information WHERE lower(user_firstname) = '".db_real_escape_string(strtolower($firstName))."' AND lower(user_lastname) = '".db_real_escape_string(strtolower($this->lastName))."'";
                        $results = db_query($sql);
                        if ($error = db_error()) {
                            throw new \Exception($error.": ".$sql);
                        }
                        while ($row = db_fetch_assoc($results)) {
                            if ($row['username']) {
                                $uids[$row['username']] = self::formatName($row['user_firstname'], $row['user_lastname']);
                                if ($showEmails) {
                                    $uids[$row['username']] .= " ".$row[$sqlField];
                                }
                            }
                        }
                    }
                }
                ksort($uids);
                return $uids;
            } else {
                $sql = "SELECT username, user_firstname, user_lastname $sqlField FROM redcap_user_information WHERE lower(user_firstname) = '".db_real_escape_string(strtolower($this->firstName))."' AND lower(user_lastname) = '".db_real_escape_string(strtolower($this->lastName))."'";
            }
        }
        $results = db_query($sql);
        if ($error = db_error()) {
            throw new \Exception($error.": ".$sql);
        }
        while ($row = db_fetch_assoc($results)) {
            if ($row['username']) {
                $uids[$row['username']] = self::formatName($row['user_firstname'], $row['user_lastname']);
                if ($showEmails) {
                    $uids[$row['username']] .= " ".$row[$sqlField];
                }
            }
        }
        ksort($uids);
        return $uids;
    }

    private static function formatName($firstName, $lastName) {
        if ($firstName && $lastName) {
            return $firstName." ".$lastName;
        } else if (!$firstName) {
            return $lastName;
        } else {
            return $firstName;
        }
    }

    private $firstName = "";
    private $lastName = "";
}

class REDCapLookupByUserid {
    public function __construct($userid) {
        $this->userid = $userid;
    }

    public function getEmail() {
        if ($this->userid) {
            $sql = "SELECT user_email FROM redcap_user_information WHERE lower(username) = '".db_real_escape_string(strtolower($this->userid))."'";
            $q = db_query($sql);
            if ($row = db_fetch_assoc($q)) {
                return $row['user_email'];
            }
        }
        return "";
    }

    public function getName() {
        if ($this->userid) {
            $sql = "SELECT user_firstname, user_lastname FROM redcap_user_information WHERE lower(username) = '".db_real_escape_string(strtolower($this->userid))."'";
            $q = db_query($sql);
            if ($row = db_fetch_assoc($q)) {
                return $row['user_firstname']." ".$row['user_lastname'];
            }
        }
        return "";
    }

    public function getLastName() {
        if ($this->userid) {
            $sql = "SELECT user_lastname FROM redcap_user_information WHERE lower(username) = '".db_real_escape_string(strtolower($this->userid))."'";
            $q = db_query($sql);
            if ($row = db_fetch_assoc($q)) {
                return $row['user_lastname'];
            }
        }
        return "";
    }

    private $userid = "";
}

