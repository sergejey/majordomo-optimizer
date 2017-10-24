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
class optimizer extends module {
/**
* optimizer
*
* Module class constructor
*
* @access private
*/
function optimizer() {
  $this->name="optimizer";
  $this->title="Optimizer";
  $this->module_category="<#LANG_SECTION_SYSTEM#>";
  $this->checkInstalled();
}
/**
* saveParams
*
* Saving module parameters
*
* @access public
*/
function saveParams($data=0) {
 $p=array();
 if (IsSet($this->id)) {
  $p["id"]=$this->id;
 }
 if (IsSet($this->view_mode)) {
  $p["view_mode"]=$this->view_mode;
 }
 if (IsSet($this->edit_mode)) {
  $p["edit_mode"]=$this->edit_mode;
 }
 if (IsSet($this->tab)) {
  $p["tab"]=$this->tab;
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
function getParams() {
  global $id;
  global $mode;
  global $view_mode;
  global $edit_mode;
  global $tab;
  if (isset($id)) {
   $this->id=$id;
  }
  if (isset($mode)) {
   $this->mode=$mode;
  }
  if (isset($view_mode)) {
   $this->view_mode=$view_mode;
  }
  if (isset($edit_mode)) {
   $this->edit_mode=$edit_mode;
  }
  if (isset($tab)) {
   $this->tab=$tab;
  }
}
/**
* Run
*
* Description
*
* @access public
*/
function run() {
 global $session;
  $out=array();
  if ($this->action=='admin') {
   $this->admin($out);
  } else {
   $this->usual($out);
  }
  if (IsSet($this->owner->action)) {
   $out['PARENT_ACTION']=$this->owner->action;
  }
  if (IsSet($this->owner->name)) {
   $out['PARENT_NAME']=$this->owner->name;
  }
  $out['VIEW_MODE']=$this->view_mode;
  $out['EDIT_MODE']=$this->edit_mode;
  $out['MODE']=$this->mode;
  $out['ACTION']=$this->action;
  $out['TAB']=$this->tab;
  $this->data=$out;
  $p=new parser(DIR_TEMPLATES.$this->name."/".$this->name.".html", $this->data, $this);
  $this->result=$p->result;
}
/**
* BackEnd
*
* Module backend
*
* @access public
*/
function admin(&$out) {
 $this->getConfig();
 $out['START_DAILY']=(int)$this->config['START_DAILY'];
 $out['START_TIME']=(int)$this->config['START_TIME'];

 if ($this->view_mode=='update_settings') {
   global $start_time;
   $this->config['START_TIME']=(int)$start_time;

   global $start_daily;
   $this->config['START_DAILY']=(int)$start_daily;

   $this->saveConfig();
   $this->redirect("?");
 }

 global $analyze;
 if ($analyze) {
  $this->analyze($out);
 }

 global $optimizenow;
 if ($optimizenow) {
  $this->optimizeAll();
  exit;
 }


 if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
  $out['SET_DATASOURCE']=1;
 }
 if ($this->data_source=='optimizerdata' || $this->data_source=='') {
  if ($this->view_mode=='' || $this->view_mode=='search_optimizerdata') {
   $this->search_optimizerdata($out);
  }
  if ($this->view_mode=='edit_optimizerdata') {
   $this->edit_optimizerdata($out, $this->id);
  }
  if ($this->view_mode=='delete_optimizerdata') {
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
 function analyze(&$out) {

 set_time_limit(0);
 $result=array();
$sqlQuery = "SELECT pvalues.ID, properties.TITLE as PTITLE, classes.TITLE as CTITLE, objects.TITLE as OTITLE
               FROM pvalues 
               LEFT JOIN objects ON pvalues.OBJECT_ID = objects.ID
               LEFT JOIN classes ON objects.CLASS_ID  = classes.ID
               LEFT JOIN properties ON pvalues.PROPERTY_ID = properties.ID
             HAVING PTITLE != ''";

$pvalues = SQLSelect($sqlQuery);
$total = count($pvalues);

for ($i = 0; $i < $total; $i++)
{

    if (defined('SEPARATE_HISTORY_STORAGE') && SEPARATE_HISTORY_STORAGE == 1) {
        $history_table = createHistoryTable($pvalues[$i]['ID']);
    } else {
        $history_table = 'phistory';
    }

   $sqlQuery = "SELECT COUNT(*) as TOTAL
                  FROM $history_table
                 WHERE VALUE_ID = '" . $pvalues[$i]['ID'] . "'";

   $tmp = SQLSelectOne($sqlQuery);

   if ($tmp['TOTAL'])
   {

      $grand_total += $tmp['TOTAL'];
      $result['RECORDS'][]=array('CLASS'=>$pvalues[$i]['CTITLE'], 'PROPERTY'=>$pvalues[$i]['PTITLE'], 'OBJECT'=>$pvalues[$i]['OTITLE'], 'TOTAL'=>$tmp['TOTAL']);
      /*
      echo $pvalues[$i]['CTITLE'] . "." . $pvalues[$i]['PTITLE'] . " (object: " . $pvalues[$i]['OTITLE'] . "): ";
      echo $tmp['TOTAL'] > 5000 ? "<b>" . $tmp['TOTAL'] . "</b>" : $tmp['TOTAL'];
      echo "<br />";
      echo str_repeat(' ', 1024);
      
      flush();
      */
   }
}

$out['RECORDS']=$result['RECORDS'];
$out['GRAND_TOTAL']=$grand_total;

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
function usual(&$out) {
 $this->admin($out);
}
/**
* optimizerdata search
*
* @access public
*/
 function search_optimizerdata(&$out) {
  require(DIR_MODULES.$this->name.'/optimizerdata_search.inc.php');
 }
/**
* optimizerdata edit/add
*
* @access public
*/
 function edit_optimizerdata(&$out, $id) {
  require(DIR_MODULES.$this->name.'/optimizerdata_edit.inc.php');
 }


 function optimizeAll() {
  set_time_limit(0);
  $records=SQLSelect("SELECT * FROM optimizerdata");
  $rules=array();
  $total=count($records);
  for($i=0;$i<$total;$i++) {
   $rule=array();
   if ($records[$i]['OBJECT_NAME'] && $records[$i]['OBJECT_NAME']!='*') {
    $key=$records[$i]['OBJECT_NAME'].'.'.$records[$i]['PROPERTY_NAME'];
   } elseif ($records[$i]['CLASS_NAME'] && $records[$i]['CLASS_NAME']!='*') {
    $key=$records[$i]['CLASS_NAME'].'.'.$records[$i]['PROPERTY_NAME'];
   } else {
    $key=$records[$i]['PROPERTY_NAME'];
   }
   $rules[$key]=array('optimize'=>$records[$i]['OPTIMIZE']);
   if ($records[$i]['KEEP']) {
    $rules[$key]['keep']=(int)$records[$i]['KEEP'];
   }
  }

//print_r($rules);

//STEP 2 -- optimize values in time

//$sqlQuery = "SELECT DISTINCT(VALUE_ID) FROM phistory";
//$values = SQLSelect($sqlQuery);

     $sqlQuery = "SELECT pvalues.ID as VALUE_ID, properties.TITLE as PTITLE, classes.TITLE as CTITLE, objects.TITLE as OTITLE
               FROM pvalues 
               LEFT JOIN objects ON pvalues.OBJECT_ID = objects.ID
               LEFT JOIN classes ON objects.CLASS_ID  = classes.ID
               LEFT JOIN properties ON pvalues.PROPERTY_ID = properties.ID
             HAVING PTITLE != ''";

     $values = SQLSelect($sqlQuery);

$total = count($values);

for ($i = 0; $i < $total; $i++)
{
   $value_id = $values[$i]['VALUE_ID'];

    if (defined('SEPARATE_HISTORY_STORAGE') && SEPARATE_HISTORY_STORAGE == 1) {
        $history_table = createHistoryTable($value_id);
    } else {
        $history_table = 'phistory';
    }

    $sqlQuery = "SELECT pvalues.ID, properties.TITLE as PTITLE, objects.TITLE as OTITLE, classes.TITLE as CTITLE
                  FROM pvalues
                  LEFT JOIN objects ON pvalues.OBJECT_ID = objects.ID
                  LEFT JOIN properties ON pvalues.PROPERTY_ID = properties.ID
                  LEFT JOIN classes ON classes.ID = objects.CLASS_ID
                 WHERE pvalues.ID = '" . $value_id . "'";

   $pvalue = SQLSelectOne($sqlQuery);

   if ($pvalue['CTITLE'] != '')
   {
      $key = $pvalue['CTITLE'] . '.' . $pvalue['PTITLE'];
      //echo $key."<br/>";
      $rule = '';
   
      if ($rules[$key])
         $rule = $rules[$key];
      elseif ($rules[$pvalue['OTITLE'] . '.' . $pvalue['PTITLE']])
         $rule = $rules[$pvalue['OTITLE'] . '.' . $pvalue['PTITLE']];
      elseif ($rules[$pvalue['PTITLE']])
         $rule = $rules[$pvalue['PTITLE']];

      if ($rule)
      {
         //processing
         echo "<h3>" . $pvalue['OTITLE'] . " (" . $key . ")</h3>";
         
         $sqlQuery = "SELECT COUNT(*) as TOTAL
                        FROM $history_table
                       WHERE VALUE_ID = '" . $value_id . "'";

         $total_before = current(SQLSelectOne($sqlQuery));

         if (isset($rule['keep']))
         {
            echo " removing old (" . (int)$rule['keep'] . ")";
            $sqlQuery = "DELETE
                           FROM $history_table
                          WHERE VALUE_ID = '" . $value_id . "'
                            AND TO_DAYS(NOW()) - TO_DAYS(ADDED) >= " . (int)$rule['keep'];
            SQLExec($sqlQuery);
         }

         if ($rule['optimize'])
         {
            echo str_repeat(' ', 1024);
            flush();

            $sqlQuery = "SELECT UNIX_TIMESTAMP(ADDED)
                           FROM $history_table
                          WHERE VALUE_ID = '" . $value_id . "'
                          ORDER BY ADDED
                          LIMIT 1";

            echo "<br /><b>Before last MONTH</b><br />";
            $end = time() - 30 * 24 * 60 * 60; // month end older
            $start = current(SQLSelectOne($sqlQuery));
            $interval = 2 * 60 * 60; // two-hours interval
            $this->optimizeHistoryData($value_id, $rule['optimize'], $interval, $start, $end);

            echo str_repeat(' ', 1024);
            flush();

            echo "<br /><b>Before last WEEK</b><br />";
            $start = $end + 1;
            $end = time() - 7 * 24 * 60 * 60; // week and older
            $interval = 1 * 60 * 60; // one-hour interval
            $this->optimizeHistoryData($value_id, $rule['optimize'], $interval, $start, $end);

            echo str_repeat(' ', 1024);
            flush();

            echo "<br /><b>Before YESTERDAY</b><br />";
            $start = $end + 1;
            $end = time() - 1 * 24 * 60 * 60; // day and older
            $interval = 20 * 60; // 20 minutes interval
            $this->optimizeHistoryData($value_id, $rule['optimize'], $interval, $start, $end);

            echo str_repeat(' ', 1024);
            flush();

            echo "<br /><b>Before last HOUR</b><br />";
            $start = $end + 1;
            $end = time() - 1 * 60 * 60; // 1 hour and older
            $interval = 3 * 60; // 3 minutes interval
            $this->optimizeHistoryData($value_id, $rule['optimize'], $interval, $start, $end);
         }
         
         $sqlQuery = "SELECT COUNT(*) as TOTAL
                        FROM phistory
                       WHERE VALUE_ID = '" . $value_id . "'";
         $total_after = current(SQLSelectOne($sqlQuery));
         echo " <b>(changed " . $total_before . " -> " . $total_after . ")</b><br />";
      }
   }
}

SQLExec("OPTIMIZE TABLE phistory;");

echo "<h1>DONE!</h1>";



 }

/**
 * Summary of optimizeHistoryData
 * @param mixed $valueID  Id value
 * @param mixed $type     Type
 * @param mixed $interval Interval
 * @param mixed $start    Begin date
 * @param mixed $end      End date
 * @return double|int
 */
function optimizeHistoryData($valueID, $type, $interval, $start, $end)
{
   $totalRemoved = 0;
   
   if (!$interval)
      return 0;

   $beginDate = date('Y-m-d H:i:s', $start);
   $endDate = date('Y-m-d H:i:s', $end);

   echo "Value ID: $valueID <br />";

    if (defined('SEPARATE_HISTORY_STORAGE') && SEPARATE_HISTORY_STORAGE == 1) {
        $history_table = createHistoryTable($valueID);
    } else {
        $history_table = 'phistory';
    }

   echo "Interval from " . $beginDate . " to " . $endDate . " (every " . $interval . " seconds)<br />";
   
   $sqlQuery = "SELECT COUNT(*)
                  FROM $history_table
                 WHERE VALUE_ID =  '" . $valueID . "'
                   AND ADDED    >= '" . $beginDate . "'
                   AND ADDED    <= '" . $endDate . "'";

   $totalValues = (int)current(SQLSelectOne($sqlQuery));
   
   echo "Total values: " . $totalValues . "<br>";
   
   if ($totalValues < 2)
      return 0;

   $tmp = $end - $start;
   $tmp2 = round($tmp / $interval);
   
   if ($totalValues <= $tmp2)
   {
      echo "... number of values ($totalValues) is less than optimal (" . $tmp2 . ") (skipping)<br />";
      return 0;
   }

   echo "Optimizing (should be about " . $tmp2 . " records)...";

   echo str_repeat(' ', 1024);
   flush();

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

   while ($start < $end)
   {
      if ($start < ($firstStart - $interval))
      {
         $start += $interval;
         continue;
      }

      if ($start > ($lastStart + $interval))
      {
         $start += $interval;
         continue;
      }

      echo ".";
      echo str_repeat(' ', 1024);
      flush();

      $sqlQuery = "SELECT * 
                     FROM $history_table
                    WHERE VALUE_ID = '" . $valueID . "'
                      AND ADDED    >= '" . date('Y-m-d H:i:s', $start) . "'
                      AND ADDED    <  '" . date('Y-m-d H:i:s', $start + $interval) . "'";
      
      $data = SQLSelect($sqlQuery);
      $total = count($data);
    
      if ($total > 1)
      {
         $values = array();
      
         for ($i = 0; $i < $total; $i++)
            $values[] = $data[$i]['VALUE'];
     
         if ($type == 'max')
            $newValue = max($values);
         elseif ($type == 'sum')
            $newValue = array_sum($values);
         else
            $newValue = array_sum($values) / $total;

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

         if ($history_table!='phistory') {
             SQLExec("OPTIMIZE TABLE ".$history_table);
         }

         $totalRemoved += $total;
      }
      
      $start += $interval;
   }

   echo "<b>Done</b> (removed: $totalRemoved)<br>";
   SQLExec("OPTIMIZE TABLE `phistory`");

   return $totalRemoved;
}



 function processSubscription($event_name, $details='') {
  if ($event_name=='HOURLY') {
   //...
   $this->getConfig();
   if ($this->config['START_DAILY'] && ((int)date('H'))==((int)$this->config['START_TIME'])) {
    $this->optimizeAll();
   }
  }
 }

/**
* optimizerdata delete record
*
* @access public
*/
 function delete_optimizerdata($id) {
  $rec=SQLSelectOne("SELECT * FROM optimizerdata WHERE ID='$id'");
  // some action for related tables
  SQLExec("DELETE FROM optimizerdata WHERE ID='".$rec['ID']."'");
 }



/**
* Install
*
* Module installation routine
*
* @access private
*/
 function install($data='') {
  subscribeToEvent($this->name, 'HOURLY');
  parent::install();
 }
/**
* Uninstall
*
* Module uninstall routine
*
* @access public
*/
 function uninstall() {
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
 function dbInstall($data) {
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
