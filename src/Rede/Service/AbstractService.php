<?php

namespace Rede\Service;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Rede\eRede;
use Rede\Exception\RedeException;
use Rede\Store;
use Rede\Transaction;
use RuntimeException;

abstract class AbstractService
{
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';

    /**
     * @var resource
     */
    protected $curl;

    /**
     * @var Store
     */
    protected $store;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $platform;

    /**
     * @var string
     */
    private $platformVersion;

    /**
     * AbstractService constructor.
     *
     * @param Store $store
     * @param LoggerInterface|null $logger
     */
    public function __construct(Store $store, LoggerInterface $logger = null)
    {
        $this->store = $store;
        $this->logger = $logger;
    }

    /**
     * @param string $platform
     * @param string $platformVersion
     *
     * @return $this
     */
    public function platform($platform, $platformVersion)
    {
        $this->platform = $platform;
        $this->platformVersion = $platformVersion;

        return $this;
    }

    /**
     * @return Transaction
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws RedeException
     */
    abstract public function execute();

    /**
     * @param string $body
     * @param string $method
     *
     * @return mixed
     * @throws RuntimeException
     */
    protected function sendRequest($body = '', $method = 'GET')
    {
        $userAgent = sprintf('User-Agent: %s',
            sprintf(eRede::USER_AGENT, phpversion(), $this->store->getFiliation(), php_uname('s'), php_uname('r'),
                php_uname('m'))
        );

        if (!empty($this->platform) && !empty($this->platformVersion)) {
            $userAgent .= sprintf(' %s/%s', $this->platform, $this->platformVersion);
        }

        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }

        $curlVersion = curl_version();

        if (is_array($curlVersion)) {
            $userAgent .= sprintf(' curl/%s %s',
                isset($curlVersion['version']) ? $curlVersion['version'] : '',
                isset($curlVersion['ssl_version']) ? $curlVersion['ssl_version'] : ''
            );
        }

        $headers = [
            str_replace('  ', ' ',
                $userAgent
            ),
            'Accept: application/json',
            'Transaction-Response: brand-return-opened'
        ];

        $this->curl = curl_init($this->store->getEnvironment()->getEndpoint($this->getService()));

        // OAuth2 Bearer Token authentication flow
        $bearer = $this->ensureBearerToken();
        if ($bearer !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }

        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            throw new RuntimeException(sprintf('Atenção, sua versão da curl não suporta TLS 1.2 e precisa ser atualizada. Sua versão atual da curl é %s',
                $curlVersion));
        }

        curl_setopt($this->curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, true);

        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($this->curl, CURLOPT_POST, true);
                break;
            default:
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (!empty($body)) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);

            $headers[] = 'Content-Type: application/json; charset=utf8';
        } else {
            $headers[] = 'Content-Length: 0';
        }

        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);

        if ($this->logger !== null) {
            $this->logger->debug(
                trim(
                    sprintf("Request Rede\n%s %s\n%s\n\n%s",
                        $method,
                        $this->store->getEnvironment()->getEndpoint($this->getService()),
                        implode("\n", $headers),
                        preg_replace('/"(cardHolderName|cardnumber|securitycode)":"[^"]+"/i', '"\1":"***"', $body)
                    )
                )
            );
        }

        $response = curl_exec($this->curl);
        $httpInfo = curl_getinfo($this->curl);

        error_log($response);

        if ($this->logger !== null) {
            $this->logger->debug(
                sprintf("Response Rede\nStatus Code: %s\n\n%s",
                    $httpInfo['http_code'],
                    $response
                )
            );

            foreach ($httpInfo as $key => $info) {
                if (is_array($info)) {
                    foreach ($info as $infoKey => $infoValue) {
                        $this->logger->debug(sprintf('Curl[%s][%s]: %s', $key, $infoKey, $infoValue));
                    }

                    continue;
                }

                $this->logger->debug(sprintf('Curl[%s]: %s', $key, $info));
            }
        }

        if (curl_errno($this->curl)) {
            throw new RuntimeException(sprintf('Curl error[%s]: %s', curl_errno($this->curl), curl_error($this->curl)));
        }

        curl_close($this->curl);

        $this->curl = null;

        return $this->parseResponse($response, $httpInfo['http_code']);
    }

    /**
     * @return string Gets the service that will be used on the request
     */
    abstract protected function getService();

    /**
     * @param string $response Parses the HTTP response from Rede
     * @param string $statusCode The HTTP status code
     *
     * @return mixed
     */
    abstract protected function parseResponse($response, $statusCode);

    /**
     * Ensures a valid Bearer Token, obtaining a new one via OAuth2 when needed.
     *
     * @return string|null
     */
    private function ensureBearerToken(): ?string
    {
        $currentToken = $this->store->getBearerToken();
        $expiresAt = $this->store->getBearerTokenExpiresAt();

        $now = time();
        if ($currentToken !== null && is_int($expiresAt) && $expiresAt > ($now + 60)) {
            return $currentToken;
        }

        return $this->requestBearerToken();
    }

    /**
     * Requests a new token via OAuth2 (client_credentials) and stores it in the Store.
     *
     * @return string|null
     */
    private function requestBearerToken(): ?string
    {
        $env = $this->store->getEnvironment();
        $tokenUrl = $env->getOAuthTokenUrl();

        if (!empty($this->logger)) {
            $this->logger->debug(sprintf('Requesting OAuth2 token at %s', $tokenUrl));
        }

        $curl = curl_init($tokenUrl);
        if ($curl === false || !is_resource($curl)) {
            throw new RuntimeException('Was not possible to create a curl instance for OAuth token.');
        }

        $basic = base64_encode(sprintf('%s:%s', $this->store->getFiliation(), $this->store->getToken()));

        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $basic,
        ];

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($curl);
        $httpInfo = curl_getinfo($curl);

        if (curl_errno($curl)) {
            $errorMessage = sprintf('Curl error on OAuth token request[%s]: %s', curl_errno($curl), curl_error($curl));
            curl_close($curl);
            throw new RuntimeException($errorMessage);
        }

        curl_close($curl);

        if (!is_string($response)) {
            throw new RuntimeException('Invalid OAuth token response');
        }

        if (!empty($this->logger)) {
            $this->logger->debug(sprintf("OAuth token response status=%s body=%s", $httpInfo['http_code'] ?? 'n/a', $response));
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Unable to parse OAuth token response');
        }

        $accessToken = $decoded['access_token'] ?? null;
        $expiresIn = $decoded['expires_in'] ?? 0;

        if (!is_string($accessToken) || $accessToken === '') {
            $errorDescription = $decoded['error_description'] ?? $decoded['error'] ?? 'Unknown error obtaining access token';
            throw new RuntimeException(sprintf('OAuth token error: %s', $errorDescription));
        }

        $expiresAt = time() + (is_int($expiresIn) ? $expiresIn : (int)$expiresIn) - 60; // margem de segurança
        $this->store->setBearerToken($accessToken, $expiresAt);

        return $accessToken;
    }
}
