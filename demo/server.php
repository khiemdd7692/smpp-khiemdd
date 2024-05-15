<?php
require_once '../src/SMPP3Server.php';
require_once '../src/SMPP3Protocol.php';

use SMPP3\SMPP3Protocol;
use SMPP3\SMPP3Server;

/*
 * 解析配置文件
 */
$config = parse_ini_file("./config.ini");
if (empty($config)) {
    die("配置文件错误");
}

$portStr = $config['server_port'] ?? '';
if (empty($portStr)) {
    die("端口错误");
}

$reportStr = $config['report'] ?? '';
if (empty($reportStr) || strlen($reportStr) != 7) {
    die("状态错误");
}


$server = new Swoole\Server('0.0.0.0', $portStr);

$server->set([
        'worker_num'            => 1,
        'enable_coroutine'      => true,
        'open_length_check'     => true,
        'open_tcp_nodelay'      => true,
        'package_length_type'   => 'N',
        'package_length_offset' => 0,
        'package_body_offset'   => 0,
    ]
);

//监听连接进入事件
$server->on('Connect', function ($server, $fd) {
    echo "Client: Connect.\n";
});


//监听连接关闭事件
$server->on('Close', function ($server, $fd) {
    echo "Client: Close.\n";
});

$server->on('Receive', function (Swoole\Server $server, $fd, $from_id, $data) use ($reportStr) {
    $protocol = new SMPP3Server();

    try {
        $protocol->setBinary($data);

        if (!$protocol->parseHeader()) {
            //解析协议头，不在允许的范围内返回公共错误
            $server->send($fd, SMPP3Protocol::packGenericNack(SMPP3Protocol::ESME_RINVCMDID, $protocol->getHeader('sequence_number')));
            return;
        }
        echo "Command ID: " . ((string)$protocol->getCommandId()) . ".\n";

        if (in_array($protocol->getCommandId(), $protocol->notHandleCommands)) {
            //如果是无需处理的
            return;
        }

        $handleRes = false;

        //解析协议体成功了，执行后续操作
        if ($protocol->parseBody()) {
            switch ($protocol->getCommandId()) {
                case SMPP3Protocol::BIND_RECEIVER:
                case SMPP3Protocol::BIND_TRANSMITTER:
                case SMPP3Protocol::BIND_TRANSCEIVER:
                    //客户端提交的连接请求
                    //1) TODO 权限校验：账户，白名单，余额
                    $system_id = $protocol->getBody('system_id');
                    $password  = $protocol->getBody('password');
                    $exist   = true;
                    if (!$exist) {
                        $server->send($fd, SMPP3Protocol::packGenericNack(SMPP3Protocol::ESME_RINVPASWD, $protocol->getHeader('sequence_number')));
                        return;
                    }
                    //2) 执行连接
                    $handleRes = $protocol->handleConnect();
                    break;
                case SMPP3Protocol::SUBMIT_SM:
                    //客户端提交的发送请求
                    $handleRes = $protocol->handleSubmit();
                    break;
                case SMPP3Protocol::UNBIND:
                    //客户端提交的断开连接请求
                    $handleRes = $protocol->handleUnbind();
                    break;
                case SMPP3Protocol::ENQUIRE_LINK:
                    //客户端提交的探活请求
                    $handleRes = $protocol->handleEnquireLink();
                    break;
            }
            echo "Receive Data: " . json_encode($protocol->getBody(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ".\n";
        }

        //发送命令回执
        if ($server->exist($fd) && $handleRes) {
            $server->send($fd, $protocol->getResponse());
        }
        //发送短信并回执状态
        if ($protocol->getCommandId() === SMPP3Protocol::SUBMIT_SM && $handleRes) {
            $body = $protocol->getBody();
            //1) TODO 系统预检：产品，通道等可用性校验

            //2）TODO 发送短信：
            $phone = $body['destination_addr'];
            $text = $body['short_message'];

            //3) TODO 回执状态：短信发送状态
            $binary = SMPP3Protocol::packDeliverSm(
                SMPP3Protocol::ESM_CLASS_DELIVERY_REPORT,
                $body['source_addr'],
                $body['destination_addr'],
                ['id' => $protocol->getMsgHexId(), 'stat' => $reportStr, 'text' => '']
            );

            $server->send($fd, $binary);
        }
    } catch (\Throwable $e) {
        var_dump($e->getMessage());
        if ($server->exist($fd)) {
            $server->send($fd, SMPP3Protocol::packGenericNack($e->getCode(), $protocol->getHeader('sequence_number')));
            //断开连接
            $server->close($fd);
        }
    }
});

//启动服务器
$server->start();