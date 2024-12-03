<?php

return array(
    'server_mode' => 'SwooleWs',
    'swoole_main' => array(
        'host' => '0.0.0.0',
        'port' => 8007,
        'callback_class' => app_test\ctrl\websocket\_callback::class,
        'ws_binary' => false,   //文本传输，不使用二进制
    ),
    'project' => array(
        'log_level' => 2,      //0上线模式，1错误显示+release，2错误显示+debug+release
        'view_mode' => 'Json',
    ),
    'conn_mapper' => array(
        'channels' => ['vip'],
    ),
);