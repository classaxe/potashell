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
        'Conservation Area' =>          'CA',
        'Conservation Park' =>          'CP',
        'Conservation Reserve' =>       'CR',
        'District Park' =>              'DP',
        'for Conservation' =>           'for Cons',
        'National Historic Site' =>     'NHS',
        'National Park' =>              'NP',
        'National Recreation Trail' =>  'NRT',
        'Point' =>                      'Pt',
        'Provincial Nature Reserve' =>  'PNR',
        'Provincial Park' =>            'PP',
        'Recreation Park' =>            'Rec P',
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
    private $modeFix;
    private $modeHelp;
    private $HTTPcontext;
    private $parkName;
    private $parkNameAbbr;
    private $pathAdifLocal;
    private $qrzApiKey;
    private $qrzPass;
    private $qrzSession;
    private $qrzUser;
    private $version;

    public function __construct() {
        $this->getCliArgs();
        $this->version = exec('git describe --tags');
        $this->getHTTPContext();
        $this->checkPhp();
        $this->loadIni();
        $this->header();
        $this->checkQrz();
        if ($this->modeHelp) {
            $this->help();
            $this->syntax();
            return;
        }
        if (!$this->modeAudit && $this->inputGSQ === null) {
            $this->syntax();
            $this->getUserArgs();
        }
        $this->process();
    }

    private function checkPhp() {
        if (!extension_loaded('mbstring')) {
            print PS::RED_BD . "ERROR:\n  PHP mbstring extension is not available.\n" . PS::RESET;
            die(0);
        }
        if (!extension_loaded('openssl')) {
            print PS::RED_BD . "ERROR:\n  PHP OpenSSL extension is not available.\n" . PS::RESET;
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
        if (empty($this->qrzUser) || empty($this->qrzPass)) {
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
            urlencode($this->qrzUser),
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
            if (strtoupper($status['CALLSIGN']) !== strtoupper($this->qrzUser)) {
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
            $gsqs[$d['MY_GRIDSQUARE']] = true;
        }
        $gsqs = array_keys($gsqs);
        sort($gsqs);
        return $gsqs;
    }

    private function getCliArgs() {
        global $argv;
        $arg1 = $argv[1] ?? null;
        $arg2 = $argv[2] ?? null;
        $arg3 = $argv[3] ?? null;
        $this->modeAudit = false;
        $this->modeCheck = false;
        $this->modeFix = false;
        $this->modeHelp = false;
        if ($arg1 && strtoupper($arg1) === 'AUDIT') {
            $this->modeAudit = true;
            return;
        }
        if ($arg1 && strtoupper($arg1) === 'HELP') {
            $this->modeHelp = true;
            return;
        }
        $this->inputPotaId = $arg1;
        $this->inputGSQ = $arg2;
        $this->modeCheck = $arg3 && strtoupper($arg3) === 'CHECK';
        $this->modeFix = $arg3 && strtoupper($arg3) === 'FIX';
    }

    private function getGSQForCall($callsign) {
        if (empty($this->qrzSession)) {
            return false;
        }
        $url = sprintf(
            "https://xmldata.qrz.com/xml/current/?s=%s;callsign=%s;agent=%s",
            urlencode($this->qrzSession),
            urlencode($callsign),
            urlencode(PS::USERAGENT)
        );
        $xml = file_get_contents($url, false, $this->HTTPcontext);
        $data = simplexml_load_string($xml);
        if (empty($data->Callsign->grid)) {
            print PS::RED_BD . "    WARNING: - No gridsquare found at QRZ.com for user " . PS::BLUE_BD . $callsign . PS::RED_BD  .".\n" . PS::RESET;
        }
        return $data->Callsign->grid ?? null;
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
        print PS::YELLOW_BD . "ARGUMENTS:\n";
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
        if ($this->modeFix) {
            print PS::GREEN_BD . "  - FIX operation specified:      " . PS::RESPONSE_Y . "\n";
        }
        $this->parkName = "POTA: " . $this->inputPotaId;
        print "\n";
    }

    private function header() {
        if (!$this->modeFix) {
            print PS::CLS;
        }
        print PS::YELLOW
            . "**************\n"
            . "* POTA SHELL *\n"
            . "**************\n"
            . "\n";
    }

    private function help() {
        print PS::YELLOW_BD . "PURPOSE:" . PS::YELLOW ."\n"
            . "  This program works with WSJT-X log files to prepare them for upload to POTA.\n"
            . "  1) It sets all " . PS::GREEN_BD ."MY_GRIDSQUARE" . PS::YELLOW ." values to a user-supplied Maidenhead GSQ value.\n"
            . "  2) It adds a new " . PS::GREEN_BD ."MY_CITY" . PS::YELLOW ." column to all rows, populated with the Park Name in this format:\n"
            . "     " . PS::CYAN_BD . "POTA: CA-1368 North Maple RP" . PS::YELLOW . " - a POTA API lookup is used to obtain the park name.\n"
            . "  3) It Fills in any missing " . PS::GREEN_BD . "GRIDSQUARE" . PS::YELLOW . " by contacting the QRZ Callbook service.\n"
            . "  4) It archives or un-archives the park log file in question - see " . PS::YELLOW_BD . "SYNTAX" . PS::YELLOW ." for more details.\n\n"
            . PS::YELLOW_BD . "CONFIGURATION:" . PS::YELLOW ."\n"
            . "  User Configuration is by means of the " . PS::BLUE_BD . "potashell.ini" . PS::YELLOW ." file located in this directory.\n\n";
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
            print PS::RESET;
            die(0);
        };
        if (!$this->config = @parse_ini_file($filename, true)) {
            print PS::RED_BD . "ERROR:\n  Unable to parse {$filename} file.\n" . PS::RESET;
            die(0);
        };
        $this->pathAdifLocal = rtrim($this->config['WSJTX']['log_directory'],'\\/') . DIRECTORY_SEPARATOR;
        if (!file_exists($this->pathAdifLocal)) {
            $this->header();
            print
                PS::RED_BD . "ERROR:\n"
                . "  The specified " . PS::CYAN_BD . "[WSJTX] log_directory" . PS::RED_BD . " specified in " . PS::BLUE_BD . $filename . PS::RED_BD ." doesn't exist.\n"
                . "  Please edit " . PS::BLUE_BD . $filename . PS::RED_BD ." and set the correct path to your WSJT-X log files.\n" . PS::RESET;
            die(0);
        }
        if (!empty($this->config['QRZ']['callsign']) && !empty($this->config['QRZ']['password'])) {
            $this->qrzUser = $this->config['QRZ']['callsign'];
            $this->qrzPass = $this->config['QRZ']['password'];
        }
        if (!empty($this->config['QRZ']['apikey'])) {
            $this->qrzApiKey = $this->config['QRZ']['apikey'];
        }
    }

    private function process() {
        print PS::YELLOW_BD . "STATUS:\n";
        if (!$this->modeAudit && (!$this->inputPotaId || !$this->inputGSQ)) {
            print PS::RED_BD . "  - One or more required parameters are missing.\n"
                . "    Unable to continue.\n" . PS::RESET;
            die(0);
        }
        if ($this->modeAudit) {
            $this->processAudit();
            return;
        }
        if (!$lookup = $this->getParkName($this->inputPotaId)) {
            print PS::RED_BD . "\nERROR:\n  Unable to get name for park {$this->inputPotaId}.\n" . PS::RESET;
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

        if (($fileAdifParkExists || $fileAdifWsjtxExists) && $this->modeFix) {
            $this->processParkFix();
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
            print PS::RED_BD . "  - Neither " . PS::BLUE_BD . "{$this->fileAdifPark}"
                . PS::RED_BD . " nor " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::RED_BD . " exist.\n"
                . "    Unable to proceed without valid data to operate upon." . PS::RESET . "\n";
            die(0);
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
            . "POTA ID | MY_GRID    | #LS | #LT | #MG | #SA | #FA | #ST | Park Name in Log File\n"
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
                $dates =    PS::dataGetDates($data);
                $date =     end($dates);
                $LS =       PS::dataCountLogs($data, $date);
                $LT =       PS::dataCountLogs($data);
                $MG =       PS::dataCountMissingGsq($data);
                $ST =       count($dates);
                $AT =       PS::dataCountActivations($data);
                $FT =       $ST - $AT;
                print
                    PS::BLUE_BD . $parkId . PS::GREEN_BD . " | "
                    . (count($MY_GRID) === 1 ?
                        PS::CYAN_BD . str_pad($MY_GRID[0], 10, ' ') :
                        PS::RED_BD . str_pad("ERROR - " . count($MY_GRID), 10, ' ')
                      ) . PS::GREEN_BD . " | "
                    . ($LS < PS::ACTIVATION_LOGS ? PS::RED_BD : '') . str_pad($LS, 3, ' ', STR_PAD_LEFT) . PS::GREEN_BD . " | "
                    . str_pad($LT, 3, ' ', STR_PAD_LEFT) . " | "
                    . PS::YELLOW_BD . str_pad(($MG ? $MG : ''), 3, ' ', STR_PAD_LEFT) . PS::GREEN_BD . " | "
                    . str_pad($AT, 3, ' ', STR_PAD_LEFT) . " | "
                    . PS::RED_BD . str_pad(($FT ? $FT : ''), 3, ' ', STR_PAD_LEFT) . PS::GREEN_BD . " | "
                    . str_pad($ST, 3, ' ', STR_PAD_LEFT) . " | "
                    . PS::BLUE_BD . $lookup['abbr'] . PS::GREEN_BD . "\n";
            }
        }
        print str_repeat('-', $lineLen) . PS::RESET . "\n";
    }

    private function processParkArchiving() {
        $adif =     new adif($this->pathAdifLocal . $this->fileAdifWsjtx);
        $data =     $adif->parser();
        $dates =    $this->dataGetDates($data);
        $last =     end($dates);
        $logs =     $this->dataCountLogs($data, $last);
        $MGs1 =     $this->dataCountMissingGsq($data);
        $MGs2 =     $MGs1;
        $locs =     $this->dataGetLocations($data);

        print PS::GREEN_BD . "  - File " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
            . " exists and contains " . PS::MAGENTA_BD . count($data) . PS::GREEN_BD . " entries.\n"
            . "  - Last session on " . PS::MAGENTA_BD . end($dates) . PS::GREEN_BD . " contained "
            . PS::MAGENTA_BD . $logs . PS::GREEN_BD . " distinct log" . ($logs === 1 ? '' : 's') . ".\n\n"
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
        print "  - Set " . PS::MAGENTA_BD . "MY_GRIDSQUARE" . PS::GREEN_BD . " to                  " . PS::CYAN_BD . "{$this->inputGSQ}" . PS::GREEN_BD . "\n"
            . "  - Set " . PS::MAGENTA_BD . "MY_CITY" . PS::GREEN_BD . " to                        " . PS::CYAN_BD . "{$this->parkNameAbbr}" . PS::GREEN_BD . "\n"
            . ($MGs1 ?
                "  - Correct " . PS::RED_BD . $MGs1 . PS::GREEN_BD . " missing gridsquares         "
                . (empty($this->qrzSession) ?
                    PS::RESPONSE_N . " (no connection to QRZ.com)"
                    : PS::RESPONSE_Y . " (QRZ.com lookups are available)"
                ) . "\n"
                : ""
            )
            . ($this->qrzApiKey ? "  - Upload park log to QRZ.com\n" : "")
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
        foreach ($data as &$record) {
            if (empty($record)) {
                continue;
            }
            if (empty($record['GRIDSQUARE'])) {
                $record['GRIDSQUARE'] = $this->getGSQForCall($record['CALL']);
                if (!empty($record['GRIDSQUARE'])) {
                    $MGs2 --;
                }
            }
            $record['MY_GRIDSQUARE'] = $this->inputGSQ;
            $record['MY_CITY'] = $this->parkNameAbbr;
        }
        $adif = $adif->toAdif($data, $this->version);
        file_put_contents($this->pathAdifLocal . $this->fileAdifPark, $adif);
        $stats = false;
        if ($this->qrzApiKey) {
            $stats = $this->uploadToQrz($data, $last);
        }


        print "  - Archived log file " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
            . "  to " . PS::BLUE_BD ."{$this->fileAdifPark}" . PS::GREEN_BD . ".\n"
            . "  - Updated " . PS::MAGENTA_BD ."MY_GRIDSQUARE" . PS::GREEN_BD ." values     to " . PS::CYAN_BD . $this->inputGSQ . PS::GREEN_BD . ".\n"
            . "  - Added " . PS::MAGENTA_BD ."MY_CITY" . PS::GREEN_BD ." and set all values to " . PS::RED_BD . $this->parkNameAbbr . PS::GREEN_BD . ".\n"
            . (!empty($this->qrzSession) && $MGs1 ? "  - Obtained " . PS::RED_BD . ($MGs2 ?
                ($MGs1 - $MGs2) . " of " . $MGs1 : $MGs1) . PS::GREEN_BD . " missing gridsquares." . PS::GREEN_BD . "\n" : ""
              )
            . ($stats ?
                  "  - Uploaded " . $logs . " Logs to QRZ.com:\n"
                . "     * Inserted:   ". $stats['INSERTED'] . "\n"
                . "     * Duplicates: " . $stats['DUPLICATE'] . "\n"
                . "     * Errors:     " . $stats['ERROR'] . "\n"
              : ""
            )
            . "\n"
            . PS::YELLOW_BD . "NEXT STEP:\n" . PS::GREEN_BD
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
        $dates =    $this->dataGetDates($data);
        $last =     end($dates);
        $logs =     $this->dataCountLogs($data, $last);
        $MGs1 =     $this->dataCountMissingGsq($data);
        $locs =     $this->dataGetLocations($data);

        print PS::GREEN_BD . "  - File " . PS::BLUE_BD . "{$fileAdif}" . PS::GREEN_BD
            . " exists and contains " . PS::MAGENTA_BD . count($data) . PS::GREEN_BD . " entries.\n"
            . ($MGs1 ? "  - There are " . PS::RED_BD . $MGs1 . PS::GREEN_BD . " missing gridsquares\n" : "")
            . "  - Last session on " . PS::MAGENTA_BD . end($dates) . PS::GREEN_BD . " contained "
            . PS::MAGENTA_BD . $logs . PS::GREEN_BD . " distinct log" . ($logs === 1 ? '' : 's') . ".\n"
            . ($logs < PS::ACTIVATION_LOGS || count($locs) > 1 ? PS::RED_BD ."\nWARNING:\n" : '')
            . ($logs < PS::ACTIVATION_LOGS ? PS::RED_BD ."  * There are insufficient logs for successful activation.\n" . PS::GREEN_BD : '')
            . (count($locs) > 1 ?
                PS::RED_BD ."  * There are " . count($locs) . " named log locations contained within this one file:\n"
                . "    - " .implode("\n    - ", $locs) . "\n". PS::GREEN_BD : ''
            )
            . PS::RESET;
    }

    private function processParkFix() {
        $fileAdifParkExists =   file_exists($this->pathAdifLocal . $this->fileAdifPark);
        $fileAdif = ($fileAdifParkExists ? $this->fileAdifPark : $this->fileAdifWsjtx);
        $adif = new adif($this->pathAdifLocal . $fileAdif);
        $data = $adif->parser();
        $MGs = 0;
        $FGs = 0;
        $locs = self::dataGetLocations($data);
        if (count($locs) > 1) {
            print PS::RED_BD ."\nERROR:\n  * There are " . count($locs) . " named log locations contained within this one file:\n"
                . "    - " .implode("\n    - ", $locs) . "\n  * The operation has been cancelled.\n". PS::RESET;
            return;
        }
        foreach ($data as &$record) {
            if (empty($record)) {
                continue;
            }
            if (empty($record['GRIDSQUARE'])) {
                $MGs++;
                if ($record['GRIDSQUARE'] = $this->getGSQForCall($record['CALL'])) {
                    $FGs++;
                };
            }
            $record['MY_GRIDSQUARE'] = $this->inputGSQ;
            $record['MY_CITY'] = $this->parkNameAbbr;
        }
        $adif = $adif->toAdif($data, $this->version);
        file_put_contents($this->pathAdifLocal . $fileAdif, $adif);
        print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD
            . "  - File " . PS::BLUE_BD . "{$fileAdif}" . PS::GREEN_BD . " with "
            . PS::CYAN_BD . count($data) . PS::GREEN_BD . " records has been fixed.\n"
            . ($MGs ?
                "  - " . PS::CYAN_BD . $MGs . PS::GREEN_BD . " missing gridsquare" . ($MGs > 1 ? "s were " : " was ")
                . (!empty($this->qrzSession) ? "fixed\n" : "NOT fixed, due to invalid QRZ callsign and password values\n    in " . PS::BLUE_BD . "potashell.ini" . PS::GREEN_BD . "\n")
                :
                ""
            )
            . PS::RESET;
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
        print PS::YELLOW_BD . "OPERATION:\n"
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
                . PS::YELLOW_BD . "NEXT STEP:\n" . PS::GREEN_BD
                . "    You may resume logging at " . PS::RED_BD . "{$this->parkName}\n\n"
                . PS::RESET;
        } else {
            print "    Operation cancelled.\n";
        }
        print PS::RESET;
    }
    private function syntax() {
        print PS::YELLOW_BD . "SYNTAX:\n"
        . "  1. " . PS::WHITE_BD . "potashell" . PS::YELLOW . "\n"
        . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::YELLOW . "\n"
        . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::YELLOW . "\n"
        . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "CHECK\n" . PS::YELLOW_BD
        . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "FIX\n" . PS::YELLOW_BD
        . "\n"
        . "     a) WITH AN ACTIVE LOG FILE:\n" . PS::YELLOW
        . "       - If an active log session has completed, this mode augments and archives\n"
        . "         " . PS::BLUE_BD ."wsjtx_log.adi" . PS::YELLOW ." to " . PS::BLUE_BD ."wsjtx_log_CA-1368.adi" . PS::YELLOW ." ready for the next session to begin,\n"
        . "         and you should manually restart " . PS::GREEN_BD ."WSJT-X" . PS::YELLOW ." to start logging to a new empty log file.\n"
        . "       - If last session has too few logs for POTA activation, a " . PS::RED_BD . "WARNING" . PS::YELLOW ." is given.\n"
        . "       - Refer to the " . PS::YELLOW_BD . "PURPOSE" . PS::YELLOW . " section in " . PS::YELLOW_BD . "HELP" . PS::YELLOW . " for the changes made.\n"
        . "\n" . PS::YELLOW_BD
        . "     b) WITHOUT AN ACTIVE LOG FILE:\n" . PS::YELLOW
        . "       - If there is NO active " . PS::BLUE_BD ."wsjtx_log.adi" . PS::YELLOW . " log file, the system looks for a file\n"
        . "         named " . PS::BLUE_BD ."wsjtx_log_CA-1368.adi" . PS::YELLOW . ", and if found, it renames it to " . PS::BLUE_BD ."wsjtx_log.adi" . PS::YELLOW . "\n"
        . "         so that the user can continue adding logs for this park.\n"
        . "\n" . PS::YELLOW_BD
        . "     c) PROMPTING FOR USER INPUTS:\n" . PS::YELLOW
        . "       - If either " . PS::BLUE_BD . "Park ID" . PS::YELLOW . " or " . PS::CYAN_BD ."GSQ" . PS::YELLOW . " is omitted, system will prompt for inputs.\n"
        . "       - Before any files are renamed or modified, user is asked to confirm the operation.\n"
        . "         If user responds " . PS::RESPONSE_Y . " operation continues, " . PS::RESPONSE_N ." aborts.\n"
        . "\n" . PS::YELLOW_BD
        . "     d) THE \"CHECK\" MODE:\n" . PS::YELLOW
        . "       - If the optional " . PS::GREEN_BD . "CHECK" . PS::YELLOW . " argument is given, system operates directly on either\n"
        . "         the Park Log file, or if that is absent, the wsjtx_log.file currently in use.\n"
        . "       - No files are renamed.\n"
        . "\n" . PS::YELLOW_BD
        . "     e) THE \"FIX\" MODE:\n" . PS::YELLOW
        . "       - If the optional " . PS::GREEN_BD . "FIX" . PS::YELLOW . " argument is given, system operates directly on either\n"
        . "         the Park Log file, or if that is absent, the wsjtx_log.file currently in use.\n"
        . "       - No files are renamed.\n"
        . "\n" . PS::YELLOW_BD
        . "  2. " . PS::WHITE_BD . "potashell " . PS::GREEN_BD . "AUDIT " . PS::YELLOW . "\n"
        . "     The system reviews ALL archived Park Log files, and produces a report on their contents.\n"
        . "\n"
        . PS::YELLOW_BD . "  3. " . PS::WHITE_BD . "potashell " . PS::YELLOW_BD . "HELP " . PS::YELLOW . "\n"
        . "     More detailed help is provided, along with this syntax guide.\n"
        . "\n"
        . str_repeat('-', 90) . PS::RESET ."\n";

    }

    private function uploadToQrz($data, $date) {
        $adifRecords = [];
        foreach ($data as $record) {
            if ($record['QSO_DATE'] !== (string) $date) {
                continue;
            }
            $adifRecords[] = adif::toAdif([$record], $this->version, true);
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
    private $records = [];
    private $options = [
        'code'	=> 'sjis-win',
    ];

    public function __construct($data, $options=[]) {
        $this->options = array_merge($this->options, $options);
        if (in_array(pathinfo($data, PATHINFO_EXTENSION), array('adi', 'adif'))) {
            $this->loadFile($data);
        } else {
            $this->loadData($data);
        }
        $this->initialize();
    }

    protected function initialize() {
        $pos = strripos($this->data, '<EOH>');
        if($pos === false) {
            throw new Exception('<EOH> is not present in the ADFI file');
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

    public static function toAdif($data, $version, $raw = false) {
        
        // construct an adif string out of data
        // construct header, (copied from an adif file)

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

        // construct records
        // format seems to be <FIELD_NAME:DATALENGTH>DATA*space* (more fields) <eor>

        foreach($data as $row) {
            foreach ($row as $key => $value) {
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
