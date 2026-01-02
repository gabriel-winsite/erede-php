<?php

namespace Rede;

class ThreeDSecure implements RedeSerializable
{
    use CreateTrait;
    use SerializeTrait;

    const DATA_ONLY = 'DATA_ONLY';
    const CONTINUE_ON_FAILURE = 'continue';
    const DECLINE_ON_FAILURE = 'decline';

    /**
     * @var string
     */
    private $cavv;

    /**
     * @var string
     */
    private $eci;

    /**
     * @var bool
     */
    private $embedded = true;

    /**
     * @var string
     */
    private $onFailure;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $userAgent;

    /**
     * @var string
     */
    private $xid;

    /**
     * @var string
     */
    private $threeDIndicator = "1";

    /**
     * @var string
     */
    private $DirectoryServerTransactionId;

    /**
     * @var string|null
     */
    private $challengePreference = null;

    /**
     * ThreeDSecure constructor.
     *
     * @param bool $embedded
     * @param string $onFailure
     * @param string|null $userAgent
     */
    public function __construct(
        bool    $embedded = true,
        string  $onFailure = self::DECLINE_ON_FAILURE,
        ?string $userAgent = null
    )
    {
        if ($this->userAgent === null) {
            $userAgent = eRede::USER_AGENT;

            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
            }

            $this->userAgent = $userAgent;
        }
    }

    /**
     * @return string
     */
    public function getThreeDIndicator()
    {
        return $this->threeDIndicator;
    }

    /**
     * @param string $threeDIndicator
     *
     * @return ThreeDSecure
     */
    public function setThreeDIndicator($threeDIndicator)
    {
        /**
         * Support for 3DS 1 will be discontinued.
         */
        if ($threeDIndicator < 2) {
            trigger_error(
                'Effective 15 October 2022, support for 3DS 1 and all related technology is discontinued.',
                time() > strtotime('2022-10-15') ? E_USER_ERROR : E_USER_DEPRECATED
            );
        }

        $this->threeDIndicator = $threeDIndicator;

        return $this;
    }

    /**
     * @return string
     */
    public function getDirectoryServerTransactionId()
    {
        return $this->DirectoryServerTransactionId;
    }

    /**
     * @param string $DirectoryServerTransactionId
     *
     * @return ThreeDSecure
     */
    public function setDirectoryServerTransactionId($DirectoryServerTransactionId)
    {
        $this->DirectoryServerTransactionId = $DirectoryServerTransactionId;
        return $this;
    }

    /**
     * @return string
     */
    public function getCavv()
    {
        return $this->cavv;
    }

    /**
     * @param string $cavv
     *
     * @return ThreeDSecure
     */
    public function setCavv($cavv)
    {
        $this->cavv = $cavv;
        return $this;
    }

    /**
     * @return string
     */
    public function getEci()
    {
        return $this->eci;
    }

    /**
     * @param string $eci
     *
     * @return ThreeDSecure
     */
    public function setEci($eci)
    {
        $this->eci = $eci;
        return $this;
    }

    /**
     * @return string
     */
    public function getOnFailure()
    {
        return $this->onFailure;
    }

    /**
     * @param string $onFailure
     *
     * @return ThreeDSecure
     */
    public function setOnFailure($onFailure)
    {
        $this->onFailure = $onFailure;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     *
     * @return ThreeDSecure
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * @param string $userAgent
     *
     * @return ThreeDSecure
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * @return string
     */
    public function getXid()
    {
        return $this->xid;
    }

    /**
     * @param string $xid
     *
     * @return ThreeDSecure
     */
    public function setXid($xid)
    {
        $this->xid = $xid;
        return $this;
    }

    /**
     * @return bool
     */
    public function isEmbedded()
    {
        return $this->embedded;
    }

    /**
     * @param bool $embedded
     *
     * @return ThreeDSecure
     */
    public function setEmbedded($embedded)
    {
        $this->embedded = $embedded;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getChallengePreference(): ?string
    {
        return $this->challengePreference;
    }

    /**
     * @param string|null $challengePreference
     * @return ThreeDSecure
     */
    public function setChallengePreference(?string $challengePreference): ThreeDSecure
    {
        $this->challengePreference = $challengePreference;
        return $this;
    }
}
