<?php

namespace App\Services;

use App\Models\NoteCategory;
use App\Models\NoteTemplate;
use App\Models\PropertyHouse;

class InspectionReportWordGenerator
{
    public function renderHtml(PropertyHouse $house): string
    {
        $house->load([
            'inspectionAreas.photos' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        $categories = NoteCategory::query()
            ->with(['templates' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => trim((string) $c->name),
                'templates' => $c->templates->pluck('text')->filter()->values()->all(),
            ])
            ->values()
            ->all();

        $grouped = [];
        $seen = [];
        foreach ($categories as $category) {
            $grouped[$category['id']] = [];
            $seen[$category['id']] = [];
        }
        $otherKey = 'other';
        $grouped[$otherKey] = [];
        $seen[$otherKey] = [];

        foreach ($house->inspectionAreas as $area) {
            foreach ($area->photos as $photo) {
                foreach ($photo->notesEntries() as $entry) {
                    $text = trim((string) ($entry['text'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $catId = $this->resolveCategoryId($entry, $categories);
                    $key = ($catId !== null && isset($grouped[$catId])) ? $catId : $otherKey;
                    $dedupeKey = $this->normalizeForDedupe($text);
                    if (isset($seen[$key][$dedupeKey])) {
                        continue;
                    }
                    $seen[$key][$dedupeKey] = true;
                    $grouped[$key][] = $text;
                }

                $desc = trim((string) $photo->description);
                if ($desc !== '') {
                    $dedupeKey = $this->normalizeForDedupe($desc);
                    if (!isset($seen[$otherKey][$dedupeKey])) {
                        $seen[$otherKey][$dedupeKey] = true;
                        $grouped[$otherKey][] = $desc;
                    }
                }
            }
        }

        $reportDate = ($house->inspection_date ?: $house->created_at ?: now())->format('Y-m-d');
        $title = $house->title ?: ('House #' . $house->id);
        $client = trim((string) $house->client_name);
        $address = trim((string) $house->address);
        $refCode = trim((string) $house->reference_code);

        $html = '<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="UTF-8">';
        $html .= '<title>' . e($title) . '</title>';
        $html .= '<style>body{font-family:Tahoma,Arial,sans-serif;font-size:14px;line-height:1.9;color:#0f172a;background:#f8fafc;margin:24px}';
        $html .= '.paper{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px}.meta{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;margin-bottom:18px}';
        $html .= 'h1{font-size:26px;margin:0 0 10px;color:#065f46}h2{font-size:18px;margin:20px 0 8px;padding:8px 10px;background:#ecfeff;border-right:4px solid #0d9488;border-radius:8px}';
        $html .= '.meta div{margin:2px 0}ul{margin:8px 0 0;padding-right:22px}li{margin:6px 0}.empty{color:#64748b;font-style:italic;margin:6px 0 0}.count{color:#0f766e;font-size:12px;margin-right:4px}';
        $html .= '</style></head><body>';
        $html .= '<div class="paper">';
        $html .= '<h1>تقرير الملاحظات الكتابي</h1>';
        $html .= '<div class="meta">';
        $html .= '<div><strong>العقار:</strong> ' . e($title) . '</div>';
        $html .= '<div><strong>رقم التقرير:</strong> ' . e($refCode !== '' ? $refCode : ('H-' . $house->id)) . '</div>';
        $html .= '<div><strong>التاريخ:</strong> ' . e($reportDate) . '</div>';
        if ($client !== '') {
            $html .= '<div><strong>العميل:</strong> ' . e($client) . '</div>';
        }
        if ($address !== '') {
            $html .= '<div><strong>العنوان:</strong> ' . e($address) . '</div>';
        }
        $html .= '</div>';

        foreach ($categories as $category) {
            $notes = $grouped[$category['id']] ?? [];
            $html .= '<h2>' . e($category['name']) . ' <span class="count">(' . count($notes) . ')</span></h2>';
            if (empty($notes)) {
                $html .= '<p class="empty">لا توجد ملاحظات.</p>';
                continue;
            }
            $html .= '<ul>';
            foreach ($notes as $note) {
                $html .= '<li>' . e($note) . '</li>';
            }
            $html .= '</ul>';
        }

        $otherNotes = $grouped[$otherKey];
        $html .= '<h2>أخرى <span class="count">(' . count($otherNotes) . ')</span></h2>';
        if (empty($otherNotes)) {
            $html .= '<p class="empty">لا توجد ملاحظات.</p>';
        } else {
            $html .= '<ul>';
            foreach ($otherNotes as $note) {
                $html .= '<li>' . e($note) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div></body></html>';

        return $html;
    }

    private function normalizeForDedupe(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtolower($text);
    }

    /**
     * @param  array{text:string,category_id:int|null}  $entry
     * @param  array<int, array{id:int,name:string,templates:array<int,string>}>  $categories
     */
    private function resolveCategoryId(array $entry, array $categories): ?int
    {
        $catId = $entry['category_id'] ?? null;
        if (is_int($catId) && $catId > 0) {
            return $catId;
        }

        $text = $this->normalizeForDedupe((string) ($entry['text'] ?? ''));
        if ($text === '') {
            return null;
        }

        foreach ($categories as $category) {
            foreach ($category['templates'] as $template) {
                $template = $this->normalizeTemplateForMatch((string) $template);
                if ($template === '') {
                    continue;
                }
                if (str_contains($text, $template)) {
                    return (int) $category['id'];
                }
            }
        }

        return null;
    }

    private function normalizeTemplateForMatch(string $template): string
    {
        $template = str_replace(NoteTemplate::LOCATION_PLACEHOLDER, '', $template);
        $template = preg_replace('/\s+في\s+$/u', '', $template) ?? $template;

        return $this->normalizeForDedupe($template);
    }
}

