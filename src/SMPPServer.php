<?php

namespace SMPP;

class SMPPServer
{
    public $allowCommands = [
        SMPPProtocol::GENERIC_NACK,
        SMPPProtocol::BIND_RECEIVER,
        SMPPProtocol::BIND_TRANSMITTER,
        SMPPProtocol::BIND_TRANSCEIVER,
        SMPPProtocol::UNBIND,
        SMPPProtocol::UNBIND_RESP,
        SMPPProtocol::SUBMIT_SM,
        SMPPProtocol::DELIVER_SM_RESP,
        SMPPProtocol::ENQUIRE_LINK,
        SMPPProtocol::ENQUIRE_LINK_RESP,
    ];
    public $notHandleCommands = [
        SMPPProtocol::GENERIC_NACK,
        SMPPProtocol::DELIVER_SM_RESP,
        SMPPProtocol::UNBIND_RESP,
        SMPPProtocol::ENQUIRE_LINK_RESP,
    ];
    public $needCloseFd = false;//是否需要关闭连接
    public $response;           //协议响应
    protected $commandId;       //协议动作
    protected $headerBinary;    //协议头
    protected $bodyBinary;      //协议头
    protected $headerArr;       //解析后的协议头
    protected $bodyArr;         //解析后的协议头
    protected $msgHexId;        //msg id的十六进制字符串表现
    protected $msgIdDecArr;     //十进制msgid数组
    private static $msgSequenceId = 0;

    /**
     * @return int
     */
    public static function generateMsgSequenceId(): int
    {
        return ++self::$msgSequenceId;
    }


    /**
     * @param string $binary
     * @return void
     */
    public function setBinary(string $binary)
    {
        $this->headerBinary = substr($binary, 0, 16);
        $this->bodyBinary   = substr($binary, 16);
    }

    /**
     * getCommandId 获取协议动作
     * @return int|null
     */
    public function getCommandId(): int|null
    {
        return $this->commandId;
    }

    /**
     * getResponse 获取响应数据
     * @return string
     */
    public function getResponse(): string
    {
        return $this->response;
    }

    /**
     * getMsgHexId 获取十六进制的msg id
     * @return mixed
     */
    public function getMsgHexId()
    {
        return $this->msgHexId;
    }

    /**
     * getNeedCloseFd
     * @return bool
     */
    public function getNeedCloseFd(): bool
    {
        return $this->needCloseFd;
    }

    /**
     * parseHeader 解析数据头部获取协议动作
     * @return bool
     */
    public function parseHeader(): bool
    {
        $this->headerArr = @unpack(SMPPProtocol::$headerUnpackRule, $this->headerBinary);

        $this->commandId = $this->headerArr['command_id'] ?? null;

        if (!in_array($this->commandId, $this->allowCommands)) {
            return false;
        }

        if ($this->headerArr['command_status'] !== SMPPProtocol::ESME_ROK) {
            return false;
        }

        return true;
    }

    /**
     * getHeader 获取协议头
     * @param string $key
     * @param string $default
     * @return array|string
     */
    public function getHeader(string $key = '', string $default = '')
    {
        if (empty($key)) {
            return $this->headerArr;
        }

        return $this->headerArr[$key] ?? $default;
    }

    /**
     * getBody 获取协议体
     * @param string $key
     * @param string $default
     * @return mixed
     */
    public function getBody(string $key = '', string $default = ''): mixed
    {
        if (empty($key)) {
            return $this->bodyArr;
        }

        if (isset($this->bodyArr[$key])) {
            return $this->bodyArr[$key];
        }

        return $default;
    }

    /**
     * packageErrResp
     * @param $errCode
     */
    public function packageErrResp($errCode): void
    {
        $seqNumber = $this->getHeader('sequence_number');

        switch ($this->commandId) {
            case SMPPProtocol::BIND_RECEIVER:
                $this->response = SMPPProtocol::packBindReceiverResp($errCode, $seqNumber);
                break;
            case SMPPProtocol::BIND_TRANSMITTER:
                $this->response = SMPPProtocol::packBindTransmitterResp($errCode, $seqNumber);
                break;
            case SMPPProtocol::BIND_TRANSCEIVER:
                $this->response = SMPPProtocol::packBindTransceiverResp($errCode, $seqNumber);
                break;
            case SMPPProtocol::UNBIND:
                $this->response = SMPPProtocol::packUnbindResp($errCode, $seqNumber);
                break;
            case SMPPProtocol::SUBMIT_SM:
                $this->response = SMPPProtocol::packSubmitSmResp($errCode, $seqNumber);
                break;
            case SMPPProtocol::ENQUIRE_LINK:
                $this->response = SMPPProtocol::packEnquireLinkResp($seqNumber);
                break;
        }
    }

    /**
     * parseBody 解析协议体
     * @return bool
     */
    public function parseBody(): bool
    {
        //拆除连接和客户端探活操作无协议体
        if ($this->commandId === SMPPProtocol::UNBIND || $this->commandId === SMPPProtocol::ENQUIRE_LINK) {
            return true;
        }

        switch ($this->commandId) {
            case SMPPProtocol::BIND_RECEIVER:
            case SMPPProtocol::BIND_TRANSMITTER:
            case SMPPProtocol::BIND_TRANSCEIVER:
                $this->bodyArr = SMPPProtocol::unpackBind($this->bodyBinary);
                break;
            case SMPPProtocol::SUBMIT_SM:
                $this->bodyArr = SMPPProtocol::unpackSubmitSm($this->bodyBinary);
                break;
        }

        return true;
    }

    /**
     * handle 处理协议
     * @return bool
     * @throws Exception
     */
    public function handle(): bool
    {
        switch ($this->commandId) {
            case SMPPProtocol::BIND_RECEIVER:
            case SMPPProtocol::BIND_TRANSMITTER:
            case SMPPProtocol::BIND_TRANSCEIVER:
                //客户端提交的连接请求
                return $this->handleConnect();
            case SMPPProtocol::SUBMIT_SM:
                //客户端提交的发送连接请求
                return $this->handleSubmit();
            case SMPPProtocol::UNBIND:
                //客户端提交的断开连接请求
                return $this->handleUnbind();
            case SMPPProtocol::ENQUIRE_LINK:
                //客户段提交的探活请求
                return $this->handleEnquireLink();
        }

        return false;
    }

    /**
     * handleConnect 处理连接
     * @return bool
     * @throws Exception
     */
    public function handleConnect(): bool
    {
        $this->packageConnectResp();

        return true;
    }

    /**
     * packageConnectResp
     */
    public function packageConnectResp()
    {
        switch ($this->getCommandId()) {
            case SMPPProtocol::BIND_RECEIVER:
                $commandId = SMPPProtocol::BIND_RECEIVER_RESP;
                break;
            case SMPPProtocol::BIND_TRANSMITTER:
                $commandId = SMPPProtocol::BIND_TRANSMITTER_RESP;
                break;
            default:
                $commandId = SMPPProtocol::BIND_TRANSCEIVER_RESP;
                break;
        }

        $this->response = SMPPProtocol::packBindResp($commandId, null, $this->getHeader('sequence_number'), $this->getBody('system_id'));
    }

    /**
     * generateMsgIdArr 生成msgid二进制字符串，转换成八位的数组
     * @param $spId
     * @return array
     * TODO 放到扩展里面做提高性能
     */
    public static function generateMsgIdArr(): array
    {
        $msgId = self::generateMsgSequenceId();

        //转换成二进制字符串
        $msgIdStr = sprintf('%032s', decbin($msgId));

        //分割字符串为8位一组
        $msgIdBinary = str_split($msgIdStr, 8);

        //将二进制转换为十进制因为pack只认字符串10进制数为十进制数
        $decArr = [];//十进制
        $hexArr = [];//十六进制
        foreach ($msgIdBinary as $binary) {
            $dec      = bindec($binary);
            $decArr[] = $dec;
            $hexArr[] = str_pad(dechex($dec), 2, '0', STR_PAD_LEFT);
        }

        return [$decArr, $hexArr];
    }

    /**
     * handleSubmit 处理短信提交
     * @return bool
     * @throws Exception
     */
    public function handleSubmit(): bool
    {
        //获取msgid二进制字符串
        [$this->msgIdDecArr, $hexArr] = self::generateMsgIdArr();

        $this->msgHexId = implode('', $hexArr);

        $this->response = SMPPProtocol::packSubmitSmResp(null, $this->getHeader('sequence_number'), $this->msgHexId);

        return true;
    }

    /**
     * handleUnbind 处理客户端的断开连接请求
     * @return bool
     */
    public function handleUnbind(): bool
    {
        $this->response = SMPPProtocol::packUnbindResp($this->getHeader('sequence_number'));

        return true;
    }

    /**
     * handleEnquireLink 处理客户端探活
     * @return bool
     */
    public function handleEnquireLink(): bool
    {
        $this->response = SMPPProtocol::packEnquireLinkResp($this->getHeader('sequence_number'));

        return true;
    }

    /**
     * getRespCommand
     * @return int
     */
    public function getRespCommand(): int
    {
        if ($this->commandId === SMPPProtocol::SUBMIT_SM) {
            return SMPPProtocol::SUBMIT_SM_RESP;
        }

        return 0;
    }
}