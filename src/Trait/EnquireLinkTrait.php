<?php

namespace SMPP3\Trait;

use Swoole\Coroutine;
use SMPP3\SMPP3Protocol;

trait EnquireLinkTrait
{
    protected $waitEnquireLinkResp = 0;//等待探活resp回来的数量
    protected $smscEnquireLikTime = 0; //对端主动探活时间

    /**
     * doEnquireLink
     */
    public function doEnquireLink()
    {
        while (true) {
            $enquireInterval = $this->smpp->getConfig('active_test_interval');

            //先休眠一个间隔时间
            while ($enquireInterval--) {
                Coroutine::sleep(1);

                //如果我方主动关闭了则直接停止
                if (!$this->client->isConnected()) {
                    return;
                }
            }

            //如果对端探活在一个时间间隔内则继续休眠，并且重置我方探活
            if (time() - $this->smscEnquireLikTime < $this->smpp->getConfig('active_test_interval')) {
                $this->waitEnquireLinkResp = 0;

                continue;
            }


            //发送探活
            $this->send(SMPP3Protocol::packEnquireLink());

            if (++$this->waitEnquireLinkResp > $this->smpp->getConfig('active_test_num')) {
                //如果探活未回应次数大于配置则断开链接发送unbind
                $this->unbind();

                return;
            }
        }
    }

    /**
     * resetSmscEnquireLikTime
     */
    public function resetSmscEnquireLikTime()
    {
        $this->smscEnquireLikTime = time();
    }

    /**
     * handleEnquireLink
     * @param $sequenceNumber
     */
    public function handleEnquireLink($sequenceNumber)
    {
        //发送响应
        $this->send(SMPP3Protocol::packEnquireLinkResp($sequenceNumber));
    }

    /**
     * handleEnquireLinkResp
     */
    public function handleEnquireLinkResp()
    {
        //如果对端回了探活回应，则将等待数量-1，如果对端发来探活，我方会重置这个数，所以可能为负数需要重置
        if (--$this->waitEnquireLinkResp < 0) {
            $this->waitEnquireLinkResp = 0;
        }
    }
}