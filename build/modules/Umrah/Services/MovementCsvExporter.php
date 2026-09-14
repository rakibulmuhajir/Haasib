<?php

namespace App\Modules\Umrah\Services;

/** Serializes only the already-authorized screen/PDF projection; never queries raw records. */
class MovementCsvExporter
{
    public function export(array $report, string $companyName): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to prepare movement export.');
        }
        try {
            // Excel recognizes Unicode passenger/company names with a UTF-8 BOM.
            fwrite($stream, "\xEF\xBB\xBF");
            $write = function (array $row) use ($stream): void {
                $safe = array_map($this->safeCell(...), $row);
                if (fputcsv($stream, $safe, ',', '"', '', "\r\n") === false) {
                    throw new \RuntimeException('Unable to write movement export.');
                }
            };
            $write(['Movement Report', $companyName]);
            $write(['Period', $report['period_label']]);
            $write(['Generated', $report['generated_at']]);
            $write(['Times', 'Local clock at the event location; blank means time not specified.']);
            $write(['Summary scope', 'Selected date window and permitted agent; before event/readiness filters.']);
            $write(['Measure', 'Value', 'Unit']);
            foreach ($report['summary'] as $item) {
                $write([$item['label'], $item['value'], $item['key'] === 'needs_attention' ? 'Events' : 'People scheduled']);
            }
            if (! $report['shows_details']) {
                $write(['Access', 'Movement totals only; passenger and operational details are restricted.']);

                rewind($stream);

                return stream_get_contents($stream);
            }
            $filters = $report['filters'];
            $write([]);
            $write(['Event filter', $report['event_types'][$filters['event_type']] ?? 'All events']);
            $write(['Readiness filter', $report['readiness_options'][$filters['readiness']]]);
            $write(['Counting', 'One row per event. Passenger movements may include the same person on several events.']);
            $write(['Date', 'Local time', 'Event', 'From', 'To', 'Location', 'Movement', 'Flight', 'Airport',
                'Hotel', 'City', 'Room type', 'Rooms', 'Pax movements', 'Readiness', 'Needs attention',
                'Voucher', 'Group', 'Agent', 'Transport provider', 'Vehicle', 'Vehicles', 'Capacity',
                'Driver', 'Driver phone', 'Terminal', 'Passengers', 'Passports', 'Nationalities']);
            foreach ($report['events'] as $event) {
                $transport = $event['transport'] ?? [];
                $passengers = $event['passengers'] ?? [];
                $write([$event['scheduled_date'], $event['is_all_day'] ? '' : ($event['scheduled_time'] ?? ''),
                    $event['type_label'], $event['origin'], $event['destination'], $event['location'], $event['headline'],
                    $event['flight'], $event['airport'], $event['hotel'], $event['city'], $event['room_type'] ?? '',
                    $event['room_count'] ?? '', $event['passenger_count'], $event['readiness_label'],
                    implode('; ', $event['readiness_issues']), $event['voucher']['number'] ?? '',
                    $event['group']['number'] ?? '', $event['agent']['name'] ?? '', $transport['provider'] ?? '',
                    $transport['vehicle'] ?? '', $transport['quantity'] ?? '', $transport['capacity'] ?? '',
                    $transport['driver'] ?? '', $transport['driver_phone'] ?? '', $transport['terminal'] ?? '',
                    implode("\n", array_column($passengers, 'name')),
                    implode("\n", array_map(fn ($p) => $p['passport'] ?? '', $passengers)),
                    implode("\n", array_map(fn ($p) => $p['nationality'] ?? '', $passengers))]);
            }
            $write([]);
            $write(['Filtered event count', count($report['events'])]);
            $write(['Filtered passenger movements (not unique travellers)', array_sum(array_column($report['events'], 'passenger_count'))]);
            if (count($report['events']) === 0) {
                $write(['Result', 'No events match the selected report filters.']);
            }
            rewind($stream);

            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    private function safeCell(mixed $value): string
    {
        $text = str_replace("\0", '', (string) ($value ?? ''));

        // CSV quoting alone does not stop Excel evaluating untrusted formulas.
        return preg_match('/^[\s\p{Z}\x{FEFF}]*[=+@-]|^[\t\r\n]/u', $text) ? "'".$text : $text;
    }
}
