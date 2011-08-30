<?php
class Seer {

    protected $conn,$stmt;

    public function __construct($user,$pwd,$base,$euro=true) {
       $this->conn = oci_connect(strtoupper($user)
                                    ,strtoupper($pwd)
                                    ,strtoupper($base));
       if($euro) {
           $this->execute("ALTER SESSION SET NLS_DATE_FORMAT='DD/MM/RRRR HH24:MI:SS'");
       }
    }

    public function execute($query,$commit=false) {
        return($this->execute_query($query,$commit));
    }

    public function insert($table,$fields,$commit=false,$returning=null) {
        $query = "INSERT INTO ".$table."(";
        $bindings = array();
        for($i = 0; $i < count($fields); $i++) {
            $bindings[] = ":FIELD_".$i;
        }
        $query .= join(",",array_keys($fields)).")VALUES(".join(",",$bindings).")";
        if($returning != null) {
            $query .= " RETURNING ".$returning." INTO :RET_FIELD";
        }
        return $this->execute_query_with_bindings($query,array_values($fields),$commit);
    }

    public function update($table,$fields,$conditions=null,$commit=false) {
        $query = "UPDATE ".$table." SET ";
        $bindings = array();
        $values = array_values($fields);
        $num_fields = count($fields);
        $i = 0;
        foreach($fields as $k=>$v) {
            $bindings[] = strtoupper($k)." = ".":FIELD_".$i;
            $i++;
        }
        $query .= join(",",$bindings);
        if($conditions != null) {
            $query .= " WHERE ";
            $num_fields--;
            foreach($conditions as $k=>$v) {
                $num_fields++;
                $query .= strtoupper($k)." = :FIELD_".$num_fields." AND ";
                $values[] = $v;
            }
            $query = substr($query,0,-4);
        }
        return $this->execute_query_with_bindings($query,$values,$commit);
    }

    public function delete($table,$fields,$commit=false) {
        $query = "DELETE FROM ".$table." WHERE ";
        $bindings = array();
        $i = 0;
        foreach($fields as $k=>$v) {
            $bindings[] = $k." = ".":FIELD_".$i;
            $i++;
        }
        $query .= join(" AND ",$bindings);
        return $this->execute_query_with_bindings($query,array_values($fields),$commit);
    }

    public function find($query) {
        $this->execute_query($query);
        return(oci_fetch_row($this->stmt));
    }

    public function find_all($query) {
        $this->execute_query($query);
        oci_fetch_all($this->stmt,$result,null,null,OCI_FETCHSTATEMENT_BY_ROW + OCI_NUM);
        return($result);
    }

    public function to_utf8($data) {
        return($this->iterate_data($data,'UTF-8'));
    }

    public function to_iso8859($data) {
        return($this->iterate_data($data,'ISO-8859-1'));
    }

    public function renderJSON($data) {
        header("Content-Type: application/json");
        flush();
        echo(json_encode($data));
    }

    public function datetimedb($data=null) {
        if($data != null) {
            return date('d/m/Y H:i:s',$this->datetotime($data));
        } else {
            return date('d/m/Y H:i:s');
        }
    }

    public function datetotime($data,$euro=true,$time_mod=null) {
        if($euro == true) {
            $data = split("/",$data);
            $data = $data[1]."/".$data[0]."/".$data[2];
        }
        if($time_mod != null) {
            return strtotime($time_mod,strtotime($data));
        } else {
            return strtotime($data);
        }
    }

    public function __destruct() {
        oci_commit($this->conn);
        oci_close($this->conn);
    }

    private function execute_query($query,$commit=false) {
        $this->stmt = oci_parse($this->conn, $query);
        $status = oci_execute($this->stmt,($commit == true ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT));
        return $this->verify_transaction($status);
    }

    private function execute_query_with_bindings($query,$values,$commit=false) {
        $this->stmt = oci_parse($this->conn, $query);
        for($i = 0; $i < count($values); $i++) {
            oci_bind_by_name($this->stmt,":FIELD_".$i,$values[$i],-1);
        }
        $status = oci_execute($this->stmt,($commit == true ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT));
        return $this->verify_transaction($status);
    }

    private function verify_transaction($status) {
        if($status) {
            return($status);
        } else {
            $e = oci_error();
            error_log($e['message']);
            oci_rollback($this->conn);
            return false;
        }
    }

    private function iterate_data($data,$charset) {
        if(is_array($data)) {
            if($this->is_assoc($data)) {
                foreach($data as $k=>$v) {
                    if(is_array($v)) {
                        foreach($v as $field) {
                            $field = $this->convert_data($field,$charset);
                        }
                    } else {
                        $data[$k] = $this->convert_data($v,$charset);
                    }
                }
            } else {
                foreach($data as $subset) {
                    if(is_array($subset)) {
                        foreach($subset as $field) {
                            $field = $this->convert_data($field,$charset);
                        }
                    } else {
                        $subset = $this->convert_data($subset,$charset);
                    }
                }
            }
        } else {
            $data = $this->convert_data($data,$charset);
        }
        return($data);
    }

    private function convert_data($data,$charset) {
        if($charset == 'UTF-8') {
            $ENCODING_ORDER = "ISO-8859-1,WINDOWS-1252,GBK,WINDOWS-1251,UTF-8,ASCII";
        } else {
            $ENCODING_ORDER = 'UTF-8,ISO-8859-1,WINDOWS-1252,GBK,WINDOWS-1251,ASCII';
        }
        $enc = mb_detect_encoding($data,$ENCODING_ORDER);
        return(iconv($enc,$charset,$data));
    }

    private function is_assoc($array) {
        foreach (array_keys($array) as $k => $v) {
            if ($k !== $v) {
              return true;
            }
        }
        return false;
    }
}

