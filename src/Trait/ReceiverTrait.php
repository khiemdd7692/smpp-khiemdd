<?php

namespace SMPP3\Trait;

use SMPP3\SMPP3Protocol;

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
        $this->send(SMPP3Protocol::packDeliverSmResp($sequenceNumber));
    }
}