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
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages([
                'mutamers_file' => 'The selected file could not be opened as an Excel workbook.',
            ]);
        }

        try {
            $expanded = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $expanded += $zip->statIndex($i)['size'] ?? 0;
            }
            if ($zip->numFiles > 2000 || $expanded > 20 * 1024 * 1024) {
                throw ValidationException::withMessages(['mutamers_file' => 'This workbook is too large when unpacked. Export a list of at most 500 passengers.']);
            }
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

        $headerMap = [];
        $headerIndex = null;
        foreach (array_slice($rows, 0, 20, true) as $index => $row) {
            $candidate = $this->headerMap($row);
            if (count($candidate) === count(self::REQUIRED_HEADERS)) {
                $headerMap = $candidate;
                $headerIndex = $index;
                break;
            }
        }
        $missing = array_diff(array_keys(self::REQUIRED_HEADERS), array_keys($headerMap));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'mutamers_file' => 'This does not look like a mutamer list export. Missing columns: '.implode(', ', $missing).'.',
            ]);
        }

        $mutamers = [];
        $seen = [];
        foreach (array_slice($rows, ($headerIndex ?? 0) + 1) as $row) {
            $name = trim((string) ($row[$headerMap['mutamer name']] ?? ''));
            $passport = trim((string) ($row[$headerMap['passport number']] ?? ''));
            $age = $row[$headerMap['mutamer age']] ?? '';
            $errors = [];
            $nationality = $this->nationality($row[$headerMap['nationality']] ?? null);
            if (! array_key_exists($nationality, Agent::COUNTRIES)) {
                $errors[] = 'Nationality is missing or not recognized. Correct it in the workbook.';
            }
            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = 'Name is required and must be at most 255 characters.';
            }
            if ($passport === '' || mb_strlen($passport) > 100) {
                $errors[] = 'Passport is required and must be at most 100 characters.';
            }
            if ($age === '' || ! is_numeric($age) || (float) $age < 0 || (float) $age > 130 || floor((float) $age) != (float) $age) {
                $errors[] = 'Age must be a whole number from 0 to 130.';
            }
            $key = mb_strtoupper(preg_replace('/\s+/u', '', $passport));
            if ($key !== '' && isset($seen[$key])) {
                $errors[] = 'Duplicate passport; first appears on row '.$seen[$key].'.';
            }
            if ($key !== '') {
                $seen[$key] ??= $row['_source_row'];
            }
            $mutamers[] = [
                'source_row' => $row['_source_row'],
                'errors' => $errors,
                'full_name' => $name,
                'passport_number' => $passport,
                'imported_age' => $this->age($row[$headerMap['mutamer age']] ?? null),
                'date_of_birth' => null,
                'service_type' => 'visa_transport',
                'transport_charge_amount' => 0,
                'nationality' => $nationality,
                'visa_status' => 'received',
            ];
        }

        if (count($mutamers) > 500) {
            throw ValidationException::withMessages(['mutamers_file' => 'Import at most 500 passengers per group. Split this workbook into smaller lists.']);
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
                if ($columnIndex > 100) {
                    continue;
                }
                $row[$columnIndex] = $this->cellValue($xpath, $cell, $sharedStrings);
            }

            if (array_filter($row, fn ($value) => trim((string) $value) !== '') !== []) {
                $row['_source_row'] = (int) $rowNode->getAttribute('r');
                $rows[] = $row;
                if (count($rows) > 520) {
                    throw ValidationException::withMessages(['mutamers_file' => 'Import at most 500 passengers per group.']);
                }
            }
        }

        return $rows;
    }

    private function cellValue(DOMXPath $xpath, DOMElement $cell, array $sharedStrings): string
    {
        if ($xpath->query('./x:f', $cell)->length > 0) {
            throw ValidationException::withMessages(['mutamers_file' => 'The workbook contains formulas. Export passenger values only, then upload again.']);
        }
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
        if ($value === null || $value === '' || ! is_numeric($value) || floor((float) $value) != (float) $value) {
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

        return $nationality;
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
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw ValidationException::withMessages(['mutamers_file' => 'The workbook contains unsupported XML declarations.']);
        }
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $dom->loadXML($xml, LIBXML_NONET)) {
                throw ValidationException::withMessages(['mutamers_file' => 'The workbook contains damaged worksheet data. Export it again.']);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $dom;
    }

    private function xpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xpath;
    }
}
