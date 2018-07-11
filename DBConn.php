<?php

require_once('HttpException.php');

if (! class_exists ( "DBConn" )) {
//Clase de Acceso a datos unificada...

class DBConn{
    private $connection;
    private $cn; 
    private $id;
    private $rows; 
    private $sql;
    private $fields; 
    private $key; 
    private $tablestamps;
    private $flags;
    private $tables; 
    private $select;
    private $order; 
    private $group;
    private $where; 
    private $includeDeleted;
    private $ignore;
    private $with;
    private $raw;

    private $active = "DEFAULT"; // Default work connection 
    private $debug = true; // Debug mode for dislaying errors 
    private $protocol = "MYSQL"; // Default database engine 
    private $session = "COMP_USR"; // Session variable with the ID of the current user 
    

    /**
     * 
     * @param type $conn - specific connection to use 
     * @param type $session - user session variable name 
     * @param type $pro - specific engine to use (MYSQL, SQLDRIVER)
     */
    public function __construct($conn = null, $session = null, $pro = null){
       if($conn)
           $this->active = $conn;
       if($session)
           $this->session = $session;
       if($pro)
           $this->protocol = $pro;
       
       $this->connection['DEFAULT'] = array('HOST' => 'localhost', 'USER' => 'root', 'PWD' => '123456', 'DB' => 'mydatabase');
       
       
       $this->tablestamps = array(
           "updated_by" => $_SESSION[$this->session], 
           "updated_at" => "NOW()"
       );
       $this->flags = array("active");
       $this->init();
       
    }
    
    /**
     * initialize class vars to use them 
     */
    private function init() {
       $this->where = array();
       $this->select = array();
       $this->order = array();
       $this->group = array();
       $this->ignore = array();
       $this->raw = array();
       $this->includeDeleted = false;
    }
    
    /**
     * get the current time zone according with the summer schedule
     * @return string 
     */
    private function getTimezone() {
        $year = date("Y");
        $now =strtotime(date("Y-m-d"));  
        $start_summer = strtotime($year . "-03-31 next Sunday");
        $end_summer = strtotime($year . "-11-01 last Sunday");
        if ($now >= $start_summer && $now < $end_summer) 
            return "-5:00";
        else 
            return "-6:00";
    } 
    
    /**
     * set the session var name for current user 
     * @param type $session
     * @return $this
     */
    public function setSession($session) {
        $this->session = $session;
        return $this;
    }
    
    /**
     * set the fields for stamps used for updates automatically on any table
     * @param type $stamps
     * @return $this
     */
    public function setTablestamps($stamps) {
        $this->tablestamps = $stamps;
        return $this;
    }
    
    /**
     * set the fields used as flags for updates instead of deletes on any table
     * @param type $flags
     * @return $this
     */
    public function setFlags($flags) {
        $this->flags = $flags;
        return $this;
    }
    
    /**
     * set the current database engine to use 
     * @param type $pro
     * @return $this
     */
    public function setProtocol($pro){
        $this->protocol = $pro;
        return $this;
    }
    
    /**
     * get the number of rows affected by the last operation 
     * @return type
     */
    public function affectedRows(){
        return $this->rows;
    }
    
    /**
     * get the current credentials used in this connection 
     * @return type
     */
    public function getCredentials() {
        return $this->connection[$this->active];
    }
    
    /**
     * get the auto inserted ID on the last query 
     * @return type
     */
    public function getInserted() {
        return $this->id; 
    }
    
    /**
     * opens the database connection and get the active link 
     * @return type
     */
    public function Connect(){
      try{
            switch($this->protocol){
                case "MYSQL":
                    $this->cn = mysqli_connect($this->connection[$this->active]['HOST'],
                                       $this->connection[$this->active]['USER'],
                                       $this->connection[$this->active]['PWD']) 
                        or $this->Error("CONNECTION ERROR $this->active");
                    $db = mysqli_select_db($this->cn, $this->connection[$this->active]['DB']) 
                        or $this->Error("CONNECTION ERROR $this->active");

                break;
                case "SQLDRIVER":
                    $this->cn = sqlsrv_connect($this->connection[$this->active]['HOST'] . ($this->connection[$this->active]['PORT']?",".$this->connection[$this->active]['PORT']:""), 
                            array("Database" => $this->connection[$this->active]['DB'], 
                                  "UID" => $this->connection[$this->active]['USER'], 
                                  "PWD" => $this->connection[$this->active]['PWD']) 
                            );
                    if(!$this->cn) $this->Error("CONNECTION ERROR $this->active");
                    break;
            }
        }catch(Exception $ex){
            $this->Error($ex->getMessage());
        }
        return $this->cn;
    }
    
    /**
     * Close the active connection 
     */
    public function CloseDB(){
        try {
            switch($this->protocol){
                case "MYSQL":
                    mysqli_close($this->cn);
                    break;
                case "SQLDRIVER":
                    sqlsrv_close($thi->cn);
                    break;
            }
        } catch (Exception $ex) {
            $this->Error($ex->getMessage());
        }
        
    }
    
    /**
     * get the results from a query and return them as a list in ARRAY or OBJECT format 
     * @param type $sql
     * @param type $format
     * @return type
     */
    public function getArray($sql = "", $format = "ARRAY"){
        if(!$sql) $sql = $this->getSQL();
        $ds = $this->query($sql);
        $result = array();
        try {
            switch($this->protocol){
                case "MYSQL":
                    switch($format){
                        case "ARRAY":
                            $result = mysqli_fetch_all($ds, "MYSQLI_ASSOC");
                        break;
                        case "OBJECT":
                             while($row = mysqli_fetch_object($ds)){
                                $result[] = $row;
                             }
                        break;
                    }
                break;
                case "SQLDRIVER":
                    switch($format){
                        case "ARRAY":
                            while($r = sqlsrv_fetch_array($ds, SQLSRV_FETCH_ASSOC))
                                $result[] = $r;
                        break;
                        case "OBJECT":
                             while($r = sqlsrv_fetch_object($ds))
                                $result[] = $r;
                        break;
                    }
                    break;
            }
        } catch (Exception $ex) {
            $this->Error($ex->getMessage());
        } 
        
        return $this->getWiths($result);
    }
    
    /**
     * get the results from a query in JSON list format 
     * @param type $sql
     * @return type
     */
    public function getJSON($sql = "") {
        if(!$sql) $sql = $this->getSQL();
        return json_encode($this->getArray($sql));
    }
    
    /**
     * get only one row from a query in OBJECT format 
     * @param type $sql
     * @return type
     */
    public function getObject($sql = ""){
        if(!$sql) $sql = $this->getSQL();
        $ds = $this->query($sql);
        try {
            switch($this->protocol){
                case "MYSQL":
                    if(mysqli_num_rows($ds) > 0){
                        $obj = mysqli_fetch_object($ds);
                    }else{
                        $obj = null;
                    }
                break;
                case "SQLDRIVER":
                    if(sqlsrv_has_rows($ds))
                        $obj = sqlsrv_fetch_object($ds);
                    else
                        $obj = null;
                break;
            }
        } catch (Exception $ex) {
            $this->Error($ex->getMessage());
        } 
        
        return $obj;
    }
    
    /**
     * get only the first value from a query 
     * @param type $sql
     * @return type
     */
    public function getOne($sql = ""){
        if(!$sql) $sql = $this->getSQL();
        $ds = $this->query($sql);
        try {
            switch($this->protocol){
                case "MYSQL":
                    if(mysqli_num_rows($ds) > 0){
                        $data = mysqli_fetch_row($ds);
                        $one = htmlspecialchars($data[0]);
                    }else
                        $one = null;
                break;
                case "SQLDRIVER":
                    if(sqlsrv_has_rows($ds)){
                        $data = sqlsrv_fetch_array($ds);
                        $one = $data[0];
                    }else
                        $one = null;
                    break;
            }
        } catch (Exception $ex) {
            $this->Error($ex->getMessage());
        }
        
        return $one;
    }
    
    /**
     * get the next available ID from a table
     * @param type $field
     * @param type $table
     * @param type $condition
     * @return type
     */
    public function getID($field, $table, $condition = ""){ 	
        try {
           switch($this->protocol){
                case "MYSQL":
                    $sql = "select IFNULL(max(" . $field . "), 0) + 1 from " . $table . ($condition==""?"":" where " . $condition);		
                    $id = $this->getOne($sql);
                break;
                case "SQLDRIVER":
                    $sql = "select ISNULL(max(" . $field . "), 0) + 1 from " . $table . ($condition==""?"":" where " . $condition);		
                    $id = $this->getOne($sql);
                break;
            }
        } catch (Exception $ex) {
            $this->Error($ex->getMessage());
        } 
        
        return $id;
    }
    
    /**
     * check existence of something into a table based on the condition given 
     * @param type $field
     * @param type $table
     * @param type $condition
     * @return type
     */
    public function exist($field, $table, $condition){
        try {
            switch($this->protocol){
                case "MYSQL":
                    $sql = "select IFNULL(" . $field . ", 0) from " . $table . " where " . $condition;		
                    $ds = $this->query($sql);
                    $res = mysqli_fetch_row($ds);
                break;
                case "SQLDRIVER":
                    $sql = "select ISNULL(" . $field . "), NULL) from " . $table . ($condition==""?"":" where " . $condition);		
                    $ds = $this->query($sql);
                    $res = sqlsrv_fetch_array($ds);
                break;
            }
        } catch (Exception $ex) {
            $this->Error($ex->getMessage());
        } 
        
        return $res[0];
    }
    
    /**
     * executes a query, only for insert, update, delete operations
     * @param type $sql
     * @return type
     */
    public function execute($sql){
        $this->query($sql);
        $this->CloseDB();
        return $this->rows;
    }
    
    /**
     * maps an array list, getting a plain value array or concated string 
     * @param type $data
     * @param type $value
     * @param type $implode
     * @return type
     */
    public function lists($data, $value, $implode = false){
        $array = Array();
        foreach($data as $r){
            $array[ ] = $r[$value];
        }
        if($implode)
            return implode (",", $array);
        return $array;
    }
    
    /**
     * save a element into the table, based on the WHERE condition for insert or update 
     * @param type $table
     * @param type $data
     * @param type $where
     * @return type
     */
    public function save($table, $data, $where = array()){
        $this->getTable($table);
        if(array_filter($where)){
            $sql = "update $table set ";
        }else{
            $sql = "insert into $table set ";
        }
        $sql .= implode(",", array_merge( $this->build($data), $this->getStamps() ) );
        if(array_filter($where)){
            $sql .= " where " . $this->build($where, " and ");
        }
        return $this->execute($sql);
    }
    
    /**
     * delete or update with the declared flags, some records based on the where conditions 
     * @param type $table
     * @param type $where
     * @return type
     */
    public function delete($table, $where) {
        if($where){
            $this->getTable($table);
            if($delete = $this->softDelete()){
                $sql = "update $table set " . implode(", ", array_merge($this->build($delete), $this->getStamps() ));
            }else{
                $sql = "delete from $table"; 
            }
            $sql .= " where ";
            if(is_numeric($where)){
                $sql .= $this->key->name . " = " . $where;
            }elseif(array_filter($where)){
                $sql.= $this->build($where, " and ");
            }else{
                $this->Error("Where format not valid");
            }
            return $this->execute($sql);
        }else{
            $this->Error("No Where Statement declared");
        }
    }
    
    /**
     * set the fields to be included on the select part for the query builder 
     * @param type $params
     * @return $this
     */
    public function select($params) {
        if(is_array($params)){
            $this->select = $params;
        }else{
            $this->select = array($params);
        }
        return $this;
    }
    
    /**
     * set the main table to be the source for the query builder 
     * @param type $source
     * @return $this
     */
    public function from($source) {
        $this->tables = array();
        $this->with = array();
        $this->tables[0] = array("name" => $source);
        return $this;
    }
    
    /**
     * set one table to make a join with another in the tables array, pointing out the  
     * name of this table, the target table to join with an index reference, 
     * the field name for relationship and the join type (LEFT, RIGHT...)
     * @param type $params
     * @return $this
     */
    public function join($params) {
        if($this->tables[0]){
            if(is_array($params)){
                $name = $params[0];
                $target = $params[1] ? $params[1] : 0; 
                $relation = $params[2] ? $params[2] : $this->getTarget($target) . "_id"; 
                $join = $params[3] ? $params[3] : ""; 
            }else{
                $name = $params;
                $target =  0; 
                $relation = $this->getTarget(0) . "_id"; 
                $join = ""; 
            }
            $this->tables[] = array("name" => $name, "target" => $target, "relation" => $relation,  "join" => $join);
            
        }else{
            $this->Error("Not main source selected yet");
        }
        return $this;
    }
    
    /**
     * set the order fields for the query builder 
     * @param type $params
     * @return $this
     */
    public function order($params) {
        if(is_array($params)){
            $this->order = $params;
        }else{
            $this->order = array($params);
        }
        return $this;
    }
    
    /**
     * set the group fields for the query builer 
     * @param type $params
     * @return $this
     */
    public function group($params) {
        if(is_array($params)){
            $this->group = $params;
        }else{
            $this->group = array($params);
        }
        return $this;
    }
    
    /**
     * set the group where conditions for the query builer 
     * @param type $params
     * @return $this
     */
    public function where($params) {
        if(is_array($params)){
            $this->where = $params;
        }else{
            $this->where = array($params);
        }
        return $this;
    }
    
    /**
     * set the RAW special conditions for the query builer 
     * @param type $params
     * @return $this
     */
    public function whereRaw($params) {
        if(is_array($params)){
            foreach($params as $i => $raw){
                $this->raw[$i][] = $raw;
            }
        }else{
            $this->Error("RAW Statement must be an array");
        }
        return $this;
    }
    
    /**
     * include records marked as DELETED with the flags, to be included in query 
     * @param type $params
     * @return $this
     */
    public function withDeletes() {
        
        $this->includeDeleted = true;
        
        return $this;
    }
    
    /**
     * set one relation with relative data that has to be extracted with the main query, 
     * attached as an ARRAY LIST on the results
     * @param type $table
     * @param type $foreign
     * @param type $alias
     * @param type $pluck
     * @return $this
     */
    public function withMany($table, $foreign, $alias = "", $pluck = "") {
        $this->addWith("MANY", $table, $foreign, $alias, $pluck);
        return $this;
    }
    
    /**
     * set one relation with relative data that has to be extracted with the main query, 
     * attached as a SINGLE OBJECT on the results
     * @param type $table
     * @param type $foreign
     * @param type $alias
     * @param type $pluck
     * @return $this
     */
    public function withOne($table, $foreign, $alias = "", $pluck = "") {
        $this->addWith("ONE", $table, $foreign, $alias, $pluck);
        return $this;
    }
    
    
    /**
     * Ignore the flags declared to reach all the records "DELETEDS" on any table
     * @param type $params
     * @return $this
     */
    public function ignoreActive($params) {
        if(is_array($params)){
            $this->ignore = $params;
            return $this;
        }else{
            $this->Error("Ignore statement must be an array");
        }
    }
    
    /**
     * Build and return all the query saved in the instance 
     * @return type
     */
    public function getSQL() {
        $sql = "";
        $sql .= $this->getSelect();
        $sql .= $this->getSource();
        $sql .= $this->getWhere();
        $sql .= $this->getGroup();
        $sql .= $this->getOrder();
        
        $this->init();
        return $sql;
    }
    
    /**
     * receive a sql query ready to execute on the database and return the results 
     * @param type $sql
     * @return type
     */
    private function query($sql){
        $this->sql = $sql;
        $this->Connect();
        try{
            switch($this->protocol){
                case "MYSQL":
                    mysqli_query($this->cn, "SET NAMES 'utf8'");
                    mysqli_query($this->cn, "SET time_zone = '" . $this->getTimezone() . "'");
                    $result = mysqli_query($this->cn, $sql) or $this->Error();
                    $this->rows = mysqli_affected_rows($this->cn);
                    $this->id = mysqli_insert_id($this->cn);
                break;
                case "SQLDRIVER":
                    $result = sqlsrv_query($this->cn, $sql) or $this->Error();
                    $this->rows = sqlsrv_rows_affected($result);
                    break;
            }
        } catch (Exception $ex) {
            $this->Error($ex->getMessage());
        }
        return $result;
    }
    
    private function addWith($type, $table, $foreign, $alias, $pluck) {
        if($this->tables){
            $this->with[$type][] = array("table" => $table, "foreign" => $foreign, "alias" => $alias ? $alias : $table, "pluck" => $pluck);
        }else{
            $this->Error("With statement needs a main source table selected");
        }
        return $this;
    }
    
    private function getTable($table) {
        try {
            $meta = new static();
            $columns = mysqli_fetch_all($this->query("SHOW COLUMNS FROM $table"), MYSQLI_ASSOC);
            $this->fields = $this->lists($columns, "Field");
            $meta->fields = $this->fields;

            $fn = function($item) {
                return ($item['Key'] == "PRI");
            };
            $this->key = new static();
            if($k = array_filter($columns, $fn)){
                $this->key->name = $k[0]['Field'];
                $this->key->auto = substr_count($k[0]['Extra'], "auto_increment");
            }
            $meta->key = $this->key;
        } catch (Exception $ex) {
            $this->Error($ex->getMessage());
        } 
        
        return $meta;
    }
    
    private function softDelete(){
        if($this->flags){
            $delete = array();
            foreach($this->flags as $flag){
                if(in_array($flag, $this->fields))
                    $delete[$flag] = 0;
            }
            return $delete;
        }
        return false;
    }

    private function getStamps() {
        if($this->tablestamps){
            $stamps = array();
            foreach($this->tablestamps as $k => $v){
                if(in_array($k, $this->fields) && $v){
                    $stamps[] = "$k = $v";
                }
            }
            return $stamps;
        }
    }
    
    private function build($array, $join = "") {
        foreach($array as $k => $v){
            if(in_array($k, $this->fields) && !in_array($k, array_keys($this->tablestamps)) ) 
              $builder[$k] = $v; 
        }
        
        $fn_map = function($k, $v) {
           return $k .  " = " . (isset($v) ? "'$v'" : "null");
        };
        $map = array_map($fn_map, array_keys($builder), $builder);
        
        if($join)
            return implode($join, $map);
        return $map;
    }
   
    private function getSelect() {
        $sql = "SELECT "; 
        if($this->select){
            if(count($this->select) <= count($this->tables)){
                foreach($this->select as $i => $select){
                    foreach(explode(",", $select) as $field){
                        $array[] = $this->tables[$i]['name'] . "." . trim($field);
                    }
                }
                $sql .= implode(", ", $array);
            }else{
                $this->Error("Select statement doesn't match with tables count");
            }
        }else{
            $sql.= " * ";
        }
        return $sql;
    }
    
    private function getSource(){
        if($this->tables){
            $source[] = " FROM " . $this->tables[0]['name'];
            for($i=1; $i<count($this->tables); $i++){
                $source[] = $this->tables[$i]['join'] .  " JOIN "
                            . $this->tables[$i]['name'] . " ON " 
                            . $this->getRelation($i);
            }
        }else{
            $this->Error("You have no tables registered yet");
        }
        return implode(" ", $source);
    }
    
    private function getRelation($index) {
        $rel = $this->tables[$index]['relation'];
        if(substr_count($rel, "=")){
            $rel = explode("=", $rel);
            $on[] = $this->tables[ $index ]['name'] . "." . trim($rel[0]);
            $on[] = $this->tables[ $this->tables[$index]['target'] ]['name'] . "." . trim($rel[1]);
            $relation = implode(" = ", $on);
        }else{
            $relation = $rel . " = " . $this->tables[ $this->tables[$index]['target'] ]['name'] . ".id";
        }
        return $relation;
    }
    
    private function getOrder() {
        if($this->order){
            if(count($this->order) <= count($this->tables)){
                foreach($this->order as $i => $ord){
                    foreach(explode(",", $ord) as $o)
                        $order[] = $this->tables[$i]['name'] . "." . trim($o);
                }
            }else{
                $this->Error("Order statement doesn't match with tables count");
            }
            return " ORDER BY " . implode(", ", $order);
        }
    }
    
    private function getGroup() {
        if($this->group){
            if(count($this->group) <= count($this->tables)){
                foreach($this->group as $i => $group){
                    foreach(explode(",", $group) as $g)
                        $array[] = $this->tables[$i]['name'] . "." . trim($g);
                }
            }else{
                $this->Error("Group statement doesn't match with tables count");
            }
            return " GROUP BY " . implode(", ", $array);
        }
    }
    
    private function getWhere() {
        if($this->flags && !$this->includeDeleted){
            foreach($this->tables as $i => $table){
                if(!in_array($i, $this->ignore)){
                    $this->getTable($table['name']);
                    $actives = array();
                    foreach($this->flags as $f){
                        if(in_array($f, $this->fields))
                            $actives[] = "$f = 1";
                    }
                    if($actives){
                        $w = $this->where[$i] ? explode(",", $this->where[$i]) : array();
                        $this->where[$i] = implode(",", array_merge($w, $actives));
                    }
                }
            }
        }
        
        if($this->where){
            if(count($this->where) <= count($this->tables)){
                $operators = array(">=", "<=", "<>", "!=", "=", ">", "<", "like", "is null", "is not null");
                foreach($this->where as $i => $where){
                    foreach(explode(",", $where) as $w){
                        foreach($operators as $ope){
                            if(substr_count($w, $ope)){
                                $condition = explode($ope, $w); 
                                $value = "'" . trim($condition[1]) . "'";
                                $array[] = $this->tables[$i]['name'] . "." . trim($condition[0]) . " " . $ope . " " . $value;
                            }
                        }
                    }
                }
            }else{
                $this->Error("Where statement doesn't match with tables count");
            }  
        }
        if($this->raw){
            foreach($this->raw as $i => $raw){
                foreach($raw as $r)
                    $array[] = $this->tables[$i]['name'] . "." . $r;
            }
        }
        
        if($array)
            return " WHERE " . implode(" and ", $array);
        
    }
    
    private function getWiths($data) {
        if($this->with){
            $key = $this->getTable($this->tables[0]['name'])->key->name;
            try {
                foreach ($data as $i => $d){
                    foreach($this->with as $type => $with){
                        foreach ($with as $w) {
                            $where = array($w['foreign'] . " = '" . $d[$key] . "'");
                            if($this->flags){
                                $map = $this->getTable($w['table'])->fields;
                                foreach($this->flags as $flag){
                                    if(in_array($flag, $map)){
                                        $where[] = $flag . " = 1";
                                    }
                                }
                            }
                            $sql = "select * from " . $w['table'] . " where " . implode(" and ", $where);
                            $result = $this->query($sql);
                            if($type == 'MANY'){
                                $add = mysqli_fetch_all($result, MYSQLI_ASSOC);
                            }elseif($type == 'ONE'){
                                $add = mysqli_fetch_assoc($result);
                            }
                            if($w['pluck'])
                                $data[$i][ $w['alias'] ] = $this->lists($add, $w['pluck']);
                            else
                                $data[$i][ $w['alias'] ] = $add;
                        }
                    }
                }
            } catch (Exception $ex) {
                $this->Error($ex->getMessage());
            } 
        }
        return $data;
    }
   
    private function getTarget($index){
        $target = $this->tables[$index]['name'];
        if(substr($target, -1) == "s")
            return substr ($target, 0, strlen($target)-1);
        return $target;
    }

    
    private function Error($msg = ""){
        if($this->debug){
            switch($this->protocol){
                case "MYSQL":
                    $error = (mysqli_error($this->cn) ? mysqli_error($this->cn) : $msg) . " | " . $this->sql;
                break;
                case "SQLDRIVER":
                    $err = sqlsrv_errors();
                    $error = ($err[0] ? $err[0]['message'] : $msg) . " | " .  $this->sql;
                break;
            }
            throw new HttpException($error);
        }else{
            throw new HttpException();
        }
    }
    
}

}

?>
