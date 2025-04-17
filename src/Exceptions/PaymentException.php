<?php

namespace Kosar501\JibitClient\Exceptions;

class PaymentException
{
    private string $client;
    private string $apiKey;
    private string $apiSecret;

    public function __construct(string $apiKey, string $apiSecret)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->client = new Client([
            'base_uri' => 'https://api.jibit.ir/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Accept' => 'application/json',
            ],
        ]);
    }
}