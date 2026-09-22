<?php

namespace App\Telegram\Keyboards\AdminsKeyboard\Finance;

use App\Telegram\Keyboards\Base\BaseAdminKeyboard;
use SergiX44\Nutgram\Telegram\Properties\ButtonStyle;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class MainWalletKeyboard extends BaseAdminKeyboard
{
    public static function make(): InlineKeyboardMarkup
    {
        return self::build()

            // ─── 📥 آخر الإيداعات + 📤 آخر السحوبات ───
            ->pairButtons(
                '📥 آخر الإيداعات',
                'admin.finance.main-wallet.deposits',
                '📤 آخر السحوبات',
                'admin.finance.main-wallet.withdraws',
                ButtonStyle::SUCCESS,
                ButtonStyle::DANGER,
            )

            // ─── 📊 إحصائيات مفصّلة + 🔄 مزامنة ───
            ->pairButtons(
                '📊 إحصائيات مفصّلة',
                'admin.finance.main-wallet.stats',
                '🔄 مزامنة',
                'admin.finance.main-wallet.sync',
                ButtonStyle::PRIMARY,
                ButtonStyle::PRIMARY,
            )

            // ─── 📜 آخر 20 عملية ───
            ->fullButton(
                '📜 آخر 20 عملية',
                'admin.finance.main-wallet.transactions',
                ButtonStyle::PRIMARY,
            )

            // ─── ↩️ رجوع (بدون لون) ───
            ->backButton('admin.finance')
            ->toMarkup();
    }
}
