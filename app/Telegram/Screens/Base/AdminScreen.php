<?php

namespace App\Telegram\Screens\Base;

class AdminScreen
{
    public static function make(
        string $title,
        string $description,
    ): string {
        return implode("\n", [
            '⚡ <b>VEXORA</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            $title,
            '',
            $description,
            '',
            '━━━━━━━━━━━━━━━━━━',
        ]);
    }
}
