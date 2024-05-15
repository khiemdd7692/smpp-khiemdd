<?php

namespace SMPP3;

class SMPP3Server
{
    public $allowCommands = [
        SMPP3Protocol::GENERIC_NACK,
        SMPP3Protocol::BIND_RECEIVER,
        SMPP3Protocol::BIND_TRANSMITTER,
        SMPP3Protocol::BIND_TRANSCEIVER,
        SMPP3Protocol::UNBIND,
        SMPP3Protocol::UNBIND_RESP,
        SMPP3Protocol::SUBMIT_SM,
        SMPP3Protocol::DELIVER_SM_RESP,
        SMPP3Protocol::ENQUIRE_LINK,
        SMPP3Protocol::ENQUIRE_LINK_RESP,
    ];
    public $notHandleCommands = [
        SMPP3Protocol::GENERIC_NACK,
        SMPP3Protocol::DELIVER_SM_RESP,
        SMPP3Protocol::UNBIND_RESP,
        SMPP3Protocol::ENQUIRE_LINK_RESP,
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
        $this->headerArr = @unpack(SMPP3Protocol::$headerUnpackRule, $this->headerBinary);

        $this->commandId = $this->headerArr['command_id'] ?? null;

        if (!in_array($this->commandId, $this->allowCommands)) {
            return false;
        }

        if ($this->headerArr['command_status'] !== SMPP3Protocol::ESME_ROK) {
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
            case SMPP3Protocol::BIND_RECEIVER:
                $this->response = SMPP3Protocol::packBindReceiverResp($errCode, $seqNumber);
                break;
            case SMPP3Protocol::BIND_TRANSMITTER:
                $this->response = SMPP3Protocol::packBindTransmitterResp($errCode, $seqNumber);
                break;
            case SMPP3Protocol::BIND_TRANSCEIVER:
                $this->response = SMPP3Protocol::packBindTransceiverResp($errCode, $seqNumber);
                break;
            case SMPP3Protocol::UNBIND:
                $this->response = SMPP3Protocol::packUnbindResp($errCode, $seqNumber);
                break;
            case SMPP3Protocol::SUBMIT_SM:
                $this->response = SMPP3Protocol::packSubmitSmResp($errCode, $seqNumber);
                break;
            case SMPP3Protocol::ENQUIRE_LINK:
                $this->response = SMPP3Protocol::packEnquireLinkResp($seqNumber);
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
        if ($this->commandId === SMPP3Protocol::UNBIND || $this->commandId === SMPP3Protocol::ENQUIRE_LINK) {
            return true;
        }

        switch ($this->commandId) {
            case SMPP3Protocol::BIND_RECEIVER:
            case SMPP3Protocol::BIND_TRANSMITTER:
            case SMPP3Protocol::BIND_TRANSCEIVER:
                $this->bodyArr = SMPP3Protocol::unpackBind($this->bodyBinary);
                break;
            case SMPP3Protocol::SUBMIT_SM:
                $this->bodyArr = SMPP3Protocol::unpackSubmitSm($this->bodyBinary);
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
            case SMPP3Protocol::BIND_RECEIVER:
            case SMPP3Protocol::BIND_TRANSMITTER:
            case SMPP3Protocol::BIND_TRANSCEIVER:
                //客户端提交的连接请求
                return $this->handleConnect();
            case SMPP3Protocol::SUBMIT_SM:
                //客户端提交的发送连接请求
                return $this->handleSubmit();
            case SMPP3Protocol::UNBIND:
                //客户端提交的断开连接请求
                return $this->handleUnbind();
            case SMPP3Protocol::ENQUIRE_LINK:
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
            case SMPP3Protocol::BIND_RECEIVER:
                $commandId = SMPP3Protocol::BIND_RECEIVER_RESP;
                break;
            case SMPP3Protocol::BIND_TRANSMITTER:
                $commandId = SMPP3Protocol::BIND_TRANSMITTER_RESP;
                break;
            default:
                $commandId = SMPP3Protocol::BIND_TRANSCEIVER_RESP;
                break;
        }

        $this->response = SMPP3Protocol::packBindResp($commandId, null, $this->getHeader('sequence_number'), $this->getBody('system_id'));
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

        $this->response = SMPP3Protocol::packSubmitSmResp(null, $this->getHeader('sequence_number'), $this->msgHexId);

        return true;
    }

    /**
     * handleUnbind 处理客户端的断开连接请求
     * @return bool
     */
    public function handleUnbind(): bool
    {
        $this->response = SMPP3Protocol::packUnbindResp($this->getHeader('sequence_number'));

        return true;
    }

    /**
     * handleEnquireLink 处理客户端探活
     * @return bool
     */
    public function handleEnquireLink(): bool
    {
        $this->response = SMPP3Protocol::packEnquireLinkResp($this->getHeader('sequence_number'));

        return true;
    }

    /**
     * getRespCommand
     * @return int
     */
    public function getRespCommand(): int
    {
        if ($this->commandId === SMPP3Protocol::SUBMIT_SM) {
            return SMPP3Protocol::SUBMIT_SM_RESP;
        }

        return 0;
    }
}