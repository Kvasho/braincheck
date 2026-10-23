<?php

declare(strict_types=1);

use App\Support\SafeCsv;

it('neutralises cells that spreadsheets would run as formulas', function (string $input, string $expected): void {
    expect(SafeCsv::cell($input))->toBe($expected);
})->with([
    'equals' => ['=HYPERLINK("http://evil","x")', '\'=HYPERLINK("http://evil","x")'],
    'plus' => ['+1+1', "'+1+1"],
    'minus' => ['-2+3', "'-2+3"],
    'at' => ['@SUM(A1)', "'@SUM(A1)"],
    'tab' => ["\t=1", "'\t=1"],
    'carriage return' => ["\r=1", "'\r=1"],
    'plain text' => ['Nino Beridze', 'Nino Beridze'],
    'georgian' => ['ნინო', 'ნინო'],
    'formula char inside' => ['a=b', 'a=b'],
    'empty' => ['', ''],
]);

it('formats scalars and quotes per RFC 4180', function (): void {
    expect(SafeCsv::cell(null))->toBe('')
        ->and(SafeCsv::cell(true))->toBe('1')
        ->and(SafeCsv::cell(12))->toBe('12')
        ->and(SafeCsv::line(['a,b', 'say "hi"', '=x']))->toBe("\"a,b\",\"say \"\"hi\"\"\",'=x\n");
});

it('starts with a UTF-8 BOM', function (): void {
    $csv = SafeCsv::document(['Name'], [['ნინო']]);

    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and(substr($csv, 3))->toBe("Name\nნინო\n");
});
