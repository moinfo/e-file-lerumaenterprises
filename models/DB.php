<?php

class DB {
    var $hostname, $name, $username, $password;
    var $conn = NULL;
    var $queries = array(); // This will hold multiple queries to be commited as a transaction
    var $result; // Cache for the result of any operation
    var $port = '';
    var $autocommit = true;
    var $error;
////////////////////////////////////////////////////////////////
    /**
     * Initiate the Database class
     * @param int
     * @return self
     */
    function __construct(){
        $this->hostname = DB_HOSTNAME;
        $this->username = DB_USERNAME;
        $this->password = DB_PASSWORD;
        $this->name = DB_NAME;
        $this->connect();
        $this->conn->autocommit($this->autocommit);
    }
////////////////////////////////////////////////////////////////
    /**
     * Connects to the database
     * @param
     * @return boolean
     */
    public function connect(){	//// Connet to database
        if ($this->port != ''){
            $this->conn = new MySQLi($this->hostname, $this->username, $this->password, $this->name, $this->port);
        } else {
            $this->conn = new MySQLi($this->hostname, $this->username, $this->password, $this->name);
        }
        if (mysqli_connect_error()) {
            die('Database Connection Error (' . $this->conn->connect_errno . ') ' . $this->conn->connect_error());
        } else { $this->conn->select_db($this->name); return true; }
    }
    /**
     * In case the connection is lost, reconnect
     * @param
     * @return null
     */
    public function reconnect(){	//// Connet to database
        if ($this->conn->connect_error){
            $this->connect();
        }
    }

////////////////////////////////////////////////////////////////

    /**
     * Disconnect from the database the database
     * @param
     * @return boolean
     */
    public function disconnect(){ $this->conn->close();}
////////////////////////////////////////////////////////////////
    /**
     * Push queries into the query stack
     * @param String mysql query
     * @access	public
     * @return	bool
     */
    function pushQuery($query){
        array_push($this->queries,$query);
    }

    /**
     * Empty the array stack
     * @access	public
     * @return	bool
     */
    function clearQueries(){
        $this->queries = array();
    }

    /**
     * Commit a transaction
     * @access	public
     * @return	bool
     */
    function commitTransaction(){
        $all_ok = true;
        $this->conn->autocommit(FALSE);

        foreach($this->queries as $index=>$sql){
            $this->conn->query($sql) ? NULL : $all_ok = FALSE;
        }

        if($all_ok){
            $this->result = $this->conn->commit()? true : false;
        } else {
            $this->conn->rollback();
            $this->result = false;
        }

        $this->conn->autocommit($this->autocommit);
        return  $this->result;
    }

////////////////////////////////////////////////////////////////
    /**
     * Get the number of affected Rows
     * @access	public
     * @return	integer
     */
    function affectedRows() { return $this->conn->affected_rows; }
////////////////////////////////////////////////////////////////
    /**
     * Perform a query
     * @param String Query screen
     * @param String Type of query
     * @param Mixed Single row flag OR array of parameters for prepared statement
     * @param Array Parameters for prepared statement (if $single_row is a string)
     * @return boolean
     */
    public function query($query, $type = 'ROW',  $single_row = false, $params = null){
        $this->reconnect();

        // Handle prepared statements
        if (is_array($single_row)) {
            $params = $single_row;
            $single_row = ($type === 'ROW');
        }

        if ($params && is_array($params)) {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                $this->log("Mysqli Error: " . $this->conn->error);
                return false;
            }

            // Bind parameters
            $types = str_repeat('s', count($params)); // Assume all strings for simplicity
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            if ($stmt->error) {
                $this->log("Mysqli Error: " . $stmt->error);
                $stmt->close();
                return false;
            }

            $this->result = $stmt->get_result();

            if ($type === 'INSERT') {
                $insertId = $this->conn->insert_id;
                $stmt->close();
                return $insertId;
            } elseif ($type === 'UPDATE' || $type === 'DELETE' || $type === 'CREATE') {
                $affected = $stmt->affected_rows;
                $stmt->close();
                return $affected;
            } else {
                // SELECT query
                if ($this->result) {
                    // Check if we want a single row (ROW type or $single_row is true but not 'ALL')
                    if (($single_row === true || $type === 'ROW') && $single_row !== 'ALL') {
                        $row = $this->result->fetch_assoc();
                        $stmt->close();
                        return $row ? $row : false;
                    } else {
                        // Return all rows for 'ALL' or false $single_row
                        $rows = [];
                        while ($row = $this->result->fetch_assoc()) {
                            $rows[] = $row;
                        }
                        $stmt->close();
                        return $rows;
                    }
                }
                $stmt->close();
                return false;
            }
        }

        // Original query logic for non-prepared statements
        if(in_array($type, ['ROW','UPDATE'])) {
            if($this->result = $this->conn->query($query)){
                $res = $this->result;
            } else {
                $res = $this->result;
            }
            return ($single_row && is_array($res))  ? reset($res) : $res;
        } else {
            return $this->fetchQuery($query, $type, $single_row);
        }

    }


////////////////////////////////////////////////////////////////
    /**
     * Perform a query and return an asscociative array instead of resource
     * @param query
     * @return array()
     */
    public function fetchQuery($query, $type ="SELECT", $single_row = false){
        $this->reconnect();
        $r = array();
        if($this->result = $this->query($query)){
            while ($a = $this->result->fetch_assoc()) {
                array_push($r,$a);
            }
            //if(count($r)==1){ $r = $r[0]; }// if single result is screened//
        } else { $r = null;  $this->log($this->conn->error);}
        return (is_array($r) && $single_row) ? reset($r) : $r;

    }
////////////////////////////////////////////////////////////////

    /**
     * fetch items from the database
     * @param $table
     * @param string $field
     * @param null $key
     * @param null $keyValue
     * @param null $ext
     * @param null $limit
     * @return array()
     */
    public function fetch($table,$field="*",$key=NULL,$keyValue=NULL,$ext=NULL,$limit=NULL){
        $table = (is_array($table))?implode("`,`",$table):$table;
        $field = (is_array($field))?implode(",",$field):$field;

        $this->reconnect();
        $r = array();
        $q = "SELECT ".$field." FROM `". $table . "`";
        if($key!=NULL){$q .= " WHERE ".$key."='". $this->conn->real_escape_string((string) $keyValue)."'";}
        if($ext!=NULL){$q .= " ".$ext."";}
        if($limit!=NULL){$q .= " LIMIT ".$limit."";}
        if($this->result = $this->query($q)){
            while ($a = $this->result->fetch_assoc()) {
                array_push($r,$a);
            }
            //if(count($r)==1){ $r = $r[0]; }// if single result is screened//
        } else { $r = false; }
        return $r;

    }

    /**
     * Alias for the fetch function
     * @param $table
     * @param string $field
     * @param null $key
     * @param null $keyValue
     * @param null $ext
     * @param null $limit
     * @return array()
     */
    public function get($table,$field="*",$key=NULL,$keyValue=NULL,$ext=NULL,$limit=NULL)
    {
        return $this->fetch($table,$field,$key,$keyValue,$ext,$limit);
    }
////////////////////////////////////////////////////////////////

    /**
     * Search for a phrase in a db
     * @param $table
     * @param $column
     * @param $keyword
     * @param string $pre
     * @param string $post
     * @return array()
     */
    public function search($table, $column, $keyword, $pre = "%", $post="%"){
        $table = (is_array($table))?implode("`,`",$table):$table;

        $this->reconnect();
        $r = array();
        $keyword = $this->conn->real_escape_string($keyword);
        if(is_array($column)) {
            $glue = "` LIKE '".$pre.$keyword.$post."' OR `";
            $q = "SELECT * FROM `". $table ."` WHERE `". implode($glue,$column) . "` LIKE '".$pre.$keyword.$post."'";
        } else {
            $q = "SELECT * FROM `". $table ."` WHERE `".$column. "` LIKE '".$pre . $keyword .$post ."'";
        }

        if($this->result = $this->query($q)){
            while ($a = $this->result->fetch_assoc()) {
                array_push($r,$a);
            }

        } else { $r = false; }
        return $r;

    }

////////////////////////////////////////////////////////////////
    /**
     * Insert Item Into Db
     * @param table
     * @param data
     * @return array()
     */
    public function insert($table,$data){
        $this->reconnect();
        $query = "INSERT INTO $table SET ";
        foreach ($data as $key => $value) {
            if ($value != null) {
                $value = $this->conn->real_escape_string((string) $value);
                $query .= " $key ='$value',";
            }

        }
        $query = rtrim($query, ',');

        if($this->result = $this->conn->query($query)){
            return $this->conn->insert_id;
        } else {
            $this->error = $this->conn->error;
            return false;

        }







    }
////////////////////////////////////////////////////////////////
    /**
     * Replace statement
     * @access	public
     * @param	string	the table name
     * @param	data the data array
     * @return	string
     */
    public function replace($table, $data)
    {
        $this->reconnect();
        $cols = array_keys($data);
        $values = array_values($data);
        $conn = $this->conn;
        array_walk($values, function(&$n) use ($conn) {
            if(! is_numeric($n)){ $n = "'".$conn->real_escape_string((string) $n)."'"; }
        });
        $cols_str = join(', ', $cols);
        $vals_str = join(', ' , $values);
        $sql = "REPLACE INTO `{$table}` ({$cols_str}) VALUES ({$vals_str})"; // values escaped above
        if($this->result = $this->conn->query($sql)){
            return true;
        } else {
            $this->error = $this->conn->error;
            return false;
        }
    }

/////////////////////////////////////////////////////////////////
    /**
     * Delete an item from Db
     * @param table
     * @param
     * @return array()
     */
    public function delete($table,$key,$keyValue,$limit=null){
        $limit = ($limit != null)?"LIMIT ".$limit:"";
        $this->reconnect();
        $keyValue = $this->conn->real_escape_string((string) $keyValue);
        $q = "DELETE FROM `{$table}` WHERE `{$key}` = '{$keyValue}' {$limit}";
        if($this->result = $this->conn->query($q)){
            return $this->conn->affected_rows > 0 ? true : false;
        } else {
            $this->error = $this->conn->error;
            return false;
        }

    }


////////////////////////////////////////////////////////////////
    /**
     * Update an item in the DB
     * @param table
     * @param
     * @return array()
     */
    public function update($table, $data, $key, $keyValue , $limit=null){ //@todo remove limit when unnecessary
        $this->reconnect();
        $cols = array_keys($data);
        $values = array_values($data);
        $limit = ($limit != null)?"LIMIT ".$limit:"";
        $str = "";
        foreach($data as $col=>$val){
            $val = $this->conn->real_escape_string((string) $val);
            $str .= "`".$col ."` = '". $val ."',";
        }

        $keyValue = $this->conn->real_escape_string((string) $keyValue);
        $q = "UPDATE `". $table ."` SET ".trim($str,",")." WHERE `".$key."` = '". $keyValue. "' ".$limit."";
        if($this->result = $this->conn->query($q)){
            return true;
        } else {
            $this->error = $this->conn->error;
            return false;
        }
    }

////////////////////////////////////////////////////////////////<br>

    /**
     *The Auto Increment generated by last insert
     * @param null
     * @return mixed
     */
    public function  insertId(){
        return $this->conn->insert_id;
    }
///////////////////////////////////////////////////////////////
    /**
     *Truncate a table
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    function truncate($table)
    {
        $sql =  "TRUNCATE ".$table;
        $this->result = $this->query($sql);
        return $this->result;
    }

    //////////////////////////////////////////////////////////////////

    /**
     * Check if a table exists
     * @param $database
     * @param $tableName
     * @return bool ()
     */
    public function table_exists($database, $tableName)
    {
        $tables = array();
        $tablesResult = $this->query("SHOW TABLES FROM $database;");
        while ($row = $this->conn->fetch_row($tablesResult)) $tables[] = $row[0];
        return(in_array($tableName, $tables));
    }

///////////////////////////////////////////////////////////////

    public function createTable($tableName){

    }

    public function dropTable($tableName){

    }

    public function renameTable($tableName,$newName){

    }
///////////////////////////////////////////////////////////////
    /**
     * Get a specific value from a key
     * @param table
     * @param
     * @return array()
     */
    public function valueFromKey($table,$column,$keyValue,$key="id"){
        $row = $this->fetch($table,"*",$key,$keyValue);
        $row = reset($row);
        return $row[$column];
    }


///////////////////////////////////////////////////////////////////////
    /**
     * Get information about current connection
     * @access	public
     * @return	string
     */
    function connectionInfo(){
        return $this->conn->get_client_info();
    }

///////////////////////////////////////////////////////////////////////////\
    /**
     * Clean query of som staffs
     *
     */
    function cleanQuery($sql){

        // Delete table returns 0 so the hack for this is bellow
        if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql))
        {
            $sql = preg_replace("/^\s*DELETE\s+FROM\s+(\S+)\s*$/", "DELETE FROM \\1 WHERE 1=1", $sql);
        }
        return $sql;
    }

    /**
     * Recent error message in this
     * @access	private
     * @return	string
     */
    function errorMessage()
    {
        return  $this->conn->error;
    }
///////////////////////////////////////////////////////////////////////////
    /**
     * Version number query string
     *
     * @access	public
     * @return	string
     */
    function version(){
        return "SELECT version() AS ver";
    }

    /**
     * Free the result
     * @return	null
     */
    function freeResult()
    {
        if (is_object($this->result))
        {
            mysqli_free_result($this->result);
            $this->result = FALSE;
        }
    }
    private function log($text){
        // Never echo DB errors to the response (information disclosure).
        // Write to the PHP error log instead.
        error_log("Mysqli Error: ".$text);
    }

    public function sanitize(&$data) {
        if(is_array($data)) {
            foreach ($data as $index => $datum) {
                $this->sanitize($data[$index]); // recurse on the element, not the whole array
            }
        } else {
            $data = $this->conn->real_escape_string((string) $data);
        }
    }

    /**
     * Get last insert ID
     * @return int
     */
    public function getLastInsertId() {
        return $this->conn->insert_id;
    }
}


?>