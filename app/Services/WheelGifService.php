<?php

namespace App\Services;

use App\Models\Wheel;
use App\Models\WheelPrize;
use Illuminate\Support\Facades\Log;

class WheelGifService
{
    private const SIZE = 500;
    private const CENTER = 250;
    private const RADIUS = 210;
    private const FRAMES = 24;          // عدد الإطارات
    private const FRAME_DELAY = 8;      // 80ms لكل إطار
    private const TOTAL_DURATION = 3;   // 3 ثوان

    /**
     * توليد GIF للعجلة تدور وتتوقف عند الجائزة.
     *
     * @param  Wheel  $wheel
     * @param  int  $winningIndex  فهرس الجائزة الفائزة (0-based)
     * @return string مسار ملف GIF
     */
    public function generate(Wheel $wheel, int $winningIndex): string
    {
        $prizes = $wheel->activePrizes()->orderBy('sort_order')->get();

        if ($prizes->isEmpty()) {
            throw new \RuntimeException('لا توجد جوائز نشطة');
        }

        $prizeCount = $prizes->count();
        $sliceAngle = 360 / $prizeCount;

        // ✅ الزاوية النهائية (مركز الجائزة الفائزة)
        $finalAngle = ($winningIndex * $sliceAngle) + ($sliceAngle / 2);

        // ✅ الزاوية الكلية للدوران (3 لفات كاملة + الزاوية النهائية)
        $totalRotation = (360 * 3) + $finalAngle;

        // ✅ توليد الإطارات
        $frames = [];

        for ($i = 0; $i < self::FRAMES; $i++) {
            $progress = $i / (self::FRAMES - 1);

            // ✅ دالة التباطؤ (ease-out cubic)
            $easedProgress = 1 - pow(1 - $progress, 3);

            // ✅ الزاوية الحالية
            $currentAngle = $totalRotation * $easedProgress;

            // ✅ رسم الإطار
            $frames[] = $this->renderFrame($prizes, $currentAngle, $winningIndex, $progress >= 1.0);
        }

        // ✅ توليد GIF
        $gifPath = $this->createAnimatedGif($frames);

        // ✅ تنظيف الإطارات
        foreach ($frames as $frame) {
            imagedestroy($frame);
        }

        return $gifPath;
    }

    // ============================================================
    //  رسم إطار واحد
    // ============================================================

    private function renderFrame($prizes, float $rotation, int $winningIndex, bool $isFinal): \GdImage
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);

        // ✅ خلفية شفافة
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        // ✅ الألوان
        $borderColor = imagecolorallocate($image, 30, 30, 30);
        $pointerColor = imagecolorallocate($image, 255, 215, 0);
        $pointerShadow = imagecolorallocate($image, 200, 150, 0);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $highlightColor = imagecolorallocate($image, 255, 215, 0);

        $prizeCount = $prizes->count();
        $sliceAngle = 360 / $prizeCount;

        // ✅ رسم القطاعات
        foreach ($prizes as $index => $prize) {
            // ✅ حساب زاوية القطاع (مع الدوران)
            $startAngle = ($index * $sliceAngle) + $rotation - 90;
            $endAngle = $startAngle + $sliceAngle;

            // ✅ لون القطاع
            $isWinner = $isFinal && $index === $winningIndex;

            if ($isWinner) {
                $color = $highlightColor;
            } else {
                $color = $this->getSliceColor($image, $index);
            }

            // ✅ رسم القطاع
            imagefilledarc(
                $image,
                self::CENTER,
                self::CENTER,
                self::RADIUS * 2,
                self::RADIUS * 2,
                (int) $startAngle,
                (int) $endAngle,
                $color,
                IMG_ARC_PIE
            );

            // ✅ رسم خط فاصل
            $this->drawSliceLine($image, $startAngle, $borderColor);

            // ✅ كتابة النص
            $this->drawSliceText($image, $prize, $startAngle, $sliceAngle, $textColor);
        }

        // ✅ الحدود الخارجية
        imagesetthickness($image, 6);
        imageellipse($image, self::CENTER, self::CENTER, self::RADIUS * 2, self::RADIUS * 2, $borderColor);
        imagesetthickness($image, 1);

        // ✅ الدائرة الداخلية (المركز)
        imagefilledellipse($image, self::CENTER, self::CENTER, 60, 60, $borderColor);
        imagefilledellipse($image, self::CENTER, self::CENTER, 50, 50, $pointerColor);

        // ✅ المؤشر (في الأعلى)
        $this->drawPointer($image, $pointerColor, $pointerShadow);

        return $image;
    }

    // ============================================================
    //  رسم خط فاصل بين القطاعات
    // ============================================================

    private function drawSliceLine($image, float $angle, $color): void
    {
        $rad = deg2rad($angle);
        $x1 = self::CENTER;
        $y1 = self::CENTER;
        $x2 = self::CENTER + (int) (cos($rad) * self::RADIUS);
        $y2 = self::CENTER + (int) (sin($rad) * self::RADIUS);

        imagesetthickness($image, 3);
        imageline($image, (int) $x1, (int) $y1, $x2, $y2, $color);
        imagesetthickness($image, 1);
    }

    // ============================================================
    //  كتابة نص القطاع
    // ============================================================

    private function drawSliceText($image, WheelPrize $prize, float $startAngle, float $sliceAngle, $textColor): void
    {
        // ✅ زاوية منتصف القطاع
        $midAngle = $startAngle + ($sliceAngle / 2);
        $rad = deg2rad($midAngle);

        // ✅ موقع النص
        $textRadius = self::RADIUS * 0.65;
        $textX = self::CENTER + (int) (cos($rad) * $textRadius);
        $textY = self::CENTER + (int) (sin($rad) * $textRadius);

        // ✅ اسم الجائزة
        $name = $prize->icon . ' ' . $prize->name;
        $this->drawText($image, $name, $textX, $textY - 12, $textColor, 14);

        // ✅ القيمة
        if ((float) $prize->value > 0) {
            $value = number_format((float) $prize->value, 0) . ' ' . $prize->currency;
            $this->drawText($image, $value, $textX, $textY + 8, $textColor, 11);
        }
    }

    // ============================================================
    //  رسم النص (مع دعم العربي)
    // ============================================================

    private function drawText($image, string $text, int $centerX, int $centerY, $color, int $fontSize): void
    {
        $font = $this->getFontPath();

        if (! $font) {
            // ✅ خط افتراضي
            $width = imagefontwidth(3) * strlen($text);
            imagestring($image, 3, $centerX - (int) ($width / 2), $centerY, $text, $color);
            return;
        }

        // ✅ حساب الأبعاد
        $bbox = imagettfbbox($fontSize, 0, $font, $text);
        $width = $bbox[2] - $bbox[0];
        $height = $bbox[1] - $bbox[7];

        $x = $centerX - (int) ($width / 2);
        $y = $centerY + (int) ($height / 2);

        imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
    }

    // ============================================================
    //  رسم المؤشر (سهم في الأعلى)
    // ============================================================

    private function drawPointer($image, $color, $shadow): void
    {
        $centerX = self::CENTER;
        $topY = 5;
        $pointerHeight = 45;
        $pointerWidth = 20;

        // ✅ ظل
        $shadowPoints = [
            $centerX - $pointerWidth + 2,
            $topY + 2,
            $centerX + $pointerWidth + 2,
            $topY + 2,
            $centerX + 2,
            $topY + $pointerHeight + 2,
        ];
        imagefilledpolygon($image, $shadowPoints, $shadow);

        // ✅ السهم
        $points = [
            $centerX - $pointerWidth,
            $topY,
            $centerX + $pointerWidth,
            $topY,
            $centerX,
            $topY + $pointerHeight,
        ];
        imagefilledpolygon($image, $points, $color);
    }

    // ============================================================
    //  ألوان القطاعات
    // ============================================================

    private function getSliceColor($image, int $index): int
    {
        $palette = [
            [231, 76, 60],    // أحمر
            [241, 196, 15],   // أصفر
            [46, 204, 113],   // أخضر
            [52, 152, 219],   // أزرق
            [155, 89, 182],   // بنفسجي
            [230, 126, 34],   // برتقالي
            [26, 188, 156],   // تركواز
            [192, 57, 43],    // أحمر داكن
            [243, 156, 18],   // ذهبي
            [39, 174, 96],    // أخضر داكن
            [41, 128, 185],   // أزرق داكن
            [142, 68, 173],   // بنفسجي داكن
            [211, 84, 0],     // برتقالي داكن
            [22, 160, 133],   // تركواز داكن
            [44, 62, 80],     // رمادي داكن
            [127, 140, 141],  // رمادي
        ];

        $rgb = $palette[$index % count($palette)];

        return imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
    }

    // ============================================================
    //  المسار للخط العربي
    // ============================================================

    private function getFontPath(): ?string
    {
        $paths = [
            public_path('fonts/Cairo-Bold.ttf'),
            public_path('fonts/Amiri-Bold.ttf'),
            public_path('fonts/NotoNaskhArabic-Bold.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    // ============================================================
    //  توليد GIF من الإطارات
    // ============================================================

    private function createAnimatedGif(array $frames): string
    {
        // ✅ مجلد مؤقت
        $dir = storage_path('app/wheel');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // ✅ مسار الإطارات المؤقتة
        $frameDir = $dir . '/frames_' . uniqid();
        mkdir($frameDir, 0755, true);

        // ✅ حفظ كل إطار كـ PNG
        $framePaths = [];
        foreach ($frames as $i => $frame) {
            $path = $frameDir . '/frame_' . str_pad($i, 3, '0', STR_PAD_LEFT) . '.png';
            imagepng($frame, $path);
            $framePaths[] = $path;
        }

        // ✅ استخدام ImageMagick إذا متوفر (أفضل جودة)
        $convert = trim(shell_exec('which convert 2>/dev/null') ?? '');

        if ($convert) {
            return $this->createGifWithImageMagick($framePaths, $dir, $frameDir);
        }

        // ✅ استخدام GD كبديل
        return $this->createGifWithGd($frames, $dir);
    }

    // ============================================================
    //  GIF بـ ImageMagick
    // ============================================================

    private function createGifWithImageMagick(array $framePaths, string $dir, string $frameDir): string
    {
        $outputPath = $dir . '/wheel_' . uniqid() . '.gif';

        // ✅ أمر ImageMagick
        $delay = (int) round(100 / self::FRAME_DELAY); // 100ms / delay
        $cmd = sprintf(
            'convert -delay %d -loop 0 -dispose previous %s %s 2>&1',
            $delay,
            escapeshellarg($frameDir . '/frame_*.png'),
            escapeshellarg($outputPath)
        );

        $output = shell_exec($cmd);

        // ✅ تنظيف الإطارات
        array_map('unlink', glob($frameDir . '/*'));
        @rmdir($frameDir);

        if (! file_exists($outputPath)) {
            Log::error('ImageMagick GIF creation failed', ['output' => $output]);
            throw new \RuntimeException('فشل إنشاء GIF: ' . $output);
        }

        return $outputPath;
    }

    // ============================================================
    //  GIF بـ GD (بديل)
    // ============================================================

    private function createGifWithGd(array $frames, string $dir): string
    {
        // ⚠️ GD لا يدعم GIF متحرك مباشرة
        // الحل: استخدام GIFEncoder يدوي
        // ⚠️ هذا معقد — سنستخدم ImageMagick فقط

        throw new \RuntimeException(
            'ImageMagick غير مثبت. يرجى تثبيته: sudo apt install imagemagick'
        );
    }
}
