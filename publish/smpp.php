<?php
declare(strict_types=1);

/**
 * SMPP3 Server Config
 * @author Jena
 */

use Hyperf\Server\Server;
use Hyperf\Server\Event;

return [
    'servers' => [
        [
            'name'      => 'tcp',
            'type'      => Server::SERVER_BASE,
            'host'      => '0.0.0.0',
            'port'      => 8890,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_RECEIVE => [Path\To\Controller\TcpServer::class, 'onReceive'],
            ],
            'settings'  => [
                'worker_num'            => 1,
                'enable_coroutine'      => true,
                'open_length_check'     => true,
                'open_tcp_nodelay'      => true,
                'package_length_type'   => 'N',
                'package_length_offset' => 0,
                'package_body_offset'   => 0,
            ],
        ],
    ],
];