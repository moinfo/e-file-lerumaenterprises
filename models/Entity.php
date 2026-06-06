<?php

/**
 * This class will act as a bridge that maps any object to a single table within a table
 * The class can be reusable for any table
 * @author ringle
 */
#[\AllowDynamicProperties] // silence PHP 8.2+ dynamic-property deprecations (harmless comment on PHP 7); inherited by all subclasses
class Entity {
    var $table;
    var $primary_key = array();
    var $auto_increment = array();
    var $ignore = array(); // columns to ignore
    var $mandatory = array();
    var $columns=array();
    var $column_list =array();
    var $db;
    /**
     * If id is specified then the object will be taken from database.
     * @param type $id
     */
    public function __construct($id = null) {
        $this->db = new DB();
        $this->columns = $this->db->query("SHOW columns FROM ".$this->table);
        foreach($this->columns as $key=>$item){
            if(!in_array($item['Field'], $this->ignore)){
                array_push($this->column_list,$item['Field']);
                if($item['Null']=="NO"){
                    array_push($this->mandatory,$item['Field']);
                }
                if($item['Key']=="PRI"){
                    array_push($this->primary_key, $item['Field']);
                }
                if($item['Extra']=="auto_increment"){
                    array_push($this->auto_increment, $item['Field']);
                } else {
                    $name = $item['Field'];
                    $value = ($item['Default'] != "CURRENT_TIMESTAMP")?$item['Default']:NULL;
                    if($value==NULL && $item['Null'] == 'NO' && ($item['Type'] == "int(11)" || $item['Type']="float")){
                        $value = 0;
                    }
                    $this->$name = $value;
                }
            }
        }


        if($id!=null){
            $res = $this->db->query("SELECT * FROM ".$this->table." WHERE id=?","SELECT",true,[$id]);
            if(is_array($res) && count($res)>0){
                array_walk($res, function(&$item,$key){
                    if(!in_array($item, $this->ignore)){
                        $this->$key = $item;
                    }
                });
            } else {
                return false;
            }
        } else {

        }
    }
/////////////////////////////// db functions ///////////////////////////////////////
    private function isPrimary($column){
        return in_array($column, $this->primary_key);
    }
    private function isAutoIncrement($column){
        return in_array($column, $this->auto_increment);
    }
////////////////////////////////////////////////////////////////////////////////////

    /**
     * Retrieve an array containing all data of this object that have corresponding columns in the db table
     * @param null $columns optionally specify columns to be retrieved
     * @return array
     */
    public function getData($columns = NULL){ //
        $data = array();

        if($columns != NULL){
            $cols = $columns;
        } else {
            $cols = $this->column_list;
        }
        foreach($cols as $key){
            if(!in_array($key, $this->ignore)){
                $data[$key] = (isset($this->$key) ? $this->$key : NULL);
            }
        }
        return $data;
    }

    /**
     * Insert this object in to the database and return the insert id
     * @return bool|int
     */
    public function add(){ // Add this entity to the database            
        $data = $this->getData();
        $data = array_diff_key($data, array_flip($this->auto_increment));
        if($res = $this->db->insert($this->table, $data)){
            $myAI = reset($this->auto_increment);
            $this->$myAI = $res;

        } else {
            $res = false;
        }
        return $res;
    }

    /**
     * Update this entity on the database
     * @return bool
     */
    public function update(){ //
        $data = $this->getData();
        $data = array_diff_key($data, array_flip($this->auto_increment));
        $data = array_diff_key($data, array_flip($this->ignore));
        $data = array_filter($data);
        $myPk = reset($this->primary_key);
        return $this->db->update($this->table,  $data, 'id', $this->$myPk);
    }

    /**
     * Check if the object already exists and update it or just add it
     * @return bool|int
     */
    public function save(){ // if new add else update
        $myPK = reset($this->primary_key);
        if(isset($this->$myPK)){
            return $this->update();
        } else {
            return $this->add();
        }
    }

    /**
     * Delete this object from database table // TODO implement softdelete too
     * @return bool
     */
    public function delete(){ // Delete this item from the database
        $myPK =  reset($this->primary_key);
        return $this->db->query("DELETE FROM ".$this->table." WHERE ".$myPK." = '".$this->$myPK."'","DELETE");
    }

    /**
     * Get value of a specific field/(table column) of this object
     * @param $columnName
     * @return mixed
     */
    public function get($columnName){ // Return a specific column value
        if(in_array($columnName,$this->column_list)){
            return $this->$columnName;
        }
    }

    /**
     * Assign value to a field (table column) of this object
     * @param $columnName
     * @param $value
     */
    public function set($columnName,$value){ // Assign a specific column value
        if(in_array($columnName,$this->column_list)){
            $this->$columnName = $value;
        }
    }

    /**
     * Map a key-value array to fields of this object (table columns)
     * @param $keyValue_array
     */
    public function patch($keyValue_array){
        if(is_array($keyValue_array)){
            foreach ($keyValue_array as $key => $value) {
                $this->set($key,$value);
            }
        }
    }

    /**
     * Returns true if this object is already created (is having an id)
     * @param $hard_check [forece checking in the database if it really exists]
     * @return bool
     */
    public function exists($hard_check = false) {
        $myPK =  reset($this->primary_key);
        if(isset($this->$myPK)) {
            if($hard_check) {
                $check = $this->db->query("SELECT COUNT(id) as ids FROM {$this->table} WHERE {$myPK} = '".$this->$myPK."'", "SELECT", 1);
                return (is_array($check)) ? $check['ids'] > 0 : false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    public static function all($objects=false, $where=""){
        $me = get_called_class();
        $n = new $me();
        $table = $n->table;
        $wh = ($where != "")?"WHERE ".$where:"";
        $res = $me->db->query("SELECT * FROM ".$table." ".$wh);
        $ret = array();
        foreach ($res as $key => $itm) {
            if($objects){
                array_push($ret, new $me($itm['id'])); //todo change the id to primary key
            } else {
                array_push($ret, $itm);
            }
        }

        return $ret;

    }
}
