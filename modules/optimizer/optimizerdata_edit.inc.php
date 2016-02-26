<?php
/*
* @version 0.1 (wizard)
*/
  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $table_name='optimizerdata';
  $rec=SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");
  if ($this->mode=='update') {
   $ok=1;
  //updating 'CLASS_NAME' (varchar)
   global $class_name;
   $rec['CLASS_NAME']=$class_name;
  //updating 'OBJECT_NAME' (varchar)
   global $object_name;
   $rec['OBJECT_NAME']=$object_name;
  //updating 'PROPERTY_NAME' (varchar)
   global $property_name;
   $rec['PROPERTY_NAME']=$property_name;
  //updating 'KEEP' (varchar)
   global $keep;
   $rec['KEEP']=(int)$keep;
  //updating 'OPTIMIZE' (varchar)
   global $optimize;
   $rec['OPTIMIZE']=$optimize;
  //UPDATING RECORD
   if ($ok) {
    if ($rec['ID']) {
     SQLUpdate($table_name, $rec); // update
    } else {
     SQLExec("DELETE FROM $table_name WHERE CLASS_NAME='".DBSafe($rec['CLASS_NAME'])."' AND OBJECT_NAME='".DBSafe($rec['OBJECT_NAME'])."' AND PROPERTY_NAME='".DBSafe($rec['PROPERTY_NAME'])."'");
     $new_rec=1;
     $rec['ID']=SQLInsert($table_name, $rec); // adding new record
    }
    $out['OK']=1;
   } else {
    $out['ERR']=1;
   }
  }
  if (is_array($rec)) {
   foreach($rec as $k=>$v) {
    if (!is_array($v)) {
     $rec[$k]=htmlspecialchars($v);
    }
   }
  }

  if (!$rec['ID']) {
   global $class_name;
   $rec['CLASS_NAME']=$class_name;
   global $object_name;
   $rec['OBJECT_NAME']=$object_name;
   global $property_name;
   $rec['PROPERTY_NAME']=$property_name;

  }

  outHash($rec, $out);
