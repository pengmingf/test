<?php
return [
    'SERVER_NAME' => "EasySwoole",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT' => 9501,
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
            'host' => 'rm-bp19i51ws60kx2q5v.mysql.rds.aliyuncs.com',
            'port' => 3306,
            'user' => 'dbx198554aluw208',
            'password' => 'lanmao_jfqDB',
            'database' => 'lanmao_jfq',
            'timeout' => 5,
            'charset' => 'utf8'
        ],
        'lanmaoquchong1' => [
            'host' => 'rm-bp1n64hm6hb24sgu6.mysql.rds.aliyuncs.com',
            'port' => 3306,
            'user' => 'tuser',
            'password' => '9Ton2014',
            'database' => 'lanmaoquchong1',
            'timeout' => 5,
            'charset' => 'utf8'
        ],
        'lanmaoquchong2' => [
            'host' => 'rm-bp12912c6ipb9ox9p.mysql.rds.aliyuncs.com',
            'port' => 3306,
            'user' => 'tuser',
            'password' => '9Ton2014',
            'database' => 'lanmaoquchong2',
            'timeout' => 5,
            'charset' => 'utf8'
        ],
        'lanmaoquchong3' => [
            'host' => 'rm-bp12912c6ipb9ox9p.mysql.rds.aliyuncs.com',
            'port' => 3306,
            'user' => 'lmqc3',
            'password' => '9Ton2014',
            'database' => 'lanmaoquchong3',
            'timeout' => 5,
            'charset' => 'utf8'
        ]
    ]
];
