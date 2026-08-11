<?php

declare(strict_types=1);

namespace App\Service;

final class ConsultationContentFilter
{
    /** @var list<string> */
    private const ADVERTISING_PHRASES = [
        'advertising',
        'advertisement',
        'sponsored post',
        'guest post',
        'guest article',
        'backlink',
        'link building',
        'link exchange',
        'seo package',
        'digital marketing',
        'social media promotion',
        'press release distribution',
        'casino promotion',
        'crypto promotion',
    ];

    public function isAdvertising(string $message): bool
    {
        $message = strtolower($message);

        foreach (self::ADVERTISING_PHRASES as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
