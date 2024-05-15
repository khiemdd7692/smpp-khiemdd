<?php

namespace SMPP3;

use Closure;
use Swoole\Coroutine;
use SMPP3\Util\Receiver;
use SMPP3\Util\Transceiver;
use SMPP3\Util\Transmitter;

class SMPP3Client
{
    /** @var Transceiver */
    protected $transceiver;
    /** @var Receiver */
    protected $receiver;
    /** @var Transmitter */
    protected $transmitter;

    protected $config;

    protected $sequenceNum;
    protected $startBindTime;

    public $errCode = 0;
    public $errMsg = '';

    /** @var Coroutine\Channel */
    protected $pduChannel;

    public function __construct($config)
    {
        $this->checkConfig($config);
    }

    /**
     * getChannel
     * @return Coroutine\Channel
     */
    public function getChannel()
    {
        return $this->pduChannel;
    }

    /**
     * getTransmitter
     * @return Transmitter
     */
    public function getTransmitter()
    {
        return $this->transmitter;
    }

    /**
     * getReceiver
     * @return Receiver
     */
    public function getReceiver()
    {
        return $this->receiver;
    }

    /**
     * getConfig
     * @param $key
     * @param null $default
     * @return mixed
     */
    public function getConfig($key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * getStartBindTime
     * @return mixed
     */
    public function getStartBindTime()
    {
        return $this->startBindTime;
    }

    /**
     * syncClientErr
     * @param $errCode
     * @param $errMsg
     */
    public function syncClientErr($errCode, $errMsg)
    {
        $this->errCode = $errCode;
        $this->errMsg  = $errMsg;
    }

    /**
     * checkConfig
     * @param $config
     */
    protected function checkConfig($config)
    {
        $config['system_type']       = empty($config['system_type']) ? 'WWW' : $config['system_type'];
        $config['interface_version'] = empty($config['interface_version']) ? 52 : (int)$config['interface_version'];
        $config['addr_ton']          = empty($config['addr_ton']) ? 1 : (int)$config['addr_ton'];
        $config['addr_npi']          = empty($config['addr_npi']) ? 1 : (int)$config['addr_npi'];
        $config['address_range']     = empty($config['address_range']) ? '' : $config['address_range'];

        $this->sequenceNum = $config['sequence_start'];
        $this->config      = $config;
    }


    /**
     * login
     * @param $ip
     * @param $port
     * @param $account
     * @param $pwd
     * @param $timeout
     * @return array|bool
     * @throws Exception
     */
    public function login($ip, $port, $account, $pwd, $timeout)
    {
        $this->startBindTime = time();

        if (strlen($account) > 15) {
            $this->syncClientErr(SMPP3Protocol::ESME_RINVSYSID, 'Invalid System ID');

            return false;
        }

        if (strlen($pwd) > 8) {
            $this->syncClientErr(SMPP3Protocol::ESME_RINVPASWD, 'Invalid Password');

            return false;
        }

        //接收一体模式
        $this->transceiver = new Transceiver($this);

        return $this->transceiver->bind(
            $ip, $port, $account, $pwd, $timeout,
            function () {
                //如果链接成功则创建探活
                Coroutine::create([$this->transceiver, 'doEnquireLink']);
            }
        );
        //接收分离模式
//            $this->transmitter = new Transmitter($this);
//
//            return $this->transmitter->bind(
//                $ip, $port, $account, $pwd, $timeout,
//                //成功则则创建receiver
//                $this->createReceiver(...func_get_args())
//            //不成功则返回bind的失败resp
//            );
    }

    /**
     * logout
     */
    public function logout()
    {
        $this->transceiver->unbind();
//            $this->transmitter->unbind();
//            $this->receiver->unbind();
    }

    /**
     * createReceiver
     * @param $ip
     * @param $port
     * @param $account
     * @param $pwd
     * @param $timeout
     * @return Closure
     */
    protected function createReceiver($ip, $port, $account, $pwd, $timeout)
    {
        return function () use ($ip, $port, $account, $pwd, $timeout) {
            //如果成功则需要进行receiver的bind
            $this->receiver = new Receiver($this);

            return $this->receiver->bind($ip, $port, $account, $pwd, $timeout, function () {
                //如果成功则创建两个客户端的探活
                Coroutine::create([$this->transmitter, 'doEnquireLink']);

                Coroutine::create([$this->receiver, 'doEnquireLink']);
            }, function () {
                //如果失败，则需要断开transmitter
                $this->transmitter->unbind();
            });
        };
    }

    /**
     * recv 暂时不设置超时有需要在弄
     * @param $timeout
     * @return bool|array
     */
    public function recv($timeout)
    {
        if (!isset($this->pduChannel)) {
            //创建pdu数据通道
            $this->pduChannel = new Coroutine\Channel(100000);

            Coroutine::create([$this->transceiver, 'recv']);
//                //只接收submit_resp
//                Coroutine::create([$this->transmitter, 'recv']);
//
//                //只接收deliver
//                Coroutine::create([$this->receiver, 'recv']);
        }

        return $this->pduChannel->pop($timeout);
    }

    /**
     * submit
     * @param $mobile
     * @param $text
     * @param $ext
     * @return array
     */
    public function submit($mobile, $text, $ext)
    {
        $client = $this->transceiver;
//            $client = $this->transmitter;

        return $client->submitSm($this->config['src_id_prefix'] . $ext, $mobile, $text);
    }
}