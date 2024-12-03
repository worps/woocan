<?php

return array(
    'server_mode' => 'Fastcgi',
    'project' => array(
        'log_level' => 2, //0上线模式，1错误显示+release，2错误显示+debug+release
        'view_mode' => 'Json',
        'headers' => [
            'Access-Control-Allow-Origin' => '*',
            'Content-Type' => 'application/json',
        ],
    ),
);