<?php
namespace Woocan\Server\Callback;

use \Woocan\Core\Response;
use \Woocan\Core\Request;
use \Woocan\Core\Context;

class SwooleWs extends Base
{
    public function onStart()
    {
        parent::onStart();

        //建立集群
        //C('cluster') && \Woocan\Server\Cluster\SwooleWs::addMe();
    }

    public function onShutDown()
    {
        parent::onShutDown();

        //退出集群
        //C('cluster') && \Woocan\Server\Cluster\SwooleWs::removeMe();
    }

    /* HTTP */
    public function onRequest($request, $response)
    {
        Context::baseCoInit($request, $response, null, Request::REQUEST_SW_HTTP);

        $data = $request->server['request_uri'] . '?' . $request->server['query_string'];
        $this->_handleReq($data, null);
    }

    /* WS */
    public function onMessage($server, $frame)
    {
        Context::baseCoInit(null, null, $frame->fd, Request::REQUEST_SW_WEBSCOKET);

        $this->_handleReq($frame->data, $frame->fd);
    }
}
