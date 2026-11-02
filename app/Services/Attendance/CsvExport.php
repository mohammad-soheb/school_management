<?php

namespace App\Services\Attendance;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV downloads for attendance reports, safe to open in Excel / Sheets.
 *
 * Formula injection: a cell whose text starts with = + - @ (or a tab / carriage return) would be run as a formula by
 * a spreadsheet, so such cells are prefixed with an apostrophe. Plain numbers (ints / floats) are written as numbers.
 */
final class CsvExport
{
    public static function cell(string|int|float|bool|null $value): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * The CSV text (UTF-8 with BOM so Excel shows Hindi names correctly).
     *
     * @param  iterable<list<string|int|float|bool|null>>  $rows
     */
    public static function toString(iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, array_map(self::cell(...), $row), ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /** @param  array{filename: string, rows: iterable<list<string|int|float|bool|null>>}  $export */
    public static function download(array $export): StreamedResponse
    {
        $csv = self::toString($export['rows']);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $export['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
