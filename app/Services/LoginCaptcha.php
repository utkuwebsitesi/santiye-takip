<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class LoginCaptcha
{
    /**
     * @return array{question: string, token: string}
     */
    public function issue(): array
    {
        $first = random_int(2, 9);
        $second = random_int(1, 9);
        $multiply = random_int(0, 1) === 1;
        $answer = (string) ($multiply ? $first * $second : $first + $second);
        $token = Crypt::encryptString(json_encode([
            'answer' => $answer,
            'expires_at' => now()->addMinutes(10)->timestamp,
            'nonce' => bin2hex(random_bytes(16)),
        ], JSON_THROW_ON_ERROR));

        return [
            'question' => "{$first} ".($multiply ? '×' : '+')." {$second} = ?",
            'token' => $token,
        ];
    }

    public function verifyAndConsume(string $token, string $answer): bool
    {
        try {
            $challenge = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        if (! is_array($challenge) || ! isset($challenge['answer'], $challenge['expires_at'], $challenge['nonce'])) {
            return false;
        }

        if ((int) $challenge['expires_at'] < now()->timestamp) {
            return false;
        }

        $unused = Cache::add(
            'login-captcha:'.hash('sha256', $token),
            true,
            now()->addMinutes(10)
        );

        if (! $unused) {
            return false;
        }

        return hash_equals((string) $challenge['answer'], trim($answer));
    }
}
