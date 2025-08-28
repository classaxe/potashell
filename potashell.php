#!/usr/bin/php
<?php
/*****************************************
 * POTA SHELL    Copyright (C) 2024-2005 *
 * Authors:        Martin Francis VA3PHP *
 *                          James Fraser *
 * --------------------------------------*
 * https://github.com/classaxe/potashell *
 *****************************************/
class PS {
    const ACTIVATION_LOGS_POTA = 10;
    const ACTIVATION_LOGS_WWFF = 44;
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
        'PROGRAM',
        'LOC_ID',
        'ALT_PROGRAM',
        'ALT_LOC_ID',
        'MY_CITY',
        'MY_GRIDSQUARE',
        'TX_PWR',
        'COMMENT',
        'DX',
        'TO_CLUBLOG',
        'TO_QRZ',
        'TO_POTA',
        'TO_WWFF'
    ];
    const MAXLEN = 180;

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
    const INVERSE =     "\e[7m";
    const NORMAL =      "\e[27m";
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

    const TEXTSUBS = [
        '<r>' =>        PS::RED,
        '<R>' =>        PS::RED_BD,
        '<g>' =>        PS::GREEN,
        '<G>' =>        PS::GREEN_BD,
        '<y>' =>        PS::YELLOW,
        '<Y>' =>        PS::YELLOW_BD,
        '<b>' =>        PS::BLUE,
        '<B>' =>        PS::BLUE_BD,
        '<m>' =>        PS::MAGENTA,
        '<M>' =>        PS::MAGENTA_BD,
        '<c>' =>        PS::CYAN,
        '<C>' =>        PS::CYAN_BD,
        '<w>' =>        PS::WHITE,
        '<W>' =>        PS::WHITE_BD,
        '<CLS>' =>      PS::CLS,
        '<INVERSE>' =>  PS::INVERSE,
        '<NORMAL>' =>   PS::NORMAL,
        '<RESET>' =>    PS::RESET,
        '<RESP_Y>' =>   PS::RESPONSE_Y,
        '<RESP_N>' =>   PS::RESPONSE_N,

        '[C]' =>        PS::YELLOW . "C" . PS::GREEN_BD,
        '[Q]' =>        PS::YELLOW . "Q" . PS::GREEN_BD,
        '[P]' =>        PS::YELLOW . "P" . PS::GREEN_BD,
        '[W]' =>        PS::YELLOW . "W" . PS::GREEN_BD,
        '[POTA_REQ]' => PS::YELLOW_BD . PS::ACTIVATION_LOGS_POTA . PS::GREEN_BD,
        '[WWFF_REQ]' => PS::YELLOW_BD . PS::ACTIVATION_LOGS_WWFF . PS::GREEN_BD,
    ];

    const COLUMNS_AUDIT = [
            'QTH ID' =>     ['color' => '<B>',  'len' => 9,    'pad' => 'r',    'source' => 'qthId',        'help' => ''],
            'ALT ID' =>     ['color' => '<Y>',  'len' => 9,    'pad' => 'r',    'source' => 'qthIdAlt',     'help' => ''],
            'MY_GRID' =>    ['color' => '<C>',  'len' => 10,   'pad' => 'r',    'source' => 'myGsqFmt',     'help' => ''],
            'MY_CALL' =>    ['color' => '<M>',  'len' => 10,   'pad' => 'r',    'source' => 'myCallsign',   'help' => ''],
            'LATEST LOG' => ['color' => '<W>',  'len' => 10,   'pad' => 'r',    'source' => 'dateFmt',      'help' => ''],
            '#LT' =>        ['color' => '<Y>',  'len' => 3,    'pad' => 'r',    'source' => 'count_LT',     'help' => 'Logs in Total - [WWFF_REQ] required for WWFF activation'],
            '#ST' =>        ['color' => '',     'len' => 3,    'pad' => 'r',    'source' => 'count_ST',     'help' => 'Sessions in Total'],
            '#SA' =>        ['color' => '',     'len' => 3,    'pad' => 'r',    'source' => 'count_AT',     'help' => 'Successful Activations'],
            '#FA' =>        ['color' => '',     'len' => 3,    'pad' => 'r',    'source' => 'count_FA',     'help' => 'Failed Activations'],
            '#MG' =>        ['color' => '',     'len' => 3,    'pad' => 'r',    'source' => 'count_MG',     'help' => 'Missing Grid Squares'],
            '#LS' =>        ['color' => '<Y>',  'len' => 3,    'pad' => 'r',    'source' => 'count_LS',     'help' => 'Logs in Latest Session - [POTA_REQ] required for POTA activation'],
            '#B' =>         ['color' => '',     'len' => 2,    'pad' => 'r',    'source' => 'count_BT',     'help' => 'Number of bands'],
            'DX KM' =>      ['color' => '<c>',  'len' => 6,    'pad' => 'l',    'source' => 'bestDx',       'help' => 'Best Distance in KM'],
            'UPLOAD' =>     ['color' => '<y>',  'len' => 7,    'pad' => 'r',    'source' => 'uploadStatus', 'help' => 'Uploaded logs to [C]-Clublog, [Q]-QRZ, [P]-POTA  session export file, [W]-WWFF session export file'],
            'Park Name' =>  ['color' => '<B>',  'len' => 10,   'pad' => 'r',    'source' => 'myCity',       'help' => ''],
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
    private $modeExport;
    private $modeHelp;
    private $modeInvalid;
    private $modeMigrate;
    private $modePush;
    private $modePushQty;
    private $modeReview;
    private $modeSpot;
    private $modeSummary;
    private $modeSyntax;
    private $HTTPcontext;
    private $locationName;
    private $locationNameAbbr;
    private $lookupProgram;
    private $lookupLocId;
    private $lookupAltProgram;
    private $lookupAltLocId;
    private $pathAdifLocal;
    private $php;
    private $sessionAdifDirectory;
    private $sortBy;
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
            prt($this->processAudit());
            return;
        }
        if ($this->modeHelp) {
            prt($this->showHelp());
            return;
        }
        if ($this->modeExport) {
            $this->processExport();
            return;
        }
        if ($this->modeMigrate) {
            $this->processMigrate();
            return;
        }
        if ($this->modeSyntax) {
            prt($this->showSyntax());
            return;
        }
        if ($this->inputGSQ === null) {
            prt($this->showSyntax());
            $this->argsGetInput();
        }
        // submodes check, review, spot, summary and push are all supported
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
        $this->modeExport = false;
        $this->modeHelp = false;
        $this->modeMigrate = false;
        $this->modePush = false;
        $this->modePushQty = false;
        $this->modeReview = false;
        $this->modeSpot = false;
        $this->modeSummary = false;
        $this->modeSyntax = false;
        if ($arg1 && strtoupper($arg1) === 'AUDIT') {
            $this->modeAudit = true;
            if ($arg2 && in_array($arg2, array_keys(PS::COLUMNS_AUDIT))) {
                $this->sortBy = $arg2;
            }
            return;
        }
        if ($arg1 && strtoupper($arg1) === 'EXPORT') {
            $this->modeExport = true;
            if ($arg2) {
                $this->sortBy = $arg2;
            }
            return;
        }
        if ($arg1 && strtoupper($arg1) === 'HELP') {
            $this->modeHelp = true;
            return;
        }
        if ($arg1 && strtoupper($arg1) === 'MIGRATE') {
            $this->modeMigrate = true;
            return;
        }
        if ($arg1 && strtoupper($arg1) === 'SYNTAX') {
            $this->modeSyntax = true;
            return;
        }
        $this->inputQthId =     $arg1;
        $this->inputGSQ =       $arg2;
        $this->mode =           $arg3 ? $arg3 : '';
        switch(strtoupper($this->mode)) {
            case '':
                break;
            case 'CHECK':
                $this->modeCheck = true;
                break;
            case 'PUSH':
                $this->modePush = true;
                break;
            case 'REVIEW':
                $this->modeReview = true;
                break;
            case 'SPOT':
                $this->modeSpot = true;
                break;
            case 'SUMMARY':
                $this->modeSummary = true;
                break;
            default:
                $this->modeInvalid = true;
                break;
        }
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
        prt("\n<Y>ARGUMENTS:\n");
        if ($this->inputQthId === null) {
            prt("<G>  - Please provide Location ID:  <B>");
            $fin = fopen("php://stdin","r");
            $this->inputQthId = trim(fgets($fin));
        } else {
            prt("<G>  - Supplied Location ID:        <B>{$this->inputQthId}\n");
        }
        if ($this->inputGSQ === null) {
            prt("<G>  - Please provide 8/10-char GSQ: <C>");
            $fin = fopen("php://stdin","r");
            $this->inputGSQ = trim(fgets($fin));
        } else {
            prt("<G>  - Supplied Gridsquare:          <C>{$this->inputGSQ}\n");
        }
        $this->locationName = "POTA: " . $this->inputQthId;
        prt("\n");
    }

    private function argsLoadIni() {
        $filename = 'potashell.ini';
        $example =  'potashell.ini.example';
        if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . $filename)) {
            $this->showHeader();
            $contents = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $example);
            $contents = str_replace("; This is a sample configuration file for potashell\r\n", '', $contents);
            $contents = str_replace("; Copy this file to potashell.ini, and modify it to suit your own needs\r\n", '', $contents);
            prt("<R>ERROR:\n  The <B>{$filename} <R>Configuration file was missing.\n");
            if (file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . $filename, $contents)) {
                prt("  <R>It has now been created.\n  Please edit the new <B>{$filename} <R>file, and supply your own values.\n");
            }
            prt("<RESET>\n");
            die(0);
        };
        if (!$this->config = @parse_ini_file($filename, true)) {
            $this->showHeader();
            prt("<R>ERROR:\n  Unable to parse <B>{$filename} <R>file.<RESET>\n\n");
            die(0);
        };
        $this->pathAdifLocal = rtrim($this->config['WSJTX']['log_directory'],'\\/') . DIRECTORY_SEPARATOR;
        if (!file_exists($this->pathAdifLocal)) {
            $this->showHeader();
            prt(
                "<R>ERROR:\n"
                . "  The specified <Y>[WSJTX] <C>log_directory <R>specified in <B>{$filename} <R>doesn't exist.\n"
                . "  Please edit <B>{$filename} <R>and set the correct path to your WSJT-X log files.<RESET>\n\n"
            );
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
                $url = "https://logs.classaxe.com/custom/endpoint.php?response=OK";
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
                prt("<R>ERROR:\n  Unable to connect to Clublog.com for log uploads:<B>" . $e->getMessage() . "<RESET>\n\n");
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
        return "<G>  - Attempted to upload <C>{$stats['ATTEMPTED']} <G>new Logs to <Y>ClubLog.com<G>\n"
            . ($stats['INSERTED'] ?        "     * Inserted:       {$stats['INSERTED']}\n" : "")
            . ($stats['UPDATED'] ?         "     * Updated:        {$stats['UPDATED']}\n" : "")
            . ($stats['DUPLICATE'] ?    "<R>     * Duplicates:     {$stats['DUPLICATE']}\n" : "")
            . ($stats['ERROR'] ?        "<R>     * Errors:         {$stats['ERROR']}\n" : "")
            . ($stats['LAST_ERROR'] ?   "<R>     * Last Error:     {$stats['LAST_ERROR']}\n" : "");
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
            $record['PROGRAM'] =        $this->lookupProgram;
            $record['LOC_ID'] =         $this->lookupLocId;
            $record['ALT_PROGRAM'] =    $this->lookupAltProgram;
            $record['ALT_LOC_ID'] =     $this->lookupAltLocId;
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
                $item = "\n" . str_repeat(' ', $indent) . "$item<C>$qty";
                $lineLen = $indent;
            } else {
                $item .= "<C>$qty";
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

    private function getLocationDetails($qthID) {
        if (!empty($this->customLocations)) {
            foreach ($this->customLocations as $location) {
                $locationBits = explode('|', $location);
                if ($locationBits[0] === $qthID) {
                    return [
                        'abbr' =>           $locationBits[1],
                        'name' =>           $qthID,
                        'loc_id' =>         $qthID,
                        'program' =>        'CUSTOM',
                        'alt_program' =>    '',
                        'alt_loc_id' =>     '',
                    ];
                }
            }
        }

        return $this->parkGetInfo($qthID);
    }

    private function internetCheck() {
        if (!@fsockopen('www.example.com', 80)) {
            prt("<R>WARNING:\n"
                . "  - You have no internet connection.\n"
                . "  - Automatic gridquare and park name lookups will not work.\n"
                . "  - QRZ uploads are not possible at this time.\n\n<RESET>"
            );
            return;
        }
        $this->hasInternet = true;
    }

    private function parkGetInfo($qthId) {
        if (!$this->hasInternet) {
            $prefix = explode('-', $qthId)[0];
            switch($prefix) {
                case 'CA':
                case 'GB':
                case 'GD':
                case 'IE':
                case 'IM':
                case 'MX':
                case 'PM':
                case 'US':
                    $program = 'POTA';
                    break;

                case 'XX':
                    $program = "CUSTOM";
                    break;

                case 'GFF':
                case 'GDFF':
                case 'GIFF':
                case 'VEFF':
                    $program = "WWFF";
                    break;

                default:
                    $program = $prefix;
            }
            return [
                'name' =>           $qthId,
                'abbr' =>           $program . ': ' . $qthId,
                'loc_id' =>         $qthId,
                'program' =>        $program,
                'alt_program' =>    '',
                'alt_loc_id' =>     '',
            ];
        }

        $url = "https://logs.classaxe.com/park/" . trim($qthId);
        $data = @file_get_contents($url, false, $this->HTTPcontext);
        if (!$data) {
            return false;
        }
        $data = json_decode($data);
        $parkName = trim($data->name);
        $parkNameAbbr = strtr(
            $data->program . ': ' . $qthId . ($data->alt_program ? ' / ' . $data->alt_program . ': ' . $data->alt_ref : '') . ' | ' . $parkName,
            PS::NAME_SUBS
        );
        return [
            'abbr' =>           $parkNameAbbr,
            'name' =>           $parkName,
            'loc_id' =>         $qthId,
            'program' =>        $data->program,
            'alt_program' =>    $data->alt_program,
            'alt_loc_id' =>     $data->alt_ref,
        ];
    }

    private function parkSaveLogs(&$data, $force = false) {
        if (!$this->sessionAdifDirectory) {
            return '';
        }
        if (!isset($data[0])) {
            return '';
        }
        $potaLocId =    ($data[0]['PROGRAM'] === 'POTA' ? $data[0]['LOC_ID'] : ($data[0]['ALT_PROGRAM'] === 'POTA' ? $data[0]['ALT_LOC_ID'] : ""));
        $wwffLocId =    ($data[0]['PROGRAM'] === 'WWFF' ? $data[0]['LOC_ID'] : ($data[0]['ALT_PROGRAM'] === 'WWFF' ? $data[0]['ALT_LOC_ID'] : ""));
        $callsign =     str_replace('/', '-', $data[0]['STATION_CALLSIGN']);
        $exportPota =   [];
        $exportWwff =   [];
        foreach ($data as &$record) {
            if (!isset($record['TO_WWFF'])) {
                prt("<R>No TO_WWFF column in {$data[0]['LOC_ID']}\n");
                break;
            }
            if ($record['PROGRAM'] === 'POTA' || $record['ALT_PROGRAM'] === 'POTA') {
                if ($record['TO_POTA'] !== 'Y' || $force) {
                    $exportPota[] = $record;
                    $record['TO_POTA'] = 'Y';
                    $lastDatePota = $record['QSO_DATE'];
                }
            }
            if ($record['PROGRAM'] === 'WWFF' || $record['ALT_PROGRAM'] === 'WWFF') {
                if ($record['TO_WWFF'] !== 'Y' || $force) {
                    $exportWwff[] = $record;
                    $record['TO_WWFF'] = 'Y';
                    $lastDateWwff = $record['QSO_DATE'];
                }
            }
        }

        if (!empty($exportPota)) {
            // Named according to https://www.veff.ca/rules - 6.6 - "callsign@reference YYYYMMDD"
            $filenamePota = $this->sessionAdifDirectory . DIRECTORY_SEPARATOR . 'POTA_' . $callsign . '@' . $potaLocId . '_' . $lastDatePota . '.adi';
            if (file_exists($filenamePota)) {
                $adif =     new adif($filenamePota);
                $complete = array_merge($adif->parser(), $exportPota);
            } else {
                $adif =     new adif('');
                $complete = $exportPota;
            }
            $adif =     $adif->toAdif($complete, $this->version, false, true);
            file_put_contents($filenamePota, $adif);
        }

        if (!empty($exportWwff)) {
            // Named according to https://www.veff.ca/rules - 6.6 - "callsign@reference YYYYMMDD"
            $filenameWwff = $this->sessionAdifDirectory . DIRECTORY_SEPARATOR . 'WWFF_' . $callsign . '@' . $wwffLocId . '_' . $lastDateWwff . '.adi';
            if (file_exists($filenameWwff)) {
                $adif =     new adif($filenameWwff);
                $complete = array_merge($adif->parser(), $exportWwff);
            } else {
                $adif =     new adif('');
                $complete = $exportWwff;
            }
            $adif =     $adif->toAdif($complete, $this->version, false, true);
            file_put_contents($filenameWwff, $adif);
        }
        return "<G>"
            . (count($exportPota) ?
                "  - Inserted <C>" . str_pad(count($exportPota), 3, ' ', STR_PAD_LEFT)
                . " <G>new POTA " . (count($exportPota) === 1 ? "log" : "logs")
                . " in <Y>Session export file <C>{$filenamePota}<G>\n"
            : "")
            . (count($exportWwff) ?
                "  - Inserted <C>" . str_pad(count($exportWwff), 3, ' ', STR_PAD_LEFT)
                . " <G>new WWFF " . (count($exportWwff) === 1 ? "log" : "logs")
            . " in <Y>Session export file <C>{$filenameWwff}<G>\n"
            : "");
    }


    private function phpCheck() {
        $libs = [
            'curl',
            'mbstring',
            'openssl'
        ];
        $msg = "\n<R>ERROR:\n  <G>PHP <Y>%s <G>extension is not available.\n"
            . "  <G>PHP version <Y>%s<G>, php.ini file: <Y>%s<RESET>\n\n";
        foreach ($libs as $lib) {
            if (!extension_loaded($lib)) {
                prt(sprintf($msg, $lib, phpversion(),(php_ini_loaded_file() ? php_ini_loaded_file() : "None")));
                die(0);
            }
        }
    }

    private function potaPublishSpot() {
        $url = 'https://api.pota.app/spot/';
        // $url = 'https://logs.classaxe.com/custom/endpoint.php';
        $activator = ($this->inputQthId === 'K-TEST' ? 'ABC123' : $this->qrzApiCallsign);
        $data = json_encode([
            'activator' =>  $activator,
            'spotter' =>    $this->qrzApiCallsign,
            'frequency' =>  $this->spotKhz,
            'reference' =>  $this->lookupLocId,
            'source' =>     'Potashell ' . $this->version,
            'comments' =>   $this->spotComment . ($this->lookupAltLocId ? ' | ' . $this->lookupLocId . ' / ' . $this->lookupAltLocId : '')
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

    private function process() {
        prt("<Y>STATUS:\n");
        if (!$this->inputQthId || !$this->inputGSQ) {
            prt(
                "<R>  - One or more required parameters are missing.\n"
                . "    Unable to continue.\n<RESET>"
            );
            die(0);
        }
        if (!$lookup = $this->getLocationDetails($this->inputQthId)) {
            prt(
                "<R>  - Unable to get name for park <B>{$this->inputQthId}<R>\n"
                . "    Unable to continue.\n<RESET>"
            );
            die(0);
        }
        $this->locationName =       $lookup['name'];
        $this->locationNameAbbr =   $lookup['abbr'];
        $this->lookupProgram =      $lookup['program'];
        $this->lookupLocId =        $lookup['loc_id'];
        $this->lookupAltProgram =   $lookup['alt_program'];
        $this->lookupAltLocId =     $lookup['alt_loc_id'];
        prt("<G>  - Command:          <W>potashell <C>{$this->inputQthId} <B>{$this->inputGSQ} "
            . "<G>{$this->mode} {$this->modePushQty} <M>{$this->argCheckBand}\n"
            . "<G>  - Location Id:      <Y>{$this->lookupProgram }: {$this->lookupLocId}"
            . ($this->lookupAltProgram ?
                " <G>/<Y> {$this->lookupAltProgram}: {$this->lookupAltLocId}"
                : ""
            )
            . "\n"
            . "<G>  - Identified QTH:   <C>{$this->locationName}\n"
            . "<G>  - Name for Log:     <C>{$this->locationNameAbbr}\n\n"
        );
        $this->fileAdifPark =   "wsjtx_log_{$this->inputQthId}.adi";
        $this->fileAdifWsjtx =  "wsjtx_log.adi";

        $fileAdifParkExists =   file_exists($this->pathAdifLocal . $this->fileAdifPark);
        $fileAdifWsjtxExists =  file_exists($this->pathAdifLocal . $this->fileAdifWsjtx);

        if (($fileAdifParkExists || $fileAdifWsjtxExists) && $this->modeInvalid) {
            prt("<R>ERROR:\n  Unknown mode <G>{$this->mode}<RESET>\n");
            return;
        }

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

        if ($this->modeSpot && $this->lookupProgram === 'POTA') {
            $this->processParkSpot();
            return;
        }

        if ($fileAdifParkExists && $fileAdifWsjtxExists) {
            $adif1 = new adif($this->pathAdifLocal . $this->fileAdifPark);
            $data1 = $adif1->parser();
            $adif2 = new adif($this->pathAdifLocal . $this->fileAdifWsjtx);
            $data2 = $adif2->parser();
            prt("<R>  - Both <B>{$this->fileAdifPark} <R>and <B>{$this->fileAdifWsjtx} <R>exist.\n"
                . "    File <B>{$this->fileAdifPark} <R>contains <M>" . count($data1) . " <R>entries\n"
                . "    File <B>{$this->fileAdifWsjtx} <R>contains <M>" . count($data2) . " <R>entries\n"
                . "\n"
                . "    Manual user intervention is required to prevent data loss.\n<RESET>"
            );
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
        $count = [
            'POTA' => 0,
            'WWFF' => 0,
            'DUAL' => 0,
            'CUSTOM' => 0,
        ];
        $logbooks = [];

        $files = glob($this->pathAdifLocal . "wsjtx_log_*-*.adi");
        if (!$files) {
            return "<Y>\nRESULT:\n  <G>No log files found.<RESET>\n";
        }

        foreach ($files as $i => $file) {
            if (!is_file($file)) {
                continue;
            }
            if ($i > 4) {
                continue;   // For development testing
            }
            $adif =     new adif($file);
            $data =     $adif->parser();
            $logbook =  new Logbook($data);
//            if (static::dataCountUploadType($data, 'TO_WWFF')) {
//                foreach ($data as &$entry) {
//                    $entry['to_WWFF'] = '';
//                }
//                $adif =     $adif->toAdif($data, $this->version, false, true);
//                file_put_contents($file, $adif);
//            }
            $logbooks[] = $logbook;
            $count[$logbook->program]++;
        }
        $cols = [];
        foreach (PS::COLUMNS_AUDIT as $k => $v) {
            $col = str_pad($k, $v['len'], ' ', $v['pad'] === 'l' ? STR_PAD_LEFT : STR_PAD_RIGHT);
            if ($this->sortBy === $k) {
                $col = "<INVERSE>{$col}<NORMAL>";
            }
            $cols[] = $col;
        }
        $cols = implode(' <G>|<C> ', $cols);
        $stats = [];
        foreach($count as $key => $value) {
            if ($value) {
                $stats[] = "<C>{$key}: <Y>{$value}";
            }
        }

        $out = "<Y>STATUS:\n"
            . "  <G>Performing Audit on all location Log files in <B>{$this->pathAdifLocal}\n"
            . ($this->sortBy ? "  <G>Results sorted by <C><INVERSE> {$this->sortBy} <NORMAL>\n" : "")
            . "\n"
            . "<Y>KEY:\n";
        foreach (PS::COLUMNS_AUDIT as $k => $v) {
            if (!$v['help']) {
                continue;
            }
            $out .= "  <C>" . str_pad($k . "<G> = ", 16, ' ') . $v['help'] . "\n";
        }
        $out .=
            "\n<Y>RESULT:\n<G><--->\n"
            . "<C>" . $cols . "<G>\n"
            . "<--->\n";

        if ($this->sortBy) {
            usort($logbooks, function($a, $b) {
                if ($a->{PS::COLUMNS_AUDIT[$this->sortBy]['source']} === $b->{PS::COLUMNS_AUDIT[$this->sortBy]['source']}) {
                    return 0;
                }
                return ($a->{PS::COLUMNS_AUDIT[$this->sortBy]['source']} < $b->{PS::COLUMNS_AUDIT[$this->sortBy]['source']}) ? -1 : 1;
            });
        }
        foreach ($logbooks as $l) {
            $line = [];
            // d($l);
            foreach (PS::COLUMNS_AUDIT as $k => $v) {
                $color = $v['color'];
                $value = $l->{$v['source']};
                switch ($k) {
                    case 'MY_GRID':
                        if (count($l->myGsqs)!==1) {
                            $color = '<R>';
                        }
                        break;
                    case '#LT':
                        if (in_array($l->program,['WWFF', 'DUAL']) && $l->count_LT < PS::ACTIVATION_LOGS_WWFF){
                            $color = '<R>';
                        }
                        break;
                    case '#LS':
                        if (in_array($l->program, ['POTA', 'DUAL']) && $l->count_LS < PS::ACTIVATION_LOGS_POTA) {
                            $color = '<R>';
                        }
                        break;
                    case 'DX KM':
                        $value = number_format($value);
                        break;
                }
                $line[] = $color . str_pad($value, $v['len'], ' ', $v['pad'] === 'l' ? STR_PAD_LEFT : STR_PAD_RIGHT);
            }
            $out .= implode(" <G>| ", $line) . "\n";
        }
        $out .= "<G><--->\n" . ($stats ? "Park Stats: " . implode('<G>, ', $stats) . "\n" : '') . "<RESET>\n";
        return $out;
    }

    private function processExport() {
        $force = false;  // Set to true to export everything, regardless of previous export status

        prt(
            "<Y>STATUS:\n  <G>Performing Export to POTA and WWFF files for all location Log files in <B>{$this->pathAdifLocal}\n"
        );
        $files = glob($this->pathAdifLocal . "wsjtx_log_??-*.adi");
        if (!$files) {
            prt("\n<Y>RESULT:\n<G>No log files found.<RESET>\n");
            return;
        }

        foreach ($files as $i => $file) {
//            print $file ."\n";
            if (!is_file($file)) {
                continue;
            }
            if ($i > 4) {
                // continue;   // For development testing
            }
            $adif = new adif($file);
            $data = $adif->parser();
            prt($this->parkSaveLogs($data, $force));
            $adif = $adif->toAdif($data, $this->version, false, true);
            file_put_contents($file, $adif);
        }
        prt("\n<Y>DONE\n<RESET>");
    }

    private function processMigrate()
    {
        prt("<Y>STATUS:\n<G>Performing Migration on all WWJT-X Log files requiring upgrade in <B>{$this->pathAdifLocal}\n");

        $files = glob($this->pathAdifLocal . "wsjtx_log_??-*.adi");
        if (!$files) {
            prt("\n<Y>RESULT:\n<G>No log files found.<RESET>\n");
            return;
        }
        prt("\n");
        $count = 0;
        foreach ($files as $i => $file) {
            if (!is_file($file)) {
                continue;
            }
            if ($i + 1 > 4) {
                // continue;    // For development testing
            }
            $count++;
            $fn = basename($file);
            $qthId = explode('.', explode('_', $fn)[2])[0];
            $lookup = $this->getLocationDetails($qthId);
            if (!$lookup) {
                prt("  <B>" . str_pad($count, 3, ' ', STR_PAD_LEFT) . ". "
                    . "<Y>{$fn} <R>NOT migrated - park data not found\n");
                continue;
            }

            $adif = new adif($file);
            $data = $adif->parser();
            if (isset($data[0]['PROGRAM'])) {
                prt("  <B>" . str_pad($count, 3, ' ', STR_PAD_LEFT) . ". "
                    ."<Y>{$fn} <G>already migrated\n"
                );
                continue;
            }
            foreach ($data as &$entry) {
                $entry['LOC_ID'] = $lookup['loc_id'];
                $entry['PROGRAM'] = $lookup['program'];
                $entry['ALT_LOC_ID'] = $lookup['alt_loc_id'];
                $entry['ALT_PROGRAM'] = $lookup['alt_program'];
            }
            $data = $this->dataSetColumnOrder($data);
            $adif =     $adif->toAdif($data, $this->version, false, true);
            file_put_contents($file, $adif);
            prt("  <B>" . str_pad($count, 3, ' ', STR_PAD_LEFT) . ". <Y>{$fn} <G>migrated\n");
        }
        prt("<RESET>");
    }


    private function processParkArchiving() {
        $adif =     new adif($this->pathAdifLocal . $this->fileAdifWsjtx);
        $data =     $adif->parser();
        $result =   $this->dataFix($data);
        $data =     $result['data'];
        $dates =    Logbook::dataGetDates($data);
        $date =     end($dates);
        $logs =     $this->dataCountLogs($data, $date);
        $MGs1 =     $this->dataCountMissingGsq($data);
        $locs =     $this->dataGetLocations($data);

        prt(static::showStats($data, $date)
            . static::showLogs($data, $date)
            . "<Y>PENDING OPERATION:\n<G>"
            . "  - Archive log file <B>{$this->fileAdifWsjtx} <G>to <B>{$this->fileAdifPark}<G>\n"
        );

        if (count($locs) > 1) {
            prt("<R>\nERROR:\n  * There are <Y>" . count($locs) . " <R>named log locations contained within this one file:\n"
                . "<C>    - " . implode("\n<R>    - <C>", $locs) . "\n<R>"
                . "  * Manual intervention is required.\n"
                . "  * The operation has been cancelled.\n"
                . "<RESET>"
            );
            return;
        }
        prt(($this->qrzApiKey ?           "  - Upload park log to <Y>Clublog.org\n<G>" : "")
            . ($this->clublogCheck() ?      "  - Upload park log to <Y>QRZ.com\n<G>" : "")
            . ($this->sessionAdifDirectory ?"  - Save <B>Session log file<G>\n" : "")
            . "\n"
            . (
                ($this->lookupProgram === 'POTA' && $logs < PS::ACTIVATION_LOGS_POTA) ||
                ($this->lookupProgram === 'WWFF' && $logs < PS::ACTIVATION_LOGS_WWFF)
                ? "<R>WARNING:\n    There are insufficient logs for successful activation.\n\n"
                : ""
            )
        );

        if (isset($locs[0]) && trim(substr($locs[0], 0, 14)) !== trim(substr($this->locationNameAbbr, 0, 14))) {
            prt("<R>ERROR:\n"
                . "  * The log contains reports made at      <B>{$locs[0]}\n<R>"
                . "  * You indicate that your logs were from <B>{$this->locationNameAbbr}\n<R>"
                . "  * The operation has been cancelled.\n<RESET>"
            );
            return;
        }

        prt("<Y>CHOICE:\n<G>    Proceed with operation? (Y/N) ");

        $fin = fopen("php://stdin","r");
        $response = strToUpper(trim(fgets($fin)));
        prt("\n<Y>RESULT:\n<G>");
        if (strtoupper($response) !== 'Y') {
            prt("    Operation cancelled.\n<RESET>");
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
            $resPota = $this->parkSaveLogs($data);
        }
        $adif =     $adif->toAdif($data, $this->version, false, true);
        file_put_contents($filename, $adif);
        prt("  - Archived log file <B>{$this->fileAdifWsjtx} <G>to <B>{$this->fileAdifPark}<G>.\n"
            . "  - Updated <M>MY_GRIDSQUARE <G>values     to <C>{$this->inputGSQ}<G>.\n"
            . "  - Added <M>MY_CITY <G>and set all values to <CY>{$this->locationNameAbbr}<G>.\n"
            . (!empty($this->qrzSession) && $status['GRIDSQUARE']['missing'] ? "  - Obtained <C>"
                . ($status['GRIDSQUARE']['fixed'] ?
                    ($status['GRIDSQUARE']['missing'] - $status['GRIDSQUARE']['fixed']) . " of " . $status['GRIDSQUARE']['missing']
                :
                    $status['GRIDSQUARE']['missing']
                )
                . " <G>missing gridsquares.\n" : ""
              )
            . $resCL
            . $resQrz
            . $resPota
            . "\n"
        );
        if ($this->lookupProgram === 'POTA') {
            prt("<Y>CLOSE SPOT:\n<G>    Would you like to close this spot on pota.app (Y/N)     <B>");
            $fin = fopen("php://stdin", "r");
            $response = strToUpper(trim(fgets($fin)));

            if ($response === 'Y') {
                prt("<G>    Please enter frequency in KHz:                          <M>");
                $this->spotKhz = trim(fgets($fin));

                prt("<G>    Enter comment - e.g. QRT - moving to CA-1234            <R>");
                $this->spotComment = trim(fgets($fin));

                $this->processParkSpot();
            }
        }

        PS::wsjtxUpdateInifile($this->qrzApiCallsign, $this->inputGSQ);

        prt("\n<Y>NEXT STEP:\n<G>"
            . "  - You should now restart WSJT-X before logging at another park, where\n"
            . "    a fresh <B>{$this->fileAdifWsjtx} <G>file will be created.\n"
            . "  - Alternatively, run this script again with a new Location ID to resume\n"
            . "    logging at a previously visited park.\n<RESET>"
        );
    }

    private function processParkCheck($all = false, $showLogs = false) {
        $fileAdifParkExists =   file_exists($this->pathAdifLocal . $this->fileAdifPark);
        $fileAdif = ($fileAdifParkExists ? $this->fileAdifPark : $this->fileAdifWsjtx);
        $adif =     new adif($this->pathAdifLocal . $fileAdif);
        $data =     $adif->parser();
        $result =   $this->dataFix($data);
        $data =     $result['data'];
        $dates =    Logbook::dataGetDates($data);
        $date =     ($all ? null : end($dates));
        $band =     $this->argCheckBand;
        $logs =     $this->dataCountLogs($data, $date);
        $MGs =      $this->dataCountMissingGsq($data);
        $locs =     $this->dataGetLocations($data);

        prt("<G>  - File <B>{$fileAdif} <G>exists and contains <C>" . count($data) . " <G>entries.\n"
            . ($MGs ? "  - There are <R>{$MGs} <G>missing gridsquares\n" : "")
            . static::showStats($data, $date, $band)
            . ($showLogs ? static::showLogs($data, $date, $band) : "")
            . (
                ($this->lookupProgram === 'POTA' && $logs < PS::ACTIVATION_LOGS_POTA) ||
                ($this->lookupProgram === 'WWFF' && $logs < PS::ACTIVATION_LOGS_WWFF) ||
                count($locs) > 1 ? "\n<R>WARNING:\n" : ''
            )
            . (
                ($this->lookupProgram === 'POTA' && $logs < PS::ACTIVATION_LOGS_POTA) ||
                ($this->lookupProgram === 'WWFF' && $logs < PS::ACTIVATION_LOGS_WWFF)
                ? "<R>  * There are insufficient logs for successful activation.\n<G>" : ''
            )
            . (count($locs) > 1 ?
                "\n<R>ERROR:\n  * There are " . count($locs) . " named log locations contained within this one file:\n"
                    . "    - " .implode("\n    - ", $locs) . "\n  * The operation has been cancelled.\n"
                : ""
            )
            . "<RESET>"
        );

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
        $dates =    Logbook::dataGetDates($data);
        $date =     ($this->modePushQty ? false : end($dates));

        $adif = $adif->toAdif($data, $this->version, false, true);
        file_put_contents($filename, $adif);
        $resCL =    '';
        $resQrz =   '';
        $resSave =  '';
        if ($this->clublogCheck()) {
            $resCL = $this->clublogUpload($data, $date);
        }
        if ($this->qrzApiCallsign && $this->qrzApiKey) {
           // $resQrz = $this->qrzUpload($data, $date);
        }
        if ($this->sessionAdifDirectory) {
            // $resSave = $this->parkSaveLogs($data);
        }
        $adif = adif::toAdif($data, $this->version, false, true);
        file_put_contents($filename, $adif);
        prt($resCL . $resQrz . $resSave . "<RESET>\n");
    }

    private function processParkSpot() {
        $activator = ($this->inputQthId === 'K-TEST' ? 'ABC123' : $this->qrzLogin);
        $comment = $this->spotComment . ($this->lookupAltLocId ? ' | ' .$this->lookupLocId . ' / ' . $this->lookupAltLocId : '');
        $linelen = 44 + strlen($comment);
        prt("\n<Y>PENDING OPERATION:\n"
            . "<G>    The following spot will be published at pota.app:\n\n"
            . "<W>     Activator  Spotter    KHz      Park Ref   Comments\n"
            . "    " . str_repeat('-', $linelen) . "\n"
            . "     "
            . str_pad($activator, 10, ' ') . ' '
            . str_pad($this->qrzLogin, 10, ' ') . ' '
            . str_pad($this->spotKhz, 8, ' ') . ' '
            . str_pad($this->inputQthId, 10, ' ') . ' '
            . $comment . "\n"
            . "    " . str_repeat('-', $linelen) . "\n\n"
            . "<Y>CONFIRMATION REQUIRED:\n"
            . "<G>    Please confirm that you want to publish the spot: (Y/N) <B>"
        );
        $fin = fopen("php://stdin","r");
        $response = strToUpper(trim(fgets($fin)));
        if ($response !== 'Y') {
            prt("\n<Y>RESULT:\n<G>    Spot has NOT been published.\n<RESET");
            return false;
        }
        $result = $this->potaPublishSpot();
        if ($result === true) {
            prt("\n<Y>RESULT:\n<G>"
                . "  - Your spot at <B>{$this->inputQthId} <G>on <M>{$this->spotKhz} KHz<G>"
                . " has been published on <Y>pota.app <G>as <R>\"{$comment}\"\n<RESET>"
            );
            return true;
        }
        prt("\n<R>ERROR:\n  - An error occurred when trying to publish your spot:\n    <B>{$result}\n<RESET>");
        return false;
    }

    private function processParkInitialise() {
        PS::wsjtxUpdateInifile($this->qrzApiCallsign, $this->inputGSQ);

        prt("<G>  - This is a first time visit, since neither <B>{$this->fileAdifPark} <G>nor <B>{$this->fileAdifWsjtx} <G>exist.\n\n");

        if ($this->lookupProgram === 'POTA') {
            prt("<Y>PUBLISH SPOT:\n<G>    Would you like to publish this spot to pota.app (Y/N)   <B>");
            $fin = fopen("php://stdin","r");
            $response = strToUpper(trim(fgets($fin)));
            if ($response === 'Y') {
                prt("<G>    Please enter frequency in KHz:                          <M>");
                $this->spotKhz = trim(fgets($fin));

                prt("<G>    Enter a comment, starting with mode e.g. \"FT8 QRP 5w\"   <B>");
                $this->spotComment = trim(fgets($fin));

                $this->processParkSpot();
            }
        }
        prt( "\n<Y>NEXT STEP:\n<G>"
            . "  - Please restart WSJT-X if you were logging at another park to allow <B>{$this->fileAdifWsjtx} <G>to be created.<RESET>\n");
    }

    private function processParkUnarchiving() {
        PS::wsjtxUpdateInifile($this->qrzApiCallsign, $this->inputGSQ);

        $adif = new adif($this->pathAdifLocal . $this->fileAdifPark);
        $data = $adif->parser();
        $locs =     $this->dataGetLocations($data);

        prt("<G>  - File <B>{$this->fileAdifPark} <G>exists and contains <M>" . count($data) . " <G>entries.\n"
            . "  - File <B>{$this->fileAdifWsjtx}<G> does NOT exist.\n\n"
        );
        if (count($locs) > 1) {
            prt("<R>ERROR:\n  * There are " . count($locs) . " named log locations contained within this one file:\n"
                . "    - " . implode("\n    - ", $locs) . "\n"
                . "  * Manual intervention is required.\n"
                . "  * The operation has been cancelled.\n<RESET>"
            );
            return;
        }
        prt("<Y>PENDING OPERATION:\n"
            . "<G>  - Rename archived log file <B>{$this->fileAdifPark} <G>to <B>{$this->fileAdifWsjtx}<G>\n"
            . "  - Resume logging at park <R>{$this->locationName}\n\n"
            . "<Y>CHOICE:\n<G>    Continue with operation? (Y/N) "
        );
        $fin = fopen("php://stdin","r");
        $response = strToUpper(trim(fgets($fin)));

        prt("\n<Y>RESULT:\n<G>");

        if ($response === 'Y') {
            rename(
                $this->pathAdifLocal . $this->fileAdifPark,
                $this->pathAdifLocal . $this->fileAdifWsjtx
            );
            prt( "    Renamed archived log file <B>{$this->fileAdifPark} <G>to <B>{$this->fileAdifWsjtx}\n\n");
            if ($this->lookupProgram === 'POTA') {
                prt("<Y>PUBLISH SPOT:\n<G>    Would you like to publish this spot to pota.app (Y/N)   <B>");
                $fin = fopen("php://stdin", "r");
                $response = strToUpper(trim(fgets($fin)));
                if ($response === 'Y') {
                    prt("<G>    Please enter frequency in KHz:                          <M>");
                    $this->spotKhz = trim(fgets($fin));

                    prt("<G>    Enter a comment, starting with mode e.g. \"FT8 QRP 5w\"   <R>");
                    $this->spotComment = trim(fgets($fin));

                    $this->processParkSpot();
                }
            }
            prt("\n<Y>NEXT STEP:\n<G>    You may resume logging at <R>{$this->locationName}\n\n");
        } else {
            prt( "    Operation cancelled.\n");
        }
        prt("<RESET>");
    }

    private function qrzCheck() {
        if (empty($this->qrzLogin) || empty($this->qrzPass)) {
            prt("<R>WARNING:\n"
                . "  QRZ.com credentials were not found in <B>potashell.ini<R>.\n"
                . "  Missing GSQ values for logged contacts cannot be fixed without\n"
                . "  valid QRZ credentials.\n<RESET>"
            );
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
            prt("<R>ERROR:\n"
                . "  QRZ.com reports <B>\"" . trim($data->Session->Error) . "\"<R>\n"
                . "  Missing GSQ values for logged contacts cannot be fixed without\n"
                . "  valid QRZ credentials.\n<RESET>"
            );
            die(0);
        }
        if (empty($data->Session->Key)) {
            prt("<R>ERROR:\n  QRZ.com reports an invalid session key, so automatic log uploads are possible at this time.\n\n"
                . "  Missing GSQ values for logged contacts cannot be fixed without valid QRZ credentials.\n\n<RESET>"
            );
            return false;
        }
        $this->qrzSession = $data->Session->Key;
        if (empty($this->qrzApiKey)) {
            prt("<R>WARNING:\n"
                . "  QRZ.com <B>[QRZ]apikey <R>is missing in <B>potashell.ini<R>.\n"
                . "  Without a valid XML Subscriber apikey, you won't be able to automatically upload\n"
                . "  archived logs to QRZ.com.\n\n<RESET>"
            );
            return false;
        }
        try {
            $url = sprintf(
                "https://logbook.qrz.com/api?KEY=%s&ACTION=STATUS",
                urlencode($this->qrzApiKey)
            );
            $raw = file_get_contents($url);
        } catch (\Exception $e) {
            prt("<R>WARNING:\n  Unable to connect to QRZ.com for log uploads: <B>" . $e->getMessage() . "<R>.\n\n<RESET>");
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
                prt("<R>ERROR:\n  Unable to connect to QRZ.com for log uploads:\n<B>  - Wrong callsign for [QRZ]apikey\n<RESET>");
                die(0);
            }
            return true;
        }

        if (isset($status['REASON'])) {
            if (strpos($status['REASON'], 'invalid api key') !== false) {
                prt("<R>ERROR:\n  Unable to connect to QRZ.com for log uploads:\n<B>  - Invalid QRZ Key\n<RESET>");
                die(0);
            }
            if (strpos($status['REASON'], 'user does not have a valid QRZ subscription') !== false) {
                prt("<R>ERROR:\n  Unable to connect to QRZ.com for log uploads:\n<B>  - Not XML Subscriber\n\n<RESET>");
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
            prt("<R>    WARNING: No gridsquare found at QRZ.com for callsign <B>{$callsign}\n<RESET>");
            return null;
        }
        return (string) $data->Callsign->grid;
    }

    private function qrzGetItuForCall($callsign) {
        $data = $this->qrzGetInfoForCall($callsign);
        if (empty($data->Callsign->country)) {
            prt("<R>    WARNING: No country found at <Y>QRZ.com <R>for callsign <B>{$callsign}\n<RESET>");
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
                prt("<R>WARNING:\n  Unable to connect to <Y>QRZ.com <R>for log uploads:\n<B>" . $e->getMessage() . ".\n\n<RESET>");
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
                        prt("{$status['REASON']}\n");
                        $stats['ERROR']++;
                        $record['TO_QRZ'] = $status['REASON'];
                    }
                    break;
            }
            $processed++;
        }
        return "<G>  - Uploaded <C>{$stats['ATTEMPTED']} <G>new Logs to <Y>QRZ.com<G>.\n"
            . ($stats['INSERTED'] ?        "     * Inserted:       {$stats['INSERTED']}\n" : "")
            . ($stats['DUPLICATE'] ?    "<R>     * Duplicates:     " . $stats['DUPLICATE'] . "\n" : "")
            . ($stats['WRONG_CALL'] ?   "<R>     * Wrong Callsign: " . $stats['WRONG_CALL'] . "\n" : "")
            . ($stats['ERROR'] ?        "<R>     * Errors:         " . $stats['ERROR'] . "\n" : "");
    }

    private function showHeader() {
        $name = "POTA SHELL  {$this->version}";
        $line = str_repeat('*', strlen($name) + 4);
        prt("<CLS><Y>{$line}\n"
            . "* <Y>POTA SHELL  <G>{$this->version}<Y> *\n"
            . "* <Y>PHP         <G>" . str_pad($this->php, strlen($this->version), ' ', STR_PAD_LEFT). "<Y> *\n"
            . "{$line}\n\n"
        );
    }

    private function showHelp() {
        return
        "<Y>PURPOSE:\n<y>"
        . "  This program works with <Y>WSJT-X<y> log files to prepare them for upload to POTA.\n"
        . "  1) It sets all <Y>MY_GRIDSQUARE<y> values to your supplied <C>Maidenhead GSQ value<y>.\n"
        . "  2) It adds a new <Y>MY_CITY<y> column to all rows, populated with the Park Name in this format:\n"
        . "     <C>POTA: CA-1368 North Maple RP <y>- a POTA API lookup is used to obtain the park name.\n"
        . "  3) It obtains missing <Y>GRIDSQUARE<y>, <Y>STATE<y> and <Y>COUNTRY<y> values from the QRZ Callbook service.\n"
        . "  4) It archives or un-archives the park log file in question - see below for more details.\n"
        . "  5) It can post a <G>SPOT<y> to POTA with a given frequency, mode and park ID to alert \"hunters\"\n"
        . "     to the start or end of your activation session.\n"
        . "  6) It also updates values for <Y>MyCall<y> and <Y>MyGrid<y> in your <B>WSJT-X.ini <y>file if they have changed,\n"
        . "     so that the correct call and gridsquare are broadcast during your activations.\n"
        . "\n"
        . "<Y>CONFIGURATION:<y>\n"
        . "  User Configuration is by means of the <B>potashell.ini <y> file located in this directory.\n"
        . "\n"
        . "<Y>SYNTAX:\n"
        . $this->showSyntax(1)
        . "\n<Y>"
        . "     a) PROMPTING FOR USER INPUTS:\n<y>"
        . "       - If either <B>Park ID <y>or <C>GSQ <y>is omitted, potashell prompts for these values.\n"
        . "       - Before any files are renamed or modified, user must confirm the operation.\n"
        . "         If user responds <RESP_Y> operation continues, <RESP_N> aborts.\n"
        . "\n<Y>"
        . "     b) WITHOUT AN ACTIVE LOG FILE:\n<y>"
        . "       - If there is NO active <B>wsjtx_log.adi <y>log file, potashell looks for a file for the\n"
        . "         indicated park, e.g. <B>wsjtx_log_CA-1368.adi<y>, and if the user confirms the operation,\n"
        . "         potashell renames it to <B>wsjtx_log.adi <y>so that logs can be added for this park.\n<Y>"
        . "         1. <C>WSJT-X <y>must be restarted if running, so the <B>wsjtx_log.adi <y>file can be read or created.\n<Y>"
        . "         2. <y>The user is then asked if they want to add a <G>SPOT <y>for the park on the POTA website.\n"
        . "            If user responds <RESP_Y>, potashell asks for <Y>frequency <y>and <R>comment <y>- usually mode, e.g. <R>FT8<y>\n"
        . "\n<Y>"
        . "     c) WITH AN ACTIVE LOG FILE:\n<y>"
        . "       - If latest session has too few logs for POTA activation, a <R>WARNING <y>is given.\n"
        . "       - If the log contains logs from more than one location, the process is halted.\n"
        . "       - If an active log session has completed, and the user confirms the operation:\n<Y>"
        . "         1. <y>The <B>wsjtx_log.adi <y>file is renamed to <B>wsjtx_log_CA-1368.adi\n<Y>"
        . "         2. <y>Any missing <G>GRIDSQUARE<y>, <G>STATE <y>and <G>COUNTRY <y>values for the other party are added.\n<Y>"
        . "         3. <y>The supplied gridsquare - e.g. <C>FN03FV82 <y>is written to all <G>MY_GRIDSQUARE <y>fields\n<Y>"
        . "         4. <y>The identified park - e.g. <C>POTA: CA-1368 North Maple RP <Y>is written to all <G>MY_CITY <y>fields\n<Y>"
        . "         5. <y>If you have an internet connection, logs in the current session are sent to <B>QRZ.com<y>,\n"
        . "            and if your <B>potashell.ini <y>file contains <Y>ClubLog <y>credentials, to <Y>ClubLog.com <y>also.\n<Y>"
        . "         6. <y>If configured to do so, logs for the current session are used to create or append\n"
        . "            a POTA log file suitable for submitting to POTA.\n<Y>"
        . "         7. <y>The user is asked if they'd like to mark their <G>SPOT <y>in POTA as QRT (inactive).\n<Y>"
        . "            <y>If they respond <RESP_Y>, potashell prompts for the <Y>frequency <y>and <R>comment<y>, usually\n"
        . "            starting with the code <R>QRT <y>indicating that the activation attempt has ended -\n"
        . "            for example: <R>QRT - moving to CA-1369\n"
        . "\n<Y>"
        . "     d) THE \"CHECK\" MODE:\n<y>"
        . "       - If the <G>CHECK <y>argument is given, system operates directly on either\n"
        . "         the Park Log file, or if that is absent, the <B>wsjtx_log.adi <y>file currently in use.\n<Y>"
        . "         1. <y>A full list and summary for all logs in the latest session are shown, together with\n"
        . "            distances for each contact in KM.\n<Y>"
        . "         2. <y>Missing <G>GRIDSQUARE<y>, <G>STATE <y>and <G>COUNTRY <y>values for the other party are added.\n"
        . "            No files are renamed.\n<Y>"
        . "         3. <y>An optional <Y>band <y>argument will limit stats to only contacts made on that band.\n"
        . "\n<Y>"
        . "     e) THE \"REVIEW\" MODE:\n<y>"
        . "       - If the <G>REVIEW <y>argument is given, system behaves exactly as in the <Y>CHECK <y>mode,\n"
        . "         but stats and logs for all sessions in the park are shown.\n"
        . "\n<Y>"
        . "     f) THE \"SUMMARY\" MODE:\n<y>"
        . "       - If the <G>SUMMARY <y>argument is given, system behaves exactly as in the <Y>REVIEW <y>mode,\n"
        . "         but no logs are listed.\n"
        . "\n<Y>"
        . "     f) THE \"PUSH\" MODE:\n<y>"
        . "       - If the <G>PUSH <y>argument is given, system operates directly on either\n"
        . "         the Park Log file, or if that is absent, the <B>wsjtx_log.adi <y>file currently in use.\n<Y>"
        . "         1. <y>The logs achieved so far in the latest session are uploaded to <Y>QRZ.com<y>.\n<Y>"
        . "         2. <y>Any logs sent to <Y>QRZ.com <y>have their <B>TO_QRZ <y>flag set to 'Y' to indicate these\n"
        . "            have been uploaded.\n<Y>"
        . "         3. <y>If potashell.ini file contains your <Y>Clublog <y>credentials, logs will also go to <Y>ClubLog.com<y>.\n"
        . "            Any logs sent to <Y>ClubLog.com <y>have their <B>TO_CLUBLOG <y>flag set to 'Y' to indicate these\n"
        . "            have been uploaded.\n<Y>"
        . "         4. <y>If configured to do so, logs for the current session are used to create or append a POTA\n"
        . "            log file suitable for submitting to POTA.\n"
        . "            No files are renamed, and you won't be prompted to add a spot to <Y>pota.app<y>.\n<Y>"
        . "         5. <y>If an optional number argument - e.g. <G>50 <y>is given, the maximum number of logs that will be\n"
        . "            processed is capped at that number.\n"
        . "            Use a maximum value of <G>100 <y>to avoid overloading <Y>Clublog <y>with too many requests in 5 minutes.\n<Y>"
        . "         6. <y>If the optional <G>ALL <y>argument is given, all logs at the park which are not indicated\n"
        . "            as having been already pushed will be sent to <Y>QRZ.com<y>.\n"
        . "\n<Y>"
        . "     g) THE \"SPOT\" MODE:\n<y>"
        . "       - If the <G>SPOT <y>argument is given, a 'spot' is posted to the pota.app website.\n<Y>"
        . "         1. <y>The first parameter is the frequency in KHz.\n<Y>"
        . "         2. <y>The final parameter is the intended transmission mode or \"QRT\" to close the spot.\n"
        . "            Use quotes around the last parameter to group words together.\n<Y>"
        . "         3. <y>To safely test this feature without users responding, use <B>K-TEST <y>as the park ID.\n"
        . "            When the <B>K-TEST <y>test park is specified, the Activator callsign will be set to <B>ABC123<y>.\n"
        . "            e.g: <W>potashell <B>K-TEST <Y>AA11BB22 <G>SPOT <M>14074 <R>\"FT8 - Test for POTASHELL spotter mode\"\n"
        . "\n<Y>"
        . $this->showSyntax(2)
        . "       - The system reviews ALL archived Park Log files, and produces a report on their contents.\n"
        . "       - If an optional valid column header is given, results are sorted by that column.\n"
        . "\n<Y>"
        . $this->showSyntax(3)
        . "       - Detailed help is provided.\n"
        . "\n<Y>"
        . $this->showSyntax(4)
        . "       - Basic syntax is provided.\n"
        . "\n\n<RESET>";
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
            ['label' => 'LOC',      'src' => 'LOC_ID',           'len' => 3],
            ['label' => 'GSQ',      'src' => 'MY_GRIDSQUARE',    'len' => 3],
            ['label' => 'CALLSIGN', 'src' => 'CALL',             'len' => 8],
            ['label' => 'BAND',     'src' => 'BAND',             'len' => 4],
            ['label' => 'MODE',     'src' => 'MODECOMP',         'len' => 4],
            ['label' => 'STATE',    'src' => 'STATE',            'len' => 5],
            ['label' => 'COUNTRY',  'src' => 'COUNTRY',          'len' => 7],
            ['label' => 'GSQ',      'src' => 'GRIDSQUARE',       'len' => 3],
            ['label' => 'KM',       'src' => 'DX',               'len' => 3],
            ['label' => 'UPLOAD',   'src' => '_',                'len' => 7],
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
        $header_bd = ["<C>$num<G>"];
        foreach ($columns as &$column) {
            if ($column['src'] === false) {
                continue;
            }
            $header[] = str_pad($column['label'], $column['len']);
            $header_bd[] = "<C>" . str_pad($column['label'], $column['len']) . "<G>";
        }
        $head =     implode(' | ', $header);
        $head_bd =  implode(' | ', $header_bd);
        $rows = [];
        foreach ($logs as $i => $log) {
            $row = ["<Y>" . str_pad( '' . ($i + 1), $columns[0]['len'], ' ', STR_PAD_LEFT) . "<G>"];
            foreach ($columns as &$column) {
                if ($column['src'] === false) {
                    continue;
                }
                switch($column['src']) {
                    case '_':
                        $row[] = "<Y>"
                            . (isset($log['TO_CLUBLOG']) ? ($log['TO_CLUBLOG'] === 'Y' ? 'C' : ' ') : ' ') . ' '
                            . (isset($log['TO_QRZ']) ?     ($log['TO_QRZ'] === 'Y' ?     'Q' : ' ') : ' ') . ' '
                            . (isset($log['TO_POTA']) ?    ($log['TO_POTA'] === 'Y' ?    'P' : ' ') : ' ') . ' '
                            . (isset($log['TO_WWFF']) ?    ($log['TO_WWFF'] === 'Y' ?    'W' : ' ') : ' ') . ' '
                            . "<G>";
                        break;
                    case 'DX':
                        $row[] = "<C>" . str_pad((isset($log[$column['src']]) ? $log[$column['src']] : ''), $column['len'], " ", STR_PAD_LEFT) . "<G>";
                        break;
                    default:
                        $row[] = "<C>" . str_pad((isset($log[$column['src']]) ? $log[$column['src']] : ''), $column['len']) . "<G>";
                        break;
                }
            }
            $rows[] = implode(' | ', $row);
        }
        $line = str_repeat('-', strlen($head) + 1);
        return
            "<Y>LOGS:" . str_repeat(' ', strlen($head) - 74)
            . "<C>UPLOAD <G>= Uploaded logs to "
            . "<Y>C<G>-Clublog, "
            . "<Y>Q<G>-QRZ, "
            . "<Y>P<G>-POTA File, "
            . "<Y>W<G>-WWFF File.\n"
            . "$line\n"
            . $head_bd . "\n"
            . "$line\n"
            . " " . implode("\n ", $rows) . "\n"
            . "$line\n"
            . "\n";
    }

    private static function showStats($data, $date = null, $band = null) {
        $logs =         static::dataCountLogs($data, $date, $band);
        $bands =        static::dataGetBands($data, $date, $band);
        $countries =    static::dataGetCountries($data, $date, $band);
        $dx =           static::dataGetBestDx($data, $date, $band);
        $states =       static::dataGetStates($data, $date, $band);
        $indent =       20;
        $dateFmt =      ($date ? substr($date, 0, 4) . "-" . substr($date, 4, 2) . "-" . substr($date, 6, 2) : "");

        $header = "<G>  - "
            . ($date ? "Stats for last session on <C>{$dateFmt}<G>" : "All time stats for park")
            . ($band ? " on <C>{$band}<G>" : "")
            . ":\n";

        $stats = "    There were <C>{$logs} <G>distinct log" . ($logs === 1 ? '' : 's')
            . " on <C>" . count($bands) . " <G>" . (count($bands) === 1 ? "band" : "bands")
            . " from <C>" . count($countries) . " <G>" . (count($countries) === 1 ? "country" : "countries")
            . (count($states) ? " and <C>" . count($states) . " <G>state" . (count($states) === 1 ? "" : "s") : "")
            . ($logs ? " - best DX was <B>" . number_format($dx) . " <G>KM." : "")
            ."\n";

        $bands =        PS::formatLineWrap(PS::MAXLEN, $indent, $bands, true);
        $countries =    PS::formatLineWrap(PS::MAXLEN, $indent, $countries);
        $states =       PS::formatLineWrap(PS::MAXLEN, $indent, $states);

        return
              $header
            . $stats
            . (count($bands) ?
                  "      - " . (count($bands) === 1 ?     "Band: " : "Bands:") . "      <Y>"
                  . implode("<G>, <Y>", $bands) . "<G>\n"
                  : ""
              )
            . (count($countries) ?
                  "      - " . (count($countries) === 1 ? "Country:  " : "Countries:") . "  <Y>"
                . implode("<G>, <Y>", $countries) . "<G>\n"
                . (count($states) ?
                      "      - " . (count($states) === 1 ? "State: ": "States:") . "     <Y>" . implode("<G>, <Y>", $states) . "<G>\n"
                : ""
                )
                . "\n"
                : ""
            );
    }

    private function showSyntax($step = false) {
        switch ($step) {
            case 1:
                return "<Y>"
                    . "  1. <W>potashell\n"
                    . "     <W>potashell <B>CA-1368\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>CHECK\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>CHECK <Y>160m\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>REVIEW\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>REVIEW <Y>160m\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>SUMMARY\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>SUMMARY <Y>160m\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>PUSH\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>PUSH 50\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>PUSH ALL\n"
                    . "     <W>potashell <B>CA-1368 <C>FN03FV82 <G>SPOT <Y>14074 <R>\"FT8 - QRP 4w\"\n";
            case 2:
                return "<Y>  2. <W>potashell <G>AUDIT\n"
                    . "     <W>potashell <G>AUDIT <R>\"LATEST LOG\"\n<y>";
            case 3:
                return "<Y>  3. <W>potashell <G>HELP\n<y>";
            case 4:
                return "<Y>  4. <W>potashell <G>SYNTAX\n<y>";
            default:
                return
                    $this->showSyntax(1) . "\n"
                    . $this->showSyntax(2) . "\n"
                    . $this->showSyntax(3) . "\n"
                    . $this->showSyntax(4) . "<RESET>";
        }
    }

    private function wsjtxUpdateInifile($call, $gsq) {
        $filename = rtrim($this->config['WSJTX']['log_directory'],'\\/') . DIRECTORY_SEPARATOR . 'WSJT-X.ini';
        if (!$wsjtxIniConfig = PS::parse_ini($filename, true)) {
            prt("<R>ERROR:\n  Unable to parse <B>{$filename} <R> file.\n\n<RESET>");
            die(0);
        };
        if (!isset($wsjtxIniConfig['Configuration']['MyCall']) || !isset($wsjtxIniConfig['Configuration']['MyGrid'])) {
            prt("<R>ERROR:\n  Unable to read values for <C>MyCall <R>and <C>MyGrid <R>in <C>Configuration <R>section of\n"
                . "  <B>{$filename}\n\n<RESET>"
            );
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

class Logbook {
    public $count_AT = 0;       // Activations Total
    public $count_BT = 0;       // Bands Total
    public $count_FA = 0;       // Failed Activations
    public $count_LS = 0;       // Unique Logs Last Session
    public $count_LT = 0;       // Unique Logs Total
    public $count_MG = 0;       // Missing Gridsquares
    public $count_ST = 0;       // Sessions Total
    public $bestDx = 0;
    public $date;
    public $dateFmt;
    public $logs = [];
    public $program;
    public $qthId;
    public $qthIdAlt;
    public $myCallsign;
    public $myCity;
    public $myGsqs = [];
    public $myGsqFmt;
    public $uploadStatus;

    public function __construct($logs) {
        $this->logs =       $logs;
        $this->myGsqs =     static::dataGetMyGrid($logs);
        $this->myGsqFmt = (count($this->myGsqs) === 1 ?
            $this->myGsqs[0] :
            (count($this->myGsqs) === 0 ? 'MISSING' : 'ERR ' . count($this->myGsqs) . ' GSQS')
        );

        $this->myCity =     $logs[0]['MY_CITY'];
        $this->qthId =      isset($logs[0]['LOC_ID']) ? $logs[0]['LOC_ID'] : "";
        $this->qthIdAlt =   isset($logs[0]['ALT_LOC_ID']) ? $logs[0]['ALT_LOC_ID'] : "";
        $this->myCallsign = $logs[0]['STATION_CALLSIGN'];

        $dates =            static::dataGetDates($this->logs);
        $this->bestDx =     static::dataGetBestDx($this->logs);
        $this->date =       end($dates);
        $this->dateFmt =    substr($this->date, 0, 4) . '-' . substr($this->date, 4, 2) . '-' . substr($this->date,6);
        $this->count_LS =   static::dataCountLogs($this->logs, $this->date);
        $this->count_LT =   static::dataCountLogs($this->logs);
        $this->count_MG =   static::dataCountMissingGsq($this->logs);
        $this->count_ST =   count($dates);
        $this->count_AT =   static::dataCountActivations($this->logs);
        $this->count_FA =   $this->count_ST - $this->count_AT;
        $this->count_BT =   static::dataCountBands($this->logs);
        $this->uploadStatus =
            (static::dataCountUploadType($this->logs, 'TO_CLUBLOG') === count($this->logs) ? 'C' : ' ') . ' '
            . (static::dataCountUploadType($this->logs, 'TO_QRZ') === count($this->logs) ? 'Q' : ' ') . ' '
            . (static::dataCountUploadType($this->logs, 'TO_POTA') === count($this->logs) ? 'P' : ' ') . ' '
            . (static::dataCountUploadType($this->logs, 'TO_WWFF') === count($this->logs) ? 'W' : ' ');

        if (!empty($this->logs[0]['PROGRAM']) && !empty($this->logs[0]['ALT_PROGRAM'])) {
            $this->program = 'DUAL';
        } else {
            $this->program = $this->logs[0]['PROGRAM'];
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

    private static function dataGetMyGrid($data) {
        $gsqs = [];
        foreach ($data as $d) {
            if (isset($d['MY_GRIDSQUARE'])) {
                $gsqs[$d['MY_GRIDSQUARE']] = true;
            } else {
                return [];
            }
        }
        $gsqs = array_keys($gsqs);
        sort($gsqs);
        return $gsqs;
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

    public static function dataGetDates($data) {
        $dates = [];
        foreach ($data as $d) {
            $dates[$d['QSO_DATE']] = true;
        }
        $dates = array_keys($dates);
        sort($dates);
        return $dates;
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

}

function prt($text) {
    $subs = PS::TEXTSUBS;
    $subs['<--->'] = str_repeat('-', PS::MAXLEN);
    print strtr($text, $subs);
}

function d($var) {
    print "<pre>". print_r($var, true) ."</pre>";
}

function dd($var) {
    var_dump($var);
    exit;
}

new PS();
