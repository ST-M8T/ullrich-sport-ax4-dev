<?php

namespace App\Support\Security;

use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    public static function rule(): Password
    {
        $min = max(1, (int) config('security.passwords.min_length', 12));

        $rule = Password::min($min)->uncompromised();

        $requireUpper = config('security.passwords.require_uppercase', true);
        $requireLower = config('security.passwords.require_lowercase', true);
        $requireNumeric = config('security.passwords.require_numeric', true);
        $requireSymbol = config('security.passwords.require_symbol', true);

        if ($requireUpper && $requireLower) {
            $rule = $rule->mixedCase();
        } elseif ($requireUpper || $requireLower) {
            $rule = $rule->letters();
        }

        if ($requireNumeric) {
            $rule = $rule->numbers();
        }

        if ($requireSymbol) {
            $rule = $rule->symbols();
        }

        return $rule;
    }

    public static function generate(?int $length = null): string
    {
        $min = max(8, (int) config('security.passwords.min_length', 12));
        $length = max($length ?? $min, $min);

        $characterSets = [
            'lower' => 'abcdefghijkmnopqrstuvwxyz',
            'upper' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'numbers' => '23456789',
            'symbols' => '!@#$%^&*()-_=+[]{}<>?',
        ];

        $requiredSets = [];

        if (config('security.passwords.require_lowercase', true)) {
            $requiredSets[] = $characterSets['lower'];
        }

        if (config('security.passwords.require_uppercase', true)) {
            $requiredSets[] = $characterSets['upper'];
        }

        if (config('security.passwords.require_numeric', true)) {
            $requiredSets[] = $characterSets['numbers'];
        }

        if (config('security.passwords.require_symbol', true)) {
            $requiredSets[] = $characterSets['symbols'];
        }

        if (empty($requiredSets)) {
            $requiredSets[] = $characterSets['lower'].$characterSets['upper'];
        }

        $passwordCharacters = [];

        foreach ($requiredSets as $set) {
            $passwordCharacters[] = self::randomCharacterFromSet($set);
        }

        $combinedSet = implode('', $requiredSets);

        while (count($passwordCharacters) < $length) {
            $passwordCharacters[] = self::randomCharacterFromSet($combinedSet);
        }

        shuffle($passwordCharacters);

        return implode('', $passwordCharacters);
    }

    private static function randomCharacterFromSet(string $set): string
    {
        $max = strlen($set) - 1;
        if ($max < 0) {
            throw new \InvalidArgumentException('Character set must not be empty.');
        }

        return $set[random_int(0, $max)];
    }
}
