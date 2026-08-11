<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

final class ConsultationSubmissionLimiter
{
    private const int IP_LIMIT = 3;
    private const int IP_WINDOW = 3600;
    private const int EMAIL_LIMIT = 2;
    private const int EMAIL_WINDOW = 86400;

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function allows(string $ipAddress, string $email): bool
    {
        $ipKey = $this->key('ip', $ipAddress);
        $emailKey = $this->key('email', strtolower($email));

        $ipAttempts = $this->recentAttempts($ipKey, self::IP_WINDOW);
        $emailAttempts = $this->recentAttempts($emailKey, self::EMAIL_WINDOW);

        if (count($ipAttempts) >= self::IP_LIMIT || count($emailAttempts) >= self::EMAIL_LIMIT) {
            return false;
        }

        $now = time();
        $this->saveAttempts($ipKey, [...$ipAttempts, $now], self::IP_WINDOW);
        $this->saveAttempts($emailKey, [...$emailAttempts, $now], self::EMAIL_WINDOW);

        return true;
    }

    /** @return list<int> */
    private function recentAttempts(string $key, int $window): array
    {
        $item = $this->cache->getItem($key);
        $attempts = $item->isHit() ? $item->get() : [];

        if (!is_array($attempts)) {
            return [];
        }

        $cutoff = time() - $window;

        return array_values(array_filter($attempts, static fn (mixed $attempt): bool => is_int($attempt) && $attempt > $cutoff));
    }

    /** @param list<int> $attempts */
    private function saveAttempts(string $key, array $attempts, int $window): void
    {
        $item = $this->cache->getItem($key);
        $item->set($attempts);
        $item->expiresAfter($window);
        $this->cache->save($item);
    }

    private function key(string $type, string $value): string
    {
        return 'consultation_submission_'.$type.'_'.hash('sha256', $value);
    }
}
