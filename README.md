# potashell <img src="https://docs.pota.app/assets/documents/logo.png" alt="Parks On The Air Logo" width="100" height="100" style="float:right">


**PURPOSE:**<br>

 [**Potashell**](https://github.com/classaxe/potashell) is tool for Radio Amateurs making
  FT4 or FT8 contacts using [**WSJT-X**](https://wsjt.sourceforge.io) while operating from
  recognised locations in the **["Parks On The Air"](pota.app)** _**(POTA)**_ Program.<br>

  The script is used to manage and augment **`.adi`** files created by **WSJT-X** and operates directly
  on **adi** log files located within **WSJT-X** data folder.<br>

  When using this system, each park's logs will be archived at the end of the session to a
  specific park log file - eg `wsjtx_log_CA-1368.adi`.  This allows you to maintain logbooks
  specific to each park that you visit.<br>

  The values for **`MY_GRIDSQUARE`** within a log session file are all set to a user-supplied value,
  and a new **`MY_CITY`** column is added, with the value being set to a value obtained by
  automatically looking up the park details through the POTA API, in the following format:<br>
  `POTA: CA-1368 North Maple RP`<br>
  Please note that certain name substitutions are made to keep the name length manageable.

  Users with an active [**QRZ.com**](https://qrz.com) **_XML Subscriber_** account (see
  https://shop.qrz.com/collections/subscriptions) can provide their XML API key and credentials
  to enable the system automatically lookup any missing gridsquares for contacted stations, and
  to have their logs automatically uploaded to their QRZ.com logbook at the end of each session.<br>

---

**REQUIREMENTS**
  1. You need [PHP](https://php.net) installed and available to run at the command prompt:<br>
     Type ```php -v``` at the command prompt to verify your installed PHP version.<br>
  2. You should have WSJT-X installed, and be able to provide the path to the stored data files.
  3. You will need an active [**QRZ.com**](https://qrz.com) XML Subscriber's account for the
     automatic lookup of missing gridsquares to work, and for the system to automatically
     upload your logs to your QRZ.com logbook at the end of each session.

---

**CONFIGURATION:**<br>
User Configuration is by means of the potashell.ini file located in this folder.

---

**OPERATING MODES:**<br>
  1. BEGINNING AN ACTIVATION<br>
     [**Potashell**](https://github.com/classaxe/potashell) can be used at the start of a
     session to determine if you already have a log for park in question, and if so, that
     park's logfile - e.g. `wsjtx_log_CA-1368.adi` will be renamed to `wsjtx_log.adi`, 
     allowing **WSJT-X** to continue adding logs to that specific log file, and to correctly
     report on new continents, countries, gridsquares and calls for each location at which you
     operate.<br><br>

  2. DURING AN ACTIVATION<br>
     - The `check` option can be used with [**Potashell**](https://github.com/classaxe/potashell) at
     any time during an activation to determine if you have duplicated contacts with a single
     callsign within a session, and to report on how many more unique contacts are needed for a
     successful activation - [POTA](pota.app) requires 10 completed QSOs during a single operating
     session to successfully 'Activate' the park.
     - The `fix` option can also be used at any time to lookup any missing gridsquares and write in
     your full gridsquare reference for the park you are at, together with the park name in
     **`MY_GRIDSQUARE`**<br><br>

  3. AT THE END OF AN ACTIVATION ATTEMPT<br>
     Run the potashell script with the park identifier and accurate gridsquare reference when there
     is a `wsjtx_log.adi` file present in the **WSJT-X** data folder to end the session.<br>
     - The `wsjtx_log.adi` file is renamed to it's unique park specific name, e.g. `wsjtx_log_CA-1368.adi`
     - Any missing gridsquares for worked stations are obtained and inserted
     - The values for `MY_CITY` and `MY_GRIDSQUARE` are filled in from the supplied user input and a
       POTA API lookup
     - The newly added logs for the current session are uploaded to QRZ.com (previously existing logs in
       the same file are skipped)
     - With the `wsjtx_log.adi` file now renamed and unavailable to **WSJT-X**, that program should be
       restarted when a new park activation attempt begins, so that a new log can be created or an old
       log file renamed to place it back in scope for log entry to occur.<br><br> 

  4. AUDIT MODE<br>
     One of the cooler features is a built-in audit mode.  This will produce an output like this:<br>
     ![Potashell Audit](https://logs.classaxe.com/images/potashell/potashell_audit.png)<br><br>

  5. GETTING HELP<br>
     To obtain detailed help, run `potashell help`:
     ![Potashell Help](https://logs.classaxe.com/images/potashell/potashell_help.png)<br><br>
