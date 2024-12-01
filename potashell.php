#!/usr/bin/php
<?php
class potashell {
    public $potaId;
    public $GSQ;

    public function __construct() {
        global $argv;
        $this->potaId = $argv[1] ?? null;
        $this->GSQ = $argv[2] ?? null;
        $this->header();
        $this->help();
        $this->getCliArgs();
        print "\n" . print_r([$this->potaId,$this->GSQ], true) . "\n";
        print "Hello World - this is PHP " . phpversion() . "\n";
    }

    private function getCliArgs() {
        if ($this->potaId === null) {
            print "[1;32mPlease provide POTA Park ID:[1;34m           ";
            $fin = fopen("php://stdin","r");
            $this->potaId = trim(fgets($fin));
        } else {
            print "[0;32mSupplied POTA Park ID:[34m                 " . $this->potaId . "\n";
        }
        if ($this->GSQ === null) {
            print "[1;32mPlease provide POTA 8-char Gridsquare:[1;36m ";
            $fin = fopen("php://stdin","r");
            $this->GSQ = trim(fgets($fin));
        } else {
            print "[0;32mSupplied Gridsquare:[36m                   " . $this->GSQ . "\n";
        }
        print "[0m";
    }

    private function header() {
        print <<< EOD
[33m**************
* POTA SHELL *
**************
\n
EOD;
    }

    private function help() {
        if ($this->GSQ !== null) {
            return;
        }
        print <<< EOD
[1mPURPOSE: [0;33m
Operates on [1;34mwsjtx_log.adi[0;33m file located in [1;34mWSJT-X[0;33m data folder.

[1mARGUMENTS: [0;33m
System takes two args: Park Code - [1;34mCA-1368[0;33m, and 8-char GSQ value - [1;36mFN03FV82[0;33m.

[1mOPERATION: [0;33m
  1 System asks the user to confirm the operation that is about to take place.
     * If user responds [1;42m Y [0;33m operation continues, [1;41m N [0;33m aborts.

  2 Renames [1;31mwsjtx_log.adi[0;33m to [1;32mwsjtx_log_CA-1368.adi[0;33m according to park code given.
    If [1;31mwsjtx_log.adi[0;33m isn't initially present, but [1;32mwsjtx_log_CA-1368.adi[0;33m is,
    system asks if user wishes to resume logging at this park.
     * If user responds [1;42m Y [0;33m, file is renamed to [1;31mwsjtx_log.adi[0;33m and operation ends.
     * If user responds [1;41m N [0;33m, operation continues as below.

  3 Updates all [1mMY_GRIDSQUARE[0;33m values with supplied 8-character GSQ value.

  4 Populates new [1mMY_CITY[0;33m column populated with Park name obtained by looking up supplied Park ID.

  5 Contacts QRZ API service to obtain any missing [1mGRIDSQUARE[0;33m values as needed.

[1mCONFIGURATION: [0;33m
User Configuration is by means of the [1;34mpotashell.ini[0;33m file located in this directory.

[1mSYNTAX: [0;1;37m
potashell[0;33m [1;34mCA-1368[0;33m [1;36mFN03FV82[0;33m
[0m

EOD;
    }
}

new potashell();
