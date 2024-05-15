<?php

namespace SMPP3\Util;

use SMPP3\Abstract\BaseTrans;
use SMPP3\SMPP3Protocol;
use SMPP3\Trait\ReceiverTrait;

/**
 *
 */
class Receiver extends BaseTrans
{
    use ReceiverTrait;

    /**
     * getBindPdu
     * @param $account
     * @param $pwd
     * @return string
     */
    public function getBindPdu($account, $pwd)
    {
        return SMPP3Protocol::packBindReceiver(
            $account,
            $pwd,
            $this->smpp->getConfig('system_type'),
            $this->smpp->getConfig('interface_version'),
            $this->smpp->getConfig('addr_ton'),
            $this->smpp->getConfig('addr_npi'),
            $this->smpp->getConfig('address_range')
        );
    }

    /**
     * unpackBindResp
     * @param $pdu
     * @return array|bool
     */
    public function unpackBindResp($pdu)
    {
        $headerArr = SMPP3Protocol::unpackHeader(substr($pdu, 0, 16));

        if ($headerArr['command_id'] !== SMPP3Protocol::BIND_RECEIVER_RESP) {
            return false;
        }

        $bodyArr = SMPP3Protocol::unpackBindResp(substr($pdu, 16));

        return array_merge($headerArr, $bodyArr);
    }

    /**
     * handlePdu
     * @param $pdu
     * @return array
     */
    public function handlePdu($pdu)
    {
        $headerArr = SMPP3Protocol::unpackHeader(substr($pdu, 0, 16));

        $this->resetSmscEnquireLikTime();

        //只返回submit_resp和deliver 其他的接收处理后跳过
        switch ($headerArr['command_id']) {
            case SMPP3Protocol::DELIVER_SM:
                $data = SMPP3Protocol::unpackDeliverSm(substr($pdu, 16));

                if (empty($data)) {
                    break;
                }

                $this->handleDeliverSm($headerArr['sequence_number']);

                return array_merge($headerArr, $data);
            case SMPP3Protocol::ENQUIRE_LINK:
                $this->handleEnquireLink($headerArr['sequence_number']);

                break;
            case SMPP3Protocol::ENQUIRE_LINK_RESP:
                $this->handleEnquireLinkResp();

                break;
            case SMPP3Protocol::UNBIND:
                $this->handleUnbind($headerArr['sequence_number']);

                break;
            default:
                break;
        }

        return [];
    }

    /**
     * close
     */
    public function close()
    {
        $this->client->close();

        $this->smpp->getTransmitter()->unbind();
    }
}