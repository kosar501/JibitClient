<?php

namespace Kosar501\JibitClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Kosar501\JibitClient\Cache\FileCache;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class Identity
{
    private string $apiKey;
    private string $apiSecret;
    private string $accessToken = 'accessToken';
    private string $refreshToken = 'refreshToken';
    private string $apiUrl = 'https://napi.jibit.ir';

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
    public function verifyMobileNationalCodeMatch(string $nationalCode, string $mobileNumber): array
    {
        return $this->request('GET', '/v1/services/matching', [
            'nationalCode' => $nationalCode,
            'mobileNumber' => $mobileNumber,
        ]);
    }

    /**
     * Retrieve address information from a postal code using the postal service API.
     *
     * This method queries the postal service API to get detailed address information
     * associated with a given 10-digit postal code.
     *
     * The response includes the following fields:
     * - code: string - The postal code that was queried
     * - addressInfo: array - An array containing detailed address information:
     *   - postalCode: string - The postal code
     *   - address: string - Complete address string
     *   - province: string - Province name
     *   - district: string - District name
     *   - street: string - Street name
     *   - no: string - Number (پلاک)
     *   - floor: string - Floor (طبقه)
     *
     * @param string $postalCode A 10-digit postal code to query
     * @return array Returns an associative array with address information
     * @throws \GuzzleHttp\Exception\GuzzleException If there's an error during the HTTP request
     * @throws \InvalidArgumentException If the postal code is invalid (not 10 digits)
     * @throws InvalidArgumentException
     */
    public function getAddressFromPostalCode(string $postalCode): array
    {
        if (strlen($postalCode) !== 10 || !ctype_digit($postalCode)) {
            throw new \InvalidArgumentException('Postal code must be a 10-digit number');
        }

        return $this->request('GET', 'v1/services/postal', [
            'code' => $postalCode
        ]);
    }

    /**
     * Verify if a bank card number matches the given national code and birth date.
     *
     * This method checks the compatibility between a bank card number, national code,
     * and birth date to determine if they belong to the same individual.
     *
     * @param string $nationalCode 10-digit national code (کدملی)
     * @param string $birthDate Birth date in Solar (Shamsi) calendar format (YYYY/MM/DD)
     * @param string $cardNumber Bank card number (شماره کارت)
     * @return array Returns an associative array with the following structure:
     *         [
     *             'matched' => bool // true if card matches national code and birth date
     *         ]
     * @throws \GuzzleHttp\Exception\GuzzleException If there's an error during the HTTP request
     * @throws \InvalidArgumentException If any parameter is invalid
     */
    public function verifyCardNumberNationalCodeMatch(
        string $nationalCode,
        string $birthDate,
        string $cardNumber
    ): array {
        // Validate national code (10 digits)
        if (strlen($nationalCode) !== 10 || !ctype_digit($nationalCode)) {
            throw new \InvalidArgumentException('National code must be a 10-digit number');
        }

        // Basic birth date format validation (YYYY/MM/DD)
        if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $birthDate)) {
            throw new \InvalidArgumentException('Birth date must be in YYYY/MM/DD format');
        }

        // Basic card number validation (assuming minimum 16 digits)
        if (strlen($cardNumber) < 16 || !ctype_digit($cardNumber)) {
            throw new \InvalidArgumentException('Card number must be at least 16 digits');
        }

        return $this->request('GET', '/v1/services/matching', [
            'cardNumber' => $cardNumber,
            'nationalCode' => $nationalCode,
            'birthDate' => $birthDate
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
            'secretKey' => $this->apiSecret,
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
            $response = $client->post('/ide/' . $url, [
                'json' => $data,
            ]);
        } else {
            //$data is query params
            $response = $client->get('/ide/' . $url, [
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