<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // احصل على القيم الحالية
        $col = DB::select("SHOW COLUMNS FROM transactions WHERE Field = 'type'")[0]->Type ?? '';
        preg_match("/enum\((.*)\)/", $col, $m);
        $current = $m[1] ?? '';

        // إذا كانت موجودة مسبقًا → تجاهل
        if (str_contains($current, "'deposit_bonus'")) {
            return;
        }

        // القيم الجديدة = الحالية + deposit_bonus
        $values = array_map(
            fn($v) => trim($v, "' "),
            explode(',', $current)
        );

        $values[] = 'deposit_bonus';

        // حذف التكرارات
        $values = array_values(array_unique($values));

        $enum = "'" . implode("','", $values) . "'";

        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM({$enum}) NOT NULL");
    }

    public function down(): void
    {
        $col = DB::select("SHOW COLUMNS FROM transactions WHERE Field = 'type'")[0]->Type ?? '';
        preg_match("/enum\((.*)\)/", $col, $m);
        $current = $m[1] ?? '';

        $values = array_map(
            fn($v) => trim($v, "' "),
            explode(',', $current)
        );

        $values = array_filter($values, fn($v) => $v !== 'deposit_bonus');

        $enum = "'" . implode("','", $values) . "'";

        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM({$enum}) NOT NULL");
    }
};
