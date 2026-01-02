<?php

namespace Rede;

use stdClass;

class Environment implements RedeSerializable
{
    const PRODUCTION = 'https://api.userede.com.br/erede';
    const SANDBOX = 'https://api.userede.com.br/desenvolvedores';
    const VERSION_V1 = 'v1';
    const VERSION_V2 = 'v2';

    /**
     * OAuth2 token endpoints
     */
    public const OAUTH_TOKEN_PRODUCTION = 'https://api.userede.com.br/redelabs/oauth2/token';
    public const OAUTH_TOKEN_SANDBOX = 'https://rl7-sandbox-api.useredecloud.com.br/oauth2/token';

    /**
     * @var string
     */
    private $ip;

    /**
     * @var string
     */
    private $sessionId;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var string OAuth2 token endpoint URL
     */
    private $oauthTokenUrl;

    /**
     * Creates a environment with its base url and version
     *
     * @param string $baseUrl
     * @param string $version
     */
    private function __construct($baseUrl, $version = Environment::VERSION_V1)
    {
        $this->endpoint = sprintf('%s/%s/', $baseUrl, $version);

        if ($baseUrl === Environment::PRODUCTION) {
            $this->oauthTokenUrl = Environment::OAUTH_TOKEN_PRODUCTION;
        } elseif ($baseUrl === Environment::SANDBOX) {
            $this->oauthTokenUrl = Environment::OAUTH_TOKEN_SANDBOX;
        } else {
            $this->oauthTokenUrl = rtrim($baseUrl, '/') . '/oauth2/token';
        }
    }

    /**
     * @param string $version
     * @return Environment A preconfigured production environment
     */

    public static function production($version = null)
    {
        return new Environment(Environment::PRODUCTION);
    }

    /**
     * @param string $version
     *
     * @return Environment A preconfigured sandbox environment
     */
    public static function sandbox($version = null)
    {
        return new Environment(Environment::SANDBOX);
    }

    /**
     * @param string $service
     *
     * @return string Gets the environment endpoint
     */
    public function getEndpoint($service)
    {
        return $this->endpoint . $service;
    }

    /**
     * @return string OAuth2 token endpoint URL
     */
    public function getOAuthTokenUrl()
    {
        return $this->oauthTokenUrl;
    }

    /**
     * @param string $oauthTokenUrl
     * @return $this
     */
    public function setOAuthTokenUrl(string $oauthTokenUrl)
    {
        $this->oauthTokenUrl = $oauthTokenUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     *
     * @return Environment
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * @param $sessionId
     *
     * @return Environment
     */
    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        $consumer = new stdClass();
        $consumer->ip = $this->ip;
        $consumer->sessionId = $this->sessionId;

        return ['consumer' => $consumer];
    }
}
