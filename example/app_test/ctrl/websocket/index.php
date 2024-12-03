<?php
namespace app_test\ctrl\websocket;

use \Woocan\Core\Request;
use \Woocan\Core\Context;
use \Woocan\Core\Response;
use \Woocan\Lib\ConnMapper;

class index
{
	use \Woocan\AppBase\Ctrl;
	
	function main()
	{
        $vip = $this->getParam('vip', 'intval', false, false) ?? 0;

        // 给指定vip的所有人广播消息
        $list = ConnMapper::getChannelUsers('vip', $vip);
        $count = Response::broadcast("hello", Request::REQUEST_SW_WEBSCOKET, array_keys($list));

        return json_encode([
            'code'      => 1,
            'all_users' => ConnMapper::getAll(),
            'broadcast' => $list,
            'my_fd'     => Context::baseCoGet('_fd'),
            'count'     => $count,
        ]);
    }
}