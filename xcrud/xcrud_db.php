<?php
/** Database driver; f0ska xCRUD v.1.6.26; 03/2015 */
class Xcrud_db
{
    private static $_instance = array();
    private $connect;
    public $result;
    private $dbhost;
    private $dbuser;
    private $dbpass;
    private $dbname;
    private $dbencoding;
    private $magic_quotes;

    public static function get_instance($params = false)
    {
        if (is_array($params))
        {
            list($dbuser, $dbpass, $dbname, $dbhost, $dbencoding) = $params;
            $instance_name = sha1($dbuser . $dbpass . $dbname . $dbhost . $dbencoding);
        }
        else
        {
            $instance_name = 'db_instance_default';
        }
        if (!isset(self::$_instance[$instance_name]) or null === self::$_instance[$instance_name])
        {
            if (!is_array($params))
            {
                $dbuser = Xcrud_config::$dbuser;
                $dbpass = Xcrud_config::$dbpass;
                $dbname = Xcrud_config::$dbname;
                $dbhost = Xcrud_config::$dbhost;
                $dbencoding = Xcrud_config::$dbencoding;
            }
            self::$_instance[$instance_name] = new self($dbuser, $dbpass, $dbname, $dbhost, $dbencoding);
        }
        return self::$_instance[$instance_name];
    }
    private function __construct($dbuser, $dbpass, $dbname, $dbhost, $dbencoding)
    {
        // $this->magic_quotes = @get_magic_quotes_runtime();
        if (strpos($dbhost, ':') !== false)
        {
            list($host, $port) = explode(':', $dbhost, 2);
            preg_match('/^([0-9]*)([^0-9]*.*)$/', $port, $socks);
            $this->connect = mysqli_connect($host, $dbuser, $dbpass, $dbname, $socks[1] ? $socks[1] : null, $socks[2] ? $socks[2] : null);
        }
        else
            $this->connect = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
        if (!$this->connect)
            $this->error('Connection error. Can not connect to database');
        $this->connect->set_charset($dbencoding);
        if ($this->connect->error)
            $this->error($this->connect->error);
        if (Xcrud_config::$db_time_zone)
            $this->connect->query('SET time_zone = \'' . Xcrud_config::$db_time_zone . '\'');
    }
    public function query($query = '')
    {
        $result = $this->connect->query($query, MYSQLI_USE_RESULT);
        $this->result = $result;
        if ($this->connect->error)
            $this->error($this->connect->error . '<pre>' . $query . '</pre>');
        return $this->connect->affected_rows;
    }
    public function insert_id()
    {
        return $this->connect->insert_id;
    }
    public function result()
    {
        $out = array();
        if ($this->result)
        {
            while ($obj = $this->result->fetch_assoc())
            {
                $out[] = $obj;
            }
            $this->result->free();
        }
        return $out;
    }
    public function row()
    {
        $obj = $this->result->fetch_assoc();
        $this->result->free();
        @$this->connect->next_result();// Fix 'command out of sync' error (By ringo)
        return $obj;
    }
    public function escape($val, $not_qu = false, $type = false, $null = false, $bit = false)
    {
        if ($type)
        {
            switch ($type)
            {
                case 'bool':
                    if ($bit)
                    {
                        return (int)$val ? 'b\'1\'' : 'b\'0\'';
                    }
                    return (int)$val ? 1 : ($null ? 'NULL' : 0);
                    break;
                case 'int':
                    $val = preg_replace('/[^0-9\-]/', '', $val);
                    if ($val === '')
                    {
                        if ($null)
                        {
                            return 'NULL';
                        }
                        else
                        {
                            $val = 0;
                        }
                    }
                    if ($bit)
                    {
                        return 'b\'' . $val . '\'';
                    }
                    return $val;
                    break;
                case 'float':
                    if ($val === '')
                    {
                        if ($null)
                        {
                            return 'NULL';
                        }
                        else
                        {
                            $val = 0;
                        }
                    }
                    return '\'' . $this->connect->real_escape_string($val) . '\'';
                    break;
                default:
                    if (trim($val) == '')
                    {
                        if ($null)
                        {
                            return 'NULL';
                        }
                        else
                        {
                            return '\'\'';
                        }
                    }
                    else
                    {
                        if ($type == 'point')
                        {
                            $val = preg_replace('[^0-9\.\,\-]', '', $val);
                        }
                        //return '\'' . ($this->magic_quotes ? (string )$val : $this->connect->real_escape_string((string )$val)) . '\'';
                    }
                    break;
            }
        }
        if ($not_qu)
            return $this->magic_quotes ? (string )$val : $this->connect->real_escape_string((string )$val);
        return '\'' . ($this->magic_quotes ? (string )$val : $this->connect->real_escape_string((string )$val)) . '\'';
    }
    public function escape_like($val, $pattern = array('%', '%'))
    {
        if (is_int($val))
            return '\'' . $pattern[0] . (int)$val . $pattern[1] . '\'';
        if ($val == '')
        {
            return '\'\'';
        }
        else
        {
            return '\'' . $pattern[0] . ($this->magic_quotes ? (string )$val : $this->connect->real_escape_string((string )$val)) .
                $pattern[1] . '\'';
        }
    }
    private function error($text = 'Error!')
    {

        try{
            $data = ["time"=>date("Y-m-d h:i:s"),"type"=>"SQL","description"=>$text];
            error_log(json_encode($data)."\n", 3, $_SERVER['DOCUMENT_ROOT']."/logs/sw_xcrud_errors.log");
        } catch (Exception $ex) {
            
        }
        $full_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $support_link = '<a style="cursor:pointer; color:#fff !important; border-radius: 6px;" class="reporting_link btn btn-md btn-danger" onclick="reportXcrudError('."'".addslashes($text)."','".md5(time())."','".addslashes($full_url)."'".');">Report this error</a>';
        

        /* My version */
        $error_msg = '<div class="xcrud-error" 
            style="position:relative;line-height:1.25;padding:15px;color:#000;margin:10px;border:4px solid #BA0303;border-radius:10px; font-family:Arial,sans-serif;background:#fff; max-width:1000px;margin:auto;">
            <h4>Hi, looks like something went wrong!</h4><p title="'.addslashes($text).'">An internal error has occurred during this operation. Click the button below to report the error</p>
            <span  style="font-size:10px;bottom:3px;right:5px;">'.$support_link.'</span>
            </div>';
        
        exit($error_msg);
    }
    
    /**
     * This is a method that will return the instance of the mysqli object used by xCrud
     * @return msqli object
     * Added by Ringo
     */
    public function getConnection(){
        return $this->connect;
    }
}
