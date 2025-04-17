<?php

namespace Kosar501\JibitClient\Cache;

use Psr\SimpleCache\CacheInterface;
use DateInterval;

class FileCache implements CacheInterface
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        $file = $this->cacheDir . $key;

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (isset($data['expires_at']) && $data['expires_at'] > time()) {
                return $data['value'];
            } else {
                unlink($file); // Remove expired cache
            }
        }
        return $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param DateInterval|int|null $ttl
     * @return bool
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->validateKey($key);
        $file = $this->cacheDir . $key;
        $expiresAt = $this->calculateExpiration($ttl);

        $data = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        return (bool)file_put_contents($file, json_encode($data));
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);
        $file = $this->cacheDir . $key;

        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    /**
     * @param iterable $keys
     * @param mixed|null $default
     * @return iterable
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * @param iterable $values
     * @param DateInterval|int|null $ttl
     * @return bool
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * @param iterable $keys
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * @param string $key
     * @return void
     */
    private function validateKey(string $key): void
    {
        if (preg_match('/[{}()\/\\@:]/', $key)) {
            throw new \InvalidArgumentException('Invalid key: ' . $key);
        }
    }

    /**
     * @param DateInterval|int|null $ttl
     * @return int|null
     */
    private function calculateExpiration(DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null; // No expiration
        }

        if ($ttl instanceof DateInterval) {
            return (new \DateTime())->add($ttl)->getTimestamp();
        }

        return time() + $ttl;
    }
}