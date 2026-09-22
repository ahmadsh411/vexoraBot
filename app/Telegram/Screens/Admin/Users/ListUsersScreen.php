<?php

namespace App\Telegram\Screens\Admin\Users;

use App\Models\User;
use App\Telegram\Keyboards\AdminsKeyboard\Users\ListUserKeyboard;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ListUsersScreen
{
    public const PER_PAGE = 10;

    public static function text(int $page = 1): string
    {
        $page = max(1, $page);

        $total   = User::count();
        $active  = User::where('is_active', true)->count();
        $blocked = User::where('is_active', false)->count();

        return implode("\n", [
            '⚡ <b>VEXORA</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 <b>المستخدمون</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📊 <b>الإحصائيات</b>',
            '',
            '👤 الإجمالي: <b>' . $total . '</b>',
            '🟢 النشطون: <b>' . $active . '</b>',
            '🔴 الموقوفون: <b>' . $blocked . '</b>',
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '📄 الصفحة: <b>' . $page . '</b>',
            '',
            'اختر مستخدماً:',
        ]);
    }

    public static function keyboard(int $page = 1): InlineKeyboardMarkup
    {
        $page = max(1, $page);

        $total = User::count();

        $users = User::query()
            ->orderByDesc('created_at')
            ->forPage($page, self::PER_PAGE)
            ->get();

        return ListUserKeyboard::make(
            users: $users,
            currentPage: $page,
            hasPrev: $page > 1,
            hasNext: ($page * self::PER_PAGE) < $total,
        );
    }
}
