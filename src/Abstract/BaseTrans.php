<?php

namespace SMPP3\Abstract;

use SMPP3\SMPP3Protocol;
use Swoole\Coroutine;
use Swoole\Coroutine\Client;
use Swoole\Coroutine\Channel;
use SMPP3\Trait\EnquireLinkTrait;
abstract class BaseTrans
{
    use EnquireLinkTrait;

    /** @var Client */
    protected $client;
    /** @var SMPP3Client */
    protected $smpp;
    /** @var Channel */
    protected $channel;

    abstract public function getBindPdu($account, $pwd);

    abstract public function unpackBindResp($pdu);

    abstract public function handlePdu($pdu);

    abstract public function close();

    public function __construct($smpp)
    {
        $this->client = new Client(SWOOLE_SOCK_TCP);

        $this->client->set([
            'open_length_check'     => true,
            'package_length_type'   => 'N',
            'package_length_offset' => 0,
            'package_body_offset'   => 0,
        ]);

        $this->smpp = $smpp;
    }

    /**
     * clientErr
     * @param bool $close
     * @return bool
     */
    protected function clientErr($close = true)
    {
        $this->smpp->syncClientErr($this->client->errCode, $this->client->errMsg);

        if ($close) {
            $this->client->close();
        }

        return false;
    }

    /**
     * customError
     * @param $errCode
     * @param $errMsg
     * @param bool $close
     * @return bool
     */
    protected function customError($errCode, $errMsg, $close = false)
    {
        $this->smpp->syncClientErr($errCode, $errMsg);

        if ($close) {
            $this->client->close();
        }

        return false;
    }

    /**
     * getSurplusTimeout
     * @param $timeout
     * @return bool|int
     */
    protected function getSurplusTimeout($timeout)
    {
        //如果过期时间是永不过期则直接返回
        if ($timeout <= 0) {
            return $timeout;
        }

        //求取剩余的超时时间
        $timeout = $timeout - time() + $this->smpp->getStartBindTime();

        return $timeout <= 0 ? false : $timeout;
    }

    /**
     * checkAndGetPdu
     * @param $timeout
     * @return bool|mixed
     */
    protected function checkAndGetPdu($timeout = -1)
    {
        if (!$this->client->isConnected()) {
            return $this->customError(8, 'the connection is broken');
        }

        if (($responsePdu = $this->client->recv($timeout)) === false) {
            //接收错误，如超时 同步客户端错误，不关闭链接

            if ($this->client->errCode === 104) {
                $this->customError(8, 'the connection is broken');
            } else {
                $this->clientErr(false);
            }

            return false;
        }

        if ($responsePdu === '') {
            //对端主动关闭了tcp链接 同步自定义错误，不关闭链接
            return $this->customError(8, 'the connection is broken');
        }

        if (strlen($responsePdu) < 16) {
            //login Response pdu长度异常 同步自定义错误，不关闭链接
            return $this->customError(93, 'Incorrect pdu command id');
        }

        return $responsePdu;
    }

    /**
     * doHook
     * @param $hook
     * @return mixed|null
     */
    protected function doHook($hook)
    {
        $hook && $hookRes = call_user_func($hook);

        return $hookRes ?? null;
    }

    /**
     * bind
     * @param $ip
     * @param $port
     * @param $account
     * @param $pwd
     * @param $timeout
     * @param callable|null $success
     * @param callable|null $fail
     * @return bool|array|mixed
     */
    public function bind($ip, $port, $account, $pwd, $timeout, ?callable $success = null, ?callable $fail = null)
    {
        if (($timeout = $this->getSurplusTimeout($timeout)) === false) {
            //如果超时了则返回超时错误，断开链接
            $this->customError(110, 'Connection timed');

            return self::doHook($fail) ?? false;
        }

        //进行tcp链接
        if (!$this->client->connect($ip, (int)$port, $timeout)) {
            //出错则断开链接
            $this->clientErr();

            return self::doHook($fail) ?? false;
        }

        //获取链接pdu
        $pdu = $this->getBindPdu($account, $pwd);

        //如果tcp链接就刚刚好超时了则返回超时错误 实际tcp链接上了
        if (($timeout = $this->getSurplusTimeout($timeout)) === false) {
            //断开链接
            $this->customError(110, 'Connection timed', true);

            return self::doHook($fail) ?? false;
        }

        //发送bind pdu
        if (!$this->client->send($pdu)) {
            //发送出错断开链接
            $this->clientErr();

            return self::doHook($fail) ?? false;
        }

        if (($responsePdu = $this->checkAndGetPdu($timeout)) === false) {
            //pdu接收错误 错误已经同步所以直接关闭链接就行
            $this->client->close();

            return self::doHook($fail) ?? false;
        }

        //解包
        if (($responseArr = $this->unpackBindResp($responsePdu)) === false) {
            //出错只有一种可能commandId不对，这是后断开链接
            $this->customError(8, 'Incorrect pdu command id', true);

            return self::doHook($fail) ?? false;
        }

        if ($responseArr['command_status'] !== SMPP3Protocol::ESME_ROK) {
            //如果链接失败，则关闭tcp链接
            $this->client->close();

            $hook = $fail;
        } else {
            $hook = $success;

            if (!isset($this->channel)) {
                $this->channel = new Channel(5000);

                Coroutine::create(function () {
                    while (true) {
                        if (!($this->channel instanceof Channel)) {
                            break;
                        }

                        $data = $this->channel->pop();

                        if (is_string($data)) {
                            $this->client->send($data);
                        } else {
                            $this->client->send($data[0]);

                            $this->client->close();

                            $this->channel->close();

                            $this->channel = null;
                        }
                    }
                });
            }
        }

        return self::doHook($hook) ?? $responseArr;
    }

    /**
     * unbind
     */
    public function unbind()
    {
        if ($this->client->errCode !== 8 && $this->client->errCode !== 110) {
            $pdu = SMPP3Protocol::packUnbind();

            $this->send([$pdu]);
        }
    }

    /**
     * handleUnbind
     * @param $sequenceNumber
     */
    public function handleUnbind($sequenceNumber)
    {
        $this->send([SMPP3Protocol::packUnbindResp($sequenceNumber)]);
    }

    /**
     * recv
     */
    public function recv()
    {
        while (true) {
            //recv出错
            if (($responsePdu = $this->checkAndGetPdu()) === false) {
                //如果错误是110或者8则断开链接其他情况不断开
                if ($this->smpp->errCode === 8 || $this->smpp->errCode === 110) {
                    //关闭tcp链接 如果是receiver或者transmitter则还需要关闭对应的接或者收器
                    $this->close();

                    //将false推入队列
                    $this->smpp->getChannel()->push($responsePdu);

                    break;
                }

                //其他情况比如长度不足则直接跳过
                Coroutine::sleep(1);

                continue;
            }

            $unpackData = $this->handlePdu($responsePdu);

            if ($unpackData) {
                $this->smpp->getChannel()->push($unpackData);
            }
        }
    }

    /**
     * send
     * @param $data
     */
    public function send($data)
    {
        if ($this->channel instanceof Channel) {
            $this->channel->push($data);
        }
    }
}