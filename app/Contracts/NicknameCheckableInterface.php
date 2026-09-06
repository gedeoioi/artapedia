<?php

namespace App\Contracts;

interface NicknameCheckableInterface
{
    public function checkNickname(string $gameCode, string $userId, ?string $zoneId = null): array;
}
