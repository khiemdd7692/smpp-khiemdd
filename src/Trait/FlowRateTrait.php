<?php

namespace SMPP3\Trait;

use Swoole\Coroutine;
trait FlowRateTrait
{
    protected $maxFlowRate;              //最大流速
    protected $currentFlowRate = 0;      //当前流速
    protected $currentSecond;            //当前流速时间
    protected $currentHundredMillisecond;//当前流速时间的毫秒百位数
    protected $type = 1;                 //1一秒分成十分，2不分

    /**
     * setMaxFlowRate
     * @param $flowRate
     */
    public function setMaxFlowRate($flowRate, $type = 1)
    {
        $this->type = $type;

        if ($this->type === 1) {
            $this->maxFlowRate = (int)($flowRate / 10);
        } else {
            $this->maxFlowRate = $flowRate;
        }
    }


    /**
     * flowRateControl 流速控制秒为维度
     */
    public function flowRateControl()
    {
        [$currentMillisecond, $currentSecond] = explode(' ', microtime());

        //记录的秒数时间和数量已经不在当前时间了则更新
        if ($currentSecond !== $this->currentSecond) {
            $this->currentFlowRate = 1;

            $this->currentSecond = $currentSecond;

            $this->currentHundredMillisecond = '0';

            return;
        }

        if ($this->type === 1) {
            //当前百位的毫秒数
            $currentHundredMillisecond = $currentMillisecond[2];

            if ($currentHundredMillisecond !== $this->currentHundredMillisecond) {
                $this->currentFlowRate = 1;

                $this->currentHundredMillisecond = $currentHundredMillisecond;

                return;
            }
        }

        //流速在范围内
        if (++$this->currentFlowRate < $this->maxFlowRate) {
            return;
        }

        //当前需要休眠的时间
        if ($this->type === 1) {
            $sleepTime = 0.1 - ('0.0' . $currentMillisecond[3] . $currentMillisecond[4]);
        } else {
            $sleepTime = 1 - ('0.' . $currentMillisecond[2] . $currentMillisecond[3] . $currentMillisecond[4]);
        }


        if ($sleepTime >= 0.001) {
            //超过流速了则休眠
            Coroutine::sleep($sleepTime);
        }
    }
}