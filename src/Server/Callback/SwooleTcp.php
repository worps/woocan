<?php
namespace Woocan\Server\Callback;

use \Woocan\Core\Request;
use \Woocan\Core\Context;

class SwooleTcp extends Base
{
    /**
     * TCP 
     * 这里不做封包粘包处理，需要请自行实现
     */
    public function onReceive($server, $fd, $reactor_id, $data)
    {
        Context::baseCoInit(null, null, $fd, Request::REQUEST_SW_TCP);

        $serverSetting = $this->isRpc ? C('swoole_rpc.setting') : C('swoole_main.setting');
        $data = $this->_tcpUnpack($data, $serverSetting);

        $this->_handleReq($data, $fd);
    }

    /* tcp拆包 */
    private function _tcpUnpack($msg, $serverSetting)
    {
        // 使用固定包头+包体格式
        if (isset($serverSetting['package_body_offset'])) {
            $headLen = $serverSetting['package_body_offset'];
            $format = $serverSetting['package_length_type'];

            $bodyLen = unpack($format , $msg)[1];
            $body = substr($msg, $headLen, $bodyLen);
            return $body;
        }
        return $msg;
    }
}
