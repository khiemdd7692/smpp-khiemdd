<?php
namespace SMPP3;
/**
 * SMPP3协议
 * @author JENA
 * @since 20240418
 */
class SMPP3Protocol
{
    //操作
    const GENERIC_NACK = 0x00000000;
    const BIND_RECEIVER = 0x00000001;
    const BIND_RECEIVER_RESP = 0x80000001;
    const BIND_TRANSMITTER = 0x00000002;
    const BIND_TRANSMITTER_RESP = 0x80000002;
    const QUERY_SM = 0x00000003;
    const QUERY_SM_RESP = 0x80000003;
    const SUBMIT_SM = 0x00000004;
    const SUBMIT_SM_RESP = 0x80000004;
    const DELIVER_SM = 0x00000005;
    const DELIVER_SM_RESP = 0x80000005;
    const UNBIND = 0x00000006;
    const UNBIND_RESP = 0x80000006;
    const REPLACE_SM = 0x00000007;
    const REPLACE_SM_RESP = 0x80000007;
    const CANCEL_SM = 0x00000008;
    const CANCEL_SM_RESP = 0x00000008;
    const BIND_TRANSCEIVER = 0x00000009;
    const BIND_TRANSCEIVER_RESP = 0x80000009;
    const OUTBIND = 0x0000000B;
    const ENQUIRE_LINK = 0x00000015;
    const ENQUIRE_LINK_RESP = 0x80000015;
    const SUBMIT_MULTI = 0x00000021;
    const SUBMIT_MULTI_RESP = 0x80000021;
    const ALERT_NOTIFICATION = 0x000000101;
    const DATA_SM = 0x000000103;
    const DATA_SM_RESP = 0x800000103;
    //deliver_sm中esm_class为4则代表是report
    const ESM_CLASS_DELIVERY_REPORT = 0x4;
    const ESM_CLASS_DELIVERY = 0x8;
    const ESM_CLASS_UDHI = 0x40;
    //错误
    const ESME_ROK = 0x00000000;                                                                             //无错误
    const ESME_RBINDFAIL = 0x0000000D; //Bind Failed.
    const ESME_RINVCMDID = 0x00000003;                                                                       //无效的命令ID
    const ESME_RINVSRCADR = 0x0000000A;                                                                      //原地址无效
    const ESME_RINVPASWD = 0x0000000E;                                                                       //密码错误
    const ESME_RINVSYSID = 0x0000000F;                                                                       //无效的sp
    const ESME_RTHROTTLED = 0x00000058;                                                                      //超速
    const ESME_PREPARE_START = 0x00000501;                                                                   //服务器未初始化好
    const ESME_SERVER_RESOURCE_ERR = 0x00000502;                                                             //服务器资源耗尽
    const ESME_EXCEED_CO_NUM = 0x00000503;                                                                   //携程数量过多
    const ESME_ERR_CONNECT_NUM_OUT = 0x00000504;                                                             //重试连接数超限
    const ESME_EXCEED_CON_NUM = 0x00000505;                                                                  //连接数超限
    const ESME_PRODUCT_LOCKED = 0x00000506;                                                                  //产品被锁定
    const ESME_PRODUCT_TYPE_LOCKED = 0x00000507;                                                             //产品类型被锁定
    const ESME_INS_BALANCE = 0x00000510;                                                                     //余额不足
    const ESME_ERR_LONG = 0x00000511;                                                                        //长信参数错误
    const ESME_PRODUCT_TYPE_ERR = 0x00000512;                                                                //长信参数错误
    const TAG_SAR_MSG_REF_NUM = 0x020C;
    const TAG_SAR_TOTAL_SEGMENTS = 0x020E;
    const TAG_SAR_SEGMENT_SEQNUM = 0x020F;
    const TAG_MESSAGE_PAYLOAD = 0x0424;
    const DATA_CODING_DEFAULT = 0;
    const DATA_CODING_UCS2 = 8;
    const ESME_RINVDCS = 0x00000104; //Invalid Data Coding Scheme.
    // UCS-2BE (Big Endian)
    public static $headerUnpackRule = 'Ncommand_length/Ncommand_id/Ncommand_status/Nsequence_number';        //头部解析规则
    public static $headerPackRule = 'NNNN';                                                                  //头部解析规则
    private static $sequenceId = 0;

    public static function generateProSequenceId()
    {
        return ++self::$sequenceId;
    }

    /**
     * packBind
     * @param $commandId
     * @param $account
     * @param $pwd
     * @param $systemType
     * @param $interfaceVersion
     * @param $addr_ton
     * @param $addr_npi
     * @param $address_range
     * @return string
     */
    protected static function packBind($commandId, $account, $pwd, $systemType, $interfaceVersion, $addr_ton, $addr_npi, $address_range): string
    {
        //生成响应体
        $respBodyBinary = pack(
            'a' . (strlen($account) + 1) .
            'a' . (strlen($pwd) + 1) .
            'a' . (strlen($systemType) + 1) .
            'CCC' .
            'a' . (strlen($address_range) + 1),
            $account,
            $pwd,
            $systemType,
            $interfaceVersion,
            $addr_ton,
            $addr_npi,
            $address_range
        );

        //生成响应头
        $respHeaderBinary = pack(self::$headerPackRule, strlen($respBodyBinary) + 16, $commandId, null, self::generateProSequenceId());

        return $respHeaderBinary . $respBodyBinary;
    }

    /**
     * packBindResp
     * @param $commandId
     * @param $commandStatus
     * @param $sequenceNum
     * @param $systemId
     * @return string
     */
    public static function packBindResp($commandId, $commandStatus, $sequenceNum, $systemId): string
    {
        if ($systemId) {
            $respBodyBinary = pack('a' . (strlen($systemId) + 1), $systemId);
        } else {
            $respBodyBinary = '';
        }

        $respHeaderBinary = pack(self::$headerPackRule, strlen($respBodyBinary) + 16, $commandId, $commandStatus, $sequenceNum);

        return $respHeaderBinary . $respBodyBinary;
    }

    /**
     * packBindTransceiver
     * @param $account
     * @param $pwd
     * @param $systemType
     * @param $interfaceVersion
     * @param $addr_ton
     * @param $addr_npi
     * @param $address_range
     * @return string
     */
    public static function packBindTransceiver($account, $pwd, $systemType, $interfaceVersion, $addr_ton, $addr_npi, $address_range): string
    {
        return self::packBind(self::BIND_TRANSCEIVER, ...func_get_args());
    }

    /**
     * packBindTransceiverResp
     * @param $commandStatus
     * @param $sequenceNum
     * @param null $systemId
     * @return string
     */
    public static function packBindTransceiverResp($commandStatus, $sequenceNum, $systemId = null): string
    {
        return self::packBindResp(self::BIND_TRANSCEIVER_RESP, $commandStatus, $sequenceNum, $systemId);
    }

    /**
     * packBindTransmitter
     * @param $account
     * @param $pwd
     * @param $systemType
     * @param $interfaceVersion
     * @param $addr_ton
     * @param $addr_npi
     * @param $address_range
     * @return string
     */
    public static function packBindTransmitter($account, $pwd, $systemType, $interfaceVersion, $addr_ton, $addr_npi, $address_range): string
    {
        return self::packBind(self::BIND_TRANSMITTER, ...func_get_args());
    }

    /**
     * packBindTransmitterResp
     * @param $commandStatus
     * @param $sequenceNum
     * @param null $systemId
     * @return string
     */
    public static function packBindTransmitterResp($commandStatus, $sequenceNum, $systemId = null): string
    {
        return self::packBindResp(self::BIND_TRANSMITTER_RESP, $commandStatus, $sequenceNum, $systemId);
    }

    /**
     * packBindReceiver
     * @param $account
     * @param $pwd
     * @param $systemType
     * @param $interfaceVersion
     * @param $addr_ton
     * @param $addr_npi
     * @param $address_range
     * @return string
     */
    public static function packBindReceiver($account, $pwd, $systemType, $interfaceVersion, $addr_ton, $addr_npi, $address_range): string
    {
        return self::packBind(self::BIND_RECEIVER, ...func_get_args());
    }

    /**
     * packBindReceiverResp
     * @param $commandStatus
     * @param $sequenceNum
     * @param null $systemId
     * @return string
     */
    public static function packBindReceiverResp($commandStatus, $sequenceNum, $systemId = null): string
    {
        return self::packBindResp(self::BIND_RECEIVER_RESP, $commandStatus, $sequenceNum, $systemId);
    }

    /**
     * packUnbind
     * @return string
     */
    public static function packUnbind(): string
    {
        return pack(self::$headerPackRule, 16, self::UNBIND, null, self::generateProSequenceId());
    }

    /**
     * packUnbindResp
     * @param $sequenceNum
     * @param null $commandStatus
     * @return string
     */
    public static function packUnbindResp($sequenceNum, $commandStatus = null): string
    {
        return pack(self::$headerPackRule, 16, self::UNBIND_RESP, $commandStatus, $sequenceNum);
    }

    /**
     * packSubmitAndDeliver
     * @param $sourceAddr
     * @param $destinationAddr
     * @param $shortMessage
     * @param $esmClass
     * @param $commandId
     * @param $sequenceNum
     * @param $dataEncoding
     * @return string
     */
    protected static function packSubmitAndDeliver($sourceAddr, $destinationAddr, $shortMessage, $esmClass, $commandId, $sequenceNum, $dataEncoding): string
    {
        $sourceAddrLen = strlen($sourceAddr);

        if ($sourceAddrLen > 21) {
            $sourceAddrLen = 21;

            $sourceAddr = substr($sourceAddr, 0, 21);
        }

        $destinationAddrLen = strlen($destinationAddr);

        if ($destinationAddrLen > 21) {
            $destinationAddrLen = 21;

            $destinationAddr = substr($destinationAddr, 0, 21);
        }

        $smsLen = strlen($shortMessage);

        if ($commandId === self::DELIVER_SM && $smsLen > 254) {
            //如果是deliver超长不支持分片，需要走payload
            $payload    = $shortMessage;
            $payloadLen = $smsLen;

            $shortMessage = '';
            $smsLen       = 0;
        }

        $respBodyBinary = pack(
            'aCC' .
            'a' . ($sourceAddrLen + 1) .
            'CC' .
            'a' . ($destinationAddrLen + 1) .
            'CCCaaCCCCC' .
            'a' . $smsLen
            ,
            null,                //service_type
            5,                   //source_addr_ton
            0,                   //source_addr_npi
            $sourceAddr,         //source_addr
            1,                   //dest_addr_ton
            1,                   //dest_addr_npi
            $destinationAddr,    //destination_addr
            $esmClass,           //esm_class 长信如果需要拆分发送则需要设置此字段 合并发送则默认无需设置
            0,                   //protocol_id
            3,                   //priority_flag
            null,                //schedule_delivery_time
            null,                //validity_period
            1,                   //registered_delivery
            0,                   //replace_if_present_flag
            $dataEncoding,       //data_coding
            0,                   //sm_default_msg_id
            $smsLen,             //sm_length
            $shortMessage//sm_length
        );

        if (isset($payload)) {
            $respBodyBinary .= pack('nna*', 0x0424, $payloadLen, $payload);
        }

        //生成响应头
        $respHeaderBinary = pack(self::$headerPackRule, strlen($respBodyBinary) + 16, $commandId, null, $sequenceNum);

        return $respHeaderBinary . $respBodyBinary;
    }

    /**
     * packSubmitSm
     * @param $sourceAddr
     * @param $destinationAddr
     * @param $shortMessage
     * @param $sequenceNum
     * @param $esmClass
     * @param $dataEncoding
     * @return string
     */
    public static function packSubmitSm($sourceAddr, $destinationAddr, $shortMessage, $sequenceNum, $esmClass, $dataEncoding): string
    {
        return self::packSubmitAndDeliver($sourceAddr, $destinationAddr, $shortMessage, $esmClass, self::SUBMIT_SM, $sequenceNum, $dataEncoding);
    }

    /**
     * packSubmitSmResp
     * @param $commandStatus
     * @param $sequenceNum
     * @param null $msgId
     * @return string
     */
    public static function packSubmitSmResp($commandStatus, $sequenceNum, $msgId = null): string
    {
        if ($msgId) {
            $respBodyBinary = pack('a' . (strlen($msgId) + 1), $msgId);
        } else {
            $respBodyBinary = '';
        }

        $respHeaderBinary = pack(self::$headerPackRule, strlen($respBodyBinary) + 16, self::SUBMIT_SM_RESP, $commandStatus, $sequenceNum);

        return $respHeaderBinary . $respBodyBinary;
    }

    /**
     * packDeliverSm
     * @param $esmClass
     * @param $sourceAddr
     * @param $destinationAddr
     * @param $shortMessage
     * @return string
     */
    public static function packDeliverSm($esmClass, $sourceAddr, $destinationAddr, $shortMessage): string
    {
        if ($esmClass === self::ESM_CLASS_DELIVERY_REPORT) {
            //report
            $date = date('ymdHi');

            $shortMessage = implode(' ', [
                'id:' . $shortMessage['id'],
                'sub:' . '000',
                'dlvrd:' . '000',
                'submit date:' . $date,
                'done date:' . $date,
                'stat:' . $shortMessage['stat'],
                'err:' . '000',
                'text:' . $shortMessage['text'],
            ]);
        }

        return self::packSubmitAndDeliver($sourceAddr, $destinationAddr, $shortMessage, $esmClass, self::DELIVER_SM, self::generateProSequenceId(), self::DATA_CODING_UCS2);
    }

    /**
     * packDeliverSmResp
     * @param $sequenceNum
     * @return string
     */
    public static function packDeliverSmResp($sequenceNum): string
    {
        $respBodyBinary = pack('a', null);

        //生成响应头
        $respHeaderBinary = pack(self::$headerPackRule, strlen($respBodyBinary) + 16, self::DELIVER_SM_RESP, self::ESME_ROK, $sequenceNum);

        return $respHeaderBinary . $respBodyBinary;
    }

    /**
     * packEnquireLink
     * @return string
     */
    public static function packEnquireLink(): string
    {
        return pack(self::$headerPackRule, 16, self::ENQUIRE_LINK, null, self::generateProSequenceId());
    }

    /**
     * packEnquireLinkResp
     * @param $sequenceNum
     * @return false|string
     */
    public static function packEnquireLinkResp($sequenceNum)
    {
        return pack(self::$headerPackRule, 16, self::ENQUIRE_LINK_RESP, null, $sequenceNum);
    }

    /**
     * packGenericNack
     * @param $commandStatus
     * @param $sequenceNum
     * @return false|string
     */
    public static function packGenericNack($commandId, $commandStatus, $sequenceNum)
    {
        return pack(self::$headerPackRule, 16, $commandId, $commandStatus, $sequenceNum);
    }

    /**
     * unpackHeader
     * @param $headerBinary
     * @return array
     */
    public static function unpackHeader($headerBinary): array
    {
        return @unpack(self::$headerUnpackRule, $headerBinary) ?: [];
    }

    /**
     * unpackBind
     * @param $bodyBinary
     * @return array
     */
    public static function unpackBind($bodyBinary): array
    {
        if (empty($bodyBinary)) {
            return [];
        }

        $binaryArr = explode(chr(0), $bodyBinary, 3);

        if (empty($binaryArr[0]) || empty($binaryArr[1])) {
            return [];
        }

        $bodyArr = unpack('a' . strlen($binaryArr[0]) . 'system_id/a' . strlen($binaryArr[1]) . 'password', $binaryArr[0] . $binaryArr[1]);

        return $bodyArr ?: [];
    }

    /**
     * unpackBindResp
     * @param $bodyBinary
     * @return array
     */
    public static function unpackBindResp($bodyBinary): array
    {
        if (empty($bodyBinary)) {
            return [];
        }

        $binaryArr = explode($bodyBinary, chr(0), 2);

        $bodyArr = @unpack('a' . strlen($binaryArr[0]) . 'system_id', $binaryArr[0]) ?: [];

        if (isset($binaryArr[1]) && $tagArr = @unpack('ntag/nlength/Cvalue', $binaryArr[1])) {
            $bodyArr['sc_interface_version'] = $tagArr['value'];
        }

        return $bodyArr;
    }

    /**
     * unpackSubmitAndDeliver
     * @param $bodyBinary
     * @return array
     */
    protected static function unpackSubmitAndDeliver($bodyBinary): array
    {
        $serviceTypePos = strpos($bodyBinary, chr(0));

        $sourceAddrOffset = $serviceTypePos + 3;

        $sourceAddrPos = strpos($bodyBinary, chr(0), $sourceAddrOffset);

        $destinationAddrOffset = $sourceAddrPos + 3;

        $destinationAddrPos = strpos($bodyBinary, chr(0), $destinationAddrOffset);

        if ($serviceTypePos === false || $sourceAddrPos === false || $destinationAddrPos === false) {
            return [];
        }

        $scheduleDeliveryTimeOffset = $destinationAddrPos + 4;

        $scheduleDeliveryTimePos = strpos($bodyBinary, chr(0), $scheduleDeliveryTimeOffset);

        if ($scheduleDeliveryTimePos === $scheduleDeliveryTimeOffset) {
            //如果null的位置和偏移量相等，则代表是1位
            $scheduleDeliveryTimeLength = 1;

            $validityPeriodOffset = $scheduleDeliveryTimePos + 1;
        } else {
            //否则代表是17位
            $scheduleDeliveryTimeLength = 17;

            $validityPeriodOffset = $scheduleDeliveryTimePos + 18;
        }

        $validityPeriodPos = strpos($bodyBinary, chr(0), $validityPeriodOffset);

        if ($validityPeriodPos === $validityPeriodOffset) {
            $validityPeriodLength = 1;
        } else {
            $validityPeriodLength = 17;
        }

        $smLengthPos = $validityPeriodPos + 5;

        $serviceTypeLength = $serviceTypePos + 1;

        $sourceAddrLength = $sourceAddrPos - $serviceTypePos - 2;

        $destinationAddrLength = $destinationAddrPos - $sourceAddrPos - 2;

        $smLength = unpack('C', $bodyBinary[$smLengthPos]);

        if ($smLength === false) {
            return [];
        }

        $smLength = reset($smLength);

        $rules = [
            'a' . $serviceTypeLength . 'service_type',
            'Csource_addr_ton',
            'Csource_addr_npi',
            'a' . $sourceAddrLength . 'source_addr',
            'Cdest_addr_ton',
            'Cdest_addr_npi',
            'a' . $destinationAddrLength . 'destination_addr',
            'Cesm_class',
            'Cprotocol_id',
            'Cpriority_flag',
            'a' . $scheduleDeliveryTimeLength . 'schedule_delivery_time',
            'a' . $validityPeriodLength . 'validity_period',
            'Cregistered_delivery',
            'Creplace_if_present_flag',
            'Cdata_coding',
            'Csm_default_msg_id',
            'Csm_length',
            'a' . $smLength . 'short_message',
        ];

        $dataSm = @unpack(implode('/', $rules), $bodyBinary);

        if ($dataSm === false) {
            return [];
        }

        $tagsBinary = substr($bodyBinary, $smLengthPos + $smLength + 1);

        $tags = self::unpackTag($tagsBinary);

        if (isset($tags[self::TAG_MESSAGE_PAYLOAD])) {
            //长信转短信
            $dataSm['short_message'] = $tags[self::TAG_MESSAGE_PAYLOAD];
        } elseif (isset($tags[self::TAG_SAR_TOTAL_SEGMENTS])) {
            $dataSm['long_total']  = $tags[self::TAG_SAR_TOTAL_SEGMENTS];
            $dataSm['long_index']  = $tags[self::TAG_SAR_SEGMENT_SEQNUM];
            $dataSm['long_unique'] = $tags[self::TAG_SAR_MSG_REF_NUM];
        } elseif ($dataSm['esm_class'] & self::ESM_CLASS_UDHI) {
            $udhLen = substr($dataSm['short_message'], 0, 1);

            $dataSm['udh_len'] = unpack('cUdhLen', $udhLen)['UdhLen'];

            if ($dataSm['udh_len'] == 5) {
                $udh                     = substr($dataSm['short_message'], 3, 3);
                $dataSm['short_message'] = substr($dataSm['short_message'], 6);
                $dataSm                  += (array)unpack('clong_unique/clong_total/clong_index', $udh);
            } else {
                $udh                     = substr($dataSm['short_message'], 3, 4);
                $dataSm['short_message'] = substr($dataSm['short_message'], 7);
                $dataSm                  += (array)unpack('nlong_unique/clong_total/clong_index', $udh);
            }
        }

        foreach ($dataSm as $key => &$value) {
            if (is_array($value)) {
                foreach ($value as &$val) {
                    $val = is_string($val) ? trim($val) : $val;
                }
            } else {
                if ($key === 'short_message') {
                    continue;
                }

                $value = is_string($value) ? trim($value) : $value;
            }
        }

        return $dataSm;
    }

    /**
     * packLongSmsSlice
     * @param $message
     * @param $mark
     * @param $total
     * @param $index
     * @return string
     */
    public static function packLongSmsSlice($message, $mark, $total, $index): string
    {
        $udh = pack('cccccc', 5, 0, 3, $mark, $total, $index);

        return $udh . $message;
    }

    /**
     * unpackTag
     * @param $binary
     * @return array
     */
    public static function unpackTag($binary): array
    {
        if (empty($binary) || empty($lenBin = substr($binary, 2, 2))) {
            return [];
        }

        $len = unpack('n', $lenBin);

        if ($len === false) {
            return [];
        }

        $len = reset($len);

        $tag = unpack('nname/nlength/a' . $len . 'value', $binary);

        if ($tag === false) {
            return [];
        }

        $tag = [$tag['name'] => $tag['value']];

        $surplusBinary = substr($binary, 4 + $len);

        $nextTag = self::unpackTag($surplusBinary);

        if (empty($nextTag)) {
            return $tag;
        } else {
            return ($tag + $nextTag) ?: [];
        }
    }

    /**
     * unpackSubmitSm
     * @param $bodyBinary
     * @return array
     */
    public static function unpackSubmitSm($bodyBinary): array
    {
        return self::unpackSubmitAndDeliver($bodyBinary);
    }

    /**
     * unpackSubmitSmResp
     * @param $bodyBinary
     * @return array
     */
    public static function unpackSubmitSmResp($bodyBinary): array
    {
        if ($bodyBinary) {
            $bodyArr = @unpack('a' . strlen($bodyBinary) . 'message_id', $bodyBinary);
        }

        $bodyArr = empty($bodyArr) ? [] : $bodyArr;

        foreach ($bodyArr as &$value) {
            $value = is_string($value) ? trim($value) : $value;
        }

        return $bodyArr;
    }

    /**
     * unpackDeliverSm
     * @param $bodyBinary
     * @return array
     */
    public static function unpackDeliverSm($bodyBinary): array
    {
        if (empty($deliverArr = self::unpackSubmitAndDeliver($bodyBinary))) {
            return [];
        }

        if ($deliverArr['esm_class'] === self::ESM_CLASS_DELIVERY_REPORT) {
            //代表report 需要继续解包message
            $tmp = explode(' ', $deliverArr['short_message']);

            if ($tmp === false) {
                return [];
            }

            if (count($tmp) > 7) {
                //有的submit_data是以下划线有的以空格
                if (strpos($tmp[3], ':') === false) {
                    unset($tmp[3]);

                    $tmp[4] = 'submit_' . $tmp[4];
                }

                //兼容done date
                if (strpos($tmp[5], ':') === false) {
                    unset($tmp[5]);

                    $tmp[6] = 'done_' . $tmp[6];
                }
            }

            $deliverArr['short_message'] = [];

            foreach ($tmp as $value) {
                if (strpos($value, ':') === false) {
                    continue;
                }

                [$k, $v] = explode(':', $value, 2);

                $deliverArr['short_message'][$k] = $v;
            }
        }

        foreach ($deliverArr as $key => &$value) {
            if (is_array($value)) {
                foreach ($value as &$val) {
                    $val = is_string($val) ? trim($val) : $val;
                }
            } else {
                if ($key === 'short_message') {
                    continue;
                }

                $value = is_string($value) ? trim($value) : $value;
            }
        }

        return $deliverArr;
    }
}