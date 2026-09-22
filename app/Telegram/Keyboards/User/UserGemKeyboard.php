<?php

namespace App\Telegram\Keyboards\User;

use App\Models\User;
use App\Services\GemService;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class UserGemKeyboard
{
    // ============================================================
    //  🏠 القائمة الرئيسية
    // ============================================================
    public static function main(User $user, GemService $service): InlineKeyboardMarkup
    {
        $balance   = $service->getBalanceInt($user);
        $minEx     = $service->getExchangeMinGems();
        $minWheel  = $service->getWheelMinGems();

        $canExchange = $balance >= $minEx;
        $canWheel    = $balance >= $minWheel;

        $keyboard = InlineKeyboardMarkup::make();

        // ─── 💱 استبدال برصيد ───
        if ($canExchange) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '💱 استبدال برصيد',
                    callback_data: 'user.gems.exchange',
                    style: ButtonStyle::SUCCESS,
                ),
            );
        } else {
            $remaining = $minEx - $balance;
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🔒 استبدال (تحتاج ' . $remaining . ')',
                    callback_data: 'user.gems.noop',
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        // ─── 🎡 فتح العجلة ───
        if ($canWheel) {
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🎡 فتح العجلة',
                    callback_data: 'user.gems.wheel',
                    style: ButtonStyle::SUCCESS,
                ),
            );
        } else {
            $remaining = $minWheel - $balance;
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    text: '🔒 العجلة (تحتاج ' . $remaining . ')',
                    callback_data: 'user.gems.noop',
                    style: ButtonStyle::DANGER,
                ),
            );
        }

        // ─── 📜 السجل ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '📜 سجل الجواهر',
                callback_data: 'user.gems.history',
                style: ButtonStyle::PRIMARY,
            ),
        );

        // ─── ↩️ رجوع (بدون لون) ───
        $keyboard->addRow(
            InlineKeyboardButton::make(
                text: '🏠 رجوع للوحة',
                callback_data: 'user.dashboard',
            ),
        );

        return $keyboard;
    }

    // ============================================================
    //  💱 اختيار العملة
    // ============================================================
    public static function exchangeChoice(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💰 NSP',
                    callback_data: 'user.gems.exchange.nsp',
                    style: ButtonStyle::SUCCESS,
                ),
                InlineKeyboardButton::make(
                    text: '💵 USD',
                    callback_data: 'user.gems.exchange.usd',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع',
                    callback_data: 'user.gems',
                ),
            );
    }

    // ============================================================
    //  ✅ بعد الاستبدال
    // ============================================================
    public static function success(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💎 جواهري',
                    callback_data: 'user.gems',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🏠 لوحة التحكم',
                    callback_data: 'user.dashboard',
                ),
            );
    }

    // ============================================================
    //  🎡 بعد فتح العجلة
    // ============================================================
    public static function wheelSuccess(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🎡 اذهب للعجلة',
                    callback_data: 'user.wheel',
                    style: ButtonStyle::SUCCESS,
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: '💎 جواهري',
                    callback_data: 'user.gems',
                    style: ButtonStyle::PRIMARY,
                ),
            )
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '🏠 لوحة التحكم',
                    callback_data: 'user.dashboard',
                ),
            );
    }

    // ============================================================
    //  📜 السجل
    // ============================================================
    public static function history(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            // ─── ↩️ رجوع (بدون لون) ───
            ->addRow(
                InlineKeyboardButton::make(
                    text: '⬅️ رجوع للجواهر',
                    callback_data: 'user.gems',
                ),
            );
    }
}
