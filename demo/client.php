<?php
require_once '../src/SMPP3Client.php';
require_once '../src/SMPP3Protocol.php';
require_once '../src/Trait/EnquireLinkTrait.php';
require_once '../src/Trait/FlowRateTrait.php';
require_once '../src/Trait/ReceiverTrait.php';
require_once '../src/Trait/TransmitterTrait.php';
require_once '../src/Abstract/BaseTrans.php';
require_once '../src/Util/GSMEncoder.php';
require_once '../src/Util/Receiver.php';
require_once '../src/Util/Transmitter.php';
require_once '../src/Util/Transceiver.php';


use Swoole\Coroutine;
use SMPP3\SMPP3Client;
use SMPP3\SMPP3Protocol;

$config = parse_ini_file("./config.ini");
if (empty($config)) {
    die("配置文件错误");
}

/*
 * 校验
 */
$portStr = $config['client_port'] ?? '';
if (empty($portStr)) {
    die("端口错误");
}

$msgContent = $config['msg'] ?? '';
if (empty($msgContent)) {
    die("请配置要发送的内容");
}

$mobileStr = $config['mobile'] ?? '';
if (empty($msgContent)) {
    die("请配置起始发送号码");
}


$submitTime = $reportTime = [];

$mark = false;

Coroutine\run(function () use ($config) {
    $poolSize = $GLOBALS['argv'][1] ?? 20;
    //第一个参数是并发数
    $msgNum = $GLOBALS['argv'][2] ?? 1000;

    $GLOBALS['totalSubNum'] = $poolSize * $msgNum;

    //第二个参数是单个发送条数
    $GLOBALS['totalNum'] = 2 * $poolSize * $msgNum;

    $GLOBALS['startTime'] = time();

    $pool = [];

    for ($i = 0; $i < $poolSize; $i++) {
        $smpp = new SMPP3Client(
            [
                'sequence_start'       => $i * 100000,
                'sequence_end'         => 100000000,   //在这个区间循环使用id，重新登录时候将从新在sequence_start开始
                'active_test_interval' => 100000000.5, //1.5s检测一次
                'active_test_num'      => 3,           //10次连续失败就切断连接
                'service_id'           => "831948",    //业务类型
                'src_id_prefix'        => "10690",     //src_id的前缀+submit的扩展号就是整个src_id
                'submit_per_sec'       => 200,         //每秒多少条限速，达到这个速率后submit会自动Co sleep这个协程，睡眠的时间按照剩余的时间来
                //例如每秒100，会分成10分，100ms最多发10条，如果前10ms就发送完了10条，submit的时候会自动Co sleep 90ms。
                'fee_type'             => '01',        //资费类别
                'client_type'          => 1,
            ]
        );

        $arr = $smpp->login($config['server_addr'], $config['server_port'], $config['account'], $config['password'], 10); //10s登录超时

        if ($arr !== false && $arr['command_status'] == 0) {
            var_dump('客户端' . $i . '：登陆成功，' . time());

            $pool[] = $smpp;

            Swoole\Coroutine::create(function () use ($smpp, $i) {
                while (true) {
                    //默认-1永不超时 直到有数据返回；
                    //只会收到submit回执包 或 delivery的请求包
                    $pack = $smpp->recv(-1);
                    echo "Client Receive Pack: " . json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ".\n";
                    if ($pack === false) {
                        if ($smpp->errCode === 8) {
                            var_dump('客户端' . $i . '：连接断开');
                            break;
                        } else {
                            continue;
                        }
                    }

                    if ($pack['command_id'] == SMPP3Protocol::SUBMIT_SM_RESP) {
                        @$GLOBALS['totalSubRespCnt']++;
                        if ($GLOBALS['totalSubRespCnt'] == $GLOBALS['totalSubNum']) {
                            $diff = time() - $GLOBALS['startTime'];
                            var_dump("发送结束 sub_resp 耗时 $diff");
                        }

                        $GLOBALS['submitTime'][time()] = ($GLOBALS['submitTime'][time()] ?? 0) + 1;

                        if ($pack['command_status'] === 0) {
                            $GLOBALS['totalNum']--;
                        } else {
                            $GLOBALS['totalNum']                          -= 2;
                            $GLOBALS['resp_err'][$pack['command_status']] = 1;
                        }
                    } elseif ($pack['registered_delivery'] === 1) {
                        @$GLOBALS['simuCnt']++;

                        @$GLOBALS['totalRepCnt']++;

                        if ($GLOBALS['totalRepCnt'] == $GLOBALS['totalSubNum']) {
                            $diff = time() - $GLOBALS['startTime'];
                            var_dump("发送结束 report 耗时 $diff");
                        }


                        $GLOBALS['reportTime'][time()] = ($GLOBALS['reportTime'][time()] ?? 0) + 1;
                        $GLOBALS['totalNum']--;
                        $GLOBALS['report_err'][$pack['short_message']['stat']] = 1;
                    }

                    if ($GLOBALS['totalNum'] <= 0) {
                        $GLOBALS['mark'] = true;
                    }
                }
            });
        } else {
            var_dump('客户端' . $i . '：登陆失败');
            echo "Receive Data: " . json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ".\n";

            foreach ($pool as $cmpp) {
                $cmpp->logout();
            }
        }
    }


    Coroutine::create(function () {
        while (true) {
            var_dump('simulatorCnt:' . @$GLOBALS['simuCnt']);
            Coroutine::sleep(1);
        }
    });

    Coroutine::create(function () use ($pool) {
        while (true) {
            if ($GLOBALS['mark']) {
                foreach ($pool as $cmpp) {
                    $cmpp->logout();
                }

                var_dump('submit response 返回分布时间分布：');

                foreach ($GLOBALS['submitTime'] as $time => $num) {
                    var_dump($time . ':' . $num);
                }

                var_dump('report 返回分布时间分布：');

                foreach ($GLOBALS['reportTime'] as $time => $num) {
                    var_dump($time . ':' . $num);
                }

                if (!empty($GLOBALS['resp_err'])) {
                    var_dump('response错误：');

                    var_dump(array_keys($GLOBALS['resp_err']));
                }

                if (!empty($GLOBALS['report_err'])) {
                    var_dump('report错误：');
                    echo "Receive Data: " . json_encode($GLOBALS['report_err'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ".\n";
                    // var_dump(array_keys($GLOBALS['report_err']));
                }

                break;
            }

            Coroutine::sleep(1);
        }
    });

    $mobile = $config['mobile'];
    $msgContent = $config['msg'];

    foreach ($pool as $smpp) {
        Coroutine::create(function () use ($smpp, $msgNum, &$mobile, $msgContent) {
            $s = 0;
            for ($j = 0; $j < $msgNum; $j++) {
                $smpp->submit((string)$mobile, $msgContent, '0615', -1, ($s++) % 255); //默认-1 永不超时
                $mobile++;
            }

            var_dump('发送完毕，' . time());
        });
    }
});
