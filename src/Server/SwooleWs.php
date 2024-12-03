<?php
/**
 * @author lht
 * swoole_websocket模式
 */
namespace Woocan\Server;

use Woocan\Core\Interfaces\Server as IServer;

class SwooleWs extends SwooleBase implements IServer
{
    public function __construct()
    {
        parent::__construct();

        $this->serv = new \Swoole\WebSocket\Server(C('swoole_main.host'), C('swoole_main.port'), $this->workMode, SWOOLE_SOCK_TCP | $this->ssl);

        printf("swoole_websocket listening %s:%d\n", C('swoole_main.host'), C('swoole_main.port'));
    }
}