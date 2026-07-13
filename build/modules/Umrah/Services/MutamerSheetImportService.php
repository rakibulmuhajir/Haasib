<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\Agent;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class MutamerSheetImportService
{
    private const REQUIRED_HEADERS = [
        'mutamer name' => 'full_name',
        'mutamer age' => 'imported_age',
        'passport number' => 'passport_number',
        'nationality' => 'nationality',
    ];

    public function import(UploadedFile $file): array
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages([
                'mutamers_file' => 'The selected file could not be opened as an Excel workbook.',
            ]);
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheetPath = $this->firstWorksheetPath($zip);
            $rows = $this->worksheetRows($zip, $sheetPath, $sharedStrings);
        } finally {
            $zip->close();
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'mutamers_file' => 'The selected workbook does not contain any mutamer rows.',
            ]);
        }

        $headerRow = array_shift($rows);
        $headerMap = $this->headerMap($headerRow);
        $missing = array_diff(array_keys(self::REQUIRED_HEADERS), array_keys($headerMap));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'mutamers_file' => 'This does not look like a Go VT mutamers export. Missing columns: '.implode(', ', $missing).'.',
            ]);
        }

        $mutamers = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row[$headerMap['mutamer name']] ?? ''));

            if ($name === '') {
                continue;
            }

            $mutamers[] = [
                'full_name' => $name,
                'passport_number' => trim((string) ($row[$headerMap['passport number']] ?? '')),
                'imported_age' => $this->age($row[$headerMap['mutamer age']] ?? null),
                'date_of_birth' => null,
                'service_type' => 'visa_transport',
                'transport_charge_amount' => 0,
                'nationality' => $this->nationality($row[$headerMap['nationality']] ?? null),
                'visa_status' => 'received',
            ];
        }

        if ($mutamers === []) {
            throw ValidationException::withMessages([
                'mutamers_file' => 'No mutamers were found in the selected workbook.',
            ]);
        }

        return $mutamers;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $dom = $this->dom($xml);
        $xpath = $this->xpath($dom);
        $strings = [];

        foreach ($xpath->query('//x:si') ?: [] as $item) {
            $text = '';
            foreach ($xpath->query('.//x:t', $item) ?: [] as $node) {
                $text .= $node->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $rels === false) {
            throw ValidationException::withMessages([
                'mutamers_file' => 'The selected workbook is missing its worksheet metadata.',
            ]);
        }

        $workbookDom = $this->dom($workbook);
        $workbookXpath = $this->xpath($workbookDom);
        $firstSheet = $workbookXpath->query('//x:sheets/x:sheet')->item(0);

        if (! $firstSheet instanceof DOMElement) {
            throw ValidationException::withMessages([
                'mutamers_file' => 'The selected workbook does not contain a worksheet.',
            ]);
        }

        $relationId = $firstSheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
        $relsDom = $this->dom($rels);
        $relsXpath = new DOMXPath($relsDom);

        foreach ($relsXpath->query('//*[local-name() = "Relationship"]') ?: [] as $relationship) {
            if (! $relationship instanceof DOMElement || $relationship->getAttribute('Id') !== $relationId) {
                continue;
            }

            $target = ltrim($relationship->getAttribute('Target'), '/');
            return str_starts_with($target, 'xl/') ? $target : "xl/{$target}";
        }

        throw ValidationException::withMessages([
            'mutamers_file' => 'The first worksheet could not be located.',
        ]);
    }

    private function worksheetRows(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $xml = $zip->getFromName($sheetPath);
        if ($xml === false) {
            throw ValidationException::withMessages([
                'mutamers_file' => 'The first worksheet could not be read.',
            ]);
        }

        $dom = $this->dom($xml);
        $xpath = $this->xpath($dom);
        $rows = [];

        foreach ($xpath->query('//x:sheetData/x:row') ?: [] as $rowNode) {
            $row = [];

            foreach ($xpath->query('./x:c', $rowNode) ?: [] as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                $columnIndex = $this->columnIndex($cell->getAttribute('r'));
                while (count($row) < $columnIndex) {
                    $row[] = '';
                }

                $row[] = $this->cellValue($xpath, $cell, $sharedStrings);
            }

            if (array_filter($row, fn ($value) => trim((string) $value) !== '') !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function cellValue(DOMXPath $xpath, DOMElement $cell, array $sharedStrings): string
    {
        $type = $cell->getAttribute('t');

        if ($type === 'inlineStr') {
            $text = '';
            foreach ($xpath->query('.//x:t', $cell) ?: [] as $node) {
                $text .= $node->textContent;
            }

            return trim($text);
        }

        $valueNode = $xpath->query('./x:v', $cell)->item(0);
        $value = $valueNode?->textContent ?? '';

        if ($type === 's' && $value !== '') {
            return trim((string) ($sharedStrings[(int) $value] ?? ''));
        }

        return trim($value);
    }

    private function headerMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $header) {
            $normalized = strtolower(trim(preg_replace('/\s+/', ' ', (string) $header) ?? ''));
            if (array_key_exists($normalized, self::REQUIRED_HEADERS)) {
                $map[$normalized] = $index;
            }
        }

        return $map;
    }

    private function age(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $age = (int) floor((float) $value);

        return $age >= 0 && $age <= 130 ? $age : null;
    }

    private function nationality(mixed $value): string
    {
        $nationality = trim((string) $value);

        foreach (Agent::COUNTRIES as $country => $label) {
            if (strcasecmp($country, $nationality) === 0 || strcasecmp($label, $nationality) === 0) {
                return $country;
            }
        }

        return 'Pakistan';
    }

    private function columnIndex(string $cellReference): int
    {
        preg_match('/^[A-Z]+/', $cellReference, $matches);
        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    private function dom(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET);

        return $dom;
    }

    private function xpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xpath;
    }
}
