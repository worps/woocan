<?php
namespace Woocan\Rpc;

use \Woocan\Core\Log;

/**
 * 采用golang支持的JSON-RPC模式，数据边界为\n
 */
class RpcTcpCaller
{
    private $url;
    private $class;
    private $timeout;
    private $conn;

    function __construct($serverInfo, $class, $timeout=5)
    {
        $this->url = $serverInfo['addr'];
        $this->class = $class;
        $this->timeout = $timeout;
    }

    function __call($name, $arguments)
    {
        $message = $this->class. '/'. $name .'?'.http_build_query(array(
            'params' => json_encode($arguments),
            'id'     => uniqid(),
        )). "\n";

        $this->_send($message);
        $res = $this->_read();
        Log::release('rpc receive', $res);
        //截取掉最后的"\n"
        $res = substr($res, 0 , -1);
        $ret = json_decode($res, true);
        if (json_last_error() > 0){
            $ret = $res;
        }

        // golang JSON-rpc会附带error
        if (isset($ret['error'])) {
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', 'rpc received error: '.$ret['error']);
        }

        return $ret;
    }

    private function _send($message)
    {
        $this->conn = stream_socket_client($this->url, $errno, $errstr, 1);
        if (!$this->conn) {
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', 'tcp connect '.$this->url. ' failed: '.$errstr);
        }
        stream_set_timeout($this->conn, $this->timeout);

        $writeLen = fwrite($this->conn, $message);
        if ($writeLen != strlen($message)){
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', 'tcp write to '.$this->url. ' failed.');
        }
    }

    private function _read()
    {
        $res = fgets($this->conn);
        if ($res === false || $res === ''){
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', 'read from tcp failed');
        }

        return $res;
    }

    function __destruct()
    {
        if ($this->conn) {
            fclose($this->conn);
        }
    }
}