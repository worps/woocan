<?php
/**
 * @author lht
 * swoole_tcp模式
 */
namespace Woocan\Server;

use \Woocan\Core\Interfaces\Server as IServer;

class SwooleTcp extends SwooleBase implements IServer
{
    public function __construct()
    {
        parent::__construct();

        $this->serv = new \Swoole\Server(C('swoole_main.host'), C('swoole_main.port'), $this->workMode, SWOOLE_SOCK_TCP);

        printf("swoole_tcp listening %s:%d\n", C('swoole_main.host'), C('swoole_main.port'));
    }
}