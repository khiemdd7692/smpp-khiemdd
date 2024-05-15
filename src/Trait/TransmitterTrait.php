<?php

namespace SMPP3\Trait;

use SMPP3\SMPP3Protocol;
use SMPP3\Util\GSMEncoder;

trait TransmitterTrait
{
    protected $longSmsMark = 0;

    /**
     * submitSm
     * @param $srcId
     * @param $mobile
     * @param $text
     * @return array
     */
    public function submitSm($srcId, $mobile, $text)
    {
        //如果字节数超过254，则无法放在short_message字段中，可以通过三种方法解决
        //1.可通过message_payload参数一次行传输 https://smpp.org/SMPP_v3_4_Issue1_2.pdf 61页
        //2.通过sar_msg_ref_num，sar_total_segments，sar_segment_seqnum系列参数分片传输
        //3.通过将内容切分仍然放在short_message字段分片传输，这种需要配合ems_class设置为0x40进行传输（目前采用）
        if (GSMEncoder::isGsm0338($text)) {
            $dataEncoding = SMPP3Protocol::DATA_CODING_DEFAULT;

            $text = GSMEncoder::utf8ToGsm0338($text);

            //将采用gsm7编码
            if (strlen($text) > 160) {
                $splitMessages = str_split($text, 152);
                //市面上的gsm7貌似有问题以实际还是以八位传输
//                foreach ($splitMessages as $key => $message) {
//                    $splitMessages[$key] = Cmpp::packGsm7($message, 6);
//                }

                $esmClass = SMPP3Protocol::ESM_CLASS_UDHI;
            } else {
//                $splitMessages = [Cmpp::packGsm7($text, 0)];
                $splitMessages = [$text];

                $esmClass = 0;
            }

        } else {
            $dataEncoding = SMPP3Protocol::DATA_CODING_UCS2;

            $text = mb_convert_encoding($text, 'UCS-2', 'UTF-8');

            if (strlen($text) > 140) {
                $splitMessages = str_split($text, 132);

                $esmClass = SMPP3Protocol::ESM_CLASS_UDHI;
            } else {
                $splitMessages = [$text];

                $esmClass = 0;
            }
        }

        if ($esmClass === SMPP3Protocol::ESM_CLASS_UDHI) {
            $totalNum = count($splitMessages);

            $mark = $this->getLogSmsMark();

            foreach ($splitMessages as $index => $message) {
                $splitMessages[$index] = SMPP3Protocol::packLongSmsSlice($message, $mark, $totalNum, $index + 1);
            }
        }


        $sequenceNums = [];

        foreach ($splitMessages as $msg) {
            $this->flowRateControl();

            $sequenceNum = SMPP3Protocol::generateProSequenceId();

            $sequenceNums[] = $sequenceNum;

            $pdu = SMPP3Protocol::packSubmitSm($srcId, $mobile, $msg, $sequenceNum, $esmClass, $dataEncoding);

            $this->send($pdu);
        }

        return $sequenceNums;
    }

    /**
     * getLogSmsMark
     * @return int
     */
    protected function getLogSmsMark()
    {
        if (++$this->longSmsMark > 255) {
            $this->longSmsMark = 1;
        }

        return $this->longSmsMark;
    }
}