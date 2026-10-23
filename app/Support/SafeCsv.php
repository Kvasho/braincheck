<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CSV that is safe to open in spreadsheet programs:
 * - formula injection: a cell starting with =, +, -, @, tab or CR gets a
 *   leading apostrophe, so Excel / LibreOffice show it as text instead of
 *   running it (e.g. =HYPERLINK(...));
 * - UTF-8 with a BOM, so Excel shows Georgian correctly;
 * - RFC 4180 quoting via fputcsv.
 */
final class SafeCsv
{
    public const string BOM = "\xEF\xBB\xBF";

    private const array DANGEROUS_FIRST = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(string|int|float|bool|null $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };

        return $text !== '' && in_array($text[0], self::DANGEROUS_FIRST, true) ? "'".$text : $text;
    }

    /**
     * One encoded CSV line (with the line break).
     *
     * @param  list<string|int|float|bool|null>  $cells
     */
    public static function line(array $cells): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, array_map(self::cell(...), $cells), ',', '"', '');
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return $line;
    }

    /**
     * A whole document: BOM, header and rows.
     *
     * @param  list<string>  $header
     * @param  iterable<list<string|int|float|bool|null>>  $rows
     */
    public static function document(array $header, iterable $rows): string
    {
        $csv = self::BOM.self::line($header);

        foreach ($rows as $row) {
            $csv .= self::line($row);
        }

        return $csv;
    }
}
