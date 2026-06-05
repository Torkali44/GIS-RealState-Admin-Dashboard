<?php

namespace App\Services;

use App\Models\InspectionArea;
use App\Models\InspectionPhoto;
use App\Models\PropertyHouse;
use App\Support\PdfMerger;
use App\Support\RasterImageForTcpdf;
use App\Support\ReportImageEmbedCache;
use TCPDF;
use Throwable;

class InspectionReportPdfGenerator
{
    /**
     * فوق هذا العدد: توليد PDF لكل قسم ثم دمج (يدعم حتى ~500 صورة+)
     * خُفِّض من 90 إلى 30 لتوفير الذاكرة على Shared Hosting
     */
    private function sectionBuildThreshold(): int
    {
        return max(30, (int) config('report.section_build_photo_threshold', 80));
    }

    private function imageMaxDimension(): int
    {
        return max(800, min(1600, (int) config('report.image_max_dimension', 1100)));
    }

    private function jpegQuality(): int
    {
        return max(50, min(92, (int) config('report.jpeg_quality', 65)));
    }

    private function gcEveryNPhotos(): int
    {
        return max(2, (int) config('report.gc_every_n_photos', 4));
    }

    public function renderBinary(PropertyHouse $house): string
    {
        $tmp = $this->tempPdfPath($house);
        $this->renderToFile($house, $tmp);
        $binary = file_get_contents($tmp);
        @unlink($tmp);

        return $binary !== false ? $binary : '';
    }

    public function renderToFile(PropertyHouse $house, string $absolutePath): void
    {
        $house->load([
            'inspectionAreas' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'inspectionAreas.photos' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        @set_time_limit(0);
        @ignore_user_abort(true);
        @ini_set('max_execution_time', '1200');
        $prevMemory = ini_get('memory_limit');

        // رفع الذاكرة بشكل تدريجي حسب المتاح على السيرفر
        foreach (['1024M', '512M', '256M'] as $memLimit) {
            @ini_set('memory_limit', $memLimit);
            // تحقق أن القيمة أُخذت بعين الاعتبار
            $current = ini_get('memory_limit');
            if ($this->parseMemoryLimit($current) >= $this->parseMemoryLimit('256M')) {
                break;
            }
        }

        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $photoCount = $this->countPhotos($house);

        try {
            if ($photoCount >= $this->sectionBuildThreshold()) {
                try {
                    $this->renderBySectionsToFile($house, $absolutePath);
                } catch (Throwable $e) {
                    if (is_file($absolutePath)) {
                        @unlink($absolutePath);
                    }
                    // لا نحاول monolithic للمنازل الكبيرة — سيفشل بالتأكيد
                    if ($photoCount > 80) {
                        throw $e;
                    }
                    $this->renderMonolithicToFile($house, $absolutePath);
                }
            } else {
                $this->renderMonolithicToFile($house, $absolutePath);
            }

            if (! is_readable($absolutePath) || filesize($absolutePath) < 100) {
                throw new \RuntimeException('ملف التقرير لم يُنشأ بشكل صحيح.');
            }
        } finally {
            @ini_set('memory_limit', (string) $prevMemory);
        }
    }

    private function countPhotos(PropertyHouse $house): int
    {
        if ($house->relationLoaded('inspectionAreas')) {
            return (int) $house->inspectionAreas->sum(
                fn (InspectionArea $area): int => $area->relationLoaded('photos')
                    ? $area->photos->count()
                    : $area->photos()->count()
            );
        }

        return (int) InspectionPhoto::query()
            ->whereHas('inspectionArea', fn ($q) => $q->where('property_house_id', $house->id))
            ->count();
    }

    /**
     * منازل كبيرة: PDF مستقل لكل قسم (عنوان + صور) ثم دمج — ذاكرة ثابتة تقريباً.
     */
    private function renderBySectionsToFile(PropertyHouse $house, string $absolutePath): void
    {
        $ctx = $this->reportContext($house);
        $workDir = storage_path('app/reports/sections/h'.$house->id.'-'.uniqid('', true));
        @mkdir($workDir, 0755, true);
        $partials = [];
        $section = 0;

        try {
            $areas = $house->inspectionAreas;

            foreach ($areas as $area) {
                if ($area->photos->isEmpty()) {
                    continue;
                }

                $section++;
                $partialPath = $workDir.DIRECTORY_SEPARATOR.sprintf('section-%03d.pdf', $section);
                $this->renderAreaPartToFile($house, $area, $section, $partialPath, $ctx);
                $partials[] = $partialPath;

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            if ($section === 0) {
                $this->renderEmptyReportToFile($house, $absolutePath, $ctx);

                return;
            }

            if (count($partials) === 1) {
                $this->moveFile($partials[0], $absolutePath);
            } else {
                PdfMerger::merge($partials, $absolutePath);
            }
        } finally {
            $this->deleteWorkDir($workDir);
        }
    }

    /**
     * @param  array{reportNo: string, reportDate: string, clientName: string, address: string, logoPath: ?string, waIconPath: ?string, emailIconPath: ?string, footerPhone: string, footerEmail: string, footerWeb: string, footerAddress: string}  $ctx
     */
    private function renderAreaPartToFile(
        PropertyHouse $house,
        InspectionArea $area,
        int $sectionNumber,
        string $absolutePath,
        array $ctx
    ): void {
        $pdf = $this->newPdf($house);
        $tempFiles = [];

        try {
            $this->addSectionTitlePage(
                $pdf,
                $area,
                $sectionNumber,
                $ctx['reportNo'],
                $ctx['reportDate'],
                $ctx['logoPath'],
                $ctx['clientName'],
                $ctx['address'],
                $ctx
            );

            $photos = $area->photos;

            foreach ($photos as $photoIndex => $photo) {
                try {
                    $this->addPhotoPage(
                        $pdf,
                        $area,
                        $photo,
                        $photoIndex + 1,
                        $ctx['reportNo'],
                        $ctx['reportDate'],
                        $ctx['logoPath'],
                        $tempFiles,
                        $ctx['clientName'],
                        $ctx['address'],
                        $ctx
                    );
                } catch (Throwable) {
                    $this->addSkippedPhotoPage(
                        $pdf,
                        $ctx['reportNo'],
                        $ctx['reportDate'],
                        $ctx['logoPath'],
                        $ctx['clientName'],
                        $ctx['address'],
                        (int) $photo->id,
                        $ctx
                    );
                }

                if (($photoIndex + 1) % $this->gcEveryNPhotos() === 0) {
                    $this->purgeTempFiles($tempFiles);
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }
            }

            unset($photos);
            $this->purgeTempFiles($tempFiles);
            $pdf->Output($absolutePath, 'F');
        } finally {
            $this->purgeTempFiles($tempFiles);
            unset($pdf);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    /** منازل صغيرة/متوسطة: ملف PDF واحد (أسرع للعدد القليل). */
    private function renderMonolithicToFile(PropertyHouse $house, string $absolutePath): void
    {
        $ctx = $this->reportContext($house);
        $pdf = $this->newPdf($house);
        $tempFiles = [];
        $section = 0;
        $photoCount = 0;

        try {
            $areas = $house->inspectionAreas;

            foreach ($areas as $area) {
                $photos = $area->photos;

                if ($photos->isEmpty()) {
                    continue;
                }

                $section++;
                $this->addSectionTitlePage(
                    $pdf,
                    $area,
                    $section,
                    $ctx['reportNo'],
                    $ctx['reportDate'],
                    $ctx['logoPath'],
                    $ctx['clientName'],
                    $ctx['address'],
                    $ctx
                );

                foreach ($photos as $photoIndex => $photo) {
                    try {
                        $this->addPhotoPage(
                            $pdf,
                            $area,
                            $photo,
                            $photoIndex + 1,
                            $ctx['reportNo'],
                            $ctx['reportDate'],
                            $ctx['logoPath'],
                            $tempFiles,
                            $ctx['clientName'],
                            $ctx['address'],
                            $ctx
                        );
                    } catch (Throwable) {
                        $this->addSkippedPhotoPage(
                            $pdf,
                            $ctx['reportNo'],
                            $ctx['reportDate'],
                            $ctx['logoPath'],
                            $ctx['clientName'],
                            $ctx['address'],
                            (int) $photo->id,
                            $ctx
                        );
                    }

                    $photoCount++;
                    if ($photoCount % $this->gcEveryNPhotos() === 0) {
                        $this->purgeTempFiles($tempFiles);
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                }

                unset($photos);
            }

            if ($section === 0) {
                $pdf->AddPage();
                $this->addBrandedFrame(
                    $pdf,
                    $ctx['reportNo'],
                    $ctx['reportDate'],
                    $ctx['logoPath'],
                    $ctx['clientName'],
                    $ctx['address'],
                    $ctx
                );
                $pdf->SetTextColor(30, 41, 59);
                $pdf->SetFont('aealarabiya', 'B', 22);
                $pdf->SetY(130);
                $pdf->Cell(0, 14, 'لا توجد صور معاينة بعد', 0, 1, 'C');
            }

            $this->purgeTempFiles($tempFiles);
            $pdf->Output($absolutePath, 'F');
        } finally {
            $this->purgeTempFiles($tempFiles);
            unset($pdf);
        }
    }

    /**
     * @return array{
     *     reportNo: string,
     *     reportDate: string,
     *     clientName: string,
     *     address: string,
     *     logoPath: ?string
     * }
     */
    private function reportContext(PropertyHouse $house): array
    {
        return [
            'reportNo' => $house->title ?: ($house->reference_code ?: ('H-'.$house->id)),
            'reportDate' => ($house->inspection_date ?: $house->created_at ?: now())->format('Y-m-d'),
            'clientName' => (string) $house->client_name,
            'address' => (string) $house->address,
            'logoPath' => $this->resolveLogoPath(),
            'waIconPath' => $this->resolveReportAssetPath(config('report.whatsapp_icon_path', 'images/whatsapp_icon.png'))
                ?? $this->resolveReportAssetPath('images/whatsapp_icon.png'),
            'emailIconPath' => $this->resolveReportAssetPath(config('report.email_icon_path', 'images/email_icon.png'))
                ?? $this->resolveReportAssetPath('images/email_icon.png'),
            'footerPhone' => (string) config('report.footer_phone', env('REPORT_FOOTER_PHONE', '36698895')),
            'footerEmail' => (string) config('report.footer_email', env('REPORT_FOOTER_EMAIL', 'infogisguif@gmail.com')),
            'footerWeb' => (string) config('report.footer_web', env('REPORT_FOOTER_WEB', 'gis.Bahrain')),
            'footerAddress' => (string) config('report.footer_address', env('REPORT_FOOTER_ADDRESS', 'Seef District - Kingdom of Bahrain')),
        ];
    }

    /**
     * @param  array{reportNo: string, reportDate: string, clientName: string, address: string, logoPath: ?string}  $ctx
     */
    private function renderEmptyReportToFile(PropertyHouse $house, string $absolutePath, array $ctx): void
    {
        $pdf = $this->newPdf($house);
        $pdf->AddPage();
        $this->addBrandedFrame(
            $pdf,
            $ctx['reportNo'],
            $ctx['reportDate'],
            $ctx['logoPath'],
            $ctx['clientName'],
            $ctx['address'],
            $ctx
        );
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetFont('aealarabiya', 'B', 22);
        $pdf->SetY(130);
        $pdf->Cell(0, 14, 'لا توجد صور معاينة بعد', 0, 1, 'C');
        $pdf->Output($absolutePath, 'F');
        unset($pdf);
    }

    private function moveFile(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }
        if (! @copy($from, $to)) {
            throw new \RuntimeException('تعذر حفظ ملف التقرير.');
        }
        @unlink($from);
    }

    private function deleteWorkDir(string $workDir): void
    {
        if (! is_dir($workDir)) {
            return;
        }
        foreach (glob($workDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($workDir);
    }

    private function tempPdfPath(PropertyHouse $house): string
    {
        $dir = storage_path('app/reports/tmp');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir.DIRECTORY_SEPARATOR.'house-'.$house->id.'-'.uniqid('', true).'.pdf';
    }

    private function newPdf(PropertyHouse $house): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name'));
        $pdf->SetAuthor(config('app.name'));
        $pdf->SetTitle('تقرير معاينة — '.$house->title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 14, 14);
        $pdf->SetAutoPageBreak(false);
        $pdf->setRTL(true);
        $pdf->SetCompression(true);
        $pdf->setJpegQuality($this->jpegQuality());
        $pdf->SetFont('aealarabiya', '', 12);

        return $pdf;
    }

    private function addSkippedPhotoPage(
        TCPDF $pdf,
        string $reportNo,
        string $reportDate,
        ?string $logoPath,
        string $clientName,
        string $address,
        int $photoId,
        array $frameCtx = []
    ): void {
        $pdf->AddPage();
        $this->addBrandedFrame($pdf, $reportNo, $reportDate, $logoPath, $clientName, $address, $frameCtx);
        $pdf->SetTextColor(220, 38, 38);
        $pdf->SetFont('aealarabiya', '', 12);
        $pdf->SetY(120);
        $pdf->Cell(0, 10, 'تعذر تضمين الصورة #'.$photoId.' في التقرير', 0, 1, 'C');
    }

    /**
     * @param  array<int, string>  $tempFiles
     */
    private function photoDescription(InspectionPhoto $photo): string
    {
        try {
            return trim($photo->combinedDescription());
        } catch (Throwable) {
            return trim((string) $photo->description);
        }
    }

    /**
     * @param  array<int, string>  $tempFiles
     */
    private function purgeTempFiles(array &$tempFiles): void
    {
        foreach ($tempFiles as $tmp) {
            if (is_string($tmp) && is_file($tmp)) {
                @unlink($tmp);
            }
        }
        $tempFiles = [];
    }

    /**
     * تصغير الصورة إذا تجاوزت الحد الأقصى للأبعاد — يوفر الذاكرة بشكل كبير على Shared Hosting.
     * يُعيد مسار ملف مؤقت أو المسار الأصلي إذا لم يكن التصغير ضروريًا.
     * يُضيف المسار المؤقت إلى $tempFiles لحذفه لاحقاً.
     *
     * @param  array<int, string>  $tempFiles
     */
    private function prepareImageForPdf(string $sourcePath, array &$tempFiles, InspectionPhoto $photo): ?string
    {
        if (! is_readable($sourcePath)) {
            return null;
        }

        $maxDim = $this->imageMaxDimension();
        $quality = $this->jpegQuality();

        return ReportImageEmbedCache::resolve(
            $photo,
            $sourcePath,
            $maxDim,
            $quality,
            function (string $path) use ($maxDim, $quality, &$tempFiles): ?string {
                return $this->buildTempJpegForPdf($path, $maxDim, $quality, $tempFiles);
            }
        );
    }

    /**
     * @param  array<int, string>  $tempFiles
     */
    private function buildTempJpegForPdf(string $sourcePath, int $maxDim, int $quality, array &$tempFiles): ?string
    {
        $imageInfo = @getimagesize($sourcePath);
        if (! $imageInfo) {
            return null;
        }

        [$origW, $origH, $imgType] = $imageInfo;

        if ($origW <= $maxDim && $origH <= $maxDim && $imgType === IMAGETYPE_JPEG) {
            return $sourcePath;
        }

        if ($origW <= $maxDim && $origH <= $maxDim && $imgType !== IMAGETYPE_JPEG) {
            try {
                [$embedPath, $isTemp] = RasterImageForTcpdf::prepareForEmbedding($sourcePath);
                if ($isTemp && $embedPath) {
                    $tempFiles[] = $embedPath;
                }

                return $embedPath;
            } catch (Throwable) {
                return null;
            }
        }

        $ratio = $origW / $origH;
        if ($origW >= $origH) {
            $newW = $maxDim;
            $newH = (int) round($maxDim / $ratio);
        } else {
            $newH = $maxDim;
            $newW = (int) round($maxDim * $ratio);
        }

        if (! extension_loaded('gd')) {
            try {
                [$embedPath, $isTemp] = RasterImageForTcpdf::prepareForEmbedding($sourcePath);
                if ($isTemp && $embedPath) {
                    $tempFiles[] = $embedPath;
                }

                return $embedPath;
            } catch (Throwable) {
                return null;
            }
        }

        try {
            $srcImg = match ($imgType) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
                IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
                IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
                default => false,
            };

            if (! $srcImg) {
                try {
                    [$embedPath, $isTemp] = RasterImageForTcpdf::prepareForEmbedding($sourcePath);
                    if ($isTemp && $embedPath) {
                        $tempFiles[] = $embedPath;
                    }

                    return $embedPath;
                } catch (Throwable) {
                    return null;
                }
            }

            $dstImg = imagecreatetruecolor($newW, $newH);
            if (! $dstImg) {
                imagedestroy($srcImg);

                return null;
            }

            if (in_array($imgType, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
                imagealphablending($dstImg, false);
                imagesavealpha($dstImg, true);
                $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
                if ($transparent !== false) {
                    imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $transparent);
                }
            }

            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($srcImg);

            $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pdf_img_'.uniqid('', true).'.jpg';
            $saved = imagejpeg($dstImg, $tmpPath, $quality);
            imagedestroy($dstImg);

            if ($saved && is_readable($tmpPath)) {
                $tempFiles[] = $tmpPath;

                return $tmpPath;
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    private function addSectionTitlePage(
        TCPDF $pdf,
        InspectionArea $area,
        int $sectionNumber,
        string $reportNo,
        string $reportDate,
        ?string $logoPath,
        string $clientName,
        string $address,
        array $frameCtx = []
    ): void {
        $pdf->AddPage();
        $this->addBrandedFrame($pdf, $reportNo, $reportDate, $logoPath, $clientName, $address, $frameCtx);

        $name = trim((string) $area->name);
        if ($name === '') {
            $name = 'القسم '.$sectionNumber;
        }

        $pdf->setRTL(true);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetFont('aealarabiya', 'B', 44);
        $pdf->SetY(125);
        $pdf->MultiCell(0, 0, $name, 0, 'C', false, 1);
    }

    private function addPhotoPage(
        TCPDF $pdf,
        InspectionArea $area,
        InspectionPhoto $photo,
        int $photoNumber,
        string $reportNo,
        string $reportDate,
        ?string $logoPath,
        array &$tempFiles,
        string $clientName,
        string $address,
        array $frameCtx = []
    ): void {
        $pdf->AddPage();
        $this->addBrandedFrame($pdf, $reportNo, $reportDate, $logoPath, $clientName, $address, $frameCtx);

        $containerX = 18.0;
        $containerW = $pdf->getPageWidth() - 36.0;
        $footerTop = $pdf->getPageHeight() - 26.0;
        $contentW = $containerW - 12.0;
        $contentX = $containerX + 6.0;

        $areaName = trim((string) $area->name);
        $contentTop = 44.0;

        if ($areaName !== '') {
            $pdf->setRTL(true);
            $pdf->SetTextColor(185, 28, 28);
            $pdf->SetFont('aealarabiya', 'B', 20);
            $pdf->SetXY($contentX, 30);
            
            // Use MultiCell to wrap the long area name onto multiple lines if it exceeds width
            $pdf->MultiCell($contentW, 0, $this->wrapMixedTextForRtl($areaName), 0, 'C', false, 1);
            
            // Get the actual Y coordinate at the bottom of the rendered area name block
            $currentY = $pdf->GetY();

            // Draw a decorative line under the section name
            $pdf->SetDrawColor(185, 28, 28);
            $pdf->SetLineWidth(0.5);
            $lineY = max(43.0, $currentY + 2.0);
            $lineW = min(mb_strlen($areaName) * 5, $contentW * 0.6);
            $lineX = $contentX + ($contentW - $lineW) / 2;
            $pdf->Line($lineX, $lineY, $lineX + $lineW, $lineY);

            // Set contentTop below the line dynamically
            $contentTop = $lineY + 9.0;
        }

        $desc = $this->photoDescription($photo);

        // Calculate image height based on whether description exists
        $pdf->SetFont('aealarabiya', 'B', 15);
        $descH = ($desc !== '') ? max(12.0, $pdf->getStringHeight($contentW, $desc) + 5) : 0;
        $imageMaxH = max(80.0, ($footerTop - $contentTop) - $descH - 10);

        $imgH = 20.0;
        $path = $photo->reportImagePath();
        $embedPath = null;

        if ($path && is_readable($path)) {
            try {
                // استخدام الدالة الجديدة للضغط والتصغير
                $embedPath = $this->prepareImageForPdf($path, $tempFiles, $photo);
            } catch (Throwable) {
                $embedPath = null;
            }

            if ($embedPath && is_readable($embedPath)) {
                [$wPx, $hPx] = @getimagesize($embedPath) ?: [1200, 800];
                $ratio = ($wPx > 0 && $hPx > 0) ? ($hPx / $wPx) : (2 / 3);
                $imgW = $contentW;
                $imgH = $imgW * $ratio;
                if ($imgH > $imageMaxH) {
                    $imgH = $imageMaxH;
                    $imgW = $imgH / max($ratio, 0.001);
                }
                $imgX = $contentX + (($contentW - $imgW) / 2.0);

                try {
                    $pdf->setRTL(false);
                    // خُفِّض DPI من 120 إلى 96 لتوفير الذاكرة
                    $pdf->Image($embedPath, $imgX, $contentTop, $imgW, $imgH, '', '', '', false, 96, '', false, false, 1, false, false, false);
                    $pdf->setRTL(true);
                } catch (Throwable) {
                    $pdf->setRTL(true);
                    $pdf->SetTextColor(220, 38, 38);
                    $pdf->SetFont('aealarabiya', '', 10);
                    $pdf->SetXY($contentX, $contentTop);
                    $pdf->Cell($contentW, 7, 'تعذر تضمين الصورة في PDF', 0, 1, 'C');
                    $imgH = 20;
                }
            } else {
                $pdf->SetTextColor(220, 38, 38);
                $pdf->SetFont('aealarabiya', '', 10);
                $pdf->SetXY($contentX, $contentTop);
                $pdf->Cell($contentW, 7, 'الصورة غير متاحة', 0, 1, 'C');
                $imgH = 20;
            }
        } else {
            $pdf->SetTextColor(220, 38, 38);
            $pdf->SetFont('aealarabiya', '', 10);
            $pdf->SetXY($contentX, $contentTop);
            $pdf->Cell($contentW, 7, 'الصورة غير متاحة', 0, 1, 'C');
            $imgH = 20;
        }

        if ($desc !== '') {
            $descY = $contentTop + $imgH + 6;
            if (mb_strlen($desc) > 4000) {
                $desc = mb_substr($desc, 0, 4000).'…';
            }
            $bidiDesc = $this->wrapMixedTextForRtl($desc);

            $pdf->setRTL(true);
            $pdf->SetFont('aealarabiya', '', 13);

            $innerW = $contentW - 10;
            $lineH = 6.4;
            $lines = max(1, $pdf->getNumLines($bidiDesc, $innerW));
            $boxH = max(16, ($lines * $lineH) + 6);

            $pdf->SetFillColor(255, 204, 0);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.2);
            $pdf->RoundedRect($contentX, $descY, $contentW, $boxH, 2.5, '1111', 'DF');

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($contentX + 5, $descY + 3);
            $pdf->MultiCell(
                $innerW,
                $lineH,
                $bidiDesc,
                0,
                'R',
                false,
                1,
                '',
                '',
                true,
                0,
                false,
                true,
                0,
                'T',
                false
            );
        }
    }

    private function addBrandedFrame(TCPDF $pdf, string $reportNo, string $reportDate, ?string $logoPath, string $clientName = '', string $address = '', array $frameCtx = []): void
    {
        $pageW = $pdf->getPageWidth();
        $pageH = $pdf->getPageHeight();
        $containerX = 8.0;
        $containerY = 8.0;
        $containerW = $pageW - 16.0;
        $containerH = $pageH - 16.0;

        $pdf->SetFillColor(243, 244, 246);
        $pdf->Rect(0, 0, $pageW, $pageH, 'F');

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(229, 231, 235);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($containerX, $containerY, $containerW, $containerH, 'DF');

        $headerX = $containerX + 6.0;
        $headerY = $containerY + 4.0;
        $embedLogoPath = $this->prepareLogoPathForPdf($logoPath);
        $logoBox = $this->logoBoxMmForPdf($embedLogoPath ?? $logoPath);

        if ($embedLogoPath && is_readable($embedLogoPath)) {
            try {
                $pdf->setRTL(false);
                $pdf->Image(
                    $embedLogoPath,
                    $headerX,
                    $headerY,
                    $logoBox['w'],
                    $logoBox['h'],
                    '',
                    '',
                    '',
                    false,
                    (int) config('report.logo_embed_dpi', 200),
                    '',
                    false,
                    false,
                    0,
                    false,
                    false,
                    false
                );
                $pdf->setRTL(true);
            } catch (Throwable) {
            }
        }

        // Info Column
        $pdf->SetTextColor(185, 28, 28);
        $pdf->setRTL(true);
        $pdf->SetFont('aealarabiya', 'B', 9);
        $pdf->SetXY($containerX + 10, $headerY);
        $pdf->Cell(45, 5, 'رقم الطلب: ' . $reportNo, 0, 1, 'R');
        $pdf->SetFont('aealarabiya', '', 9);
        $pdf->SetXY($containerX + 10, $headerY + 6);
        $pdf->Cell(45, 5, 'تاريخ الفحص: ' . $reportDate, 0, 1, 'R');

        $pdf->SetDrawColor(226, 232, 240);
        $pdf->Line($containerX + 4, 27, $containerX + $containerW - 4, 27);

        $footerDividerY = $pageH - 24;
        $pdf->Line($containerX + 4, $footerDividerY, $containerX + $containerW - 4, $footerDividerY);

        $footerY = $pageH - 21.5;
        $footerX = $containerX + 6.0;
        $footerW = $containerW - 12.0;

        $pdf->setRTL(false);
        $pdf->SetTextColor(185, 28, 28);
        $pdf->SetFont('aealarabiya', 'B', 9);

        $footerPhone = (string) ($frameCtx['footerPhone'] ?? config('report.footer_phone', '36698895'));
        $footerEmail = (string) ($frameCtx['footerEmail'] ?? config('report.footer_email', 'infogisguif@gmail.com'));
        $footerWeb = (string) ($frameCtx['footerWeb'] ?? config('report.footer_web', 'gis.Bahrain'));
        $footerAddress = (string) ($frameCtx['footerAddress'] ?? config('report.footer_address', 'Seef District - Kingdom of Bahrain'));

        $waIconPath = $frameCtx['waIconPath'] ?? $this->resolveReportAssetPath(config('report.whatsapp_icon_path', 'images/whatsapp_icon.png'))
            ?? $this->resolveReportAssetPath('images/whatsapp_icon.png');
        $waDrawn = false;
        if ($waIconPath) {
            try {
                $pdf->Image($waIconPath, $footerX, $footerY - 0.2, 4, 4, '', '', '', false, 96, '', false, false, 0, false, false, false);
                $waDrawn = true;
            } catch (Throwable) {
                $waDrawn = false;
            }
        }
        if (!$waDrawn) {
            $this->drawWhatsappIcon($pdf, $footerX, $footerY - 0.2, 4);
        }
        $pdf->SetTextColor(185, 28, 28);
        $pdf->SetFont('aealarabiya', 'B', 9);
        $pdf->SetXY($footerX + 5, $footerY);
        $pdf->Cell(16, 4, $footerPhone . ' |    ', 0, 0, 'L');

        $emailIconPath = $frameCtx['emailIconPath'] ?? $this->resolveReportAssetPath(config('report.email_icon_path', 'images/email_icon.png'))
            ?? $this->resolveReportAssetPath('images/email_icon.png');
        $emailDrawn = false;
        if ($emailIconPath) {
            try {
                $pdf->Image($emailIconPath, $footerX + 22, $footerY - 0.2, 4, 4, '', '', '', false, 96, '', false, false, 0, false, false, false);
                $emailDrawn = true;
            } catch (Throwable) {
                $emailDrawn = false;
            }
        }
        if (!$emailDrawn) {
            $this->drawEmailIcon($pdf, $footerX + 22, $footerY + 0.2, 4);
        }
        $pdf->SetTextColor(185, 28, 28);
        $pdf->SetFont('aealarabiya', 'B', 9);
        $pdf->SetXY($footerX + 27, $footerY);
        $pdf->Cell(42, 4, ' ' . $footerEmail, 0, 0, 'L');

        $pdf->SetXY($footerX + 60, $footerY);
        $pdf->Cell(30, 4, ' |  ' . $footerWeb, 0, 1, 'L');

        $pdf->SetXY($footerX, $footerY + 4.5);
        $pdf->SetFont('aealarabiya', '', 9);
        $pdf->Cell(0, 4, $footerAddress, 0, 1, 'L');

        $pdf->setRTL(false);
        $pdf->SetXY($footerX, $footerY - 1.5);
        $pdf->SetFont('aealarabiya', 'B', 10.5);
        $pdf->Cell($footerW, 5, (string) config('report.company_name_en', 'GIS VALUATION AND EVALUATION'), 0, 1, 'R');
        $pdf->SetXY($footerX, $footerY + 3);
        $pdf->SetFont('aealarabiya', 'B', 10.5);
        $pdf->writeHTMLCell($footerW, 5, $footerX, $footerY + 3, '<p style="text-align:right;font-family:aealarabiya;font-size:10.5pt;color:#b91c1c;font-weight:bold;">'.htmlspecialchars((string) config('report.company_name_ar', 'جي إي إس للتقييم والتثمين العقاري')).'</p>', 0, 1, false, true, 'R');
        $pdf->SetXY($footerX, $footerY + 7.5);
        $pdf->SetFont('aealarabiya', '', 8);
        $pdf->Cell($footerW, 5, (string) config('report.cr_number', 'C.R. 160528-1'), 0, 1, 'R');
    }

    private function resolveLogoPath(): ?string
    {
        $candidates = array_filter([
            config('report.logo_path'),
            'images/company-logo.png',
            'images/logo.png',
        ]);

        foreach ($candidates as $candidate) {
            $path = $this->resolveReportAssetPath($candidate);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array{w: float, h: float}
     */
    private function logoBoxMmForPdf(?string $logoPath): array
    {
        $logoW = max(18.0, min(40.0, (float) config('report.logo_width_mm', 30)));
        $logoH = 14.0;

        if ($logoPath && is_readable($logoPath)) {
            $size = @getimagesize($logoPath);
            if ($size && $size[0] > 0 && $size[1] > 0) {
                $logoH = max(10.0, min(22.0, $logoW * ($size[1] / $size[0])));
            }
        }

        return ['w' => $logoW, 'h' => $logoH];
    }

    /**
     * نسخة أوضح للشعار داخل PDF (تكبير PNG الصغير قبل التضمين).
     */
    private function prepareLogoPathForPdf(?string $logoPath): ?string
    {
        if ($logoPath === null || ! is_readable($logoPath)) {
            return null;
        }

        $info = @getimagesize($logoPath);
        if (! $info) {
            return $logoPath;
        }

        [$wPx, $hPx, $type] = $info;
        $minW = max(200, (int) config('report.logo_embed_min_width', 420));

        if ($wPx >= $minW || ! extension_loaded('gd')) {
            return $logoPath;
        }

        $scale = $minW / max(1, $wPx);
        $newW = (int) round($wPx * $scale);
        $newH = (int) round($hPx * $scale);

        $cacheDir = storage_path('app/reports/logo-cache');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $cachePath = $cacheDir.DIRECTORY_SEPARATOR.'logo-'.md5($logoPath.'|'.filemtime($logoPath).'|'.$newW).'.png';
        if (is_file($cachePath) && filesize($cachePath) > 100) {
            return $cachePath;
        }

        try {
            $src = match ($type) {
                IMAGETYPE_PNG => @imagecreatefrompng($logoPath),
                IMAGETYPE_JPEG => @imagecreatefromjpeg($logoPath),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($logoPath) : false,
                default => false,
            };

            if (! $src) {
                return $logoPath;
            }

            $dst = imagecreatetruecolor($newW, $newH);
            if (! $dst) {
                imagedestroy($src);

                return $logoPath;
            }

            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            if ($transparent !== false) {
                imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $wPx, $hPx);
            imagedestroy($src);

            imagealphablending($dst, true);
            imagesavealpha($dst, true);
            imagepng($dst, $cachePath, 6);
            imagedestroy($dst);

            return is_file($cachePath) ? $cachePath : $logoPath;
        } catch (Throwable) {
            return $logoPath;
        }
    }

    /**
     * يقبل: مسار مطلق على السيرفر، أو مسار نسبي داخل public (بدون بداية /)، أو مسار نسبي من جذر المشروع.
     */
    private function resolveReportAssetPath(?string $candidate): ?string
    {
        if ($candidate === null) {
            return null;
        }
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        if (is_file($candidate)) {
            return $candidate;
        }

        $publicPath = public_path($candidate);
        if (is_file($publicPath)) {
            return $publicPath;
        }

        $basePath = base_path($candidate);
        if (is_file($basePath)) {
            return $basePath;
        }

        return null;
    }

    /**
     * تحويل قيمة memory_limit إلى bytes للمقارنة
     */
    private function parseMemoryLimit(string $val): int
    {
        $val = trim($val);
        $last = strtolower(substr($val, -1));
        $num = (int) $val;
        return match ($last) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    /**
     * Draws a self-contained WhatsApp-style icon (green circle + phone glyph).
     */
    private function drawWhatsappIcon(TCPDF $pdf, float $x, float $y, float $size): void
    {
        try {
            $cx = $x + $size / 2;
            $cy = $y + $size / 2;
            $r = $size / 2;

            $pdf->SetFillColor(37, 211, 102);
            $pdf->SetDrawColor(37, 211, 102);
            $pdf->SetLineWidth(0.05);
            $pdf->Circle($cx, $cy, $r, 0, 360, 'F');

            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(255, 255, 255);
            $pdf->SetLineWidth(0.18);

            $hr = $r * 0.55;
            $hx = $cx - $hr * 0.45;
            $hy = $cy - $hr * 0.15;

            $pdf->Circle($hx, $hy, $hr * 0.6, 200, 70, 'D');
            $pdf->Line($hx + $hr * 0.55, $hy + $hr * 0.55, $cx + $hr * 0.45, $cy + $hr * 0.7);
        } catch (Throwable) {
        }
    }

    /**
     * Draws a self-contained envelope-style email icon.
     */
    private function drawEmailIcon(TCPDF $pdf, float $x, float $y, float $size): void
    {
        try {
            $w = $size;
            $h = $size * 0.72;
            $top = $y + ($size - $h) / 2;

            $pdf->SetFillColor(220, 38, 38);
            $pdf->SetDrawColor(220, 38, 38);
            $pdf->SetLineWidth(0.08);
            $pdf->Rect($x, $top, $w, $h, 'DF');

            $pdf->SetDrawColor(255, 255, 255);
            $pdf->SetLineWidth(0.22);
            $pdf->Line($x, $top, $x + $w / 2, $top + $h * 0.55);
            $pdf->Line($x + $w, $top, $x + $w / 2, $top + $h * 0.55);
        } catch (Throwable) {
        }
    }

    /**
     * Cleans mixed Arabic/English text while avoiding visible control marks.
     */
    private function wrapMixedTextForRtl(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\)\)+/u', '))', $text) ?? $text;
        $text = preg_replace('/\(\(+/u', '((', $text) ?? $text;

        $lines = explode("\n", $text);
        foreach ($lines as $idx => $line) {
            $line = trim($line);
            if ($line === '') {
                $lines[$idx] = '';
                continue;
            }
            $lines[$idx] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * تحويل النص العربي بشكل صحيح لـ TCPDF Cell/MultiCell  
     */
    private function reshapeArabic(string $text, TCPDF $pdf): string
    {
        if (!class_exists('TCPDF_FONTS')) {
            return $text;
        }
        try {
            $unicode = [];
            $glyphs = TCPDF_FONTS::UTF8StringToArray($text, false, [], $unicode);
            $glyphs = TCPDF_FONTS::utf8Bidi($glyphs, $text, true, $unicode, []);
            return TCPDF_FONTS::UTF8ArrSubString($glyphs, '', '', $unicode);
        } catch (\Throwable) {
            return $text;
        }
    }
}