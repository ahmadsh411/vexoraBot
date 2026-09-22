<?php

namespace App\Console\Commands;

use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommand;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommandScopeChat;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommandScopeDefault;

class SetBotCommands extends Command
{
    protected $signature = 'bot:set-commands';
    protected $description = 'تسجيل أوامر البوت';

    public function handle(): int
    {
        /** @var Nutgram $bot */
        $bot = app(Nutgram::class);

        // ═══════════════════════════════════════════════════════════
        //  👤 الأوامر الافتراضية (للمستخدم العادي)
        // ═══════════════════════════════════════════════════════════
        $defaultCommands = [
            new BotCommand(
                command: 'start',
                description: 'القائمة',     // ← وصف قصير
            ),
        ];

        try {
            $bot->setMyCommands(
                commands: $defaultCommands,
                scope: new BotCommandScopeDefault(),
            );

            $this->info('✅ الأوامر الافتراضية');
        } catch (\Throwable $e) {
            $this->error('❌ ' . $e->getMessage());
            return self::FAILURE;
        }

        // ═══════════════════════════════════════════════════════════
        //  👑 أوامر الأدمن
        // ═══════════════════════════════════════════════════════════
        $adminCommands = [
            new BotCommand(
                command: 'admin',
                description: 'لوحة التحكم',  // ← وصف قصير
            ),
        ];

        $admins = User::where('is_admin', true)
            ->whereNotNull('telegram_id')
            ->get();

        $this->newLine();
        $this->info('👑 تسجيل أوامر الأدمن...');

        foreach ($admins as $admin) {
            try {
                $bot->setMyCommands(
                    commands: $adminCommands,
                    scope: new BotCommandScopeChat(
                        chat_id: $admin->telegram_id,
                    ),
                );

                $icon = $admin->is_super_admin ? '👑' : '🛡️';
                $this->line("  {$icon} {$admin->username}");
            } catch (\Throwable $e) {
                $this->warn("  ⚠️ {$admin->username}: " . $e->getMessage());
            }
        }

        // ═══════════════════════════════════════════════════════════
        //  🎧 أوامر الداعمين
        // ═══════════════════════════════════════════════════════════
        $supportCommands = [
            new BotCommand(
                command: 'start',
                description: 'القائمة',
            ),
            new BotCommand(
                command: 'support_start',
                description: 'الداعم',
            ),
        ];

        $agents = SupportAgent::whereNotNull('telegram_id')->get();

        $this->newLine();
        $this->info('🎧 تسجيل أوامر الداعمين...');

        foreach ($agents as $agent) {
            try {
                $bot->setMyCommands(
                    commands: $supportCommands,
                    scope: new BotCommandScopeChat(
                        chat_id: $agent->telegram_id,
                    ),
                );

                $this->line("  ✅ {$agent->name}");
            } catch (\Throwable $e) {
                $this->warn("  ⚠️ {$agent->name}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('🎉 تم تسجيل كل الأوامر بنجاح!');

        return self::SUCCESS;
    }
}
