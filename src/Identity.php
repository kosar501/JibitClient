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
     * @param CacheInterface|null $cache
     * @throws GuzzleException
     * @throws InvalidArgumentException
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
     * Retrieves card information based on the provided card number.
     *
     * The response includes the following fields:
     * - number: string - شماره کارت ارسالی توسط شما.
     * - cardinfo: CardInfo - اطلاعات مربوط به کارت.
     *   - bank: string - شناسه بانک مربوط به شماره حساب (رجوع شود به جدول اطلاعات بانک‌ها).
     *   - type: string - نوع کارت، یکی از مقادیر زیر:
     *     - DEBIT (نقدی)
     *     - CREDIT (اعتباری)
     *     - CREDIT (اعتباری، ویژه)
     *     - GIFT_CARD (کارت هدیه)
     *     - VIRTUAL_CARD (پیش پرداخت آنلاین)
     *     - CARD (کارت مجازی)
     *     - E_MONEY (INSTALLMENT_CARD (نامشخص))
     *   - ownerName: string - نام دارنده کارت.
     *   - depositNumber: string - شماره حساب پشت کارت.
     *
     * @param string $cardNumber The card number to retrieve information for.
     * @return array Returns an associative array with the following structure:
     *               [
     *                   'number' => 'string', // شماره کارت ارسالی توسط شما
     *                   'cardinfo' => [
     *                       'bank' => 'string', // شناسه بانک
     *                       'type' => 'string', // نوع کارت
     *                       'ownerName' => 'string', // نام دارنده کارت
     *                       'depositNumber' => 'string', // شماره حساب پشت کارت
     *                   ],
     *               ]
     * @throws GuzzleException If there is an error during the HTTP request.
     * @throws InvalidArgumentException If the cache key is invalid.
     */
    public function getCardInfo(string $cardNumber): array
    {
        return $this->request('GET', '/v1/cards', [
            'number' => $cardNumber
        ]);
    }

    /**
     * Retrieves IBAN information based on the provided IBAN.
     *
     * The response includes the following fields:
     * - value: string - شماره شبا ارسالی توسط شما.
     * - ibanInfo: IbanInfo - اطلاعات مربوط به شبا.
     *   - bank: string - شناسه بانک مربوط به شماره حساب (رجوع شود به جدول اطلاعات بانک‌ها).
     *   - depositNumber: string - شماره حساب.
     *   - iban: string - شبا به دست آمده از شماره حساب.
     *   - status: string - وضعیت حساب، یکی از مقادیر زیر:
     *     - ACTIVE (فعال)
     *     - DEPOSIT_BLOCK_WITH (حساب بانک شده است اما قابلیت واریز دارد)
     *     - BLOCK_WITHOUT_DEPOSIT (حساب بانک شده است و قابلیت واریز ندارد)
     *     - IDLE (راکد)
     *     - UNKNOWN (نامشخص)
     *   - owners: Owner[] - آرایه‌ای از صاحبان شبا.
     *     - firstName: string - نام صاحب حساب.
     *     - lastName: string - نام خانوادگی صاحب حساب.
     *
     * @param string $iban The IBAN to retrieve information for.
     * @return array Returns an associative array with the following structure:
     *               [
     *                   'value' => 'string', // شماره شبا ارسالی توسط شما
     *                   'ibanInfo' => [
     *                       'bank' => 'string', // شناسه بانک
     *                       'depositNumber' => 'string', // شماره حساب
     *                       'iban' => 'string', // شبا
     *                       'status' => 'string', // وضعیت حساب
     *                       'owners' => [
     *                           [
     *                               'firstName' => 'string', // نام صاحب حساب
     *                               'lastName' => 'string', // نام خانوادگی صاحب حساب
     *                           ],
     *                           // Additional owners...
     *                       ],
     *                   ],
     *               ]
     * @throws GuzzleException If there is an error during the HTTP request.
     * @throws InvalidArgumentException If the cache key is invalid.
     */
    public function getIbanInfo(string $iban): array
    {
        return $this->request('GET', '/v1/ibans', [
            'value' => $iban
        ]);
    }

    /**
     * Converts a card number to its corresponding account number and retrieves related information.
     *
     * The response includes the following fields:
     * - number: string - شماره کارت ارسالی توسط شما.
     * - type: string - نوع کارت، یکی از مقادیر زیر:
     *   - DEBIT (نقدی)
     *   - CREDIT (اعتباری)
     *   - CREDIT (اعتباری، ویژه)
     *   - GIFT_CARD (کارت هدیه)
     *   - VIRTUAL_CARD (پیش پرداخت آنلاین)
     *   - ONLINE_PREPAID (کارت مجازی)
     *   - INSTALLMENT_CARD (E_MONEY (نامشخص))
     * - depositInfo: DepositInfo - اطلاعات مربوط به حساب.
     *   - bank: string - شناسه بانک مربوط به شماره حساب (رجوع شود به جدول اطلاعات بانک‌ها).
     *   - depositNumber: string - شماره حساب پشت کارت.
     *
     * @param string $cardNumber The card number to convert and retrieve information for.
     * @return array Returns an associative array with the following structure:
     *               [
     *                   'number' => 'string', // شماره کارت ارسالی توسط شما
     *                   'type' => 'string', // نوع کارت
     * @throws GuzzleException If there is an error during the HTTP request.
     * @throws InvalidArgumentException If the cache key is invalid.
     */
    public function cardNumberToAccountNumber(string $cardNumber): array
    {
        return $this->request('GET', '/v1/cards', [
            'number' => $cardNumber,
            'deposit' => true
        ]);
    }

    /**
     * Converts a card number to its corresponding iban number and retrieves related information.
     *
     * The response includes the following fields:
     * - number: string - شماره کارت ارسالی توسط شما.
     * - type: string - نوع کارت، یکی از مقادیر زیر:
     *   - DEBIT (نقدی)
     *   - UNKNOWN (نامشخص)
     * - ibanInfo: IbanInfo - اطلاعات مربوط به شبا.
     * - bank: string - شناسه بانک مربوط به شماره حساب (رجوع شود به جدول اطلاعات بانک‌ها).
     * - depositNumber: string - شماره حساب.
     * - iban: string - شبا به دست آمده از شماره حساب.
     * - status: string - وضعیت حساب، یکی از مقادیر زیر:
     * - ACTIVE (فعال)
     * - DEPOSIT_BLOCK_WITH (حساب بانک شده است اما قابلیت واریز دارد)
     * - BLOCK_WITHOUT_DEPOSIT (حساب بانک شده است و قابلیت واریز ندارد)
     * - IDLE (راکد)
     * - UNKNOWN (نامشخص)
     * - owners: Owner[] - آرایه‌ای از صاحبان شبا.
     * - firstName: string - نام صاحب حساب.
     * - lastName: string - نام خانوادگی صاحب حساب.
     *
     * @param string $cardNumber The card number to convert and retrieve information for.
     * @return array Returns an associative array with the following structure:
     *               [
     *                   'number' => 'string', // شماره کارت ارسالی توسط شما
     *                   'type' => 'string', // نوع کارت
     *                   'ibanInfo' => [
     *                      'bank' => 'string', // شناسه بانک
     *                      'depositNumber' => 'string', // شماره حساب
     *                      'iban' => 'string', // شبا
     *                      'status' => 'string', // وضعیت حساب
     *                      'owners' => [
     *                            [
     *                               'firstName' => 'string', // نام صاحب حساب
     *                               'lastName' => 'string', // نام خانوادگی صاحب حساب
     *                            ],
     *                         // Additional owners...
     *                     ],
     *                  ],
     *               ]
     * @throws GuzzleException If there is an error during the HTTP request.
     * @throws InvalidArgumentException If the cache key is invalid.
     */
    public function cardNumberToIban(string $cardNumber): array
    {
        return $this->request('GET', '/v1/cards', [
            'number' => $cardNumber,
            'iban' => true
        ]);
    }

    /**
     * Checks the availability of the card-to-IBAN conversion service.
     *
     * The response includes the following fields:
     * - availabilityReport: AvailabilityReport - گزارش در دسترس بودن سرویس کارت به شبا.
     *   - bankIdentifier: string - وضعیت دسترس‌پذیری سرویس، یکی از مقادیر زیر:
     *     - AVAILABLE (در دسترس بودن)
     *     - NOT_AVAILABLE (در دسترس نبودن)
     *
     * @return array Returns an associative array with the following structure:
     *               [
     *                   'availabilityReport' => [
     *                       'bankIdentifier' => 'string', // وضعیت دسترس‌پذیری سرویس
     *                   ],
     *               ]
     * @throws GuzzleException If there is an error during the HTTP request.
     * @throws InvalidArgumentException If the cache key is invalid.
     */
    public function checkCardNumberToIBANService(): array
    {
        return $this->request('GET', '/v1/services/availability', [
            'cardToIBAN' => true
        ]);
    }

    /**
     * Check the mobile phone's compatibility with the national code.
     *
     * The response includes the following fields:
     * - matched: boolean - تطابق یا عدم تطابق کد ملی و شماره موبایل.
     *
     * @param string $nationalCode
     * @param string $mobileNumber
     * @return array Returns an associative array with the following structure:
     *          [
     *              'matched' => boolean
     *          ]
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function checkMobileMatchToNationalCode(string $nationalCode, string $mobileNumber): array
    {
        return $this->request('GET', '/v1/services/matching', [
            'nationalCode' => $nationalCode,
            'mobileNumber' => $mobileNumber,
        ]);
    }

    /*********************************************** private functions **********************************************/

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