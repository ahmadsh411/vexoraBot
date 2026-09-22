<?php

namespace App\Telegram\Screens\Admin\Users;

use App\Models\User;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class NewUsersScreen
{
    /**
     * عدد المستخدمين الذين لم يرهم الأدمن.
     */
    public static function unseenCount(): int
    {
        return User::whereNull('admin_seen_at')->count();
    }

    /**
     * نص شاشة "المستخدمون الجدد".
     */
    public static function text(): string
    {
        $users = User::whereNull('admin_seen_at')
            ->latest('created_at')
            ->limit(20)
            ->get();

        if ($users->isEmpty()) {
            return implode("\n", [
                '🆕 <b>المستخدمون الجدد</b>',
                '━━━━━━━━━━━━━━━━━━',
                '',
                '✅ لا يوجد مستخدمون جدد.',
                '',
                'كل الحسابات تم الاطلاع عليها.',
            ]);
        }

        $lines = [
            '🆕 <b>المستخدمون الجدد</b>',
            '━━━━━━━━━━━━━━━━━━',
            '',
            '👥 <b>العدد:</b> ' . $users->count(),
            '',
            '━━━━━━━━━━━━━━━━━━',
            '',
        ];

        foreach ($users as $index => $user) {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            if ($name === '') {
                $name = $user->username;
            }

            $minutesAgo = $user->created_at?->diffInMinutes(now()) ?? 0;

            if ($minutesAgo < 60) {
                $timeLabel = "قبل {$minutesAgo} دقيقة";
            } elseif ($minutesAgo < 1440) {
                $hours = floor($minutesAgo / 60);
                $timeLabel = "قبل {$hours} ساعة";
            } else {
                $days = floor($minutesAgo / 1440);
                $timeLabel = "قبل {$days} يوم";
            }

            $lines[] = ($index + 1) . '. <b>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</b>';
            $lines[] = '   📛 <code>' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') . '</code>';
            $lines[] = '   🕐 ' . $timeLabel;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * كيبورد شاشة "المستخدمون الجدد".
     */
    public static function keyboard(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        $users = User::whereNull('admin_seen_at')
            ->latest('created_at')
            ->limit(10)
            ->get();

        foreach ($users as $user) {
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            if ($name === '') {
                $name = $user->username;
            }

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🆕 ' . $name,
                    callback_data: "admin.users.show.{$user->id}",
                ),
            );
        }

        if ($users->isNotEmpty()) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '✅ تم الاطلاع على الكل',
                    callback_data: 'admin.users.new.mark-all-seen',
                ),
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '⬅️ رجوع',
                callback_data: 'admin.users',
            ),
        );

        return $keyboard;
    }
}
