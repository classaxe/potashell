# potashell

**PURPOSE:**<br>
  Operates on **wsjtx_log.adi** file located in **WSJT-X** data folder.

**ARGUMENTS:**<br>
  System takes two args: Park Code - **CA-1368**, and 8-char GSQ value - **FN03FV82**.

**OPERATION:**
1. System asks the user to confirm the operation that is about to take place.
   * If user responds **"Y"**, operation continues, **"N"**, operation ends.
2. Renames **wsjtx_log.adi** to **wsjtx_log_CA-1368.adi** according to park code given.<br>
  If **wsjtx_log.adi** isn't initially present, but **wsjtx_log_CA-1368.adi** is,<br>
  system asks if user wishes to resume logging at this park.
   * If user responds **Y**, file is renamed to **wsjtx_log.adi** and operation ends.
   * If user responds **N**, operation continues.
3. Updates all **MY_GRIDSQUARE** values with supplied 8-character GSQ value.
4. Adds new **MY_CITY** column populated using data obtained by looking up supplied Park ID.
5. Contacts QRZ API service to obtain any missing GRIDSQUARE values as needed.

**CONFIGURATION:**<br>
  User Configuration is by means of the potashell.ini file located in this directory.

**SYNTAX:**<br>
  potashell CA-1368 FN03FV82

- If either argument is omitted, system will prompt for it.
- If BOTH arguments are omitted, help will be shown.
