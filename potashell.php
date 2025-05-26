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
        'MODECOMP',
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
        'PARK',
        'MY_CITY',
        'MY_GRIDSQUARE',
        'TX_PWR',
        'COMMENT',
        'DX',
        'TO_CLUBLOG',
        'TO_QRZ',
        'TO_POTA',
    ];
    const MAXLEN = 130;

    const USERAGENT =   "POTASHELL v%s | (C) %s VA3PHP";
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

    private $argCheckBand;
    private $config;
    private $clublogApikey;
    private $clublogCallsign;
    private $clublogEmail;
    private $clublogPassword;
    private $customLocations;
    private $fileAdifPark;
    private $fileAdifWsjtx;
    private $hasInternet = false;
    private $inputGSQ;
    private $inputQthId;
    private $mode;
    private $modeAudit;
    private $modeCheck;
    private $modeHelp;
    private $modePush;
    private $modePushQty;
    private $modeReview;
    private $modeSpot;
    private $modeSummary;
    private $modeSyntax;
    private $HTTPcontext;
    private $locationName;
    private $locationNameAbbr;
    private $locationType;
    private $pathAdifLocal;
    private $php;
    private $sessionAdifDirectory;
    private $qrzApiKey;
    private $qrzApiCallsign;
    private $qrzPass;
    private $qrzSession;
    private $qrzLogin;
    private $spotKhz;
    private $spotComment;
    private $version;

    public function __construct() {
        $this->argsGetCli();
        $this->version = exec('git describe --tags');
        $this->php = phpversion();
        $this->getHTTPContext();
        $this->phpCheck();
        $this->argsLoadIni();
        $this->showHeader();
        $this->internetCheck();
        $this->qrzCheck();
        $this->clublogCheck();
        if ($this->modeAudit) {
            $this->processAudit();  // Method processAudit() prints directly for gradula display of results
            return;
        }
        if ($this->modeSyntax) {
            print $this->showSyntax();
            return;
        }
        if ($this->modeHelp) {
            $this->showHelp();
            return;
        }
        if (!$this->modeAudit && $this->inputGSQ === null) {
            print $this->showSyntax();
            $this->argsGetInput();
        }
        $this->process();
    }

    private function argsGetCli() {
        global $argv;
        $arg1 = isset($argv[1]) ? $argv[1] : null;
        $arg2 = isset($argv[2]) ? $argv[2] : null;
        $arg3 = isset($argv[3]) ? $argv[3] : null;
        $arg4 = isset($argv[4]) ? $argv[4] : null;
        $arg5 = isset($argv[5]) ? $argv[5] : null;
        $this->modeAudit = false;
        $this->modeCheck = false;
        $this->modeHelp = false;
        $this->modePush = false;
        $this->modePushQty = false;
        $this->modeReview = false;
        $this->modeSpot = false;
        $this->modeSummary = false;
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
        $this->inputQthId =     $arg1;
        $this->inputGSQ =       $arg2;
        $this->mode =           $arg3 ? $arg3 : '';
        $this->modeCheck =      $this->mode && strtoupper($this->mode) === 'CHECK';
        $this->modePush =       $this->mode && strtoupper($this->mode) === 'PUSH';
        $this->modeReview =     $this->mode && strtoupper($this->mode) === 'REVIEW';
        $this->modeSpot =       $this->mode && strtoupper($this->mode) === 'SPOT';
        $this->modeSummary =    $this->mode && strtoupper($this->mode) === 'SUMMARY';
        if ($this->modeCheck || $this->modeReview || $this->modeSummary) {
            $this->argCheckBand = $arg4;
        }
        if ($this->modeSpot) {
            $this->spotKhz = $arg4;
            $this->spotComment = $arg5;
        }
        if ($this->modePush) {
            $this->modePushQty = $arg4;
        }
    }

    private function argsGetInput() {
        print "\n" . PS::YELLOW_BD . "ARGUMENTS:\n";
        if ($this->inputQthId === null) {
            print PS::GREEN_BD . "  - Please provide Location ID:  " . PS::BLUE_BD;
            $fin = fopen("php://stdin","r");
            $this->inputQthId = trim(fgets($fin));
        } else {
            print PS::GREEN_BD . "  - Supplied Location ID:        " . PS::BLUE_BD . $this->inputQthId . "\n";
        }
        if ($this->inputGSQ === null) {
            print PS::GREEN_BD . "  - Please provide 8/10-char GSQ: " . PS::CYAN_BD;
            $fin = fopen("php://stdin","r");
            $this->inputGSQ = trim(fgets($fin));
        } else {
            print PS::GREEN_BD . "  - Supplied Gridsquare:          " . PS::CYAN_BD . $this->inputGSQ . "\n";
        }
        $this->locationName = "POTA: " . $this->inputQthId;
        print "\n";
    }

    private function argsLoadIni() {
        $filename = 'potashell.ini';
        $example =  'potashell.ini.example';
        if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . $filename)) {
            $this->showHeader();
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
            $this->showHeader();
            print
                PS::RED_BD . "ERROR:\n"
                . "  The specified " . PS::CYAN_BD . "[WSJTX] log_directory" . PS::RED_BD . " specified in " . PS::BLUE_BD . $filename . PS::RED_BD ." doesn't exist.\n"
                . "  Please edit " . PS::BLUE_BD . $filename . PS::RED_BD ." and set the correct path to your WSJT-X log files.\n"
                . PS::RESET . "\n";
            die(0);
        }

        // Clublog Details
        if (!empty($this->config['CLUBLOG']['clublog_email']) &&
            !empty($this->config['CLUBLOG']['clublog_password']) &&
            !empty($this->config['CLUBLOG']['clublog_callsign'])
        ) {
            $this->clublogEmail = $this->config['CLUBLOG']['clublog_email'];
            $this->clublogPassword = $this->config['CLUBLOG']['clublog_password'];
            $this->clublogCallsign = $this->config['CLUBLOG']['clublog_callsign'];
        }

        // CUSTOM Locations
        if (!empty($this->config['CUSTOM']['location'])) {
            $this->customLocations = $this->config['CUSTOM']['location'];
        }

        // SESSION Details
        if (!empty($this->config['SESSION']['adif_directory'])) {
            $this->sessionAdifDirectory = $this->config['SESSION']['adif_directory'];
        }

        // QRZ Details
        if (!empty($this->config['QRZ']['login']) && !empty($this->config['QRZ']['password'])) {
            $this->qrzLogin = $this->config['QRZ']['login'];
            $this->qrzPass = $this->config['QRZ']['password'];
        }
        if (!empty($this->config['QRZ']['apicallsign']) && !empty($this->config['QRZ']['apikey'])) {
            $this->qrzApiCallsign = $this->config['QRZ']['apicallsign'];
            $this->qrzApiKey = $this->config['QRZ']['apikey'];
        }
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

    private static function convertGsqToDegrees($GSQ) {
        $regExp =
            '/^(?:[a-rA-R]{2}[0-9]{2}|'                                 // FN03
            .'[a-rA-R]{2}[0-9]{2}[a-xA-X]{2}|'                          // FN03HR
            .'[a-rA-R]{2}[0-9]{2}[a-xA-X]{2}[0-9]{2}|'                  // FN03HR72
            .'[a-rA-R]{2}[0-9]{2}[a-xA-X]{2}[0-9]{2}[a-xA-X]{2})$/i';   // FN03HR72VO

        if (!$GSQ || !preg_match($regExp, $GSQ)) {
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

    private function clublogCheck() {
        if (empty($this->clublogCallsign) || empty($this->clublogEmail) || empty($this->clublogPassword)) {
            return false;
        }
        if (!$this->hasInternet) {
            return false;
        }
        $url = sprintf(
            "https://logs.classaxe.com/potashell/clublog/apikey"
            . "?CALL=%s&POTASHELL=%s&PHP=%s",
            urlencode($this->clublogCallsign),
            urlencode($this->version),
            urlencode($this->php)
        );
        $this->clublogApikey = file_get_contents($url, false, $this->HTTPcontext);
        return true;
    }

    private function clublogUpload(&$data, $date = false) {
        $stats = [
            'ATTEMPTED' =>  0,
            'UPDATED' =>    0,
            'DUPLICATE' =>  0,
            'ERROR' =>      0,
            'INSERTED' =>   0,
            'LAST_ERROR' => ''
        ];
        $processed = 0;
        $halt = false;
        foreach ($data as &$record) {
            if (isset($record['TO_CLUBLOG']) && $record['TO_CLUBLOG'] === 'Y') {
                continue;
            }
            if ($date && isset($record['QSO_DATE']) && ($record['QSO_DATE'] !== (string) $date)) {
                continue;
            }
            if ($this->modePushQty && strtolower($this->modePushQty) !== 'all' && $processed >= $this->modePushQty) {
                break;
            }
            if ($halt) {
                $stats['ERROR']++;
                continue;
            }
            $stats['ATTEMPTED']++;
            $adif = adif::toAdif([$record], $this->version, true, false);
            try {
                $url = "https://clublog.org/realtime.php";
                // $url = "https://logs.classaxe.com/custom/endpoint.php?response=OK";
                $args = [
                    'email' =>      ($this->clublogEmail),
                    'password' =>   ($this->clublogPassword),
                    'callsign' =>   ($this->clublogCallsign),
                    'api' =>        ($this->clublogApikey),
                    'adif' =>       ($adif)
                ];
                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // For HTTPS
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $args);
                $result = curl_exec($curl);
                $httpstatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
            } catch (\Exception $e) {
                print PS::RED_BD . "ERROR:\n  Unable to connect to Clublog.com for log uploads:" . PS::BLUE_BD . $e->getMessage() . PS::RED_BD  .".\n\n" . PS::RESET;
                die(0);
            }
            switch ($result) {
                case "Dupe":
                    $stats['DUPLICATE']++;
                    $record['TO_CLUBLOG'] = 'Y';
                    break;
                case "OK":
                    $stats['INSERTED']++;
                    $record['TO_CLUBLOG'] = 'Y';
                    break;
                case "Updated QSO":
                    $stats['UPDATED']++;
                    $record['TO_CLUBLOG'] = 'Y';
                    break;
                default:
                    $stats['ERROR']++;
                    if ($result) {
                        $stats['LAST_ERROR'] = $result;
                    } else {
                        $stats['LAST_ERROR'] = "HTTP Status {$httpstatus} was received from server";
                        $halt = true;
                    }
                    if (strpos($result, "Excessive realtime API usage") !== false) {
                        $halt = true;
                    }
                    break;
            }
            $processed++;
        }
        return
            PS::GREEN_BD
            . "  - Uploaded " . PS::CYAN_BD . $stats['ATTEMPTED'] . PS::GREEN_BD . " new Logs to " . PS::YELLOW_BD . "ClubLog.com" . PS::GREEN_BD . "\n"
            . ($stats['INSERTED'] ?                  "     * Inserted:       " . $stats['INSERTED'] . "\n" : "")
            . ($stats['UPDATED'] ?                   "     * Updated:        " . $stats['UPDATED'] . "\n" : "")
            . ($stats['DUPLICATE'] ?    PS::RED_BD . "     * Duplicates:     " . $stats['DUPLICATE'] . "\n" . PS::GREEN_BD : "")
            . ($stats['ERROR'] ?        PS::RED_BD . "     * Errors:         " . $stats['ERROR'] . "\n" . PS::GREEN_BD : "")
            . ($stats['LAST_ERROR'] ?   PS::RED_BD . "     * Last Error:     " . $stats['LAST_ERROR'] . "\n" . PS::GREEN_BD : "")
            ;
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

    private function dataFix($data) {
        $status = [
            'COUNTRY' => [ 'missing' => 0, 'fixed' => 0],
            'GRIDSQUARE' => [ 'missing' => 0, 'fixed' => 0],
            'STATE' => [ 'missing' => 0, 'fixed' => 0]
        ];

        $flags = ['TO_CLUBLOG', 'TO_QRZ', 'TO_POTA'];
        foreach ($data as &$record) {
            if (empty($record)) {
                continue;
            }
            if (empty($record['GRIDSQUARE'])) {
                $status['GRIDSQUARE']['missing']++;
                if ($record['GRIDSQUARE'] = $this->qrzGetGSQForCall($record['CALL'])) {
                    $status['GRIDSQUARE']['fixed']++;
                };
            }
            if (empty($record['COUNTRY'])) {
                $status['COUNTRY']['missing']++;
                if ($record['COUNTRY'] = $this->qrzGetItuForCall($record['CALL'])) {
                    $status['COUNTRY']['fixed']++;
                }
            }
            switch ($record['COUNTRY']) {
                case 'Australia':
                case 'Canada':
                case 'United States':
                    if (empty($record['STATE'])) {
                        $status['STATE']['missing']++;
                        if ($record['STATE'] = $this->qrzGetSpForCall($record['CALL'])) {
                            $status['STATE']['fixed']++;
                        }
                    }
                    break;
            }
            switch ($record['COUNTRY']) {
                case 'United States':
                    $record['COUNTRY'] = 'USA';
                    break;
            }
            foreach ($flags as $flag) {
                if (empty($record[$flag])) {
                    $record[$flag] = '';
                }
            }
            $record['MY_GRIDSQUARE'] =  $this->inputGSQ;
            $record['MY_CITY'] =        $this->locationNameAbbr;
            $record['PARK'] =           $this->inputQthId;
            $record['MODECOMP'] = ($record['MODE'] === 'MFSK' && $record['SUBMODE'] === 'FT4' ? 'FT4' : $record['MODE']);
            $myLatLon =     static::convertGsqToDegrees( $record['MY_GRIDSQUARE']);
            $theirLatLon =  static::convertGsqToDegrees($record['GRIDSQUARE']);
            if ($myLatLon && $theirLatLon) {
                $record['DX'] = number_format(round(static::calculateDX($myLatLon['lat'], $myLatLon['lon'], $theirLatLon['lat'], $theirLatLon['lon']) / 1000));
            }
        }
        return [
            'data' => $this->dataSetColumnOrder($data),
            'status' => $status
        ];
    }

    private static function dataGetBands($data, $date = null, $band = null) {
        $tmp = [];
        foreach ($data as $d) {
            if ($date && (int)$d['QSO_DATE'] !== $date) {
                continue;
            }
            if ($band && strtolower($d['BAND']) !== strtolower($band)) {
                continue;
            }
            if (!empty($d['BAND'])) {
                if (!isset($tmp[$d['BAND']])) {
                    $tmp[$d['BAND']] = 0;
                }
                $tmp[$d['BAND']] ++;
            }
        }
        uksort($tmp, function ($a, $b) {
            if ((int) $a > (int) $b) { return -1; }
            if ((int) $a < (int) $b) { return 1; }
            return 0;
        });
        return $tmp;
    }


    private static function dataGetBestDx($data, $date = null, $band = null) {
        $DX = 0;
        foreach ($data as $d) {
            if ($date && (int) $d['QSO_DATE'] !== $date) {
                continue;
            }
            if ($band && strtolower($d['BAND']) !== strtolower($band)) {
                continue;
            }
            if (!empty($d['DX'])) {
                $d['DX'] = str_replace(',', '', $d['DX']);
                if ($d['DX'] > $DX) {
                    $DX = $d['DX'];
                }
            }
        }
        return $DX;
    }

    private static function dataGetCountries($data, $date = null, $band = null) {
        $tmp = [];
        foreach ($data as $d) {
            if ($date && (int)$d['QSO_DATE'] !== $date) {
                continue;
            }
            if ($band && strtolower($d['BAND']) !== strtolower($band)) {
                continue;
            }
            if (!empty($d['COUNTRY'])) {
                if (!isset($tmp[$d['COUNTRY']])) {
                    $tmp[$d['COUNTRY']] = 0;
                }
                $tmp[$d['COUNTRY']] ++;
            }
        }
        ksort($tmp);
        return $tmp;
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

    private static function dataGetStates($data, $date = null, $band = null) {
        $tmp = [];
        foreach ($data as $d) {
            if ($date && (int) $d['QSO_DATE'] !== $date) {
                continue;
            }
            if ($band && strtolower($band) !== strtolower($d['BAND'])) {
                continue;
            }
            if (!empty($d['STATE'])) {
                if (!isset($tmp[$d['STATE']])) {
                    $tmp[$d['STATE']] = 0;
                }
                $tmp[$d['STATE']] ++;
            }
        }
        ksort($tmp);
        return $tmp;
    }

    private static function dataCountBands($data, $date = null) {
        $unique = [];
        foreach ($data as $d) {
            if (!$date || $d['QSO_DATE'] == $date) {
                $unique[$d['BAND']] = true;
            }
        }
        return count($unique);
    }

    private static function dataCountLogs($data, $date = null, $band = null) {
        $unique = [];
        foreach ($data as $d) {
            if ($date && (int) $d['QSO_DATE'] !== $date) {
                continue;
            }
            if ($band && strtolower($band) !== strtolower($d['BAND'])) {
                continue;
            }
            $unique[$d['QSO_DATE'] . '|' . $d['CALL'] . '|' . $d['BAND']] = true;
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

    private static function dataCountUploadType($data, $type) {
        $count = 0;
        foreach ($data as $d) {
            if (isset($d[$type]) && $d[$type] === 'Y') {
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

    private static function dataGetLogs($data, $date = null, $band = null) {
        $logs = [];
        foreach ($data as $d) {
            if ($date && (int) $d['QSO_DATE'] !== $date) {
                continue;
            }
            if ($band && strtolower($d['BAND']) !== strtolower($band)) {
                continue;
            }
            $logs[] = $d;
        }
        return $logs;
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

    private function dataSetColumnOrder($data) {
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

    private static function formatLineWrap($maxLen, $indent, $data, $qtyAlways = false) {
        $tmp = [];
        $lineLen = $indent;
        foreach ($data as $item => $count) {
            $qty = ($qtyAlways || ($count > 1) ? ' (' . $count . ')' : '');
            $lineLen += strlen($item . $qty . ',');
            if ($lineLen > $maxLen) {
                $item = "\n" . str_repeat(' ', $indent) . $item . PS::CYAN_BD . $qty;
                $lineLen = $indent;
            } else {
                $item .= PS::CYAN_BD . $qty;
            }
            $tmp[] = $item;
        }
        return $tmp;
    }

    private function getHTTPContext() {
        $this->HTTPcontext = stream_context_create([
            'http'=> [
                'method'=>"GET",
                'header'=>"User-Agent: " . sprintf(PS::USERAGENT, $this->version, date('Y')) . "\r\n"
            ]
        ]);
    }

    private function getLocationName($qthID) {
        if (!empty($this->customLocations)) {
            foreach ($this->customLocations as $location) {
                $locationBits = explode('|', $location);
                if ($locationBits[0] === $qthID) {
                    return [
                        'abbr' => $locationBits[1],
                        'name' => $qthID,
                        'type' => 'CUSTOM'
                    ];
                }
            }
        }

        return $this->potaGetParkName($qthID);
    }

    private function internetCheck() {
        if (!@fsockopen('www.example.com', 80)) {
            print
                PS::RED_BD . "WARNING:\n"
                . "  - You have no internet connection.\n"
                . "  - Automatic gridquare and park name lookups will not work.\n"
                . "  - QRZ uploads are not possible at this time.\n"
                . PS::RESET
                . "\n";
            return;
        }
        $this->hasInternet = true;
    }
    private function phpCheck() {
        $libs = [
            'curl',
            'mbstring',
            'openssl'
        ];
        $msg = "\n" . PS::RED_BD . "ERROR:\n" . PS::GREEN_BD . "  PHP " . PS::YELLOW_BD . "%s" . PS::GREEN_BD . " extension is not available.\n"
            . PS::GREEN_BD ."  PHP version " . PS::YELLOW_BD . "%s" . PS::GREEN_BD . ", "
            . "php.ini file: " . PS::YELLOW_BD . "%s\n"
            . PS::RESET;
        foreach ($libs as $lib) {
            if (!extension_loaded($lib)) {
                print sprintf($msg, $lib, phpversion(),(php_ini_loaded_file() ? php_ini_loaded_file() : "None"));
                die(0);
            }
        }
    }

    private function potaGetParkName($qthId) {
        if (!$this->hasInternet) {
            $type = (substr($qthId, 0, 2) === 'XX' ? 'CUSTOM' : 'POTA');
            return [
                'name' => $qthId,
                'abbr' => $type . ': ' . $qthId,
                'type' => $type
            ];
        }
        $url = "https://api.pota.app/park/" . trim($qthId);
        $data = file_get_contents($url, false, $this->HTTPcontext);
        $data = json_decode($data);
        if (!$data) {
            return false;
        }
        $parkName = trim($data->name) . ' ' . trim($data->parktypeDesc);
        $parkNameAbbr = strtr("POTA: " . $qthId . " " . $parkName, PS::NAME_SUBS);
        return [
            'abbr' => $parkNameAbbr,
            'name' => $parkName,
            'type' => 'POTA'
        ];
    }

    private function potaPublishSpot() {
        $url = 'https://api.pota.app/spot/';
        // $url = 'https://logs.classaxe.com/test.php';
        $activator = ($this->inputQthId === 'K-TEST' ? 'ABC123' : $this->qrzApiCallsign);
        $data = json_encode([
            'activator' =>  $activator,
            'spotter' =>    $this->qrzApiCallsign,
            'frequency' =>  $this->spotKhz,
            'reference' =>  $this->inputQthId,
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

    private function potaSaveLogs(&$data) {
        if (!$this->sessionAdifDirectory) {
            return '';
        }
        $filename = $this->sessionAdifDirectory . DIRECTORY_SEPARATOR . $this->locationType . "_" . $this->inputQthId . ".adi";
        $export = [];
        foreach ($data as &$record) {
            if ($record['TO_POTA'] === 'Y') {
                continue;
            }
            $record['TO_POTA'] = 'Y';
            $export[] = $record;
        }
        if (!empty($export)) {
            $complete = [];
            if (file_exists($filename)) {
                print "Appending\n";
                $adif =     new adif($filename);
                $complete = array_merge($adif->parser(), $export);
            } else {
                print "Creating\n";
                $adif =     new adif('');
                $complete = $export;
            }
            $adif =     $adif->toAdif($complete, $this->version, false, true);
            file_put_contents($filename, $adif);
        }
        return "  - Inserted " . PS::CYAN_BD . count($export) . PS::GREEN_BD . " new " . (count($export) === 1 ? "log" : "logs")
            . " in " . PS::YELLOW_BD . "Session export file\n"
            . "      " . PS::CYAN_BD . $filename . PS::GREEN_BD . "\n";

//        $adif = adif::toAdif($export, $this->version, false, true);
    }

    private function process() {
        print PS::YELLOW_BD . "STATUS:\n";
        if (!$this->inputQthId || !$this->inputGSQ) {
            print PS::RED_BD . "  - One or more required parameters are missing.\n"
                . "    Unable to continue.\n" . PS::RESET . "\n";
            die(0);
        }
        if (!$lookup = $this->getLocationName($this->inputQthId)) {
            print PS::RED_BD . "\nERROR:\n  Unable to get name for park {$this->inputQthId}.\n" . PS::RESET . "\n";
            die(0);
        }
        $this->locationName =       $lookup['name'];
        $this->locationNameAbbr =   $lookup['abbr'];
        $this->locationType =       $lookup['type'];
        print PS::GREEN_BD . "  - Command:          " . PS::WHITE_BD . "potashell "
            . PS::CYAN_BD . $this->inputQthId . ' '
            . PS::BLUE_BD . $this->inputGSQ . ' '
            . PS::GREEN_BD . $this->mode . ' ' . $this->modePushQty
            . PS::MAGENTA_BD . $this->argCheckBand . "\n"
            . PS::GREEN_BD . "  - Identified QTH:   " . PS::CYAN_BD . $this->locationName . "\n"
            . PS::GREEN_BD . "  - Name for Log:     " . PS::CYAN_BD . $this->locationNameAbbr . "\n";
        $this->fileAdifPark =   "wsjtx_log_{$this->inputQthId}.adi";
        $this->fileAdifWsjtx =  "wsjtx_log.adi";

        $fileAdifParkExists =   file_exists($this->pathAdifLocal . $this->fileAdifPark);
        $fileAdifWsjtxExists =  file_exists($this->pathAdifLocal . $this->fileAdifWsjtx);

        PS::wsjtxUpdateInifile($this->qrzApiCallsign, $this->inputGSQ);

        if (($fileAdifParkExists || $fileAdifWsjtxExists) && $this->modeCheck) {
            $this->processParkCheck(false, true);
            return;
        }

        if (($fileAdifParkExists || $fileAdifWsjtxExists) && $this->modeReview) {
            $this->processParkCheck(true, true);
            return;
        }

        if (($fileAdifParkExists || $fileAdifWsjtxExists) && $this->modeSummary) {
            $this->processParkCheck(true, false);
            return;
        }

        if (($fileAdifParkExists || $fileAdifWsjtxExists) && $this->modePush) {
            $this->processParkPush();
            return;
        }

        if ($this->modeSpot && $this->locationType === 'POTA') {
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
        print PS::YELLOW_BD . "STATUS:\n"
            . PS::GREEN_BD . "Performing Audit on all POTA Log files in "
            . PS::BLUE_BD . $this->pathAdifLocal . "\n";
        $files = glob($this->pathAdifLocal . "wsjtx_log_??-*.adi");
        if (!$files) {
            print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD . "No log files found." .  PS::RESET . "\n";
            return;
        }
        $columns = str_replace(
            "|",
            PS::GREEN_BD . "|" . PS::CYAN_BD,
            "QTH ID   | MY_GRID    | #LT | #ST | #SA | #FA | #MG | #LS | #B |  DX KM | UPLOAD | Park Name in Log File"
        );

        print PS::YELLOW_BD . "\nKEY:\n" . PS::GREEN_BD
            . "  " . PS::CYAN_BD . "#LT" . PS::GREEN_BD . " =    Logs in total - excluding duplicates\n"
            . "  " . PS::CYAN_BD . "#ST" . PS::GREEN_BD . " =    Sessions in Total\n"
            . "  " . PS::CYAN_BD . "#SA" . PS::GREEN_BD . " =    Successful Activations\n"
            . "  " . PS::CYAN_BD . "#FA" . PS::GREEN_BD . " =    Failed Activations\n"
            . "  " . PS::CYAN_BD . "#MG" . PS::GREEN_BD . " =    Missing Grid Squares\n"
            . "  " . PS::CYAN_BD . "#LS" . PS::GREEN_BD . " =    Logs for latest session - excluding duplicates. " . PS::ACTIVATION_LOGS . " required for activation.\n"
            . "  " . PS::CYAN_BD . "#B" . PS::GREEN_BD . "  =    Number of bands\n"
            . "  " . PS::CYAN_BD . "UPLOAD" . PS::GREEN_BD . " = Uploaded logs to "
            . PS::YELLOW . "C" . PS::GREEN_BD . "-Clublog, "
            . PS::YELLOW . "Q" . PS::GREEN_BD . "-QRZ, "
            . PS::YELLOW . "X" . PS::GREEN_BD . "-eXport file for session\n"
            . PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD
            . str_repeat('-', PS::MAXLEN) . "\n"
            .  PS::CYAN_BD . $columns . PS::GREEN_BD . "\n"
            . str_repeat('-', PS::MAXLEN) . "\n";
        foreach ($files as $file) {
            if (is_file($file)) {
                // if ($i++ > 4) { continue; }  // For development testing
                $fn =       basename($file);
                $qthId =    explode('.', explode('_', $fn)[2])[0];
                $lookup =   $this->getLocationName($qthId);

                $adif =     new adif($file);
                $data =     $adif->parser();
                $MY_GRID =  PS::dataGetMyGrid($data);
                if ($MY_GRID === false) {
                    print PS::RED_BD . "ERROR - file " . $fn . " has no 'MY_GRIDSQUARE column\n";
                    continue;
                }
                $dates =    static::dataGetDates($data);
                $date =     end($dates);
                $LS =       static::dataCountLogs($data, $date);
                $LT =       static::dataCountLogs($data);
                $MG =       static::dataCountMissingGsq($data);
                $ST =       count($dates);
                $AT =       static::dataCountActivations($data);
                $FT =       $ST - $AT;
                $B =        static::dataCountBands($data);
                $DX =       number_format(static::dataGetBestDx($data));

                print PS::BLUE_BD . str_pad($qthId, 8, ' ') . PS::GREEN_BD . " | "
                    . (count($MY_GRID) === 1 ?
                        PS::CYAN_BD . str_pad($MY_GRID[0], 10, ' ') :
                        PS::RED_BD . str_pad('ERR ' . count($MY_GRID) . ' GSQs', 10, ' ')
                      ) . PS::GREEN_BD . " | "

                    . str_pad($LT, 3, ' ', STR_PAD_LEFT) . ' | '
                    . str_pad($ST, 3, ' ', STR_PAD_LEFT) . ' | '
                    . str_pad($AT, 3, ' ', STR_PAD_LEFT) . ' | '
                    . PS::RED_BD . str_pad(($FT ? $FT : ''), 3, ' ', STR_PAD_LEFT) . PS::GREEN_BD . ' | '
                    . PS::RED_BD . str_pad(($MG ? $MG : ''), 3, ' ', STR_PAD_LEFT) . PS::GREEN_BD . ' | '

                    . ($lookup['type'] === 'POTA' && $LS < PS::ACTIVATION_LOGS ? PS::RED_BD : '') . str_pad($LS, 3, ' ', STR_PAD_LEFT) . PS::GREEN_BD . ' | '
                    . str_pad($B, 2, ' ', STR_PAD_LEFT) . ' | '
                    . str_pad($DX, 6, ' ', STR_PAD_LEFT) . ' |  ' . PS::YELLOW
                    . (static::dataCountUploadType($data, 'TO_CLUBLOG') === count($data) ? 'C' : ' ') . ' '
                    . (static::dataCountUploadType($data, 'TO_QRZ') === count($data) ? 'Q' : ' ') . ' '
                    . (static::dataCountUploadType($data, 'TO_POTA') === count($data) ? 'X' : ' ') . PS::GREEN_BD . ' | '
                    . (isset($lookup['abbr']) ? PS::BLUE_BD . $lookup['abbr'] . PS::GREEN_BD : PS::RED_BD . 'Lookup failed' . PS::GREEN_BD)
                    . "\n";
            }
        }
        print str_repeat('-', PS::MAXLEN) . PS::RESET . "\n";
    }

    private function processParkArchiving() {
        $adif =     new adif($this->pathAdifLocal . $this->fileAdifWsjtx);
        $data =     $adif->parser();
        $result =   $this->dataFix($data);
        $data =     $result['data'];
        $dates =    $this->dataGetDates($data);
        $date =     end($dates);
        $logs =     $this->dataCountLogs($data, $date);
        $MGs1 =     $this->dataCountMissingGsq($data);
        $locs =     $this->dataGetLocations($data);

        print static::showStats($data, $date)
            . static::showLogs($data, $date)
            . PS::YELLOW_BD . "OPERATION:\n"
            . PS::GREEN_BD . "  - Archive log file " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
            . " to " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::GREEN_BD . "\n";

        if (count($locs) > 1) {
            print PS::RED_BD . "\nERROR:\n  * There are " . count($locs) . " named log locations contained within this one file:\n"
                . "    - " . implode("\n    - ", $locs) . "\n"
                . "  * Manual intervention is required.\n"
                . "  * The operation has been cancelled.\n"
                . PS::RESET;
            return;
        }
        print ($this->qrzApiKey ?           "  - Upload park log to " . PS::BLUE_BD . "Clublog.org" . PS::GREEN_BD . "\n" : "")
            . ($this->clublogCheck() ?      "  - Upload park log to " . PS::BLUE_BD . "QRZ.com" . PS::GREEN_BD . "\n" : "")
            . ($this->sessionAdifDirectory ?"  - Save " . PS::BLUE_BD . "Session log file" . PS::GREEN_BD . "\n" : "")
            . "\n"
            . ($this->locationType === 'POTA' && $logs < PS::ACTIVATION_LOGS ? PS::RED_BD ."WARNING:\n    There are insufficient logs for successful activation.\n\n" . PS::GREEN_BD : "");

        if (isset($locs[0]) && trim(substr($locs[0], 0, 14)) !== trim(substr($this->locationNameAbbr, 0, 14))) {
            print PS::RED_BD . "ERROR:\n"
                . "  * The log contains reports made at      " . PS::BLUE_BD . $locs[0] . PS::RED_BD . "\n"
                . "  * You indicate that your logs were from " . PS::BLUE_BD . $this->locationNameAbbr . PS::RED_BD . "\n"
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
        $filename = $this->pathAdifLocal . $this->fileAdifPark;
        rename($this->pathAdifLocal . $this->fileAdifWsjtx, $filename);
        $result =   $this->dataFix($data);
        $data =     $result['data'];
        $status =   $result['status'];
        $resCL =    '';
        $resQrz =   '';
        $resPota =  '';
        if ($this->clublogCheck()) {
            $resCL = $this->clublogUpload($data, $date);
        }
        if ($this->qrzApiCallsign && $this->qrzApiKey) {
            $resQrz = $this->qrzUpload($data, $date);
        }
        if ($this->sessionAdifDirectory) {
            $resPota = $this->potaSaveLogs($data);
        }
        $adif =     $adif->toAdif($data, $this->version, false, true);
        file_put_contents($filename, $adif);
        print "  - Archived log file " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
            . "  to " . PS::BLUE_BD ."{$this->fileAdifPark}" . PS::GREEN_BD . ".\n"
            . "  - Updated " . PS::MAGENTA_BD ."MY_GRIDSQUARE" . PS::GREEN_BD ." values     to " . PS::CYAN_BD . $this->inputGSQ . PS::GREEN_BD . ".\n"
            . "  - Added " . PS::MAGENTA_BD ."MY_CITY" . PS::GREEN_BD ." and set all values to " . PS::CYAN_BD . $this->locationNameAbbr . PS::GREEN_BD . ".\n"
            . (!empty($this->qrzSession) && $status['GRIDSQUARE']['missing'] ? "  - Obtained " . PS::CYAN_BD
                . ($status['GRIDSQUARE']['fixed'] ?
                    ($status['GRIDSQUARE']['missing'] - $status['GRIDSQUARE']['fixed']) . " of " . $status['GRIDSQUARE']['missing']
                :
                    $status['GRIDSQUARE']['missing']
                )
                . PS::GREEN_BD . " missing gridsquares." . PS::GREEN_BD . "\n" : ""
              )
            . $resCL
            . $resQrz
            . $resPota
            . "\n";
        if ($this->locationType === 'POTA') {
            print PS::YELLOW_BD . "CLOSE SPOT:\n" . PS::GREEN_BD
                . "    Would you like to close this spot on pota.app (Y/N)     " . PS::BLUE_BD;

            $fin = fopen("php://stdin", "r");
            $response = strToUpper(trim(fgets($fin)));

            if ($response === 'Y') {
                print PS::GREEN_BD . "    Please enter frequency in KHz:                          " . PS::MAGENTA_BD;
                $this->spotKhz = trim(fgets($fin));

                print PS::GREEN_BD . "    Enter comment - e.g. QRT - moving to CA-1234            " . PS::RED_BD;
                $this->spotComment = trim(fgets($fin));

                $this->processParkSpot();
            }
        }

        print PS::YELLOW_BD . "\nNEXT STEP:\n" . PS::GREEN_BD
            . "  - You should now restart WSJT-X before logging at another park, where\n"
            . "    a fresh " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD . " file will be created.\n"
            . "  - Alternatively, run this script again with a new Location ID to resume\n"
            . "    logging at a previously visited park.\n"
            . PS::RESET;
    }

    private function processParkCheck($all = false, $showLogs = false) {
        $fileAdifParkExists =   file_exists($this->pathAdifLocal . $this->fileAdifPark);
        $fileAdif = ($fileAdifParkExists ? $this->fileAdifPark : $this->fileAdifWsjtx);
        $adif =     new adif($this->pathAdifLocal . $fileAdif);
        $data =     $adif->parser();
        $result =   $this->dataFix($data);
        $data =     $result['data'];
        $dates =    $this->dataGetDates($data);
        $date =     ($all ? null : end($dates));
        $band =     $this->argCheckBand;
        $logs =     $this->dataCountLogs($data, $date);
        $MGs =      $this->dataCountMissingGsq($data);
        $locs =     $this->dataGetLocations($data);

        print PS::GREEN_BD . "  - File " . PS::BLUE_BD . "{$fileAdif}" . PS::GREEN_BD
            . " exists and contains " . PS::CYAN_BD . count($data) . PS::GREEN_BD . " entries.\n"
            . ($MGs ? "  - There are " . PS::RED_BD . $MGs . PS::GREEN_BD . " missing gridsquares\n" : "")
            . static::showStats($data, $date, $band)
            . ($showLogs ? static::showLogs($data, $date, $band) : "")
            . ($this->locationType === 'POTA' && $logs < PS::ACTIVATION_LOGS || count($locs) > 1 ? PS::RED_BD ."\nWARNING:\n" : '')
            . ($this->locationType === 'POTA' && $logs < PS::ACTIVATION_LOGS ? PS::RED_BD ."  * There are insufficient logs for successful activation.\n" . PS::GREEN_BD : '')
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

    private function processParkPush() {
        $fileAdifParkExists =   file_exists($this->pathAdifLocal . $this->fileAdifPark);
        $fileAdif = ($fileAdifParkExists ? $this->fileAdifPark : $this->fileAdifWsjtx);
        $filename = $this->pathAdifLocal . $fileAdif;
        $adif =     new adif($filename);
        $data =     $adif->parser();
        $result =   $this->dataFix($data);
        $data =     $result['data'];
        $dates =    $this->dataGetDates($data);
        $date =     ($this->modePushQty ? false : end($dates));

        $adif = $adif->toAdif($data, $this->version, false, true);
        file_put_contents($filename, $adif);
        $resCL =    '';
        $resQrz =   '';
        $resPota =  '';
        if ($this->clublogCheck()) {
            $resCL = $this->clublogUpload($data, $date);
        }
        if ($this->qrzApiCallsign && $this->qrzApiKey) {
            $resQrz = $this->qrzUpload($data, $date);
        }
        if ($this->sessionAdifDirectory) {
            $resPota = $this->potaSaveLogs($data);
        }
        $adif = adif::toAdif($data, $this->version, false, true);
        file_put_contents($filename, $adif);
        print $resCL . $resQrz . $resPota . "\n" . PS::RESET;
    }

    private function processParkSpot() {
        $activator = ($this->inputQthId === 'K-TEST' ? 'ABC123' : $this->qrzLogin);
        $linelen = 44 + strlen($this->spotComment);
        print PS::YELLOW_BD . "\nPENDING OPERATION:\n"
            . PS::GREEN_BD . "    The following spot will be published at pota.app:\n\n"
            . PS::WHITE_BD . "     Activator  Spotter    KHz      Park Ref   Comments\n"
            . "    " . str_repeat('-', $linelen) . "\n"
            . "     "
            . str_pad($activator, 10, ' ') . ' '
            . str_pad($this->qrzLogin, 10, ' ') . ' '
            . str_pad($this->spotKhz, 8, ' ') . ' '
            . str_pad($this->inputQthId, 10, ' ') . ' '
            . $this->spotComment . "\n"
            . "    " . str_repeat('-', $linelen) . "\n\n"
            . PS::YELLOW_BD . "CONFIRMATION REQUIRED:\n"
            . PS::GREEN_BD . "    Please confirm that you want to publish the spot: (Y/N) " . PS::BLUE_BD;
        $fin = fopen("php://stdin","r");
        $response = strToUpper(trim(fgets($fin)));
        if ($response !== 'Y') {
            print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD . "    Spot has NOT been published.\n" . PS::RESET;
            return false;
        }
        $result = $this->potaPublishSpot();
        if ($result === true) {
            print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD
                . "  - Your spot at " . PS::BLUE_BD . $this->inputQthId . PS::GREEN_BD . " on " . PS::MAGENTA_BD . $this->spotKhz . " KHz" . PS::GREEN_BD
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
            . " nor " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD . " exist.\n\n";

        if ($this->locationType === 'POTA') {
            print PS::YELLOW_BD . "PUBLISH SPOT:\n" . PS::GREEN_BD
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
            . "  - Resume logging at park " . PS::RED_BD . "{$this->locationName}" . PS::GREEN_BD . "\n\n"
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
                . " to " . PS::BLUE_BD ."{$this->fileAdifWsjtx}" . PS::GREEN_BD . "\n\n";
            if ($this->locationType === 'POTA') {
                print PS::YELLOW_BD . "PUBLISH SPOT:\n" . PS::GREEN_BD
                    . "    Would you like to publish this spot to pota.app (Y/N)   " . PS::BLUE_BD;
                $fin = fopen("php://stdin", "r");
                $response = strToUpper(trim(fgets($fin)));
                if ($response === 'Y') {
                    print PS::GREEN_BD . "    Please enter frequency in KHz:                          " . PS::MAGENTA_BD;
                    $this->spotKhz = trim(fgets($fin));

                    print PS::GREEN_BD . "    Enter a comment, starting with mode e.g. \"FT8 QRP 5w\"   " . PS::RED_BD;
                    $this->spotComment = trim(fgets($fin));

                    $this->processParkSpot();
                }
            }
            print PS::YELLOW_BD . "\nNEXT STEP:\n" . PS::GREEN_BD
                . "    You may resume logging at " . PS::RED_BD . "{$this->locationName}\n\n"
                . PS::RESET;
        } else {
            print "    Operation cancelled.\n";
        }
        print PS::RESET;
    }

    private function qrzCheck() {
        if (empty($this->qrzLogin) || empty($this->qrzPass)) {
            print
                PS::RED_BD . "WARNING:\n"
                . "  QRZ.com credentials were not found in " . PS::BLUE_BD ."potashell.ini" . PS::RED_BD  . ".\n"
                . "  Missing GSQ values for logged contacts cannot be fixed without\n"
                . "  valid QRZ credentials.\n"
                . PS::RESET;
            return false;
        }
        if (!$this->hasInternet) {
            return false;
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
        }
        if (empty($data->Session->Key)) {
            print
                PS::RED_BD . "ERROR:\n  QRZ.com reports an invalid session key, so automatic log uploads are possible at this time.\n\n" . PS::RESET
                . "  Missing GSQ values for logged contacts cannot be fixed without valid QRZ credentials.\n\n"
                . PS::RESET;
            return false;
        }
        $this->qrzSession = $data->Session->Key;
        if (empty($this->qrzApiKey)) {
            print
                PS::RED_BD . "WARNING:\n"
                . "  QRZ.com " . PS::BLUE_BD . "[QRZ]apikey" . PS::RED_BD . " is missing in " . PS::BLUE_BD ."potashell.ini" . PS::RED_BD  .".\n"
                . "  Without a valid XML Subscriber apikey, you won't be able to automatically upload\n"
                . "  archived logs to QRZ.com.\n\n" . PS::RESET;
            return false;
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
            return true;
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
        return false;
    }

    private function qrzGetInfoForCall($callsign) {
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

    private function qrzGetGSQForCall($callsign) {
        $data = $this->qrzGetInfoForCall($callsign);
        if (empty($data->Callsign->grid)) {
            print PS::RED_BD . "    WARNING: No gridsquare found at QRZ.com for callsign " . PS::BLUE_BD . $callsign . "\n" . PS::RESET;
            return null;
        }
        return (string) $data->Callsign->grid;
    }

    private function qrzGetItuForCall($callsign) {
        $data = $this->qrzGetInfoForCall($callsign);
        if (empty($data->Callsign->country)) {
            print PS::RED_BD . "    WARNING: No country found at QRZ.com for callsign " . PS::BLUE_BD . $callsign . "\n" . PS::RESET;
            return null;
        }
        return (string) $data->Callsign->country;
    }

    private function qrzGetSpForCall($callsign) {
        $data = $this->qrzGetInfoForCall($callsign);
        return isset($data->Callsign->state) ? strtoupper((string) $data->Callsign->state) : null;
    }

    private function qrzUpload(&$data, $date = false) {
        $stats = [
            'ATTEMPTED' =>  0,
            'DUPLICATE' =>  0,
            'ERROR' =>      0,
            'INSERTED' =>   0,
            'WRONG_CALL' => 0
        ];
        $processed = 0;
        foreach ($data as &$record) {
            if ($record['TO_QRZ'] === 'Y') {
                continue;
            }
            if ($date && ($record['QSO_DATE'] !== (string) $date)) {
                continue;
            }
            if ($this->modePushQty && strtolower($this->modePushQty) !== 'all' && $processed >= $this->modePushQty) {
                continue;
            }
            $stats['ATTEMPTED']++;
            $adif = adif::toAdif([$record], $this->version, true, false);
            try {
                $url = sprintf(
                    "https://logbook.qrz.com/api?KEY=%s&ACTION=INSERT&ADIF=%s",
                    urlencode($this->qrzApiKey),
                    urlencode($adif)
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
                    $record['TO_QRZ'] = 'Y';
                    break;
                case 'FAIL':
                    if ($status['REASON'] === 'Unable to add QSO to database: duplicate') {
                        $stats['DUPLICATE']++;
                        $record['TO_QRZ'] = 'Y';
                    } elseif (strpos($status['REASON'], 'wrong station_callsign for this logbook') !== false) {
                        $stats['WRONG_CALL']++;
                        $record['TO_QRZ'] = 'Wrong Callsign';
                    } else {
                        print $status['REASON'] . "\n";
                        $stats['ERROR']++;
                        $record['TO_QRZ'] = $status['REASON'];
                    }
                    break;
            }
            $processed++;
        }
        return PS::GREEN_BD
            . "  - Uploaded " . PS::CYAN_BD . $stats['ATTEMPTED'] . PS::GREEN_BD . " new Logs to " . PS::YELLOW_BD . "QRZ.com" . PS::GREEN_BD . "\n"
            . ($stats['INSERTED'] ?                  "     * Inserted:       " . $stats['INSERTED'] . "\n" : "")
            . ($stats['DUPLICATE'] ?    PS::RED_BD . "     * Duplicates:     " . $stats['DUPLICATE'] . "\n" . PS::GREEN_BD : "")
            . ($stats['WRONG_CALL'] ?   PS::RED_BD . "     * Wrong Callsign: " . $stats['WRONG_CALL'] . "\n" . PS::GREEN_BD : "")
            . ($stats['ERROR'] ?        PS::RED_BD . "     * Errors:         " . $stats['ERROR'] . "\n" . PS::GREEN_BD : "");
    }

    private function showHeader() {
        $name = 'POTA SHELL  ' . $this->version;
        print PS::CLS . PS::YELLOW
            . str_repeat('*', strlen($name) + 4) . "\n"
            . '* ' . PS::YELLOW_BD . 'POTA SHELL  ' . PS::GREEN_BD . $this->version . PS::YELLOW . " *\n"
            . '* ' . PS::YELLOW_BD . 'PHP         ' . PS::GREEN_BD . str_pad($this->php, strlen($this->version), ' ', STR_PAD_LEFT) . PS::YELLOW . " *\n"
            . str_repeat('*', strlen($name) + 4) . "\n\n";
    }

    private function showHelp() {
        print PS::YELLOW_BD . "PURPOSE:" . PS::YELLOW ."\n"
            . "  This program works with ". PS::YELLOW_BD . "WSJT-X" . PS::YELLOW . " log files to prepare them for upload to POTA.\n"
            . "  1) It sets all " . PS::YELLOW_BD ."MY_GRIDSQUARE" . PS::YELLOW ." values to your supplied " . PS::CYAN_BD . "Maidenhead GSQ value" . PS::YELLOW .".\n"
            . "  2) It adds a new " . PS::YELLOW_BD ."MY_CITY" . PS::YELLOW ." column to all rows, populated with the Park Name in this format:\n"
            . "     " . PS::CYAN_BD . "POTA: CA-1368 North Maple RP" . PS::YELLOW . " - a POTA API lookup is used to obtain the park name.\n"
            . "  3) It obtains missing " . PS::YELLOW_BD . "GRIDSQUARE" . PS::YELLOW . ", "  . PS::YELLOW_BD . "STATE" . PS::YELLOW ." and " . PS::YELLOW_BD ."COUNTRY" . PS::YELLOW . " values from the QRZ Callbook service.\n"
            . "  4) It archives or un-archives the park log file in question - see below for more details.\n"
            . "  5) It can post a " . PS::GREEN_BD ."SPOT" . PS::YELLOW ." to POTA with a given frequency, mode and park ID to alert \"hunters\"\n"
            . "     to the start or end of your activation session.\n"
            . "  6) It also updates values for " . PS::YELLOW_BD ."MyCall" . PS::YELLOW . " and " . PS::YELLOW_BD ."MyGrid" . PS::YELLOW
            . " in your " . PS::BLUE_BD . "WSJT-X.ini" . PS::YELLOW . " file if they have changed,\n"
            . "     so that the correct call and gridsquare are broadcast during your activations.\n"
            . "\n"
            . PS::YELLOW_BD . "CONFIGURATION:" . PS::YELLOW ."\n"
            . "  User Configuration is by means of the " . PS::BLUE_BD . "potashell.ini" . PS::YELLOW ." file located in this directory.\n"
            . "\n"
            . PS::YELLOW_BD . "SYNTAX:\n"
            . $this->showSyntax(1)
            . "\n" . PS::YELLOW_BD
            . "     a) PROMPTING FOR USER INPUTS:\n" . PS::YELLOW
            . "       - If either " . PS::BLUE_BD . "Park ID" . PS::YELLOW . " or " . PS::CYAN_BD ."GSQ" . PS::YELLOW . " is omitted, potashell prompts for these values.\n"
            . "       - Before any files are renamed or modified, user must confirm the operation.\n"
            . "         If user responds " . PS::RESPONSE_Y . " operation continues, " . PS::RESPONSE_N ." aborts.\n"
            . "\n" . PS::YELLOW_BD
            . "     b) WITHOUT AN ACTIVE LOG FILE:\n" . PS::YELLOW
            . "       - If there is NO active " . PS::BLUE_BD ."wsjtx_log.adi" . PS::YELLOW . " log file, potashell looks for a file for the\n"
            . "         indicated park, e.g." . PS::BLUE_BD ."wsjtx_log_CA-1368.adi" . PS::YELLOW . ", and if the user confirms the operation,\n"
            . "         potashell renames it to " . PS::BLUE_BD ."wsjtx_log.adi" . PS::YELLOW . " so that logs can be added for this park.\n"
            . "       - " . PS::YELLOW_BD . "WSJT-X" . PS::YELLOW . " should be restarted if running, so the " . PS::BLUE_BD ."wsjtx_log.adi" . PS::YELLOW . " file can be read or created.\n"
            . "       - The user is then asked if they want to add a " . PS::GREEN_BD . "SPOT" . PS::YELLOW . " for the park on the POTA website.\n"
            . "         If user responds " . PS::RESPONSE_Y . ", potashell asks for " . PS::YELLOW_BD . "frequency" . PS::YELLOW . " and " . PS::RED_BD . "comment" . PS::YELLOW . " - usually mode, e.g. " . PS::RED_BD . "FT8" . PS::YELLOW . "\n"
            . "\n" . PS::YELLOW_BD
            . "     c) WITH AN ACTIVE LOG FILE:\n" . PS::YELLOW
            . "       - If latest session has too few logs for POTA activation, a " . PS::RED_BD . "WARNING" . PS::YELLOW ." is given.\n"
            . "       - If the log contains logs from more than one location, the process is halted.\n"
            . "       - If an active log session has completed, and the user confirms the operation:\n" . PS::YELLOW_BD
            . "         1. " . PS::YELLOW . "The " . PS::BLUE_BD . "wsjtx_log.adi" . PS::YELLOW ." file is renamed to " . PS::BLUE_BD ."wsjtx_log_CA-1368.adi\n" . PS::YELLOW_BD
            . "         2. " . PS::YELLOW . "Any missing " . PS::GREEN_BD . "GRIDSQUARE" . PS::YELLOW . ", "  . PS::GREEN_BD . "STATE" . PS::YELLOW ." and " . PS::GREEN_BD ."COUNTRY" . PS::YELLOW . " values for the other party are added.\n" . PS::YELLOW_BD
            . "         3. " . PS::YELLOW . "The supplied gridsquare - e.g. " . PS::CYAN_BD ."FN03FV82" . PS::YELLOW . " is written to all " . PS::GREEN_BD . "MY_GRIDSQUARE" . PS::YELLOW . " fields\n" . PS::YELLOW_BD
            . "         4. " . PS::YELLOW . "The identified park - e.g. " . PS::CYAN_BD . "POTA: CA-1368 North Maple RP " . PS::YELLOW . "is written to all " . PS::GREEN_BD . "MY_CITY" . PS::YELLOW . " fields\n" . PS::YELLOW_BD
            . "         5. " . PS::YELLOW . "If you have an internet connection, logs in the current session are sent to " . PS::YELLOW_BD . "QRZ.com" . PS::YELLOW . ",\n"
            . "            and if your potashell.ini file contains ClubLog credentials, to " . PS::YELLOW_BD . "ClubLog.com" . PS::YELLOW . " also.\n" . PS::YELLOW_BD
            . "         6. " . PS::YELLOW . "If configured to do so, logs for the current session are used to create or append a POTA log file suitable for submitting to POTA.\n"
            . "         7. " . PS::YELLOW . "The user is asked if they'd like to mark their " . PS::GREEN_BD . "SPOT" . PS::YELLOW . " in POTA as QRT (inactive).\n" . PS::YELLOW_BD
            . "            " . PS::YELLOW . "If they respond " . PS::RESPONSE_Y . ", potashell prompts for the " . PS::YELLOW_BD . "frequency" . PS::YELLOW . " and " . PS::RED_BD . "comment" . PS::YELLOW . ", usually\n"
            . "            starting with the code " . PS::RED_BD . "QRT" . PS::YELLOW . " indicating that the activation attempt has ended -\n"
            . "            for example: " . PS::RED_BD . "QRT - moving to CA-1369" . PS::YELLOW . "\n"
            . "\n" . PS::YELLOW_BD
            . "     d) THE \"CHECK\" MODE:\n" . PS::YELLOW
            . "       - If the " . PS::GREEN_BD . "CHECK" . PS::YELLOW . " argument is given, system operates directly on either\n"
            . "         the Park Log file, or if that is absent, the " . PS::BLUE_BD . "wsjtx_log.adi" . PS::YELLOW ." file currently in use.\n"
            . "       - A full list and summary for all logs in the latest session are shown, together with\n"
            . "         distances for each contact in KM.\n"
            . "       - Missing " . PS::GREEN_BD . "GRIDSQUARE" . PS::YELLOW . ", "  . PS::GREEN_BD . "STATE" . PS::YELLOW ." and " . PS::GREEN_BD ."COUNTRY" . PS::YELLOW . " values for the other party are added.\n"
            . "       - No files are renamed.\n"
            . "       - An optional " . PS::YELLOW_BD . "band" . PS::YELLOW . " argument will limit stats to only contacts made on that band.\n"
            . "\n" . PS::YELLOW_BD
            . "     e) THE \"REVIEW\" MODE:\n" . PS::YELLOW
            . "       - If the " . PS::GREEN_BD . "REVIEW" . PS::YELLOW . " argument is given, system behaves exactly as in the CHECK mode,\n"
            . "         but stats and logs for all sessions in the park are shown.\n"
            . "\n" . PS::YELLOW_BD
            . "     f) THE \"SUMMARY\" MODE:\n" . PS::YELLOW
            . "       - If the " . PS::GREEN_BD . "SUMMARY" . PS::YELLOW . " argument is given, system behaves exactly as in the REVIEW mode,\n"
            . "         but no logs are listed.\n"
            . "\n" . PS::YELLOW_BD
            . "     f) THE \"PUSH\" MODE:\n" . PS::YELLOW
            . "       - If the " . PS::GREEN_BD . "PUSH" . PS::YELLOW . " argument is given, system operates directly on either\n"
            . "         the Park Log file, or if that is absent, the " . PS::BLUE_BD . "wsjtx_log.adi" . PS::YELLOW ." file currently in use.\n"
            . "       - The logs achieved so far in the latest session are uploaded to " . PS::YELLOW_BD . "QRZ.com" . PS::YELLOW . ".\n"
            . "         Any logs sent to " . PS::YELLOW_BD . "QRZ.com" . PS::YELLOW . " have the " . PS::BLUE_BD . "TO_QRZ" . PS::YELLOW ." flag set to 'Y' to indicate these\n"
            . "         have been uploaded.\n"
            . "       - If potashell.ini file contains your Clublog credentials, logs will also go to " . PS::YELLOW_BD . "ClubLog.com" . PS::YELLOW . ".\n"
            . "         Any logs sent to " . PS::YELLOW_BD . "ClubLog.com" . PS::YELLOW . " have the " . PS::BLUE_BD . "TO_CLUBLOG" . PS::YELLOW ." flag set to 'Y' to indicate these\n"
            . "         have been uploaded.\n"
            . "       - If configured to do so, logs for the current session are used to create or append a POTA log file suitable for submitting to POTA.\n"
            . "       - No files are renamed and you won't be prompted to add a spot to " . PS::YELLOW_BD . "pota.app" . PS::YELLOW. ".\n"
            . "       - If an optional number - e.g." . PS::GREEN_BD . "50" . PS::YELLOW . " argument is given, the maximum number of logs that will be\n"
            . "         processed is capped at that number.\n"
            . "         Use a value of 100 to avoid overloading Clublog with too many requests in 5 minutes\n"
            . "       - If the optional " . PS::GREEN_BD . "ALL" . PS::YELLOW . " argument is given, all logs at the park which are not indicated\n"
            . "         as having been already pushed will be sent to " . PS::YELLOW_BD . "QRZ.com" . PS::YELLOW . "\n"
            . "\n" . PS::YELLOW_BD
            . "     g) THE \"SPOT\" MODE:\n" . PS::YELLOW
            . "       - If the " . PS::GREEN_BD . "SPOT" . PS::YELLOW . " argument is given, a 'spot' is posted to the pota.app website.\n"
            . "       - The next parameter is the frequency in KHz.\n"
            . "       - The final parameter is the intended transmission mode or \"QRT\" to close the spot.\n"
            . "       - Use quotes around the last parameter to group words together.\n"
            . "       - To safely test this feature without users responding, use " . PS::BLUE_BD . "K-TEST" . PS::YELLOW . " as the park ID.\n"
            . "         When the " . PS::BLUE_BD . "K-TEST" . PS::YELLOW . " test park is specified, the Activator callsign will be set to ABC123.\n"
            . "           e.g: " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "K-TEST " . PS::YELLOW_BD ."AA11BB22 " . PS::GREEN_BD . "SPOT "
            . PS::MAGENTA_BD . "14074 " . PS::RED_BD . "\"FT8 - Test for POTASHELL spotter mode\"\n" . PS::YELLOW
            . "\n" . PS::YELLOW_BD
            . $this->showSyntax(2)
            . "       - The system reviews ALL archived Park Log files, and produces a report on their contents.\n"
            . "\n" . PS::YELLOW_BD
            . $this->showSyntax(3)
            . "       - Detailed help is provided.\n"
            . "\n" . PS::YELLOW_BD
            . $this->showSyntax(4)
            . "       - Basic syntax is provided.\n"
            . "\n" . PS::YELLOW . str_repeat('-', 90)
            . PS::RESET ."\n";
    }

    private static function showLogs($data, $date = null, $band = null) {
        $logs =         static::dataGetLogs($data, $date, $band);
        if (!$logs) {
            return "";
        }
        $columns =      [
            ['label' => '#',        'src' => false,              'len' => 3],
            ['label' => 'DATE',     'src' => 'QSO_DATE',         'len' => 4],
            ['label' => 'UTC',      'src' => 'TIME_ON',          'len' => 3],
            ['label' => 'YOU',      'src' => 'STATION_CALLSIGN', 'len' => 3],
            ['label' => 'LOC ID',   'src' => 'PARK',             'len' => 4],
            ['label' => 'GSQ',      'src' => 'MY_GRIDSQUARE',    'len' => 3],
            ['label' => 'CALLSIGN', 'src' => 'CALL',             'len' => 8],
            ['label' => 'BAND',     'src' => 'BAND',             'len' => 4],
            ['label' => 'MODE',     'src' => 'MODECOMP',         'len' => 4],
            ['label' => 'STATE',    'src' => 'STATE',            'len' => 5],
            ['label' => 'COUNTRY',  'src' => 'COUNTRY',          'len' => 7],
            ['label' => 'GSQ',      'src' => 'GRIDSQUARE',       'len' => 3],
            ['label' => 'KM',       'src' => 'DX',               'len' => 3],
            ['label' => 'UPLOAD',   'src' => '_',                'len' => 6],
        ];
        foreach ($logs as $log) {
            foreach ($columns as &$column) {
                if (isset($log[$column['src']]) && strlen($log[$column['src']]) > $column['len']) {
                    $column['len'] = strlen($log[$column['src']]);
                }
            }
        }

        $columns[0]['len'] = strlen('' . (1 + count($logs)));
        $num = str_repeat(' ', max(2, strlen(number_format(count($logs))))) . '#';
        $header = [$num];
        $header_bd = [PS::CYAN_BD . $num . PS::GREEN];
        foreach ($columns as &$column) {
            if ($column['src'] === false) {
                continue;
            }
            $header[] = str_pad($column['label'], $column['len']);
            $header_bd[] = PS::CYAN_BD . str_pad($column['label'], $column['len']) . PS::GREEN;
        }
        $head =     implode(' | ', $header);
        $head_bd =  implode(' | ', $header_bd);
        $rows = [];
        foreach ($logs as $i => $log) {
            $row = [PS::YELLOW_BD . str_pad( '' . ($i + 1), $columns[0]['len'], ' ', STR_PAD_LEFT) . PS::GREEN];
            foreach ($columns as &$column) {
                if ($column['src'] === false) {
                    continue;
                }
                switch($column['src']) {
                    case '_':
                        $row[] = ' ' . PS::YELLOW
                            . (isset($log['TO_CLUBLOG']) ? ($log['TO_CLUBLOG'] === 'Y' ? 'C' : ' ') : ' ') . ' '
                            . (isset($log['TO_QRZ']) ?     ($log['TO_QRZ'] === 'Y' ?     'Q' : ' ') : ' ') . ' '
                            . (isset($log['TO_POTA']) ?    ($log['TO_POTA'] === 'Y' ?    'X' : ' ') : ' ') . ' '
                            . PS::GREEN;
                        break;
                    case 'DX':
                        $row[] = PS::CYAN . str_pad((isset($log[$column['src']]) ? $log[$column['src']] : ''), $column['len'], " ", STR_PAD_LEFT) . PS::GREEN;
                        break;
                    default:
                        $row[] = PS::CYAN . str_pad((isset($log[$column['src']]) ? $log[$column['src']] : ''), $column['len']) . PS::GREEN;
                        break;
                }
            }
            $rows[] = implode(' | ', $row);
        }
        return
            PS::YELLOW_BD . "LOGS:" . str_repeat(' ', strlen($head) - 74)
            . PS::CYAN_BD . "UPLOAD" . PS::GREEN . " = Uploaded logs to "
            . PS::YELLOW . "C" . PS::GREEN . "-Clublog, "
            . PS::YELLOW . "Q" . PS::GREEN . "-QRZ, "
            . PS::YELLOW . "X" . PS::GREEN . "-eXport file for session\n"
            . str_repeat('-', strlen($head) + 1) . "\n"
            . $head_bd . "\n"
            . str_repeat('-', strlen($head) + 1) . "\n"
            . " " . implode("\n ", $rows) . "\n"
            . str_repeat('-', strlen($head) + 1) . "\n"
            . "\n";
    }

    private static function showStats($data, $date = null, $band = null) {
        $logs =         static::dataCountLogs($data, $date, $band);
        $bands =        static::dataGetBands($data, $date, $band);
        $countries =    static::dataGetCountries($data, $date, $band);
        $dx =           static::dataGetBestDx($data, $date, $band);
        $states =       static::dataGetStates($data, $date, $band);
        $indent =       20;

        $header = PS::GREEN_BD . "  - "
            . ($date ?
                "Stats for last session on " . PS::CYAN_BD
                . substr($date, 0, 4) . "-" . substr($date, 4, 2) . "-" . substr($date, 6, 2)
                . PS::GREEN_BD
                :  "All time stats for park")
            . ($band ? " on " . PS::CYAN_BD . $band . PS::GREEN_BD : "")
            . ":\n";

        $stats = "    There were " . PS::CYAN_BD . $logs . PS::GREEN_BD . " distinct log" . ($logs === 1 ? '' : 's')
            . " on " . PS::CYAN_BD . count($bands) . PS::GREEN_BD . " " . (count($bands) === 1 ? "band" : "bands")
            . " from " . PS::CYAN_BD . count($countries) . PS::GREEN_BD . " " . (count($countries) === 1 ? "country" : "countries")
            . (count($states) ? " and " . PS::CYAN_BD . count($states) . PS::GREEN_BD . " state" . (count($states) === 1 ? "" : "s") : "")
            . ($logs ? " - best DX was " . PS::BLUE_BD . number_format($dx) . PS::GREEN_BD . " KM." : "")
            ."\n";

        $bands =        PS::formatLineWrap(PS::MAXLEN, $indent, $bands, true);
        $countries =    PS::formatLineWrap(PS::MAXLEN, $indent, $countries);
        $states =       PS::formatLineWrap(PS::MAXLEN, $indent, $states);

        return
              $header
            . $stats
            . (count($bands) ?
                  "\n      - " . (count($bands) === 1 ?     "Band: " : "Bands:") . "      "
                  . PS::YELLOW_BD . implode(PS::GREEN_BD . ', ' . PS::YELLOW_BD, $bands) . PS::GREEN_BD . "\n"
                  : ""
              )
            . (count($countries) ?
                  "\n      - " . (count($countries) === 1 ? "Country:  " : "Countries:") . "  "
                . PS::YELLOW_BD . implode(PS::GREEN_BD . ', ' . PS::YELLOW_BD, $countries) . PS::GREEN_BD . "\n"
                . (count($states) ?
                      "\n      - " . (count($states) === 1 ? "State: ": "States:") . "     "
                    . PS::YELLOW_BD . implode(PS::GREEN_BD . ', ' . PS::YELLOW_BD, $states) . PS::GREEN_BD . "\n"
                : ""
                )
                . "\n"
                : ""
            );
    }

    private function showSyntax($step = false) {
        switch ($step) {
            case 1:
                return PS::YELLOW_BD
                    . "  1. " . PS::WHITE_BD . "potashell\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "CHECK\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "CHECK " . PS::YELLOW_BD . "160m\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "REVIEW\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "REVIEW " . PS::YELLOW_BD . "160m\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "SUMMARY\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "SUMMARY " . PS::YELLOW_BD . "160m\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "PUSH\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "PUSH 50\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "PUSH ALL\n"
                    . "     " . PS::WHITE_BD . "potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82 " . PS::GREEN_BD . "SPOT " . PS::YELLOW_BD . "14074 " . PS::RED_BD . "\"FT8 - QRP 4w\"\n"
                    ;
            case 2:
                return PS::YELLOW_BD . "  2. " . PS::WHITE_BD . "potashell " . PS::GREEN_BD . "AUDIT " . PS::YELLOW . "\n";
            case 3:
                return PS::YELLOW_BD . "  3. " . PS::WHITE_BD . "potashell " . PS::GREEN_BD . "HELP " . PS::YELLOW . "\n";
            case 4:
                return PS::YELLOW_BD . "  4. " . PS::WHITE_BD . "potashell " . PS::GREEN_BD . "SYNTAX " . PS::YELLOW . "\n";
            default:
                return
                    $this->showSyntax(1) . "\n"
                    . $this->showSyntax(2) . "\n"
                    . $this->showSyntax(3) . "\n"
                    . $this->showSyntax(4) . PS::RESET;
        }
    }

    private function wsjtxUpdateInifile($call, $gsq) {
        $filename = rtrim($this->config['WSJTX']['log_directory'],'\\/') . DIRECTORY_SEPARATOR . 'WSJT-X.ini';
        if (!$wsjtxIniConfig = PS::parse_ini($filename, true)) {
            print PS::RED_BD . "ERROR:\n  Unable to parse {$filename} file.\n" . PS::RESET . "\n";
            die(0);
        };
        if (!isset($wsjtxIniConfig['Configuration']['MyCall']) || !isset($wsjtxIniConfig['Configuration']['MyGrid'])) {
            print PS::RED_BD . "ERROR:\n  Unable to read values for "
                . PS::CYAN_BD . "MyCall" . PS::RED_BD . " and "
                . PS::CYAN_BD . "MyGrid" . PS::RED_BD . " in "
                . PS::CYAN_BD . "Configuration" . PS::RED_BD . " section of\n  "
                . PS::BLUE_BD . $filename . "\n" . PS::RESET . "\n";
            die(0);
        };
        $oldMyCall =    'MyCall=' . $wsjtxIniConfig['Configuration']['MyCall'];
        $newMyCall =    'MyCall=' . $call;
        $oldMyGrid =    'MyGrid=' . $wsjtxIniConfig['Configuration']['MyGrid'];
        $newMyGrid =    'MyGrid=' . substr($gsq, 0, 6);
        $oldComment =   'LogComments=' . $wsjtxIniConfig['LogQSO']['LogComments'];
        $newComment =   'LogComments=';

        if ($oldMyCall === $newMyCall && $oldMyGrid === $newMyGrid && $oldComment === $newComment) {
            return;
        }

        $str = file_get_contents($filename);
        $str = str_replace($oldMyCall, $newMyCall, $str);   // Set My Callsign
        $str = str_replace($oldMyGrid, $newMyGrid, $str);   // Set my GSQ
        $str = str_replace($oldComment, $newComment, $str); // Fix issue with old comments persisting
        file_put_contents($filename, $str);
    }

    private static function parse_ini ( $filepath ) {
        // Thanks to goulven.ch AT gmail DOT com
        // https://www.php.net/manual/en/function.parse-ini-file.php#78815
        $ini = file($filepath);
        if (count($ini) === 0) {
            return [];
        }
        $sections = [];
        $values =   [];
        $globals =  [];
        $i = 0;
        foreach($ini as $line){
            $line = trim($line);
            // Comments
            if ($line === '' || $line[0] === ';') {
                continue;
            }
            // Sections
            if ($line[0] === '[') {
                $sections[] = substr($line, 1, -1);
                $i++;
                continue;
            }
            // Key-value pair
            list($key, $value) = explode('=', $line, 2);
            $key =      trim($key);
            $value =    trim($value);
            if ($i === 0) {
                // Array values
                if (substr($line, -1, 2) === '[]') {
                    $globals[$key][] = $value;
                } else {
                    $globals[$key] = $value;
                }
            } else {
                // Array values
                if (substr( $line, -1, 2 ) === '[]') {
                    $values[$i - 1][$key][] = $value;
                } else {
                    $values[$i - 1][$key] = $value;
                }
            }
        }
        for($j=0; $j<$i; $j++) {
            $result[$sections[$j]] = $values[$j];
        }
        return $result + $globals;
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
    var_dump($var);
    exit;
}

new PS();
