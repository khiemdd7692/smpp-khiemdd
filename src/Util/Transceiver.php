<?php

namespace SMPP\Util;

use SMPP\Abstract\BaseTrans;
use SMPP\SMPPProtocol;
use SMPP\Trait\FlowRateTrait;
use SMPP\Trait\ReceiverTrait;
use SMPP\Trait\TransmitterTrait;

/**
 *
 */
class Transceiver extends BaseTrans
{
    use FlowRateTrait, TransmitterTrait, ReceiverTrait;

    public function __construct($smpp)
    {
        parent::__construct($smpp);

        $this->setMaxFlowRate($this->smpp->getConfig('submit_per_sec'));
    }

    /**
     * getBindPdu
     * @param $account
     * @param $pwd
     * @return string
     */
    public function getBindPdu($account, $pwd)
    {
        return SMPPProtocol::packBindTransceiver(
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
        $headerArr = SMPPProtocol::unpackHeader(substr($pdu, 0, 16));

        if ($headerArr['command_id'] === SMPPProtocol::BIND_RECEIVER_RESP) {
            return false;
        }

        $bodyArr = SMPPProtocol::unpackBindResp(substr($pdu, 16));

        return array_merge($headerArr, $bodyArr);
    }

    /**
     * handlePdu
     * @param $pdu
     * @return array
     */
    public function handlePdu($pdu)
    {
        $headerArr = SMPPProtocol::unpackHeader(substr($pdu, 0, 16));

        $this->resetSmscEnquireLikTime();

        //只返回submit_resp和deliver 其他的接收处理后跳过
        switch ($headerArr['command_id']) {
            case SMPPProtocol::SUBMIT_SM_RESP:
                $data = SMPPProtocol::unpackSubmitSmResp(substr($pdu, 16));

                if (empty($data)) {
                    break;
                }

                return array_merge($headerArr, $data);
            case SMPPProtocol::DELIVER_SM:
                $data = SMPPProtocol::unpackDeliverSm(substr($pdu, 16));

                if (empty($data)) {
                    break;
                }

                $this->handleDeliverSm($headerArr['sequence_number']);

                return array_merge($headerArr, $data);
            case SMPPProtocol::ENQUIRE_LINK:
                $this->handleEnquireLink($headerArr['sequence_number']);

                break;
            case SMPPProtocol::ENQUIRE_LINK_RESP:
                $this->handleEnquireLinkResp();

                break;
            case SMPPProtocol::UNBIND:
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
    }
}