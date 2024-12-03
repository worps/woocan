<?php
namespace Woocan\Rpc;

use \Woocan\Core\MyException;

class RpcUdpCaller
{
    private $addr;
    private $class;
    private $timeout;
    private $conn;

    function __construct($serverInfo, $class, $timeout=5)
    {
        $urlParse = parse_url($serverInfo['addr']);

        $this->addr = [$urlParse['host'], $urlParse['port']];
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

        $ret = json_decode($res, true);
        if (json_last_error() > 0){
            $ret = $res;
        }
        return $ret;
    }

    private function _send($message)
    {
        if (($this->conn = @socket_create(AF_INET, SOCK_DGRAM, 0)) == FALSE) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            throw new \Exception("创建socekt失败: [$errorcode] $errormsg");
        }

        // 读写超时时间
        socket_set_option($this->conn, SOL_SOCKET, SO_RCVTIMEO, array("sec" => $this->timeout, "usec" => 0));
        socket_set_option($this->conn, SOL_SOCKET, SO_SNDTIMEO, array("sec" => $this->timeout, "usec" => 0));

        if (!socket_sendto($this->conn, $message, strlen($message), 0, $this->addr[0], $this->addr[1])) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            throw new \Exception("Could not send data: [$errorcode] $errormsg \n");
        }
    }

    private function _read()
    {
        $reply = '';
        if (socket_recv($this->conn, $reply, 655350, 0) === FALSE) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            throw new \Exception("Could not receive data: [$errorcode] $errormsg \n");
        }

        $this->_close();
        return $reply;
    }

    private function _close()
    {
        if ($this->conn) {
            @socket_close($this->conn);
            $this->conn = null;
        }
    }

    function __destruct()
    {
        $this->_close();
    }
}