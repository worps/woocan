<?php
/**
 * @author lht
 * swoole_http模式
 */
namespace Woocan\Server;

use Woocan\Core\Interfaces\Server as IServer;

class SwooleHttp extends SwooleBase implements IServer
{
    public function __construct()
    {
        parent::__construct();

        $this->serv = new \Swoole\Http\Server(C('swoole_main.host'), C('swoole_main.port'), $this->workMode, SWOOLE_SOCK_TCP | $this->ssl);

        printf("swoole_http listening %s:%d\n", C('swoole_main.host'), C('swoole_main.port'));
    }
}