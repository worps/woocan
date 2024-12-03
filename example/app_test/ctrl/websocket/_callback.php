<?php
namespace app_test\ctrl\websocket;

use Woocan\Core\Interfaces\Connector;
use \Woocan\Lib\ConnMapper;

class _callback extends \Woocan\Server\Callback\SwooleWs
{
    /* 建立连接时校验token */
    function onOpen($sever, $req)
    {
        // 设置用户信息
        ConnMapper::add($req->fd, $req->fd, []);

        // 加入vip频道
        $vip = $req->get['vip'] ?? 0;
        ConnMapper::setChannels($req->fd, ['vip'=>$vip]);

        dump("fd={$req->fd} opened");
    }

    /* websocket断开连接 */
    public function onClose($serv, $fd, $reactorId)
    {
        ConnMapper::delete($fd, $fd);

        dump("fd=$fd closed");
    }
}