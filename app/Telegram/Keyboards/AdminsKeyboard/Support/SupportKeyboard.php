<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Support;

use App\Models\SupportAgent;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class SupportKeyboard
{
    // ============================================================
    //  🛠️ للأدمن — الإدارة
    // ============================================================
    public static function admin(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        $agents = SupportAgent::ordered()->get();

        // ─── قائمة الداعمين ───
        if ($agents->isEmpty()) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '📭 لا يوجد داعمون',
                    callback_data: 'support.noop',
                ),
            );
        } else {
            foreach ($agents as $agent) {
                $icon = $agent->is_active ? '' : '🔴 ';
                $style = $agent->is_active
                    ? ButtonStyle::PRIMARY
                    : ButtonStyle::DANGER;

                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: "{$icon}{$agent->full_name}",
                        callback_data: 'support.agent.show.' . $agent->id,
                        style: $style,
                    ),
                );
            }
        }

        // ─── إضافة داعم ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '➕ إضافة داعم',
                callback_data: 'support.agent.create',
                style: ButtonStyle::SUCCESS,
            ),
        );

        // ─── رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'admin.dashboard',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  👤 تفاصيل داعم
    // ============================================================
    public static function agentDetails(SupportAgent $agent): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // ─── تعديلات ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✏️ الاسم',
                    callback_data: 'support.agent.edit-name.' . $agent->id,
                    style: ButtonStyle::PRIMARY,
                ),
                InlineKeyboardButton::make(
                    text: '🆔 المعرّف',
                    callback_data: 'support.agent.edit-username.' . $agent->id,
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ─── تفعيل/تعطيل + حذف ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: $agent->is_active ? '🔴 تعطيل' : '🟢 تفعيل',
                    callback_data: 'support.agent.toggle.' . $agent->id,
                    style: $agent->is_active ? ButtonStyle::DANGER : ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '🗑️ حذف',
                    callback_data: 'support.agent.delete.' . $agent->id,
                    style: ButtonStyle::DANGER,
                ),
            )
            // ─── رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '↩️ رجوع',
                    callback_data: 'admin.support',
                ),
            );
    }

    // ============================================================
    //  ⚠️ تأكيد الحذف
    // ============================================================
    public static function confirmDelete(SupportAgent $agent): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '✅ نعم، احذف',
                    callback_data: 'support.agent.delete-confirm.' . $agent->id,
                    style: ButtonStyle::DANGER,
                ),
                InlineKeyboardButton::make(
                    text: '✖️ إلغاء',
                    callback_data: 'support.agent.show.' . $agent->id,
                ),
            );
    }

    // ============================================================
    //  👥 للمستخدم
    // ============================================================
    public static function user(): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        $agents = SupportAgent::active()->ordered()->get();

        foreach ($agents as $agent) {
            $username = ltrim($agent->username ?? '', '@');

            if ($username === '') {
                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        text: $agent->full_name,
                        callback_data: 'support.agent.contact.' . $agent->id,
                        style: ButtonStyle::PRIMARY,
                    ),
                );
                continue;
            }

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: $agent->full_name,
                    url: 'https://t.me/' . $username,
                    style: ButtonStyle::PRIMARY,
                ),
            );
        }

        // ─── رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '↩️ رجوع',
                callback_data: 'user.dashboard',
            ),
        );

        return $keyboard;
    }
}
