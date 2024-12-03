<?php

return [
    'vars' => [
        'is_test' 		=> 1,		//测试环境
    ],
    'pool' => [
        /**
         * sqlite
         */
        'example_sqlite'=>[
            'min_size' 	=> 1,
            'connector' => \Woocan\Connector\Pdo::class,
            'db'        => ROOT_PATH. '/example.db'
        ],
    ],
];