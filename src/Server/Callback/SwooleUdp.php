<?php
namespace Woocan\Server\Callback;

use \Woocan\Core\Request;
use \Woocan\Core\Context;

class SwooleUdp extends Base
{
    /* UDP（rpc） */
    public function onPacket($server, string $data, array $clientInfo)
    {
        Context::baseCoInit(null, null, null, Request::REQUEST_SW_UDP);
        Context::baseCoSet('_udp_client', $clientInfo);

        $this->_handleReq($data, null);
    }
}
