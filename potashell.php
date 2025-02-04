#!/usr/bin/php
<?php
/*****************************************
 * POTA SHELL         Copyright (C) 2024 *
 * Authors:        Martin Francis VA3PHP *
 *                          James Fraser *
 * --------------------------------------*
 * https://github.com/classaxe/potashell *
 *****************************************/
class PS {
    const ACTIVATION_LOGS = 10;
    const ADI_COLUMNS = [
        'QSO_DATE',
        'TIME_ON',
        'CALL',
        'MODE',
        'SUBMODE',
        'BAND',
        'FREQ',
        'STATE',
        'COUNTRY',
        'GRIDSQUARE',
        'RST_SENT',
        'RST_RCVD',
        'QSO_DATE_OFF',
        'TIME_OFF',
        'STATION_CALLSIGN',
        'MY_GRIDSQUARE',
        'MY_CITY',
        'TX_PWR',
        'COMMENT',
        'DX'
    ];

    const USERAGENT =   "POTASHELL v%s | https://github.com/classaxe/potashell | Copyright (C) %s Martin Francis VA3PHP";
    const RED =         "\e[0;31m";
    const RED_BD =      "\e[1;31m";
    const GREEN =       "\e[0;32m";
    const GREEN_BD =    "\e[1;32m";
    const YELLOW =      "\e[0;33m";
    const YELLOW_BD =   "\e[1;33m";
    const BLUE =        "\e[0;34m";
    const BLUE_BD =     "\e[1;34m";
    const MAGENTA =     "\e[0;35m";
    const MAGENTA_BD =  "\e[1;35m";
    const CYAN =        "\e[0;36m";
    const CYAN_BD =     "\e[1;36m";
    const WHITE =       "\e[0;37m";
    const WHITE_BD =    "\e[1;37m";
    const RESPONSE_Y =  "\e[0;33;1;42m Y \e[0;33m";
    const RESPONSE_N =  "\e[0;33;1;41m N \e[0;33m";
    const CLS =         "\e[H\e[J";
    const RESET =       "\e[0m";

    const NAME_SUBS = [
        '–' =>                          '-',
        'Conservation Area' =>          'CA',
        'Conservation Park' =>          'CP',
        'Conservation Reserve' =>       'CR',
        'District Park' =>              'DP',
        'for Conservation' =>           'for Cons',
        'Heritage Trail' =>             'HT',
        'National Historic Site' =>     'NHS',
        'National Park' =>              'NP',
        'National Recreation Trail' =>  'NRT',
        'Point' =>                      'Pt',
        'Provincial Nature Reserve' =>  'PNR',
        'Provincial Park' =>            'PP',
        'Recreation Park' =>            'Rec P',
        'Recreation Site' =>            'Rec S',
        'Regional Park' =>              'Reg P',
        'Wilderness Park' =>            'WP',
    ];

    private $config;
    private $fileAdifPark;
    private $fileAdifWsjtx;
    private $hasInternet;
    private $inputGSQ;
    private $inputPotaId;
    private $modeAudit;
    private $modeCheck;
    private $modeHelp;
    private $modeSpot;
    private $modeSyntax;
    private $HTTPcontext;
    private $parkName;
    private $parkNameAbbr;
    private $pathAdifLocal;
    private $qrzApiKey;
    private $qrzApiCallsign;
    private $qrzPass;
    private $qrzSession;
    private $qrzLogin;
    private $spotKhz;
    private $spotComment;
    private $version;

    public function __construct() {
        $this->getCliArgs();
        $this->version = exec('git describe --tags');
        $this->getHTTPContext();
        $this->checkPhp();
        $this->loadIni();
        $this->header();
        $this->checkQrz();
        if ($this->modeSyntax) {
            print $this->syntax();
            return;
        }
        if ($this->modeHelp) {
            $this->help();
            return;
        }
        if (!$this->modeAudit && $this->inputGSQ === null) {
            print $this->syntax();
            $this->getUserArgs();
        }
        $this->process();
    }

    private static function calculateDX($latFrom, $lonFrom, $latTo, $lonTo, $earthRadius = 6371000) {
// Convert from degrees to radians
        $latFrom = deg2rad($latFrom);
        $lonFrom = deg2rad($lonFrom);
        $latTo = deg2rad($latTo);
        $lonTo = deg2rad($lonTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // Distance in meters
    }

    private function checkPhp() {
        if (!extension_loaded('mbstring')) {
            print PS::RED_BD . "ERROR:\n  PHP mbstring extension is not available.\n  (PHP " . phpversion() . ")\n" . PS::RESET;
            die(0);
        }
        if (!extension_loaded('openssl')) {
            print PS::RED_BD . "ERROR:\n  PHP OpenSSL extension is not available.\n  (PHP " . phpversion() . ")\n" . PS::RESET;
            die(0);
        }
    }

    private function checkQrz() {
        if (!@fsockopen('www.example.com', 80)) {
            print
                PS::RED_BD . "WARNING:\n"
                . "  - You have no internet connection.\n"
                . "  - Automatic gridquare and park name lookups will not work.\n"
                . "  - QRZ uploads are not possible at this time.\n"
                . PS::RESET
                . "\n";
            $this->hasInternet = false;
            return;
        }
        $this->hasInternet = true;
        if (empty($this->qrzLogin) || empty($this->qrzPass)) {
            print
                PS::RED_BD . "WARNING:\n"
                . "  QRZ.com credentials were not found in " . PS::BLUE_BD ."potashell.ini" . PS::RED_BD  . ".\n"
                . "  Missing GSQ values for logged contacts cannot be fixed without\n"
                . "  valid QRZ credentials.\n"
                . PS::RESET;
            return;
        }
        $url = sprintf(
            "https://xmldata.qrz.com/xml/current/?username=%s;password=%s;agent=%s",
            urlencode($this->qrzLogin),
            urlencode($this->qrzPass),
            urlencode(PS::USERAGENT)
        );
        $xml = file_get_contents($url, false, $this->HTTPcontext);
        $data = simplexml_load_string($xml);
        if (!empty($data->Session->Error)) {
            print
                PS::RED_BD . "ERROR:\n"
                . "  QRZ.com reports " . PS::BLUE_BD . "\"" . trim($data->Session->Error) . "\"" . PS::RED_BD  ."\n"
                . "  Missing GSQ values for logged contacts cannot be fixed without\n"
                . "  valid QRZ credentials.\n"
                . PS::RESET;
            die(0);
            return;
        }
        if (empty($data->Session->Key)) {
            print
                PS::RED_BD . "ERROR:\n  QRZ.com reports an invalid session key, so automatic log uploads are possible at this time.\n\n" . PS::RESET
            . "  Missing GSQ values for logged contacts cannot be fixed without valid QRZ credentials.\n\n"
            . PS::RESET;
        } else {
            $this->qrzSession = $data->Session->Key;
        }
        if (empty($this->qrzApiKey)) {
            print
                PS::RED_BD . "WARNING:\n"
                . "  QRZ.com " . PS::BLUE_BD . "[QRZ]apikey" . PS::RED_BD . " is missing in " . PS::BLUE_BD ."potashell.ini" . PS::RED_BD  .".\n"
                . "  Without a valid XML Subscriber apikey, you won't be able to automatically upload\n"
                . "  archived logs to QRZ.com.\n\n" . PS::RESET;
            return;
        }
        try {
            $url = sprintf(
                "https://logbook.qrz.com/api?KEY=%s&ACTION=STATUS",
                urlencode($this->qrzApiKey)
            );
            $raw = file_get_contents($url);
        } catch (\Exception $e) {
            print PS::RED_BD . "WARNING:\n  Unable to connect to QRZ.com for log uploads:" . PS::BLUE_BD . $e->getMessage() . PS::RED_BD  .".\n\n" . PS::RESET;
            die(0);
        }
        $status = [];
        $pairs = explode('&', $raw);
        foreach ($pairs as $pair) {
            list($key, $value) = explode('=', $pair, 2);
            $status[$key] = $value;
        }
        if ($status['RESULT'] === 'OK') {
            if (strtoupper($status['CALLSIGN']) !== strtoupper($this->qrzApiCallsign)) {
                print PS::RED_BD . "ERROR:\n  Unable to connect to QRZ.com for log uploads:\n"
                    . PS::BLUE_BD . "  - Wrong callsign for [QRZ]apikey\n" . PS::RESET;
                die(0);
            }
            return;
        }

        if (isset($status['REASON'])) {
            if (strpos($status['REASON'], 'invalid api key') !== false) {
                print PS::RED_BD . "ERROR:\n  Unable to connect to QRZ.com for log uploads:\n"
                    . PS::BLUE_BD . "  - Invalid QRZ Key\n" . PS::RESET;
                die(0);
            }
            if (strpos($status['REASON'], 'user does not have a valid QRZ subscription') !== false) {
                print PS::RED_BD . "ERROR:\n  Unable to connect to QRZ.com for log uploads:\n"
                    . PS::BLUE_BD . "  - Not XML Subscriber\n\n" . PS::RESET;
                die(0);
            }
        }
    }

    public static function convertGsqToDegrees($GSQ) {
        $regExp =
            '/^(?:[a-rA-R]{2}[0-9]{2}|'                                 // FN03
            .'[a-rA-R]{2}[0-9]{2}[a-xA-X]{2}|'                          // FN03HR
            .'[a-rA-R]{2}[0-9]{2}[a-xA-X]{2}[0-9]{2}|'                  // FN03HR72
            .'[a-rA-R]{2}[0-9]{2}[a-xA-X]{2}[0-9]{2}[a-xA-X]{2})$/i';   // FN03HR72VO

        if (!preg_match($regExp, $GSQ)) {
            return false;
        }
        $_GSQ =      strToUpper($GSQ);
        if (strlen($_GSQ) === 4) {
            $_GSQ = $_GSQ."LL";
        }
        if (strlen($_GSQ) === 6) {
            $_GSQ = $_GSQ."55";
        }
        if (strlen($_GSQ) === 8) {
            $_GSQ = $_GSQ."XX";
        }
        $lat=
            (ord($_GSQ[1])-65) * 10 - 90 +
            (ord($_GSQ[3])-48) +
            (ord($_GSQ[5])-65) / 24 +
            (ord($_GSQ[7])-48) / 240 +
            (ord($_GSQ[9])-65) / 5760;
        $lon=
            (ord($_GSQ[0])-65) * 20 - 180 +
            (ord($_GSQ[2])-48) * 2 +
            (ord($_GSQ[4])-65) / 12 +
            (ord($_GSQ[6])-48) / 120 +
            (ord($_GSQ[8])-65) / 2880;

        return [
            "gsq" => $GSQ,
            "lat" => round($lat, 4),
            "lon" => round($lon, 4)
        ];
    }

    private static function dataCountActivations($data) {
        $dates = [];
        foreach ($data as $d) {
            if (!isset($dates[$d['QSO_DATE']])) {
                $dates[$d['QSO_DATE']] = [];
            }
            $dates[$d['QSO_DATE']][$d['CALL'] . '|' . $d['BAND']] = true;
        }
        $activations = 0;
        foreach ($dates as $date => $logs) {
            if (count($logs) >= 10) {
                $activations ++;
            }
        }
        return $activations;
    }

    private static function dataGetCountries($data, $date = null) {
        $countries = [];
        foreach ($data as $d) {
            if ((!$date || $d['QSO_DATE'] == $date) && !empty($d['COUNTRY'])) {
                $countries[$d['COUNTRY']] = true;
            }
        }
        ksort($countries);
        return array_keys($countries);
    }

    private static function dataGetBestDx($data, $date = null) {
        $DX = 0;
        foreach ($data as $d) {
            if ((!$date || $d['QSO_DATE'] == $date) && !empty($d['DX']) && $d['DX'] > $DX) {
                $DX = $d['DX'];
            }
        }
        return $DX;
    }

    private static function dataGetLocations($data) {
        $unique = [];
        foreach ($data as $d) {
            if (empty($d['MY_CITY'])) {
                continue;
            }
            $unique[$d['MY_CITY']] = true;
        }
        return array_keys($unique);
    }

    private static function dataGetStates($data, $date = null) {
        $states = [];
        foreach ($data as $d) {
            if ((!$date || $d['QSO_DATE'] == $date) && !empty($d['STATE'])) {
                $states[$d['STATE']] = true;
            }
        }
        ksort($states);
        return array_keys($states);
    }

    private static function dataCountLogs($data, $date = null) {
        $unique = [];
        foreach ($data as $d) {
            if (!$date || $d['QSO_DATE'] == $date) {
                $unique[$d['QSO_DATE'] . '|' . $d['CALL'] . '|' . $d['BAND']] = true;
            }
        }
        return count($unique);
    }

    private static function dataCountMissingGsq($data) {
        $count = 0;
        foreach ($data as $d) {
            if (! isset($d['GRIDSQUARE']) || trim($d['GRIDSQUARE']) === '') {
                $count++;
            }
        }
        return $count;
    }

    private static function dataGetDates($data) {
        $dates = [];
        foreach ($data as $d) {
            $dates[$d['QSO_DATE']] = true;
        }
        $dates = array_keys($dates);
        sort($dates);
        return $dates;
    }

    private static function dataGetMyGrid($data) {
        $gsqs = [];
        foreach ($data as $d) {
            if (isset($d['MY_GRIDSQUARE'])) {
                $gsqs[$d['MY_GRIDSQUARE']] = true;
            } else {
                return false;
            }
        }
        $gsqs = array_keys($gsqs);
        sort($gsqs);
        return $gsqs;
    }

    private function fixData($data) {
        $status = [
            'COUNTRY' => [ 'missing' => 0, 'fixed' => 0],
            'GRIDSQUARE' => [ 'missing' => 0, 'fixed' => 0],
            'STATE' => [ 'missing' => 0, 'fixed' => 0]
        ];

        foreach ($data as &$record) {
            if (empty($record)) {
                continue;
            }
            if (empty($record['GRIDSQUARE'])) {
                $status['GRIDSQUARE']['missing']++;
                if ($record['GRIDSQUARE'] = $this->getGSQForCall($record['CALL'])) {
                    $status['GRIDSQUARE']['fixed']++;
                };
            }
            if (empty($record['COUNTRY'])) {
                $status['COUNTRY']['missing']++;
                if ($record['COUNTRY'] = $this->getItuForCall($record['CALL'])) {
                    $status['COUNTRY']['fixed']++;
                }
            }
            switch ($record['COUNTRY']) {
                case 'Australia':
                case 'Canada':
                case 'United States':
                    if (empty($record['STATE'])) {
                        $status['STATE']['missing']++;
                        if ($record['STATE'] = $this->getSpForCall($record['CALL'])) {
                            $status['STATE']['fixed']++;
                        }
                    }
                    break;
            }
            $record['MY_GRIDSQUARE'] = $this->inputGSQ;
            $record['MY_CITY'] = $this->parkNameAbbr;
            $myLatLon =     static::convertGsqToDegrees( $record['MY_GRIDSQUARE']);
            $theirLatLon =  static::convertGsqToDegrees($record['GRIDSQUARE']);
            if ($myLatLon && $theirLatLon) {
                $record['DX'] = round(static::calculateDX($myLatLon['lat'], $myLatLon['lon'], $theirLatLon['lat'], $theirLatLon['lon']) / 1000);
            }
        }
        return [
            'data' => $this->orderData($data),
            'status' => $status
        ];
    }

    private function getCliArgs() {
        global $argv;
        $arg1 = isset($argv[1]) ? $argv[1] : null;
        $arg2 = isset($argv[2]) ? $argv[2] : null;
        $arg3 = isset($argv[3]) ? $argv[3] : null;
        $arg4 = isset($argv[4]) ? $argv[4] : null;
        $arg5 = isset($argv[5]) ? $argv[5] : null;
        $this->modeAudit = false;
        $this->modeCheck = false;
        $this->modeHelp = false;
        $this->modeSpot = false;
        $this->modeSyntax = false;
        if ($arg1 && strtoupper($arg1) === 'AUDIT') {
            $this->modeAudit = true;
            return;
        }
        if ($arg1 && strtoupper($arg1) === 'HELP') {
            $this->modeHelp = true;
            return;
        }
        if ($arg1 && strtoupper($arg1) === 'SYNTAX') {
            $this->modeSyntax = true;
            return;
        }
        $this->inputPotaId = $arg1;
        $this->inputGSQ = $arg2;
        $this->modeCheck = $arg3 && strtoupper($arg3) === 'CHECK';
        $this->modeSpot = $arg3 && strtoupper($arg3) === 'SPOT';
        if ($this->modeSpot) {
            $this->spotKhz = $arg4;
            $this->spotComment = $arg5;
        }
    }

    private function getInfoForCall($callsign) {
        static $dataCache = [];
        if (empty($this->qrzSession)) {
            return false;
        }
        if (!isset($dataCache[$callsign])) {
            $url = sprintf(
                "https://xmldata.qrz.com/xml/current/?s=%s;callsign=%s;agent=%s",
                urlencode($this->qrzSession),
                urlencode($callsign),
                urlencode(PS::USERAGENT)
            );
            $xml = file_get_contents($url, false, $this->HTTPcontext);
            $dataCache[$callsign] = simplexml_load_string($xml);
        }
        return $dataCache[$callsign];
    }

    private function getGSQForCall($callsign) {
        $data = $this->getInfoForCall($callsign);
        if (empty($data->Callsign->grid)) {
            print PS::RED_BD . "    WARNING: - No gridsquare found at QRZ.com for user " . PS::BLUE_BD . $callsign . PS::RED_BD  .".\n" . PS::RESET;
            return null;
        }
        return (string) $data->Callsign->grid;
    }

    private function getItuForCall($callsign) {
        $data = $this->getInfoForCall($callsign);
        if (empty($data->Callsign->country)) {
            print PS::RED_BD . "    WARNING: - No country found at QRZ.com for user " . PS::BLUE_BD . $callsign . PS::RED_BD  .".\n" . PS::RESET;
            return null;
        }
        return (string) $data->Callsign->country;
    }

    private function getSpForCall($callsign) {
        $data = $this->getInfoForCall($callsign);
        return isset($data->Callsign->state) ? strtoupper((string) $data->Callsign->state) : null;
    }


    private function getHTTPContext() {
        $this->HTTPcontext = stream_context_create([
            'http'=> [
                'method'=>"GET",
                'header'=>"User-Agent: " . sprintf(PS::USERAGENT, $this->version, date('Y')) . "\r\n"
            ]
        ]);
    }

    private function getParkName($potaId) {
        if ($this->hasInternet) {
            $url = "https://api.pota.app/park/" . trim($potaId);
            $data = file_get_contents($url, false, $this->HTTPcontext);
            $data = json_decode($data);
            if (!$data) {
                return false;
            }
            $parkName = trim($data->name) . ' ' . trim($data->parktypeDesc);
            $parkNameAbbr = strtr("POTA: " . $potaId . " " . $parkName, PS::NAME_SUBS);
            return [
                'name' => $parkName,
                'abbr' => $parkNameAbbr
            ];
        }
        return [
            'name' => $potaId,
            'abbr' => 'POTA: ' . $potaId
        ];
    }

    private function getUserArgs() {
        print "\n" . PS::YELLOW_BD . "ARGUMENTS:\n";
        if ($this->inputPotaId === null) {
            print PS::GREEN_BD . "  - Please provide POTA Park ID:  " . PS::BLUE_BD;
            $fin = fopen("php://stdin","r");
            $this->inputPotaId = trim(fgets($fin));
        } else {
            print PS::GREEN_BD . "  - Supplied POTA Park ID:        " . PS::BLUE_BD . $this->inputPotaId . "\n";
        }
        if ($this->inputGSQ === null) {
            print PS::GREEN_BD . "  - Please provide 8/10-char GSQ: " . PS::CYAN_BD;
            $fin = fopen("php://stdin","r");
            $this->inputGSQ = trim(fgets($fin));
        } else {
            print PS::GREEN_BD . "  - Supplied Gridsquare:          " . PS::CYAN_BD . $this->inputGSQ . "\n";
        }
        $this->parkName = "POTA: " . $this->inputPotaId;
        print "\n";
    }

    private function header() {
        print PS::CLS . PS::YELLOW
        . "**************\n"
        . "* POTA SHELL *\n"
        . "**************\n"
        . "\n";
    }

    private function help() {
        print PS::YELLOW_BD . "PURPOSE:" . PS::YELLOW ."\n"
            . "  This program works with WSJT-X log files to prepare them for upload to POTA.\n"
            . "  1) It sets all " . PS::GREEN_BD ."MY_GRIDSQUARE" . PS::YELLOW ." values to your supplied Maidenhead GSQ value.\n"
            . "  2) It adds a new " . PS::GREEN_BD ."MY_CITY" . PS::YELLOW ." column to all rows, populated with the Park Name in this format:\n"
            . "     " . PS::CYAN_BD . "POTA: CA-1368 North Maple RP" . PS::YELLOW . " - a POTA API lookup is used to obtain the park name.\n"
            . "  3) It obtains missing " . PS::GREEN_BD . "GRIDSQUARE" . PS::YELLOW . ", "  . PS::GREEN_BD . "STATE" . PS::YELLOW ." and " . PS::GREEN_BD ."COUNTRY" . PS::YELLOW . " values from the QRZ Callbook service.\n"
            . "  4) It archives or un-archives the park log file in question - see below for more details.\n"
            . "  5) It can post a " . PS::GREEN_BD ."SPOT" . PS::YELLOW ." to POTA with a given frequency, mode and park ID to alert \"hunters\".\n"
            . "\n"
            . PS::YELLOW_BD . "CONFIGURATION:" . PS::YELLOW ."\n"
            . "  User Configuration is by means of the " . PS::BLUE_BD . "potashell.ini" . PS::YELLOW ." file located in this directory.\n"
            . "\n"
            . PS::YELLOW_BD . "SYNTAX:\n"
            . $this->syntax(1)
            . "\n" . PS::YELLOW_BD
            . "     a) PROMPTING FOR USER INPUTS:\n" . PS::YELLOW
            . "       - If either " . PS::BLUE_BD . "Park ID" . PS::YELLOW . " or " . PS::CYAN_BD ."GSQ" . PS::YELLOW . " is omitted, system will prompt for inputs.\n"
            . "       - Before any files are renamed or modified, the user is asked to confirm the operation.\n"
            . "       - If user responds " . PS::RESPONSE_Y . " operation continues, " . PS::RESPONSE_N ." aborts.\n"
            . "\n" . PS::YELLOW_BD
            . "     b) WITHOUT AN ACTIVE LOG FILE:\n" . PS::YELLOW
            . "       - If there is NO active " . PS::BLUE_BD ."wsjtx_log.adi" . PS::YELLOW . " log file, the system looks for a file\n"
            . "         named " . PS::BLUE_BD ."wsjtx_log_CA-1368.adi" . PS::YELLOW . ", and if found, it renames it to " . PS::BLUE_BD ."wsjtx_log.adi" . PS::YELLOW . "\n"
            . "         so that the user can continue adding logs for this park.\n"
            . "       - The WSJT-X program should be restarted at this point so that it can read or\n"
            . "         create the " . PS::BLUE_BD ."wsjtx_log.adi" . PS::YELLOW . " file.\n"
            . "       - The user is then asked if they want to add a " . PS::GREEN_BD . "SPOT" . PS::YELLOW . " for the park on the POTA website.\n"
            . "         If they respond " . PS::RESPONSE_Y . ", they will be prompted for:\n" . PS::YELLOW_BD
            . "         1. " . PS::YELLOW . "The frequency in KHz - e.g. " . PS::MAGENTA_BD . "14074\n" . PS::YELLOW_BD
            . "         2. " . PS::YELLOW . "A comment to add to the spot - usually the intended mode such as " . PS::RED_BD . "FT4" . PS::YELLOW . " or " . PS::RED_BD . "FT8" . PS::YELLOW . "\n"
            . "\n" . PS::YELLOW_BD
            . "     c) WITH AN ACTIVE LOG FILE:\n" . PS::YELLOW
            . "       - If latest session has too few logs for POTA activation, a " . PS::RED_BD . "WARNING" . PS::YELLOW ." is given.\n"
            . "       - If the log contains logs from more than one location, the process is halted.\n"
            . "       - If an active log session has completed, and the user confirms the operation:\n" . PS::YELLOW_BD
            . "         1. " . PS::YELLOW . "The " . PS::BLUE_BD . "wsjtx_log.adi" . PS::YELLOW ." file is renamed to " . PS::BLUE_BD ."wsjtx_log_CA-1368.adi\n" . PS::YELLOW_BD
            . "         2. " . PS::YELLOW . "Any missing " . PS::GREEN_BD . "GRIDSQUARE" . PS::YELLOW . ", "  . PS::GREEN_BD . "STATE" . PS::YELLOW ." and " . PS::GREEN_BD ."COUNTRY" . PS::YELLOW . " values for the other party are added.\n" . PS::YELLOW_BD
            . "         3. " . PS::YELLOW . "The supplied gridsquare - e.g. " . PS::CYAN_BD ."FN03FV82" . PS::YELLOW . " is written to all " . PS::GREEN_BD . "MY_GRIDSQUARE" . PS::YELLOW . " fields\n" . PS::YELLOW_BD
            . "         4. " . PS::YELLOW . "The identified park - e.g. " . PS::CYAN_BD . "POTA: CA-1368 North Maple RP " . PS::YELLOW . "is written to all " . PS::GREEN_BD . "MY_CITY" . PS::YELLOW . " fields\n" . PS::YELLOW_BD
            . "         5. " . PS::YELLOW . "The user is asked if they'd like to close their " . PS::GREEN_BD . "SPOT" . PS::YELLOW . " in POTA as QRT (inactive).\n" . PS::YELLOW_BD
            . "         6. " . PS::YELLOW . "If they respond " . PS::RESPONSE_Y . ", they will then be prompted for the comment to post for\n"
            . "            their spot, usually starting with the code " . PS::GREEN_BD . "QRT" . PS::YELLOW . " indicating that the activation\n"
            . "            attempt has ended.  They may respond with " . PS::GREEN_BD . "QRT - moving to CA-1369" . PS::YELLOW . " for example.\n"
            . "\n" . PS::YELLOW_BD
            . "     d) THE \"CHECK\" MODE:\n" . PS::YELLOW
            . "       - If the optional " . PS::GREEN_BD . "CHECK" . PS::YELLOW . " argument is given, system operates directly on either\n"
            . "         the Park Log file, or if that is absent, the " . PS::BLUE_BD . "wsjtx_log.adi" . PS::YELLOW ." file currently in use.\n"
            . "       - Missing " . PS::GREEN_BD . "GRIDSQUARE" . PS::YELLOW . ", "  . PS::GREEN_BD . "STATE" . PS::YELLOW ." and " . PS::GREEN_BD ."COUNTRY" . PS::YELLOW . " values for the other party are added.\n"
            . "       - No files are renamed.\n"
            . "\n" . PS::YELLOW_BD
            . "     e) THE \"SPOT\" MODE:\n" . PS::YELLOW
            . "       - If the optional " . PS::GREEN_BD . "SPOT" . PS::YELLOW . " argument is given, a 'spot' is posted to the pota.app website.\n"
            . "       - The next parameter is the frequency in KHz.\n"
            . "       - The final parameter is the intended transmission mode or \"QRT\" to close the spot.\n"
            . "       - Use quotes around the last parameter to group words together.\n"
            . "       - To safely test this feature without users responding, use " . PS::BLUE_BD . "K-TEST" . PS::YELLOW . " as the park ID.\n"
            . "         When the " . PS::BLUE_BD . "K-TEST" . PS::YELLOW . " test park is seen, the Activator callsign will be set to ABC123.\n"
            . "         " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "K-TEST " . PS::CYAN_BD ."AA11BB22 " . PS::GREEN_BD . "SPOT "
            . PS::MAGENTA_BD . "14074 " . PS::RED_BD . "\"FT8 - Test for POTASHELL spotter mode\"\n" . PS::YELLOW
            . "\n" . PS::YELLOW_BD
            . $this->syntax(2)
            . "       - The system reviews ALL archived Park Log files, and produces a report on their contents.\n"
            . "\n" . PS::YELLOW_BD
            . $this->syntax(3)
            . "       - Detailed help is provided.\n"
            . "\n" . PS::YELLOW_BD
            . $this->syntax(4)
            . "       - Basic syntax is provided.\n"
            . "\n" . PS::YELLOW . str_repeat('-', 90)
            . PS::RESET ."\n";
    }

    private function loadIni() {
        $filename = 'potashell.ini';
        $example =  'potashell.ini.example';
        if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . $filename)) {
            $this->header();
            print
                PS::RED_BD . "ERROR:\n"
                . "  The " . PS::BLUE_BD . $filename . PS::RED_BD ." Configuration file was missing.\n";

            $contents = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $example);
            $contents = str_replace("; This is a sample configuration file for potashell\r\n", '', $contents);
            $contents = str_replace("; Copy this file to potashell.ini, and modify it to suit your own needs\r\n", '', $contents);
            if (file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . $filename, $contents)) {
                print
                    PS::RED_BD . "  It has now been created.\n"
                    . "  Please edit the new " . PS::BLUE_BD . $filename . PS::RED_BD ." file, and supply your own values.\n";
            }
            print PS::RESET . "\n";
            die(0);
        };
        if (!$this->config = @parse_ini_file($filename, true)) {
            print PS::RED_BD . "ERROR:\n  Unable to parse {$filename} file.\n" . PS::RESET . "\n";
            die(0);
        };
        $this->pathAdifLocal = rtrim($this->config['WSJTX']['log_directory'],'\\/') . DIRECTORY_SEPARATOR;
        if (!file_exists($this->pathAdifLocal)) {
            $this->header();
            print
                PS::RED_BD . "ERROR:\n"
                . "  The specified " . PS::CYAN_BD . "[WSJTX] log_directory" . PS::RED_BD . " specified in " . PS::BLUE_BD . $filename . PS::RED_BD ." doesn't exist.\n"
                . "  Please edit " . PS::BLUE_BD . $filename . PS::RED_BD ." and set the correct path to your WSJT-X log files.\n"
                . PS::RESET . "\n";
            die(0);
        }
        if (!empty($this->config['QRZ']['login']) && !empty($this->config['QRZ']['password'])) {
            $this->qrzLogin = $this->config['QRZ']['login'];
            $this->qrzPass = $this->config['QRZ']['password'];
        }
        if (!empty($this->config['QRZ']['apicallsign']) && !empty($this->config['QRZ']['apikey'])) {
            $this->qrzApiCallsign = $this->config['QRZ']['apicallsign'];
            $this->qrzApiKey = $this->config['QRZ']['apikey'];
        }
    }

    private function orderData($data) {
        $ordered = [];
        // Not using <=> for PHP 5.6 compatability
        usort($data, function ($a, $b) {
            if ($a['TIME_ON'] > $b['TIME_ON']) { return 1; }
            if ($a['TIME_ON'] < $b['TIME_ON']) { return -1; }
            return 0;
        });
        usort($data, function ($a, $b) {
            if ($a['QSO_DATE'] > $b['QSO_DATE']) { return 1; }
            if ($a['QSO_DATE'] < $b['QSO_DATE']) { return -1; }
            return 0;
        });
        foreach ($data as $record) {
            $out = [];
            foreach (static::ADI_COLUMNS as $key) {
                $out[$key] = isset($record[$key]) ? $record[$key] : "";
            }
            $ordered[] = $out;
        }
        return $ordered;
    }

    private function process() {
        print PS::YELLOW_BD . "STATUS:\n";
        if (!$this->modeAudit && (!$this->inputPotaId || !$this->inputGSQ)) {
            print PS::RED_BD . "  - One or more required parameters are missing.\n"
                . "    Unable to continue.\n" . PS::RESET . "\n";
            die(0);
        }
        if ($this->modeAudit) {
            $this->processAudit();
            return;
        }
        if (!$lookup = $this->getParkName($this->inputPotaId)) {
            print PS::RED_BD . "\nERROR:\n  Unable to get name for park {$this->inputPotaId}.\n" . PS::RESET . "\n";
            die(0);
        }
        $this->parkName =       $lookup['name'];
        $this->parkNameAbbr =   $lookup['abbr'];
        print PS::GREEN_BD . "  - Identified Park:                      " . PS::BLUE_BD . $this->parkName . "\n"
            . PS::GREEN_BD . "  - Name for Log:                         " . PS::BLUE_BD . $this->parkNameAbbr . "\n";
        $this->fileAdifPark =   "wsjtx_log_{$this->inputPotaId}.adi";
        $this->fileAdifWsjtx =  "wsjtx_log.adi";

        $fileAdifParkExists =   file_exists($this->pathAdifLocal . $this->fileAdifPark);
        $fileAdifWsjtxExists =  file_exists($this->pathAdifLocal . $this->fileAdifWsjtx);

        if (($fileAdifParkExists || $fileAdifWsjtxExists) && $this->modeCheck) {
            $this->processParkCheck();
            return;
        }

        if ($this->modeSpot) {
            $this->processParkSpot();
            return;
        }

        if ($fileAdifParkExists && $fileAdifWsjtxExists) {
            $adif1 = new adif($this->pathAdifLocal . $this->fileAdifPark);
            $data1 = $adif1->parser();
            $adif2 = new adif($this->pathAdifLocal . $this->fileAdifWsjtx);
            $data2 = $adif2->parser();
            print
                PS::RED_BD . "  - Both " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::RED_BD
                . " and " . PS::BLUE_BD ."{$this->fileAdifWsjtx}" . PS::RED_BD . " exist.\n"
                . "    File " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::RED_BD . " contains " . PS::MAGENTA_BD . count($data1) . PS::RED_BD . " entries\n"
                . "    File " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::RED_BD . " contains " . PS::MAGENTA_BD . count($data2) . PS::RED_BD . " entries\n"
                . "\n"
                . "    Manual user intervention is required to prevent data loss." . PS::RESET . "\n";
            die(0);
        }
        if (!$fileAdifParkExists && !$fileAdifWsjtxExists) {
            $this->processParkInitialise();
            return;
        }

        if ($fileAdifParkExists) {
            $this->processParkUnarchiving();
            return;
        }

        if ($fileAdifWsjtxExists) {
            $this->processParkArchiving();
        }
    }

    private function processAudit() {
        $lineLen = 100;
        print PS::GREEN_BD . "Performing Audit on all POTA Log files in "
            . PS::BLUE_BD . $this->pathAdifLocal . "\n";
        $files = glob($this->pathAdifLocal . "wsjtx_log_??-*.adi");
        if (!$files) {
            print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD . "No log files found." .  PS::RESET . "\n";
            return;
        }
        print PS::YELLOW_BD . "\nKEY:\n" . PS::GREEN_BD
            . "  #LS = Logs for latest session - excluding duplicates. " . PS::ACTIVATION_LOGS . " required for activation.\n"
            . "  #LT = Logs in total - excluding duplicates\n"
            . "  #MG = Missing Grid Squares\n"
            . "  #SA = Successful Activations\n"
            . "  #FA = Failed Activations\n"
            . "  #ST = Sessions in Total\n"
            . PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD
            . str_repeat('-', $lineLen) . "\n"
            . "POTA ID  | MY_GRID    | #LS | #LT | #MG | #SA | #FA | #ST | Park Name in Log File\n"
            . str_repeat('-', $lineLen) . "\n";
        $i = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($i++ > 4) {
                    //continue;
                }
                $fn =       basename($file);
                $parkId =   explode('.', explode('_', $fn)[2])[0];
                $lookup =   $this->getParkName($parkId);

                $adif =     new adif($file);
                $data =     $adif->parser();
                $MY_GRID =  PS::dataGetMyGrid($data);
                if ($MY_GRID === false) {
                    print PS::RED_BD . "ERROR - file " . $fn . " has no 'MY_GRIDSQUARE column\n";
                    continue;
                }
                $dates =    PS::dataGetDates($data);
                $date =     end($dates);
                $LS =       PS::dataCountLogs($data, $date);
                $LT =       PS::dataCountLogs($data);
                $MG =       PS::dataCountMissingGsq($data);
                $ST =       count($dates);
                $AT =       PS::dataCountActivations($data);
                $FT =       $ST - $AT;
                print
                    PS::BLUE_BD . str_pad($parkId, 8, ' ') . PS::GREEN_BD . " | "
                    . (count($MY_GRID) === 1 ?
                        PS::CYAN_BD . str_pad($MY_GRID[0], 10, ' ') :
                        PS::RED_BD . str_pad('ERR ' . count($MY_GRID) . ' GSQs', 10, ' ')
                      ) . PS::GREEN_BD . " | "
                    . ($LS < PS::ACTIVATION_LOGS ? PS::RED_BD : '') . str_pad($LS, 3, ' ', STR_PAD_LEFT) . PS::GREEN_BD . " | "
                    . str_pad($LT, 3, ' ', STR_PAD_LEFT) . " | "
                    . PS::YELLOW_BD . str_pad(($MG ? $MG : ''), 3, ' ', STR_PAD_LEFT) . PS::GREEN_BD . " | "
                    . str_pad($AT, 3, ' ', STR_PAD_LEFT) . " | "
                    . PS::RED_BD . str_pad(($FT ? $FT : ''), 3, ' ', STR_PAD_LEFT) . PS::GREEN_BD . " | "
                    . str_pad($ST, 3, ' ', STR_PAD_LEFT) . " | "
                    . (isset($lookup['abbr']) ? PS::BLUE_BD . $lookup['abbr'] . PS::GREEN_BD : PS::RED_BD . "Lookup failed" . PS::GREEN_BD) . "\n";
            }
        }
        print str_repeat('-', $lineLen) . PS::RESET . "\n";
    }

    private function processParkArchiving() {
        $adif =     new adif($this->pathAdifLocal . $this->fileAdifWsjtx);
        $data =     $adif->parser();
        $dates =    $this->dataGetDates($data);
        $date =     end($dates);
        $logs =     $this->dataCountLogs($data, $date);
        $MGs1 =     $this->dataCountMissingGsq($data);
        $locs =     $this->dataGetLocations($data);

        print static::showStats($data, $date)
            . PS::YELLOW_BD . "OPERATION:\n"
            . PS::GREEN_BD . "  - Archive log file " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
            . " to     " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::GREEN_BD . "\n";

        if (count($locs) > 1) {
            print PS::RED_BD . "\nERROR:\n  * There are " . count($locs) . " named log locations contained within this one file:\n"
                . "    - " . implode("\n    - ", $locs) . "\n"
                . "  * Manual intervention is required.\n"
                . "  * The operation has been cancelled.\n"
                . PS::RESET;
            return;
        }
        print ($this->qrzApiKey ? "  - Upload park log to QRZ.com\n" : "")
            . "\n"
            . ($logs < PS::ACTIVATION_LOGS ? PS::RED_BD ."WARNING:\n    There are insufficient logs for successful activation.\n\n" . PS::GREEN_BD : "");

        if (isset($locs[0]) && trim(substr($locs[0], 0, 14)) !== trim(substr($this->parkNameAbbr, 0, 14))) {
            print PS::RED_BD . "ERROR:\n"
                . "  * The log contains reports made at      " . PS::BLUE_BD . $locs[0] . PS::RED_BD . "\n"
                . "  * You indicate that your logs were from " . PS::BLUE_BD . $this->parkNameAbbr . PS::RED_BD . "\n"
                . "  * The operation has been cancelled.\n"
                . PS::RESET;
            return;
        }

        print PS::YELLOW_BD . "CHOICE:\n"
            . PS::GREEN_BD . "    Proceed with operation? (Y/N) ";

        $fin = fopen("php://stdin","r");
        $response = strToUpper(trim(fgets($fin)));
        print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD;
        if (strtoupper($response) !== 'Y') {
            print "    Operation cancelled.\n" . PS::RESET;
            return;
        }
        rename(
            $this->pathAdifLocal . $this->fileAdifWsjtx,
            $this->pathAdifLocal . $this->fileAdifPark
        );
        $result =   $this->fixData($data);
        $data =     $result['data'];
        $status =   $result['status'];
        $adif =     $adif->toAdif($data, $this->version, false, true);
        file_put_contents($this->pathAdifLocal . $this->fileAdifPark, $adif);
        $stats = false;
        if ($this->qrzApiCallsign && $this->qrzApiKey) {
            $stats = $this->uploadToQrz($data, $date);
        }
        print "  - Archived log file " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
            . "  to " . PS::BLUE_BD ."{$this->fileAdifPark}" . PS::GREEN_BD . ".\n"
            . "  - Updated " . PS::MAGENTA_BD ."MY_GRIDSQUARE" . PS::GREEN_BD ." values     to " . PS::CYAN_BD . $this->inputGSQ . PS::GREEN_BD . ".\n"
            . "  - Added " . PS::MAGENTA_BD ."MY_CITY" . PS::GREEN_BD ." and set all values to " . PS::RED_BD . $this->parkNameAbbr . PS::GREEN_BD . ".\n"
            . (!empty($this->qrzSession) && $status['GRIDSQUARE']['missing'] ? "  - Obtained " . PS::RED_BD
                . ($status['GRIDSQUARE']['fixed'] ?
                    ($status['GRIDSQUARE']['missing'] - $status['GRIDSQUARE']['fixed']) . " of " . $status['GRIDSQUARE']['missing']
                :
                    $status['GRIDSQUARE']['missing']
                )
                . PS::GREEN_BD . " missing gridsquares." . PS::GREEN_BD . "\n" : ""
              )
            . ($stats ?
                  "  - Uploaded " . $logs . " Logs to QRZ.com:\n"
                . "     * Inserted:   ". $stats['INSERTED'] . "\n"
                . "     * Duplicates: " . $stats['DUPLICATE'] . "\n"
                . "     * Errors:     " . $stats['ERROR'] . "\n"
              : ""
            )
            . "\n";

        print PS::YELLOW_BD . "CLOSE SPOT:\n" . PS::GREEN_BD
        . "    Would you like to close this spot on pota.app (Y/N)     " . PS::BLUE_BD;
        $fin = fopen("php://stdin","r");
        $response = strToUpper(trim(fgets($fin)));
        if ($response === 'Y') {
            print PS::GREEN_BD . "    Please enter frequency in KHz:                          " . PS::MAGENTA_BD;
            $this->spotKhz = trim(fgets($fin));

            print PS::GREEN_BD . "    Enter comment - e.g. QRT - moving to CA-1234            " . PS::RED_BD;
            $this->spotComment = trim(fgets($fin));

            $this->processParkSpot();
        }

        print PS::YELLOW_BD . "\nNEXT STEP:\n" . PS::GREEN_BD
            . "  - You should now restart WSJT-X before logging at another park, where\n"
            . "    a fresh " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD . " file will be created.\n"
            . "  - Alternatively, run this script again with a new POTA Park ID to resume\n"
            . "    logging at a previously visited park.\n"
            . PS::RESET;
    }

    private function processParkCheck() {
        $fileAdifParkExists =   file_exists($this->pathAdifLocal . $this->fileAdifPark);
        $fileAdif = ($fileAdifParkExists ? $this->fileAdifPark : $this->fileAdifWsjtx);
        $adif =     new adif($this->pathAdifLocal . $fileAdif);
        $data =     $adif->parser();
        $result =   $this->fixData($data);
        $data =     $result['data'];
        $dates =    $this->dataGetDates($data);
        $date =     end($dates);
        $logs =     $this->dataCountLogs($data, $date);
        $MGs =      $this->dataCountMissingGsq($data);
        $locs =     $this->dataGetLocations($data);

        print PS::GREEN_BD . "  - File " . PS::BLUE_BD . "{$fileAdif}" . PS::GREEN_BD
            . " exists and contains " . PS::CYAN_BD . count($data) . PS::GREEN_BD . " entries.\n"
            . ($MGs ? "  - There are " . PS::RED_BD . $MGs . PS::GREEN_BD . " missing gridsquares\n" : "")
            . self::showStats($data, $date)
            . ($logs < PS::ACTIVATION_LOGS || count($locs) > 1 ? PS::RED_BD ."\nWARNING:\n" : '')
            . ($logs < PS::ACTIVATION_LOGS ? PS::RED_BD ."  * There are insufficient logs for successful activation.\n" . PS::GREEN_BD : '')
            . (count($locs) > 1 ?
                print PS::RED_BD ."\nERROR:\n  * There are " . count($locs) . " named log locations contained within this one file:\n"
                    . "    - " .implode("\n    - ", $locs) . "\n  * The operation has been cancelled.\n"
                : ""
            )
            . PS::RESET;

        if (count($locs) > 1) {
            return;
        }

        $adif = $adif->toAdif($data, $this->version, false, true);
        file_put_contents($this->pathAdifLocal . $fileAdif, $adif);
    }

    private function processParkSpot() {
        $activator = ($this->inputPotaId === 'K-TEST' ? 'ABC123' : $this->qrzLogin);
        print PS::YELLOW_BD . "\nPENDING OPERATION:\n"
            . PS::GREEN_BD . "    The following spot will be published at pota.app:\n\n"
            . PS::WHITE_BD . "    Activator  Spotter    KHz      Park Ref   Comments\n"
            . "    " . str_repeat('-', 80) . "\n"
            . "    "
            . str_pad($activator, 10, ' ') . ' '
            . str_pad($this->qrzLogin, 10, ' ') . ' '
            . str_pad($this->spotKhz, 8, ' ') . ' '
            . str_pad($this->inputPotaId, 10, ' ') . ' '
            . $this->spotComment . "\n"
            . "    " . str_repeat('-', 80) . "\n\n"
            . PS::YELLOW_BD . "CONFIRMATION REQUIRED:\n"
            . PS::GREEN_BD . "    Please confirm that you want to publish the spot: (Y/N) " . PS::BLUE_BD;
        $fin = fopen("php://stdin","r");
        $response = strToUpper(trim(fgets($fin)));
        if ($response !== 'Y') {
            print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD . "    Spot has NOT been published.\n" . PS::RESET;
            return false;
        }
        $result = $this->publishPotaSpot();
        if ($result === true) {
            print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD
                . "  - Your spot at " . PS::BLUE_BD . $this->inputPotaId . PS::GREEN_BD . " on " . PS::MAGENTA_BD . $this->spotKhz . " KHz" . PS::GREEN_BD
                . " has been published on " . PS::YELLOW_BD . "pota.app" . PS::GREEN_BD . " as " . PS::RED_BD . "\"" . $this->spotComment . "\"" . PS::GREEN_BD . "\n"
                . PS::RESET;
            return true;
        }
        print PS::RED_BD . "\nERROR:\n  - An error occurred when trying to publish your spot:\n"
            . "    " . PS::BLUE_BD . $result . "\n" . PS::RESET;
        return false;
    }

    private function processParkInitialise() {
        print PS::GREEN_BD . "  - This is a first time visit, since neither " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::GREEN_BD
            . " nor " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD . " exist.\n\n"
            . PS::YELLOW_BD . "PUBLISH SPOT:\n" . PS::GREEN_BD
            . "    Would you like to publish this spot to pota.app (Y/N)   " . PS::BLUE_BD;
        $fin = fopen("php://stdin","r");
        $response = strToUpper(trim(fgets($fin)));
        if ($response === 'Y') {
            print PS::GREEN_BD . "    Please enter frequency in KHz:                          " . PS::MAGENTA_BD;
            $this->spotKhz = trim(fgets($fin));

            print PS::GREEN_BD . "    Enter a comment, starting with mode e.g. \"FT8 QRP 5w\"   " . PS::RED_BD;
            $this->spotComment = trim(fgets($fin));

            $this->processParkSpot();
        }
        print "\n" . PS::YELLOW_BD . "NEXT STEP:\n" . PS::GREEN_BD
            . "  - Please restart WSJT-X if you were logging at another park to allow " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
            . " to be created." . PS::RESET . "\n";
    }

    private function processParkUnarchiving() {
        $adif = new adif($this->pathAdifLocal . $this->fileAdifPark);
        $data = $adif->parser();
        $locs =     $this->dataGetLocations($data);

        print PS::GREEN_BD . "  - File " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::GREEN_BD
            . " exists and contains " . PS::MAGENTA_BD . count($data) . PS::GREEN_BD . " entries.\n"
            . "  - File " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD . " does NOT exist.\n\n";
        if (count($locs) > 1) {
            print PS::RED_BD ."ERROR:\n  * There are " . count($locs) . " named log locations contained within this one file:\n"
                . "    - " . implode("\n    - ", $locs) . "\n"
                . "  * Manual intervention is required.\n"
                . "  * The operation has been cancelled.\n"
                . PS::RESET;
            return;
        }
        print PS::YELLOW_BD . "PENDING OPERATION:\n"
            . PS::GREEN_BD . "  - Rename archived log file " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::GREEN_BD . " to " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD ."\n"
            . "  - Resume logging at park " . PS::RED_BD . "{$this->parkName}" . PS::GREEN_BD . "\n\n"
            . PS::YELLOW_BD . "CHOICE:\n" . PS::GREEN_BD . "    Continue with operation? (Y/N) ";
        $fin = fopen("php://stdin","r");
        $response = strToUpper(trim(fgets($fin)));

        print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD;

        if ($response === 'Y') {
            rename(
                $this->pathAdifLocal . $this->fileAdifPark,
                $this->pathAdifLocal . $this->fileAdifWsjtx
            );
            print "    Renamed archived log file " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::GREEN_BD
                . " to " . PS::BLUE_BD ."{$this->fileAdifWsjtx}" . PS::GREEN_BD . "\n\n"
                . PS::YELLOW_BD . "PUBLISH SPOT:\n" . PS::GREEN_BD
                . "    Would you like to publish this spot to pota.app (Y/N)   " . PS::BLUE_BD;
            $fin = fopen("php://stdin","r");
            $response = strToUpper(trim(fgets($fin)));
            if ($response === 'Y') {
                print PS::GREEN_BD . "    Please enter frequency in KHz:                          " . PS::MAGENTA_BD;
                $this->spotKhz = trim(fgets($fin));

                print PS::GREEN_BD . "    Enter a comment, starting with mode e.g. \"FT8 QRP 5w\"   " . PS::RED_BD;
                $this->spotComment = trim(fgets($fin));

                $this->processParkSpot();
            }
            print PS::YELLOW_BD . "\nNEXT STEP:\n" . PS::GREEN_BD
                . "    You may resume logging at " . PS::RED_BD . "{$this->parkName}\n\n"
                . PS::RESET;
        } else {
            print "    Operation cancelled.\n";
        }
        print PS::RESET;
    }

    private function publishPotaSpot() {
        $url = 'https://api.pota.app/spot/';
        // $url = 'https://logs.classaxe.com/test.php';
        $activator = ($this->inputPotaId === 'K-TEST' ? 'ABC123' : $this->qrzLogin);
        $data = json_encode([
            'activator' =>  $activator,
            'spotter' =>    $this->qrzLogin,
            'frequency' =>  $this->spotKhz,
            'reference' =>  $this->inputPotaId,
            'source' =>     'Potashell ' . $this->version . ' - https://github.com/classaxe/potashell',
            'comments' =>   $this->spotComment
        ]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // For HTTPS
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type:application/json' ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        return $error ?: true;
    }

    private static function showStats($data, $date) {
        $logs =         static::dataCountLogs($data, $date);
        $countries =    static::dataGetCountries($data, $date);
        $dx =           static::dataGetBestDx($data, $date);
        $states =       static::dataGetStates($data, $date);

        return
          PS::GREEN_BD . "  - Stats for last session on " . PS::CYAN_BD
        . substr($date, 0, 4) . "-"
        . substr($date, 4, 2) . "-"
        . substr($date, 6, 2)
        . PS::GREEN_BD . ":\n"
        . "    There were " . PS::CYAN_BD . $logs . PS::GREEN_BD . " distinct log" . ($logs === 1 ? '' : 's')
        . " from " . PS::CYAN_BD . count($countries) . PS::GREEN_BD . " " . (count($countries) === 1 ? "country" : "countries")
        . (count($states) ? " and " . PS::CYAN_BD . count($states) . PS::GREEN_BD . " state" . (count($states) === 1 ? "" : "s") : "")
        . " - best DX was " . PS::BLUE_BD . $dx . PS::GREEN_BD . " KM.\n"
        . "      - " . (count($countries) === 1 ? "Country:   " : "Countries: ")
        . PS::YELLOW_BD . implode(PS::GREEN_BD . ', ' . PS::YELLOW_BD, $countries) . PS::GREEN_BD . "\n"
        . (count($states) ? "      - "
            . (count($states) === 1 ? "State:     ": "States:    ")
            . PS::YELLOW_BD . implode(PS::GREEN_BD . ', ' . PS::YELLOW_BD, $states) . PS::GREEN_BD . "\n"
            : ""
        )
        . "\n";

    }
    private function syntax($step = false) {
        switch ($step) {
            case 1:
                return PS::YELLOW_BD
                    . "  1. " . PS::WHITE_BD . "potashell\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "CHECK\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "SPOT " . PS::MAGENTA_BD . "14074 " . PS::RED_BD . "\"FT8 - QRP 4w\"\n"
                    ;
            case 2:
                return PS::YELLOW_BD . "  2. " . PS::WHITE_BD . "potashell " . PS::GREEN_BD . "AUDIT " . PS::YELLOW . "\n";
            case 3:
                return PS::YELLOW_BD . "  3. " . PS::WHITE_BD . "potashell " . PS::GREEN_BD . "HELP " . PS::YELLOW . "\n";
            case 4:
                return PS::YELLOW_BD . "  4. " . PS::WHITE_BD . "potashell " . PS::GREEN_BD . "SYNTAX " . PS::YELLOW . "\n";
            default:
                return
                    $this->syntax(1) . "\n"
                    . $this->syntax(2) . "\n"
                    . $this->syntax(3) . "\n"
                    . $this->syntax(4) . PS::RESET;
        }
    }

    private function uploadToQrz($data, $date) {
        $adifRecords = [];
        foreach ($data as $record) {
            if ($record['QSO_DATE'] !== (string) $date) {
                continue;
            }
            $adifRecords[] = adif::toAdif([$record], $this->version, true, false);
        }
        $stats = [
            'ERROR' => 0,
            'INSERTED' => 0,
            'DUPLICATE' => 0
        ];
        foreach ($adifRecords as $adifRecord) {
            try {
                $url = sprintf(
                    "https://logbook.qrz.com/api?KEY=%s&ACTION=INSERT&ADIF=%s",
                    urlencode($this->qrzApiKey),
                    urlencode($adifRecord)
                );
                $raw = file_get_contents($url);
            } catch (\Exception $e) {
                print PS::RED_BD . "WARNING:\n  Unable to connect to QRZ.com for log uploads:" . PS::BLUE_BD . $e->getMessage() . PS::RED_BD  .".\n\n" . PS::RESET;
                die(0);
            }
            $status = [];
            $pairs = explode('&', $raw);
            foreach ($pairs as $pair) {
                list($key, $value) = explode('=', $pair, 2);
                $status[$key] = $value;
            }
            switch ($status['RESULT']) {
                case 'OK':
                    $stats['INSERTED']++;
                    break;
                case 'FAIL':
                    if ($status['REASON'] === 'Unable to add QSO to database: duplicate') {
                        $stats['DUPLICATE']++;
                    } else {
                        $stats['ERROR']++;
                    }
                    break;
            }
        }
        return $stats;
    }
}

class adif {
    /**
     * ADIFインポートクラス
     *
     * ADIFデータを解析して、配列に展開する。
     *
     * PHP versions 5-8
     *
     * Licensed under The MIT License
     * Redistributions of files must retain the above copyright notice.
     *
     * @package       php-adif
     * @version       0.1
     * @since         0.1
     * @author        Mune Ando
     * @copyright     Copyright 2012, Mune Ando (http://wwww.5cho-me.com/)
     * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
     * @repo          https://github.com/muneando/php-adif
     */
    private $data;
    private $filename;
    private $records = [];
    private $options = [
        'code'	=> 'sjis-win',
    ];

    public function __construct($data, $options=[]) {
        $this->options = array_merge($this->options, $options);
        if (in_array(pathinfo($data, PATHINFO_EXTENSION), array('adi', 'adif'))) {
            $this->loadFile($data);
            $this->filename = pathinfo($data, PATHINFO_FILENAME);
        } else {
            $this->loadData($data);
        }
        $this->initialize();
    }

    protected function initialize() {
        $pos = strripos($this->data, '<EOH>');
        if ($pos === false) {
            $this->data = "<EOH>" . $this->data;
            $pos = 0;
        };
        $data = substr($this->data, $pos + 5, strlen($this->data) - $pos - 5);
        $data = str_replace(array("\r\n", "\r"), "\n", $data);
        $lines = explode("\n", $data);
        $data = '';
        foreach ($lines as $line) {
            if(substr(ltrim($line), 0, 1) != '#') {
                $data = $data . $line;
            }
        }
        $records = str_ireplace('<eor>', '<EOR>', $data);
        $tmp = explode('<EOR>', $records);
        $this->records = array_filter($tmp, function($record) {
            return $record != '';
        });
    }

    protected function loadData($data) {
        $this->data = $data;
    }

    protected function loadFile($fname) {
        $this->data = file_get_contents($fname);
    }

    public function parser() {
        $datas = [];
        foreach ($this->records as $record) {
            if(empty($record)) {
                continue;
            }
            $data = [];
            $tag = '';
            $valueLen = '';
            $value = '';
            $status = '';
            $i = 0;
            while($i < $this->strlen($record)) {
                $ch = $this->substr($record, $i, 1);
                $delimiter = FALSE;
                switch ($ch) {
                    case '\n':
                    case '\r':
                        continue 2;
                    case '<':
                        $tag = '';
                        $value = '';
                        $status = 'TAG';
                        $delimiter = TRUE;
                        break;
                    case ':':
                        if($status == 'TAG') {
                            $valueLen = '';
                            $status = 'VALUELEN';
                            $delimiter = TRUE;
                        }
                        break;
                    case '>':
                        if($status == 'VALUELEN') {
                            $value = $this->substr($record, $i+1, (int)$valueLen);
                            $data[strtoupper($tag)] = $this->convert_encoding($value);
                            $i = $i + $valueLen;
                            $status = 'VALUE';
                            $delimiter = TRUE;
                        }
                        break;
                    default:
                }
                if($delimiter === FALSE) {
                    switch ($status) {
                        case 'TAG':
                            $tag .= $ch;
                            break;
                        case 'VALUELEN':
                            $valueLen .= $ch;
                            break;
                    }
                }
                $i = $i + 1;
            }
            $datas[] = $data;
        }
        return $datas;
    }

    public static function toAdif($data, $version, $raw = false, $allFields = false) {
        $output = ($raw ? "" : "ADIF Export from POTASHELL\n"
            . "https://github.com/classaxe/potashell\n"
            . "Copyright (C) 2024, Martin Francis, James Fraser - classaxe.com\n"
            . "File generated on " . date('Y-m-d \a\t H:m:s') ."\n"
            . "<ADIF_VER:5>3.1.4\n"
            . "<PROGRAMID:9>POTAShell\n"
            . "<PROGRAMVERSION:" . strlen($version) . ">" . $version ."\n"
            . "<EOH>\n"
            . "\n"
        );
        foreach($data as $row) {
            foreach ($row as $key => $value) {
                if (!$value && !$allFields) {
                    continue;
                }
                $output .=  "<" . $key . ":" . mb_strlen($value) . ">" . $value . " ";
            }
            $output .= "<EOR>" . ($raw ? "" : "\r\n");
        }

        return $output;
    }

    protected function strlen($string) {
        if ($this->options['code'] == 'sjis-win') {
            return strlen($string);
        } else {
            return mb_strlen($string);
        }
    }

    protected function convert_encoding($string) {
        if ($this->options['code'] == 'sjis-win') {
            return mb_convert_encoding($string, 'utf-8', 'sjis-win');
        } else {
            return $string;
        }
    }

    protected function substr($string, $start, $length) {
        if ($this->options['code'] == 'sjis-win') {
            return substr($string, $start, $length);
        }
        return mb_substr($string, $start, $length, 'utf-8');
    }
}

function d($var) {
    print "<pre>". print_r($var, true) ."</pre>";
}

function dd($var) {
    d($var);
    exit;
}

new PS();
