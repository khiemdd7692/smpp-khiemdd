<?php

namespace SMPP\Trait;

use SMPP\SMPPProtocol;

/**
 *
 */
trait ReceiverTrait
{
    /**
     * handleDeliverSm
     * @param $sequenceNumber
     */
    public function handleDeliverSm($sequenceNumber)
    {
        $this->send(SMPPProtocol::packDeliverSmResp($sequenceNumber));
    }
}