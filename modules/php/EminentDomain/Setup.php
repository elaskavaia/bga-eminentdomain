<?php

namespace EminentDomain;

class Setup
{
    static function BuildDecks(int $playerCount, bool $extended_variant): array
    {
        switch ($playerCount) {
            case 2:
                $setupnums = [16, 14, 16, 12, 16];
                break;
            case 3:
                if ($extended_variant)
                    $setupnums = [18, 15, 18, 14, 18];
                else
                    $setupnums = [20, 16, 20, 16, 20];
                break;
            case 4:
                $setupnums = [20, 16, 20, 16, 20];
                break;
            case 5:
                $setupnums = [24, 20, 24, 20, 24];
                break;
            default:
                $setupnums = [16, 14, 16, 12, 16];
                break;
        }

        return $setupnums;
    }

    static function GetMaxVP(int $playerCount): int
    {
        return $playerCount === 5 ? 32 : 24;
    }
}
