#!/usr/bin/php
<?php
class PS {
    const RED =         "[0;31m";
    const RED_BD =      "[1;31m";
    const GREEN =       "[0;32m";
    const GREEN_BD =    "[1;32m";
    const YELLOW =      "[0;33m";
    const YELLOW_BD =   "[1;33m";
    const BLUE =        "[0;34m";
    const BLUE_BD =     "[1;34m";
    const MAGENTA =     "[0;35m";
    const MAGENTA_BD =  "[1;35m";
    const CYAN =        "[0;36m";
    const CYAN_BD =     "[1;36m";
    const WHITE =       "[0;37m";
    const WHITE_BD =    "[1;37m";
    const RESPONSE_Y =  "[1;42m Y [0;33m";
    const RESPONSE_N =  "[1;41m N [0;33m";
    const RESET =       "[0m";

    private $config;
    private $fileAdifPark;
    private $fileAdifWsjtx;
    private $GSQ;
    private $parkName;
    private $pathAdifLocal;
    private $potaId;

    public function __construct() {
        global $argv;
        $this->potaId = $argv[1] ?? null;
        $this->GSQ = $argv[2] ?? null;
        $this->header();
        $this->help();
        $this->checkPhp();
        $this->loadIni();
        $this->getCliArgs();
        $this->process();
    }

    private function checkPhp() {
        if (!extension_loaded('mbstring')) {
            print PS::RED_BD . "ERROR:\n  PHP mbstring extension is not available.\n" . PS::RESET;
            die(0);
        }
    }

    private function getCliArgs() {
        print PS::YELLOW_BD . "ARGUMENTS:\n";
        if ($this->potaId === null) {
            print PS::GREEN_BD . "  - Please provide POTA Park ID:" . PS::BLUE_BD . "           ";
            $fin = fopen("php://stdin","r");
            $this->potaId = trim(fgets($fin));
        } else {
            print PS::GREEN_BD . "  - Supplied POTA Park ID:" . PS::BLUE_BD . "                 " . $this->potaId . "\n";
        }
        if ($this->GSQ === null) {
            print PS::GREEN_BD . "  - Please provide POTA 8-char Gridsquare: " . PS::CYAN_BD;
            $fin = fopen("php://stdin","r");
            $this->GSQ = trim(fgets($fin));
        } else {
            print PS::GREEN_BD . "  - Supplied Gridsquare:" . PS::CYAN_BD . "                   " . $this->GSQ . "\n";
        }
        $this->parkName = "POTA: " . $this->potaId . " and name goes here";
        print "\n" . PS::RESET;
    }

    private function header() {
        print PS::YELLOW
            . "**************\n"
            . "* POTA SHELL *\n"
            . "**************\n"
            . "\n";
    }

    private function help() {
        if ($this->GSQ !== null) {
            return;
        }
        print PS::YELLOW_BD . "PURPOSE:" . PS::YELLOW ."\n"
            . "  Operates on " . PS::RED_BD . "wsjtx_log.adi" . PS::YELLOW ." file located in " . PS::BLUE_BD . "WSJT-X" . PS::YELLOW ." data folder.\n"
            . "\n"
            . PS::YELLOW_BD ."ARGUMENTS:" . PS::YELLOW ."\n"
            . "  System takes two args: Park Code - " . PS::BLUE_BD . "CA-1368" . PS::YELLOW .", and 8-char GSQ value - " . PS::CYAN_BD ."FN03FV82" . PS::YELLOW . ".\n"
            . "\n"
            . PS::YELLOW_BD . "OPERATION:" . PS::YELLOW ."\n"
            . "  1 System asks the user to confirm the operation that is about to take place.\n"
            . "     * If user responds " . PS::RESPONSE_Y . " operation continues, " . PS::RESPONSE_N ." aborts.\n"
            . "\n"
            . "  2 Renames " . PS::RED_BD ."wsjtx_log.adi" . PS::YELLOW ." to " . PS::GREEN_BD ."wsjtx_log_" . PS::BLUE_BD . "CA-1368" . PS::GREEN_BD .".adi" . PS::YELLOW ." according to park code given.\n"
            . "    If " . PS::RED_BD ."wsjtx_log.adi" . PS::YELLOW ." isn't initially present, but " . PS::GREEN_BD ."wsjtx_log_" . PS::BLUE_BD . "CA-1368" . PS::GREEN_BD .".adi" . PS::YELLOW ." is,\n"
            . "    system asks if user wishes to resume logging at this park.\n"
            . "     * If user responds " . PS::RESPONSE_Y . ", file is renamed to " . PS::RED_BD . "wsjtx_log.adi" . PS::YELLOW ." and operation ends.\n"
            . "     * If user responds " . PS::RESPONSE_N . ", operation continues as below.\n"
            . "\n"
            . "  3 Updates all " . PS::MAGENTA_BD ."MY_GRIDSQUARE" . PS::YELLOW ." values with supplied 8-character GSQ value.\n"
            . "\n"
            . "  4 Populates new " . PS::MAGENTA_BD ."MY_CITY" . PS::YELLOW ." column using data obtained by looking up supplied Park ID at POTA.\n"
            . "\n"
            . "  5 Contacts QRZ API service to obtain any missing " . PS::MAGENTA_BD ."GRIDSQUARE" . PS::YELLOW ." values as needed.\n"
            . "\n"
            . PS::YELLOW_BD . "CONFIGURATION:" . PS::YELLOW ."\n"
            . "  User Configuration is by means of the " . PS::BLUE_BD . "potashell.ini" . PS::YELLOW ." file located in this directory.\n"
            . "\n"
            . PS::YELLOW_BD . "SYNTAX:" . PS::WHITE_BD . "\n"
            . "  potashell " . PS::BLUE_BD . "CA-1368 " . PS::CYAN_BD ."FN03FV82" . PS::YELLOW ."\n"
            . "  - If either argument is omitted, system will prompt for it.\n"
            . "  - If BOTH arguments are omitted, help will be shown.\n"
            . "\n"
            . str_repeat('-', 90) . PS::RESET ."\n\n";
;
    }

    private function loadIni() {
        $filename = 'potashell.ini';
        if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . $filename)) {
            print PS::RED_BD . "ERROR:\n  Configuration file {$filename} is missing.\n" . PS::RESET;
            die(0);
        };
        if (!$this->config = @parse_ini_file($filename)) {
            print PS::RED_BD . "ERROR:\n  Unable to parse {$filename} file.\n" . PS::RESET;
            die(0);
        };
        $this->pathAdifLocal = rtrim($this->config['log_directory'],'\\/') . DIRECTORY_SEPARATOR;
    }

    private function process() {
        print PS::YELLOW_BD . "STATUS:\n";
        if (!$this->potaId || !$this->GSQ) {
            print PS::RED_BD . "  - One or more required parameters are missing.\n"
                . "    Unable to continue.\n" . PS::RESET;
            die(0);
        }
        $this->fileAdifPark =   "wsjtx_log_{$this->potaId}.adi";
        $this->fileAdifWsjtx =  "wsjtx_log.adi";
        $fileAdifParkExists =   file_exists($this->pathAdifLocal . $this->fileAdifPark);
        $fileAdifWsjtxExists =  file_exists($this->pathAdifLocal . $this->fileAdifWsjtx);
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
            $adif1 = new adif($this->pathAdifLocal . $this->fileAdifPark);
            $data1 = $adif1->parser();
            print PS::GREEN_BD . "  - File " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::GREEN_BD
                . " exists and contains " . PS::MAGENTA_BD . count($data1) . PS::GREEN_BD . " entries.\n"
                . "  - File " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD . " does NOT exist.\n\n"
                . PS::YELLOW_BD . "CHOICE:\n"
                . PS::GREEN_BD . "    Rename file to " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
                . " and resume logging at park " . PS::BLUE_BD . "{$this->potaId}" . PS::GREEN_BD . "? (Y/N) ";
            $fin = fopen("php://stdin","r");
            $response = strToUpper(trim(fgets($fin)));

            print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD;

            if ($response === 'Y') {
                print "    Renamed existing log file file " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::BLUE_BD . PS::GREEN_BD
                    . " to " . PS::BLUE_BD ."{$this->fileAdifWsjtx}\n";
            } else {
                print "    Operation cancelled.\n";
            }
            print PS::RESET;
            die(0);
        }

        if ($fileAdifWsjtxExists) {
            $adif1 = new adif($this->pathAdifLocal . $this->fileAdifWsjtx);
            $data1 = $adif1->parser();

            print PS::GREEN_BD . "  - File " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
                . " exists and contains " . PS::MAGENTA_BD . count($data1) . PS::GREEN_BD . " entries.\n"
                . "  - File " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::GREEN_BD . " does NOT exist.\n\n"
                . PS::YELLOW_BD . "CHOICE:\n"
                . PS::GREEN_BD . "    Rename " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
                . " to " . PS::BLUE_BD . "{$this->fileAdifPark}" . PS::GREEN_BD . " and update my location fields? (Y/N) ";
            $fin = fopen("php://stdin","r");
            $response = strToUpper(trim(fgets($fin)));

            print PS::YELLOW_BD . "\nRESULT:\n" . PS::GREEN_BD;
            if ($response === 'Y') {
                print "  - Renamed existing log file " . PS::BLUE_BD . "{$this->fileAdifWsjtx}" . PS::GREEN_BD
                    . " to " . PS::BLUE_BD ."{$this->fileAdifPark}" . PS::GREEN_BD . ".\n"
                    . "  - Updated " . PS::MAGENTA_BD ."MY_GRIDSQUARE" . PS::GREEN_BD ." values to " . PS::CYAN_BD . $this->GSQ . PS::GREEN_BD . ".\n"
                    . "  - Added " . PS::MAGENTA_BD ."MY_CITY" . PS::GREEN_BD ." and set all values to " . PS::CYAN_BD . $this->parkName . PS::GREEN_BD . ".\n"
                ;

                // This is where James needs to be BRILLIANT - like burping the theme for "The Muppets"
                // Write the data structure contained in $data1 back to an ADIF file format.
                // That'll be in the adif class below.
            } else {
                print "    Operation cancelled.\n";
            }
            print PS::RESET;
            die(0);
        }
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
        $this->records = explode('<EOR>', $records);
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

    public function toAdif($data) {
        
        // construct an adif string out of data
        // construct header, (copied from an adif file)

        $output = <<<HHH
ADIF Export from ADIFMaster v[3.5]
http://www.dxshell.com
Copyright (C) 2005 - 2023 UU0JC, DXShell.com
File generated on 02 Dec, 2024 at 22:49
<ADIF_VER:5>3.1.4
<PROGRAMID:10>ADIFMaster
<PROGRAMVERSION:3>3.5
<EOH>
HHH;
        
        // construct records
        // format seems to be <FIELD_NAME:DATALENGTH>DATA*space* (more fields) <eor>

        foreach($data as $row) {
            foreach ($row as $key => $value) {
                $output .=  "<" . $key . ":" . $this->strlen($value) . ">" . $value . " ";
            }
            $output .= "<eor>\r\n";
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

new PS();
