<?php

$configWindows = array(
    'server_mode' => 'Fastcgi',
    'project' => array(
        'log_level' => 2, //0上线模式，1错误显示+release，2错误显示+debug+release
        'view_mode' => 'Template',
        'route_cmd' => \app_test\config\router::Cmd_Enum,
    ),
    //中间件
    'midware' => [
        //'AccessLog' =>['key'=>['ctrl', 'method', 'time', 'uid', 'arg']],
        'XhprofStats' =>['save_path'=>ROOT_PATH.'/tmp/test/xhprof', 'catch_microtime'=>200],
    ],
    'cache' => [
        'global'=>['_adapter'=>'Yac', 'prefix'=>'test'],
    ],
);


$configLinux = array(
    'server_mode' => 'SwooleHttp',
    'swoole_main' => array(
        'host' => '0.0.0.0',
        'port' => 8005,
        'setting' => [
            'enable_coroutine' => true,
            'max_coroutine' => 10000,
            'worker_num' => 1,                              //工作进程数
            'pid_file' => ROOT_PATH.'/http.pid',			//保存master进程id
            'log_level' => 0,								//swoole日志级别（0DEBUG，1TRACE，2INFO，3NOTICE，4WARNING，5ERROR）
            'max_request' => 80000,                         //单个进程处理请求次数
            'document_root' => ROOT_PATH,
            'enable_static_handler' => true,

            'open_length_check' => true,
            'package_length_type' => 'n',
            'package_body_offset' => 2,
        ],
    ),
    'swoole_rpc' => [
        'host' => '0.0.0.0',
        'port' => 8006,
        'type' => 2,
    ],
    'project' => [
        'log_level' => 2, //0上线模式，1错误显示+release，2错误显示+debug+release
        'view_mode' => 'Template',
        'route_cmd' => \app_test\config\router::Cmd_Enum,
    ],
    //中间件
    'midware' => [
        //'AccessLog' =>['key'=>['time', 'uid', 'ctrl', 'method', 'arg']],
        'XhprofStats' =>['save_path'=>ROOT_PATH.'/tmp/test/xhprof', 'catch_microtime'=>10],
    ],
    'cache' => [
        'global'=>['_adapter'=>'Yac', 'prefix'=>'test'],
    ],
);

return IS_WIN ? $configWindows : $configLinux;