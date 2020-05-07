<?php
return [
    'SERVER_NAME' => "EasySwoole",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 9601,
        'SERVER_TYPE' => EASYSWOOLE_WEB_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER,EASYSWOOLE_REDIS_SERVER
        'SOCK_TYPE' => SWOOLE_TCP,
        'RUN_MODEL' => SWOOLE_PROCESS,
        'SETTING' => [
            'worker_num' => 8,
            'reload_async' => true,
            'max_wait_time'=>3
        ],
        'TASK'=>[
            'workerNum'=>4,
            'maxRunningNum'=>128,
            'timeout'=>15
        ]
    ],
    'TEMP_DIR' => null,
    'LOG_DIR' => null,
    
    
    'lanmao' => [
        'main_db' => [
            'host' => '47.107.45.21',
            'port' => 3306,
            'user' => 'main_db',
            'password' => 'root',
            'database' => 'main_db',
            'timeout' => 5,
            'charset' => 'utf8mb4'
        ],
        'lanmaoqc1_db' => [
            'host' => '47.107.45.21',
            'port' => 3306,
            'user' => 'lanmaoqc1_db',
            'password' => 'root',
            'database' => 'lanmaoqc1_db',
            'timeout' => 5,
            'charset' => 'utf8mb4'
        ],
        'lanmaoqc2_db' => [
            'host' => '47.107.45.21',
            'port' => 3306,
            'user' => '',
            'password' => '',
            'database' => '',
            'timeout' => 5,
            'charset' => 'utf8mb4'
        ],
        'lanmaoqc3_db' => [
            'host' => '47.107.45.21',
            'port' => 3306,
            'user' => '',
            'password' => '',
            'database' => '',
            'timeout' => 5,
            'charset' => 'utf8mb4'
        ]
    ]
];
