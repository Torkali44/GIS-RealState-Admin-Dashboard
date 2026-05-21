<?php

namespace App\Support;

use Throwable;

/**
 * دمج ملفات PDF — يعمل على Shared Hosting بدون exec/shell_exec.
 * الأولوية: FPDI (إن وُجد) → نسخ مباشر (إذا كان ملف واحد).
 * Ghostscript وpdftk كـ bonus إذا كانت متاحة على السيرفر.
 */
final class PdfMerger
{
    /**
     * @param  list<string>  $sourcePaths  مسارات مطلقة بترتيب الدمج
     */
    public static function merge(array $sourcePaths, string $destinationPath): void
    {
        $sources = array_values(array_filter($sourcePaths, static fn (string $p): bool => is_readable($p)));
        if ($sources === []) {
            throw new \RuntimeException('لا توجد ملفات PDF للدمج.');
        }

        $dir = dirname($destinationPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (count($sources) === 1) {
            if (! @copy($sources[0], $destinationPath)) {
                throw new \RuntimeException('تعذر نسخ ملف PDF.');
            }

            return;
        }

        // محاولة Ghostscript (يعمل على VPS/Dedicated فقط)
        if (self::canUseExec() && self::mergeWithGhostscript($sources, $destinationPath)) {
            return;
        }

        // محاولة pdftk (يعمل على VPS/Dedicated فقط)
        if (self::canUseExec() && self::mergeWithPdfTk($sources, $destinationPath)) {
            return;
        }

        // FPDI — الأكثر توافقاً مع Shared Hosting
        if (class_exists('setasign\\Fpdi\\Fpdi')) {
            self::mergeWithFpdiPairwise($sources, $destinationPath);

            return;
        }

        // آخر حل: دمج بسيط بدون FPDI (يجمع الملفات كـ stream بدون تحرير صفحات)
        // هذا الحل لا يعطي PDF صحيحاً تماماً لكنه أفضل من الفشل الكامل.
        // الحل الصحيح: تثبيت FPDI عبر composer require setasign/fpdi
        throw new \RuntimeException(
            'تعذر دمج أجزاء التقرير. '
            . 'الحل: شغّل على السيرفر: composer require setasign/fpdi'
            . ' — أو تواصل مع الاستضافة لتفعيل Ghostscript.'
        );
    }

    /**
     * @param  list<string>  $sources
     */
    private static function mergeWithGhostscript(array $sources, string $destinationPath): bool
    {
        $gs = self::ghostscriptBinary();
        if ($gs === null) {
            return false;
        }

        $escapedOut = escapeshellarg($destinationPath);
        $escapedIn = implode(' ', array_map('escapeshellarg', $sources));
        $cmd = escapeshellarg($gs)." -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/ebook -sOutputFile={$escapedOut} {$escapedIn} 2>&1";

        try {
            exec($cmd, $output, $code);

            return $code === 0 && is_readable($destinationPath) && filesize($destinationPath) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<string>  $sources
     */
    private static function mergeWithPdfTk(array $sources, string $destinationPath): bool
    {
        if (! self::canUseExec()) {
            return false;
        }

        $pdftk = self::findExecutable(['pdftk', 'pdftk.exe']);
        if ($pdftk === null) {
            return false;
        }

        $inputList = implode(' ', array_map('escapeshellarg', $sources));
        $cmd = escapeshellarg($pdftk)." {$inputList} cat output ".escapeshellarg($destinationPath).' 2>&1';

        try {
            exec($cmd, $output, $code);

            return $code === 0 && is_readable($destinationPath) && filesize($destinationPath) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<string>  $sources
     */
    private static function mergeWithFpdiPairwise(array $sources, string $destinationPath): void
    {
        $fpdiClass = 'setasign\\Fpdi\\Fpdi';

        $current = $sources[0];
        $tmpDir = dirname($destinationPath).DIRECTORY_SEPARATOR.'merge-'.uniqid('', true);
        @mkdir($tmpDir, 0755, true);

        try {
            for ($i = 1; $i < count($sources); $i++) {
                $next = $sources[$i];
                $out = $tmpDir.DIRECTORY_SEPARATOR.'step-'.$i.'.pdf';
                self::mergeTwoWithFpdi($fpdiClass, $current, $next, $out);

                if ($current !== $sources[0] && is_file($current)) {
                    @unlink($current);
                }

                $current = $out;

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            if (! @rename($current, $destinationPath)) {
                if (! @copy($current, $destinationPath)) {
                    throw new \RuntimeException('تعذر حفظ التقرير المدمج.');
                }
            }
        } finally {
            foreach (glob($tmpDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($tmpDir);
        }
    }

    private static function mergeTwoWithFpdi(string $fpdiClass, string $a, string $b, string $destinationPath): void
    {
        /** @var object $pdf */
        $pdf = new $fpdiClass;
        // setPrintHeader/setPrintFooter خاصة بـ TCPDF فقط — FPDI+FPDF لا تدعمهما
        if (method_exists($pdf, 'setPrintHeader')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        foreach ([$a, $b] as $source) {
            $pageCount = $pdf->setSourceFile($source);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }
        }

        $pdf->Output($destinationPath, 'F');
        unset($pdf);
    }

    private static function ghostscriptBinary(): ?string
    {
        return self::findExecutable([
            'gs',
            'gswin64c',
            'gswin32c',
            '/usr/bin/gs',
            '/usr/local/bin/gs',
        ]);
    }

    /**
     * @param  list<string>  $names
     */
    private static function findExecutable(array $names): ?string
    {
        if (! self::canUseExec()) {
            return null;
        }

        foreach ($names as $name) {
            if (str_contains($name, '/') && is_executable($name)) {
                return $name;
            }

            $which = @shell_exec(PHP_OS_FAMILY === 'Windows'
                ? 'where '.escapeshellarg($name).' 2>nul'
                : 'command -v '.escapeshellarg($name).' 2>/dev/null');

            if (is_string($which) && trim($which) !== '') {
                $path = trim(explode("\n", $which)[0]);
                if ($path !== '' && is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    private static function canUseExec(): bool
    {
        if (! function_exists('exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('exec', $disabled, true) && ! in_array('shell_exec', $disabled, true);
    }
}