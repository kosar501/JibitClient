<?php

namespace Kosar501\Jibit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Kosar501\Jibit\Cache\FileCache;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class Identity
{
    private Client $client;
    private string $apiKey;
    private string $apiSecret;

    private string $accessToken = 'accessToken';
    private string $refreshToken = 'refreshToken';
    private string $apiUrl = 'https://napi.jibit.ir/ide';

    private CacheInterface $cache;
    private string $cachePrefix = 'jibit_tokens_';

    /**
     * @param string $apiKey
     * @param string $apiSecret
     * @param CacheInterface $cache
     * @throws GuzzleException
     */
    public function __construct(string $apiKey, string $apiSecret, CacheInterface $cache = null)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;

        // Initialize cache
        $this->cache = $cache ?? new FileCache('/Cache/Storage');

        // Initialize tokens
        $this->initializeTokens();
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     * @throws GuzzleException
     */
    private function initializeTokens(): void
    {
        $tokens = $this->cache->getMultiple([
            $this->cachePrefix . 'accessToken',
            $this->cachePrefix . 'refreshToken'
        ]);

        if ($tokens &&
            isset($tokens[$this->cachePrefix . 'accessToken']) &&
            isset($tokens[$this->cachePrefix . 'refreshToken'])) {
            $this->accessToken = $tokens[$this->cachePrefix . 'accessToken'];
            $this->refreshToken = $tokens[$this->cachePrefix . 'refreshToken'];
        } else {
            $this->fetchTokens();
        }
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    private function fetchTokens(): void
    {
        $response = $this->request('POST', '/v1/tokens/generate', [
            'apiSecret' => $this->apiSecret,
            'apiKey' => $this->apiKey,
        ], false);
        $this->setTokens($response);
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    private function refreshToken(): void
    {
        if (empty($this->accessToken))
            $this->initializeTokens();

        //access token must exist
        $response = $this->request('POST', '/v1/tokens/refresh', [
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
        ], false);

        $this->setTokens($response);
    }


    /**
     * @param string $method
     * @param string $url
     * @param array $data
     * @param bool $autorization
     * @return mixed
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    private function request(string $method, string $url, array $data, bool $autorization = true): mixed
    {

        $headers = [
            'Content-Type' => 'application/json'
        ];
        if ($autorization) {
            if (empty($this->accessToken)) {
                $this->initializeTokens();
            }
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        $client = new Client([
            'base_uri' => $this->apiUrl,
            'headers' => $headers,
        ]);

        if ($method == 'POST') {
            //$data is body
            $response = $client->post($url, [
                'json' => $data,
            ]);
        } else {
            //$data is query params
            $response = $client->get($url, [
                'query' => $data,
            ]);
        }
        //failed requests
        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
            if (isset($response['code']) && $response['code'] == 'forbidden') {
                //refresh token
                $this->refreshToken();
            }
        }
        return json_decode($response->getBody(), true);

    }

    /**
     * @param $response
     * @return void
     * @throws InvalidArgumentException
     */
    private function setTokens($response): void
    {
        if (isset($response['accessToken']) && isset($response['refreshToken'])) {
            $this->cache->set($this->cachePrefix . 'accessToken', $response['accessToken'], 24 * 3600);
            $this->cache->set($this->cachePrefix . 'refreshToken', $response['refreshToken'], 48 * 3600);
            $this->accessToken = $response['accessToken'];
            $this->refreshToken = $response['refreshToken'];
        }
    }
}