<?php

if (! class_exists ( "DBConn" )) {
//Clase de Acceso a datos unificada...


class DBConn{
    private $connection; //Arreglo de conexiones que soportara la clase para su disposicion
    private $cn; // Variable contenedora de la conexion activa
    private $rows; // Almacena el numero de filas afectadas en cada operacion
    private $active = "MYAPP"; // Declara cual sera la conexion x default cuando no se especifique lo contrario
    private $debug = true; // Modo de tratado de excepciones o errores
    private $protocol = "MYSQL"; // Protocolo de conexion x default
    
    //Constructor, recibe el nombre de la conexion que se desea o en caso contrario adopta la DEFAULT como se declaro antes
    function __construct($conn = null, $pro = null){
       if($conn)
           $this->active = $conn;
       if($pro)
           $this->protocol = $pro;
       
        $this->connection['MYAPP'] = array('HOST' => 'localhost', 'USER' => 'root', 'PWD' => 'db_pwd', 'DB' => 'this_db_name');
        $this->connection['OTHERDB'] = array('HOST' => '172.22.5.41', 'USER' => 'userdb', 'PWD' => 'db_pwd', 'DB' => 'other_db_name');
        
    }
    
    private function getTimezone() {
        $year = date("Y");
        $ahora =strtotime(date("Y-m-d"));  
        $inicio_verano = strtotime($year . "-03-31 next Sunday");
        $fin_verano = strtotime($year . "-11-01 last Sunday");
        if ($ahora >= $inicio_verano && $ahora < $fin_verano) 
            return "-5:00";
        else 
            return "-6:00";
    } 
    
    public function setProtocol($pro){
        $this->protocol = $pro;
    }
    
    public function affectedRows(){
        return $this->rows;
    }
    
    public function getCredentials() {
        return $this->connection[$this->active];
    }
    
    //Realiza la conexion de la base da datos segun la conexion seleccionada
    private function Connect($id){
        switch($this->protocol){
            case "MYSQL":
                $this->cn = mysqli_connect($this->connection[$id]['HOST'],
                                   $this->connection[$id]['USER'],
                                   $this->connection[$id]['PWD']) 
                                   or die($this->Error("Error de conexion"));	
                mysqli_select_db($this->cn, $this->connection[$id]['DB']);
                mysqli_query($this->cn, "SET NAMES 'utf8'");
                mysqli_query($this->cn, "SET time_zone = '" . $this->getTimezone() . "'");
            break;
            case "MSSQL":
                $this->cn = mssql_connect($this->connection[$id]['HOST'], 
                                          $this->connection[$id]['USER'],
                                          $this->connection[$id]['PWD'])
                                          or die($this->Error("Error de conexion"));
                mssql_select_db($this->connection[$id]['DB'], $this->cn);
            break;
            case "SQLDRIVER":
                $this->cn = sqlsrv_connect($this->connection[$id]['HOST'] . ($this->connection[$id]['PORT']?",".$this->connection[$id]['PORT']:""), 
                        array("Database" => $this->connection[$id]['DB'], 
                              "UID" => $this->connection[$id]['USER'], 
                              "PWD" => $this->connection[$id]['PWD']) 
                        );
                if(!$this->cn) die($this->Error("Error de conexion"));
                break;
        }
    }
    
    private function CloseDB(){
        switch($this->protocol){
            case "MYSQL":
                mysqli_close($this->cn);
                break;
            case "MSSQL":
                mssql_close($this->cn);
                break;
            case "SQLDRIVER":
                sqlsrv_close($thi->cn);
                break;
        }
    }
    
    //Ejecuta una instruccion de MySQL recibiendo el query como tal y devuelve los resultados en forma nativa
    //guardando en bitacora la operación asi como el error en caso que se presente 
    private function query($sql){
        $this->Connect($this->active);
        switch($this->protocol){
            case "MYSQL":
                $result = mysqli_query($this->cn, $sql) or die($this->Error($sql));
                $this->rows = mysqli_affected_rows($this->cn);
            break;
            case "MSSQL":
                $result = mssql_query($sql, $this->cn) or die($this->Error($sql));
                $this->rows = mssql_rows_affected($this->cn);
            break;
            case "SQLDRIVER":
                $result = sqlsrv_query($this->cn, $sql) or die($this->Error($sql));
                $this->rows = sqlsrv_rows_affected($result);
                break;
        }
        return $result;
    }
    
    //Realiza una consulta basada en el query enviado devolviendo los resultados en formato de array asociativo
    function getArray($sql, $format = "ARRAY"){
        $ds = $this->query($sql);
        $result = array();
        switch($this->protocol){
            case "MYSQL":
                switch($format){
                    case "ARRAY":
                        while($row = mysqli_fetch_assoc($ds)){
                            $r = array();
                            foreach($row as $k => $v)
                                $r[$k] = htmlspecialchars($v);
                            $result[] = $r;
                        }
                    break;
                    case "OBJECT":
                         while($row = mysqli_fetch_object($ds)){
                            foreach(get_object_vars($row) as $k => $v)
                                $r->$k = htmlspecialchars ($v);
                            $result[] = $r;
                         }
                    break;
                }
            break;
            case "MSSQL":
                switch($format){
                    case "ARRAY":
                        while($r = mssql_fetch_assoc($ds))
                            $result[] = $r;
                    break;
                    case "OBJECT":
                         while($r = mssql_fetch_object($ds))
                            $result[] = $r;
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
        $this->CloseDB();
        return $result;
    }
    
    //Reeliza una consulta basado en el query enviado devolviendo solo el primer resultado en forma de objeto
    function getObject($sql){
        $ds = $this->query($sql);
        switch($this->protocol){
            case "MYSQL":
                if(mysqli_num_rows($ds) > 0){
                    foreach(get_object_vars(mysqli_fetch_object($ds)) as $k => $v)
                        $obj->$k = htmlspecialchars ($v);
                }
                else
                    $obj = null;
            break;
            case "MSSQL":
                if(mssql_num_rows($ds) > 0)
                    $obj = mssql_fetch_object($ds);
                else
                    $obj = null;
            break;
            case "SQLDRIVER":
                if(sqlsrv_has_rows($ds))
                    $obj = sqlsrv_fetch_object($ds);
                else
                    $obj = null;
            break;
        }
        $this->CloseDB();
        return $obj;
    }
    
    //Reeliza una consulta basado en el query enviado devolviendo un solo dato, en este caso el primero del mismo
    function getOne($sql){
        $ds = $this->query($sql);
        switch($this->protocol){
            case "MYSQL":
                if(mysqli_num_rows($ds) > 0){
                    $data = mysqli_fetch_row($ds);
                    $one = htmlspecialchars($data[0]);
                }else
                    $one = null;
            break;
            case "MSSQL":
                if(mssql_num_rows($ds) > 0){
                    $data = mssql_fetch_row($ds);
                    $one = $data[0];
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
        $this->CloseDB();
        return $one;
    }
    
    //Calcula el siguiente valor para la llave primaria de una tabla usando el nombre del campo, la tabla y
    //en caso que se requiera tambien puede recibir una condicion adicional para obtenerla
    function getID($field, $table, $condition = ""){ 	
        switch($this->protocol){
            case "MYSQL":
                $sql = "select IFNULL(max(" . $field . "), 0) from " . $table . ($condition==""?"":" where " . $condition);		
                $ds = $this->query($sql);
                $id = mysqli_fetch_row($ds);
            break;
            case "MSSQL":
                $sql = "select ISNULL(max(" . $field . "), 0) from " . $table . ($condition==""?"":" where " . $condition);		
                $ds = $this->query($sql);
                $id = mssql_fetch_row($ds);
            break;
            case "SQLDRIVER":
                $sql = "select ISNULL(max(" . $field . "), 0) from " . $table . ($condition==""?"":" where " . $condition);		
                $ds = $this->query($sql);
                $id = sqlsrv_fetch_array($ds);
            break;
        }
        $this->CloseDB();
        return $id[0] + 1;
    }
    
    //Evalua si existe un registro que cumpla la condicion enviada en base al nombre del campo y la tabla
    function exist($field, $table, $condition){
        switch($this->protocol){
            case "MYSQL":
                $sql = "select IFNULL(" . $field . ", 0) from " . $table . " where " . $condition;		
                $ds = $this->query($sql);
                $res = mysqli_fetch_row($ds);
            break;
            case "MSSQL":
                $sql = "select ISNULL(" . $field . "), NULL) from " . $table . ($condition==""?"":" where " . $condition);		
                $ds = $this->query($sql);
                $res = mssql_fetch_row($ds);
            break;
            case "SQLDRIVER":
                $sql = "select ISNULL(" . $field . "), NULL) from " . $table . ($condition==""?"":" where " . $condition);		
                $ds = $this->query($sql);
                $res = sqlsrv_fetch_array($ds);
            break;
        }
        $this->CloseDB();
        return $res[0];
    }
    
    //Ejecuta una instruccion de MySQL tales como INSERT, UPDATE Y DELETE y devuelve como resultado 
    //el numero de filas afectadas
    function execute($sql){
        $this->query($sql);
        $this->CloseDB();
        return $this->rows;
    }
    
    //Hace la peticion a un StoreProcedure recibiendo el nombre del mismo y un arreglo con los valores en el orden que
    //deben ser insertados, ademas especifica si requiere que devuelva algun valor como resultado de la operacion
    function queryStored($fn, $param, $resultFormat = ""){
        $str = "( ";
        if($param){
            foreach ($param as $p) 
                    $str .= ($p?"'".$p."'":"null") . ",";
        }
        $str = substr($str, 0, -1) . ")";
        switch($this->protocol){
            case "MYSQL":
                $sql = 'CALL ' . $fn . ' ' . $str;
            break;
            case "MSSQL":
                $sql = 'EXEC ' . $fn . ' ' . $str;
            break;
        }
        if($resultFormat)
            return $this->getArray($sql, $resultFormat);
        else
            return $this->execute($sql);
    }
    
    //Almancena el registro de errores de ejecucion en la bitacora
    function Error($sql){
        if($this->debug){
            switch($this->protocol){
                case "MYSQL":
                    echo $error = mysqli_error($this->cn) . " | " . $sql;
                break;
                case "MSSQL":
                    echo $error = "Error | " . $sql;
                break;
                case "SQLDRIVER":
                    $error = sqlsrv_errors();
                    echo $error[0]['message'] . " | " . $sql;
                break;
            }
        }
    }
    
}

}

?>
