<?php
/**
 * Optimizer
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 17:02:59 [Feb 26, 2016])
 */
//
//
class optimizer extends module
{
    /**
     * optimizer
     *
     * Module class constructor
     *
     * @access private
     */
    function optimizer()
    {
        $this->name = "optimizer";
        $this->title = "Optimizer";
        $this->module_category = "<#LANG_SECTION_SYSTEM#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 0)
    {
        $p = array();
        if (IsSet($this->id)) {
            $p["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['TAB'] = $this->tab;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        $this->getConfig();
        $out['START_DAILY'] = (int)$this->config['START_DAILY'];
        $out['START_TIME'] = (int)$this->config['START_TIME'];
        $out['AUTO_OPTIMIZE'] = (int)$this->config['AUTO_OPTIMIZE'];
        $out['KEEP_CACHED'] = (int)$this->config['KEEP_CACHED'];

        /*
        if ($this->view_mode=='optimize_now') {
            if (defined('PATH_TO_PHP'))
                $phpPath = PATH_TO_PHP;
            else
                $phpPath = IsWindowsOS() ? '..\server\php\php.exe' : 'php';

            safe_exec($phpPath.' '.dirname(__FILE__).'/optimize.php');
            echo "OK";
            exit;
        }
        */

        if ($this->view_mode == 'update_settings') {
            global $start_time;
            $this->config['START_TIME'] = (int)$start_time;

            global $start_daily;
            $this->config['START_DAILY'] = (int)$start_daily;

            global $keep_cached;
            $this->config['KEEP_CACHED'] = (int)$keep_cached;

            global $auto_optimize;
            $this->config['AUTO_OPTIMIZE'] = (int)$auto_optimize;

            $this->saveConfig();
            $this->redirect("?");
        }

        global $analyze;
        if ($analyze) {
            $this->analyze($out, $this->config['AUTO_OPTIMIZE'], 0);
        }

        global $optimizenow;
        global $id;
        if ($optimizenow) {
            $this->optimizeAll($id,gr('object'),gr('property'));
            exit;
        }


        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'optimizerdata' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_optimizerdata') {
                $this->search_optimizerdata($out);
            }
            if ($this->view_mode == 'edit_optimizerdata') {
                $this->edit_optimizerdata($out, $this->id);
            }
            if ($this->view_mode == 'delete_optimizerdata') {
                $this->delete_optimizerdata($this->id);
                $this->redirect("?");
            }
        }
    }

    /**
     * Title
     *
     * Description
     *
     * @access public
     */
    function analyze(&$out, $total_limit = 0, $auto_append = 0)
    {

        set_time_limit(0);

        $to_optimize = array();

        $result = array();

        $sqlQuery = "SELECT pvalues.ID, properties.TITLE AS PTITLE, classes.TITLE AS CTITLE, objects.TITLE AS OTITLE
               FROM pvalues 
               LEFT JOIN objects ON pvalues.OBJECT_ID = objects.ID
               LEFT JOIN classes ON objects.CLASS_ID  = classes.ID
               LEFT JOIN properties ON pvalues.PROPERTY_ID = properties.ID
             HAVING PTITLE != ''";

        $pvalues = SQLSelect($sqlQuery);
        $seen_properties = array();

        $total = count($pvalues);
        $grand_total = 0;
        for ($i = 0; $i < $total; $i++) {

            $seen_properties[] = $pvalues[$i]['ID'];


            if (defined('SEPARATE_HISTORY_STORAGE') && SEPARATE_HISTORY_STORAGE == 1) {
                $history_table = createHistoryTable($pvalues[$i]['ID']);
            } else {
                $history_table = 'phistory';
            }

            $sqlQuery = "SELECT COUNT(*) as TOTAL
                  FROM $history_table
                 WHERE VALUE_ID = '" . $pvalues[$i]['ID'] . "'";

            $tmp = SQLSelectOne($sqlQuery);

            if ($tmp['TOTAL']) {
                $grand_total += $tmp['TOTAL'];
                $rec = array('CLASS' => $pvalues[$i]['CTITLE'], 'PROPERTY' => $pvalues[$i]['PTITLE'], 'OBJECT' => $pvalues[$i]['OTITLE'], 'TOTAL' => $tmp['TOTAL']);

                $opt_rec = SQLSelectOne("SELECT * FROM optimizerdata WHERE PROPERTY_NAME LIKE '" . DbSafe($pvalues[$i]['PTITLE']) . "' AND OBJECT_NAME LIKE '" . DBSafe($pvalues[$i]['OTITLE']) . "'");
                if ($opt_rec['ID']) {
                    $rec['OPTIMIZE_NOW'] = $opt_rec['ID'];
                } else {
                    $opt_rec = SQLSelectOne("SELECT * FROM optimizerdata WHERE PROPERTY_NAME LIKE '" . DbSafe($pvalues[$i]['PTITLE']) . "' AND OBJECT_NAME='' AND CLASS_NAME LIKE '" . DBSafe($pvalues[$i]['CTITLE']) . "'");
                    if ($opt_rec['ID']) {
                        $rec['OPTIMIZE_NOW'] = $opt_rec['ID'];
                    }
                }
                if (!$opt_rec['ID'] && $tmp['TOTAL'] > $total_limit) {
                    $rec['WARNING'] = 1;
                    if ($auto_append == 1) {
                        // add optimize record automatically
                        $to_optimize[] = $rec;
                    }
                }
                $result['RECORDS'][] = $rec;
            }
        }

        foreach ($to_optimize as $optimize_rec) {
            $opt_rec = array();
            $opt_rec['CLASS_NAME'] = $optimize_rec['CLASS'];
            $opt_rec['OBJECT_NAME'] = $optimize_rec['OBJECT'];
            $opt_rec['PROPERTY_NAME'] = $optimize_rec['PROPERTY'];
            $opt_rec['OPTIMIZE'] = 'avg';
            SQLInsert('optimizerdata', $opt_rec);
            //dprint($optimize_rec,false);
        }

        if ($history_table == 'phistory' && count($seen_properties) > 0) {
            $unsortedData = SQLSelectOne("SELECT COUNT(*) AS TOTAL FROM phistory WHERE VALUE_ID NOT IN (" . implode(',', $seen_properties) . ")");
            $rec = array('CLASS' => 'Unknown', 'PROPERTY' => 'Unknown', 'OBJECT' => '', 'TOTAL' => (int)$unsortedData['TOTAL']);
            $grand_total += $rec['TOTAL'];
            $result['RECORDS'][] = $rec;
        }

        usort($result['RECORDS'], function ($a, $b) {
            if ($a['TOTAL'] == $b['TOTAL']) {
                return 0;
            }
            return ($a['TOTAL'] > $b['TOTAL']) ? -1 : 1;
        });

        $out['RECORDS'] = $result['RECORDS'];
        $out['GRAND_TOTAL'] = $grand_total;

//echo "<h2>Grand-total: " . $grand_total . "</h2><br />";
//echo str_repeat(' ', 1024);


    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->admin($out);
    }

    /**
     * optimizerdata search
     *
     * @access public
     */
    function search_optimizerdata(&$out)
    {
        require(DIR_MODULES . $this->name . '/optimizerdata_search.inc.php');
    }

    /**
     * optimizerdata edit/add
     *
     * @access public
     */
    function edit_optimizerdata(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/optimizerdata_edit.inc.php');
    }


    function optimizeAll($id = 0, $object = '', $property = '')
    {
        DebMes('Starting optimization procedure', 'optimizer');
        set_time_limit(0);


        $this->getConfig();

        if ($id) {
            $records = SQLSelect("SELECT * FROM optimizerdata WHERE ID=" . (int)$id);
        } else {
            //remove unused properties
            if (!defined('SEPARATE_HISTORY_STORAGE') || SEPARATE_HISTORY_STORAGE == 0) {
                dprint("Unsorted data", false);
                $sqlQuery = "SELECT pvalues.ID, properties.TITLE AS PTITLE, classes.TITLE AS CTITLE, objects.TITLE AS OTITLE
               FROM pvalues 
               LEFT JOIN objects ON pvalues.OBJECT_ID = objects.ID
               LEFT JOIN classes ON objects.CLASS_ID  = classes.ID
               LEFT JOIN properties ON pvalues.PROPERTY_ID = properties.ID
               HAVING PTITLE != ''";
                $pvalues = SQLSelect($sqlQuery);
                $seen_properties = array();
                $total = count($pvalues);
                for ($i = 0; $i < $total; $i++) {
                    $seen_properties[] = $pvalues[$i]['ID'];
                }
                if (count($seen_properties) > 0) {
                    $total = (int)current(SQLSelectOne("SELECT COUNT(*) AS TOTAL FROM phistory WHERE VALUE_ID NOT IN (" . implode(',', $seen_properties) . ")"));
                    dprint("Total unsorted: $total", false);
                    if ($total>0) {
                        SQLExec("DELETE FROM phistory WHERE VALUE_ID NOT IN (" . implode(',', $seen_properties) . ")");
                        dprint("DELETED", false);
                    }
                    DebMes('Total unsorted: ' . $total . ' deleted', 'optimizer');
                }
            }
            $records = SQLSelect("SELECT * FROM optimizerdata");
        }
        $rules = array();
        $total = count($records);
        for ($i = 0; $i < $total; $i++) {
            if ($records[$i]['OBJECT_NAME'] && $records[$i]['OBJECT_NAME'] != '*') {
                $key = $records[$i]['OBJECT_NAME'] . '.' . $records[$i]['PROPERTY_NAME'];
            } elseif ($records[$i]['CLASS_NAME'] && $records[$i]['CLASS_NAME'] != '*') {
                $key = $records[$i]['CLASS_NAME'] . '.' . $records[$i]['PROPERTY_NAME'];
            } else {
                $key = $records[$i]['PROPERTY_NAME'];
            }
            $rules[$key] = array('optimize' => $records[$i]['OPTIMIZE']);
            if ($records[$i]['KEEP']) {
                $rules[$key]['keep'] = (int)$records[$i]['KEEP'];
            }
        }

//print_r($rules);

//STEP 2 -- optimize values in time

//$sqlQuery = "SELECT DISTINCT(VALUE_ID) FROM phistory";
//$values = SQLSelect($sqlQuery);

        $sqlQuery = "SELECT pvalues.ID AS VALUE_ID, properties.TITLE AS PTITLE, classes.TITLE AS CTITLE, objects.TITLE AS OTITLE
               FROM pvalues 
               LEFT JOIN objects ON pvalues.OBJECT_ID = objects.ID
               LEFT JOIN classes ON objects.CLASS_ID  = classes.ID
               LEFT JOIN properties ON pvalues.PROPERTY_ID = properties.ID
             HAVING PTITLE != ''";

        if ($object!='' && $property!='') {
            $sqlQuery.=" AND PTITLE='".$property."' AND OTITLE='".$object."'";
        }


        $values = SQLSelect($sqlQuery);


        $total_records_removed = 0;

        $total = count($values);

        for ($i = 0; $i < $total; $i++) {
            $value_id = $values[$i]['VALUE_ID'];

            if (defined('SEPARATE_HISTORY_STORAGE') && SEPARATE_HISTORY_STORAGE == 1) {
                $history_table = createHistoryTable($value_id);
            } else {
                $history_table = 'phistory';
            }

            $sqlQuery = "SELECT pvalues.ID, properties.TITLE AS PTITLE, objects.TITLE AS OTITLE, classes.TITLE AS CTITLE
                  FROM pvalues
                  LEFT JOIN objects ON pvalues.OBJECT_ID = objects.ID
                  LEFT JOIN properties ON pvalues.PROPERTY_ID = properties.ID
                  LEFT JOIN classes ON classes.ID = objects.CLASS_ID
                 WHERE pvalues.ID = '" . $value_id . "'";

            $pvalue = SQLSelectOne($sqlQuery);


            if ($pvalue['CTITLE'] != '') {
                $key = $pvalue['OTITLE'] . '.' . $pvalue['PTITLE'];

                $rule = '';

                if ($rules[$key]) {
                    $rule = $rules[$key];
                } elseif ($rules[$pvalue['CTITLE'] . '.' . $pvalue['PTITLE']]) {
                    $key = $pvalue['CTITLE'] . '.' . $pvalue['PTITLE'];
                } elseif ($rules[$pvalue['PTITLE']]) {
                    $key = $pvalue['PTITLE'];
                }
                $rule = $rules[$key];

                if ($rule) {
                    //processing
                    dprint('Processing '.$pvalue['OTITLE'] . " (" . $key . ")", false);
                    DebMes('Processing ' . $pvalue['OTITLE'] . " (" . $key . ")", 'optimizer');

                    $sqlQuery = "SELECT COUNT(*) as TOTAL
                        FROM $history_table
                       WHERE VALUE_ID = '" . $value_id . "'";

                    $total_before = current(SQLSelectOne($sqlQuery));
                    DebMes('Before optimizing: ' . $total_before, 'optimizer');

                    if (isset($rule['keep'])) {
                        dprint(" removing old (" . (int)$rule['keep'] . ")",false);
                        debmes(" removing old (" . (int)$rule['keep'] . ")",'optimizer');
                        $sqlQuery = "DELETE
                           FROM $history_table
                          WHERE VALUE_ID = '" . $value_id . "'
                            AND TO_DAYS(NOW()) - TO_DAYS(ADDED) >= " . (int)$rule['keep'];
                        SQLExec($sqlQuery);
                    }

                    if ($rule['optimize']) {
                        $sqlQuery = "SELECT UNIX_TIMESTAMP(ADDED)
                           FROM $history_table
                          WHERE VALUE_ID = '" . $value_id . "'
                          ORDER BY ADDED
                          LIMIT 1";
                        dprint("Before last MONTH",false);
                        $end = time() - 30 * 24 * 60 * 60; // month end older
                        $tmp = SQLSelectOne($sqlQuery);
                        if (!is_array($tmp)) {
                            dprint("Skipping",false);
                            continue;
                        }
                        $start = current($tmp);
                        $interval = 2 * 60 * 60; // two-hours interval
                        $this->optimizeHistoryData($value_id, $rule['optimize'], $interval, $start, $end);

                        dprint("Before last WEEK",false);
                        $start = $end + 1;
                        $end = time() - 7 * 24 * 60 * 60; // week and older
                        $interval = 1 * 60 * 60; // one-hour interval
                        $this->optimizeHistoryData($value_id, $rule['optimize'], $interval, $start, $end);

                        dprint("Before YESTERDAY",false);
                        $start = $end + 1;
                        $end = time() - 1 * 24 * 60 * 60; // day and older
                        $interval = 20 * 60; // 20 minutes interval
                        $this->optimizeHistoryData($value_id, $rule['optimize'], $interval, $start, $end);

                        dprint("Before last HOUR",false);
                        $start = $end + 1;
                        $end = time() - 1 * 60 * 60; // 1 hour and older
                        $interval = 3 * 60; // 3 minutes interval
                        $this->optimizeHistoryData($value_id, $rule['optimize'], $interval, $start, $end);
                    }

                    $sqlQuery = "SELECT COUNT(*) AS TOTAL
                        FROM phistory
                       WHERE VALUE_ID = '" . $value_id . "'";
                    $total_after = current(SQLSelectOne($sqlQuery));
                    dprint("(changed " . $total_before . " -> " . $total_after . ")",false);
                    $total_records_removed += ($total_before - $total_after);
                    DebMes('After optimizing: ' . $total_after, 'optimizer');
                }
            }
        }

        DebMes('Optimization done. Total removed: ' . $total_records_removed, 'optimizer');
        SQLExec("OPTIMIZE TABLE phistory;");

        dprint("Removing shouts...",false);
        SQLExec("DELETE FROM shouts WHERE TO_DAYS(NOW())-TO_DAYS(ADDED)>7");
        $keep_cached = (int)$this->config['KEEP_CACHED'];
        if ($keep_cached) {
            dprint("Removing cached...",false);
            $deleted=0;
            $result = getDirTree(ROOT.'cms/cached');
            $total = count($result);
            for($i=0;$i<$total;$i++) {
                $tm=$result[$i]['TM'];
                if ((time()-$tm)>$keep_cached*24*60*60) {
                    $deleted++;
                    @unlink($result[$i]['FILENAME']);
                }
            }
            dprint("Cached files removed: ".$deleted,false);
        }


        dprint("DONE! (Total records removed: " . $total_records_removed . ")",false);

    }

    /**
     * Summary of optimizeHistoryData
     * @param mixed $valueID Id value
     * @param mixed $type Type
     * @param mixed $interval Interval
     * @param mixed $start Begin date
     * @param mixed $end End date
     * @return double|int
     */
    function optimizeHistoryData($valueID, $type, $interval, $start, $end)
    {
        $totalRemoved = 0;

        if (!$interval)
            return 0;

        $beginDate = date('Y-m-d H:i:s', $start);
        $endDate = date('Y-m-d H:i:s', $end);

        //echo "Value ID: $valueID <br />";

        if (defined('SEPARATE_HISTORY_STORAGE') && SEPARATE_HISTORY_STORAGE == 1) {
            $history_table = createHistoryTable($valueID);
        } else {
            $history_table = 'phistory';
        }

        dprint("Interval from " . $beginDate . " to " . $endDate . " (every " . $interval . " seconds)",false);
        debmes("Interval from " . $beginDate . " to " . $endDate . " (every " . $interval . " seconds)",'optimizer');

        $sqlQuery = "SELECT COUNT(*)
                  FROM $history_table
                 WHERE VALUE_ID =  '" . $valueID . "'
                   AND ADDED    >= '" . $beginDate . "'
                   AND ADDED    <= '" . $endDate . "'";

        $totalValues = (int)current(SQLSelectOne($sqlQuery));

        dprint("Total values: " . $totalValues,false);

        if ($totalValues < 2)
            return 0;

        $tmp = $end - $start;
        $tmp2 = round($tmp / $interval);

        if ($totalValues <= ($tmp2 + 50)) {
            dprint("... number of values ($totalValues) is less than (or about) optimal (" . $tmp2 . ") (skipping)",false);
            return 0;
        }

        dprint("Optimizing (should be about " . $tmp2 . " records)...",false);
        debmes("Optimizing (should be about " . $tmp2 . " records)...",'optimizer');


        $sqlQuery = "SELECT UNIX_TIMESTAMP(ADDED)
                  FROM $history_table
                 WHERE VALUE_ID =  '" . $valueID . "'
                   AND ADDED    >= '" . $beginDate . "'
                 ORDER BY ADDED
                 LIMIT 1";

        $firstStart = current(SQLSelectOne($sqlQuery));

        $sqlQuery = "SELECT UNIX_TIMESTAMP(ADDED)
                  FROM $history_table
                 WHERE VALUE_ID = '" . $valueID . "'
                   AND ADDED    <= '" . $endDate . "'
                 ORDER BY ADDED DESC
                 LIMIT 1";

        $lastStart = current(SQLSelectOne($sqlQuery));

        while ($start < $end) {
            if ($start < ($firstStart - $interval)) {
                $start += $interval;
                continue;
            }

            if ($start > ($lastStart + $interval)) {
                $start += $interval;
                continue;
            }

            dprint(".",false);

            $sqlQuery = "SELECT * 
                     FROM $history_table
                    WHERE VALUE_ID = '" . $valueID . "'
                      AND ADDED    >= '" . date('Y-m-d H:i:s', $start) . "'
                      AND ADDED    <  '" . date('Y-m-d H:i:s', $start + $interval) . "'";

            $data = SQLSelect($sqlQuery);
            $total = count($data);

            if ($total > 1) {
                $values = array();

                for ($i = 0; $i < $total; $i++)
                    $values[] = $data[$i]['VALUE'];

                if ($type == 'max')
                    $newValue = max($values);
                elseif ($type == 'sum')
                    $newValue = array_sum($values);
                else
                    $newValue = round(array_sum($values) / $total,4);

                $sqlQuery = "DELETE
                        FROM $history_table
                       WHERE VALUE_ID = '" . $valueID . "'
                         AND ADDED    >= '" . date('Y-m-d H:i:s', $start) . "'
                         AND ADDED    < '" . date('Y-m-d H:i:s', $start + $interval) . "'";

                SQLExec($sqlQuery);

                $addedDate = ($type == 'avg') ? $start + (int)($interval / 2) : $start + $interval - 1;

                $rec = array();
                $rec['VALUE_ID'] = $valueID;
                $rec['VALUE'] = $newValue;
                $rec['ADDED'] = date('Y-m-d H:i:s', $addedDate);

                SQLInsert($history_table, $rec);

                if ($history_table != 'phistory') {
                    SQLExec("OPTIMIZE TABLE " . $history_table);
                }

                $totalRemoved += $total;
            }

            $start += $interval;
        }

        dprint("Done (removed: $totalRemoved)",false);
        debmes("Done (removed: $totalRemoved)",'optimizer');
        return $totalRemoved;
    }


    function processSubscription($event_name, $details = '')
    {
        if ($event_name == 'HOURLY') {
            //...
            $this->getConfig();
            if ($this->config['START_DAILY'] && ((int)date('H')) == ((int)$this->config['START_TIME'])) {
                if (defined('PATH_TO_PHP'))
                    $phpPath = PATH_TO_PHP;
                else
                    $phpPath = IsWindowsOS() ? '..\server\php\php.exe' : 'php';
                safe_exec($phpPath.' '.dirname(__FILE__).'/optimize.php');

                /*
                set_time_limit(3 * 60 * 60);
                if ($this->config['AUTO_OPTIMIZE']) {
                    $this->analyze($out, $this->config['AUTO_OPTIMIZE'], 1);
                }
                $this->optimizeAll();
                */

            }
        }
    }

    /**
     * optimizerdata delete record
     *
     * @access public
     */
    function delete_optimizerdata($id)
    {
        $rec = SQLSelectOne("SELECT * FROM optimizerdata WHERE ID='$id'");
        // some action for related tables
        SQLExec("DELETE FROM optimizerdata WHERE ID='" . $rec['ID'] . "'");
    }


    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        subscribeToEvent($this->name, 'HOURLY');
        $this->getConfig();
        if (!isset($this->config['KEEP_CACHED'])) {
            $this->config['KEEP_CACHED']=30;
            $this->saveConfig();
        }
        if (!isset($this->config['START_DAILY'])) {
            $this->config['START_DAILY'] = 1;
            $this->config['START_TIME'] = 3;
            $this->config['AUTO_OPTIMIZE'] = 10000;
            $this->saveConfig();
        }
        parent::install();
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS optimizerdata');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        optimizerdata -
        */
        $data = <<<EOD
 optimizerdata: ID int(10) unsigned NOT NULL auto_increment
 optimizerdata: CLASS_NAME varchar(255) NOT NULL DEFAULT ''
 optimizerdata: OBJECT_NAME varchar(255) NOT NULL DEFAULT ''
 optimizerdata: PROPERTY_NAME varchar(255) NOT NULL DEFAULT ''
 optimizerdata: KEEP varchar(255) NOT NULL DEFAULT ''
 optimizerdata: OPTIMIZE varchar(255) NOT NULL DEFAULT ''
 optimizerdata: LOG varchar(255) NOT NULL DEFAULT ''
EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgRmViIDI2LCAyMDE2IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/


