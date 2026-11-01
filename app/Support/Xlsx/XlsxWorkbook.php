<?php

namespace App\Support\Xlsx;

/**
 * Minimal native .xlsx writer for the standard Brooks Agro report format.
 *
 * No extensions or packages needed (the zip container is written by ZipBuilder), so it
 * works on the local Laragon PHP and in the Docker image alike.
 *
 * A sheet is a title block followed by one or more tables ("sections"):
 *   ['name' => 'Sheet', 'title' => ..., 'subtitle' => ..., 'meta' => [...lines],
 *    'sections' => [['heading' => ?string, 'headers' => [...], 'rows' => [...]]]]
 * A row is a list of cells, or ['cells' => [...], 'style' => detail|heading|subtotal|total|percent].
 * Numbers stay numbers: ints/floats, "UGX 1,250", "1,250.50" and "12.5%" become numeric cells.
 */
class XlsxWorkbook
{
    /** Brand colours (ARGB without #), matching public/css/app.css tokens. */
    public const MAROON = '9B292D';
    public const MAROON_TINT = 'F8E9E6';
    public const GOLD_TINT = 'FFF1DF';
    public const INK = '171310';
    public const INK_SOFT = '806D5E';
    public const LINE = 'E8DED2';

    // Style ids (order must match styles()).
    private const S_DEFAULT = 0, S_COMPANY = 1, S_TITLE = 2, S_META = 3, S_HEADER = 4, S_TEXT = 5,
        S_INT = 6, S_MONEY = 7, S_DEC = 8, S_PCT = 9, S_SECTION = 10, S_SUB_TEXT = 11, S_SUB_NUM = 12,
        S_TOTAL_TEXT = 13, S_TOTAL_NUM = 14, S_HEADING = 15, S_SUB_DEC = 16, S_TOTAL_DEC = 17, S_PLAIN = 18, S_HEADER_RIGHT = 19;

    private array $sheets = [];

    public function addSheet(array $sheet): static
    {
        $this->sheets[] = $sheet;
        return $this;
    }

    public function toString(): string
    {
        $zip = new ZipBuilder();
        $names = [];
        foreach ($this->sheets as $i => $sheet) {
            $names[] = $this->sheetName($sheet['name'] ?? ('Report '.($i + 1)), $names);
        }
        $zip->add('[Content_Types].xml', $this->contentTypes(count($this->sheets)));
        $zip->add('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->add('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.$this->x($this->sheets[0]['title'] ?? 'Report').'</dc:title><dc:creator>Brooks Agro Farm ERP</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>');
        $zip->add('xl/workbook.xml', $this->workbookXml($names));
        $zip->add('xl/_rels/workbook.xml.rels', $this->workbookRels(count($this->sheets)));
        $zip->add('xl/styles.xml', $this->styles());
        foreach ($this->sheets as $i => $sheet) {
            $zip->add('xl/worksheets/sheet'.($i + 1).'.xml', $this->sheetXml($sheet));
        }
        return $zip->toString();
    }

    private function sheetXml(array $sheet): string
    {
        $sections = $sheet['sections'] ?? [];
        $columns = max(1, ...array_map(fn ($s) => max(count($s['headers'] ?? []), ...array_map(fn ($r) => count($this->cellsOf($r)), $s['rows'] ?: [[]])), $sections ?: [['headers' => [], 'rows' => []]]));
        $rows = [];
        $merges = [];
        $widths = array_fill(0, $columns, 10);
        $r = 0;
        $line = function (array $cells) use (&$rows, &$r) { $rows[] = [++$r, $cells]; return $r; };

        // Title block
        $merge = function ($row) use (&$merges, $columns) {
            if ($columns > 1) $merges[] = 'A'.$row.':'.$this->col($columns - 1).$row;
        };
        $merge($line([[self::S_COMPANY, 'BROOKS AGRO FARM LIMITED']]));
        $merge($line([[self::S_TITLE, (string) ($sheet['title'] ?? 'Report')]]));
        if (! empty($sheet['subtitle'])) $merge($line([[self::S_META, (string) $sheet['subtitle']]]));
        foreach ($sheet['meta'] ?? [] as $meta) $merge($line([[self::S_META, (string) $meta]]));
        $r++;

        $freezeRow = null;
        $filterRange = null;
        foreach ($sections as $index => $section) {
            if (! empty($section['heading'])) {
                $merge($line([[self::S_HEADING, (string) $section['heading']]]));
            }
            if (! empty($section['headers'])) {
                $headerRow = $line(array_map(fn ($h) => [self::S_HEADER, (string) $h], array_values($section['headers'])));
                $headerIndex = count($rows) - 1;
                $numericCells = $textCells = [];
                foreach (array_values($section['headers']) as $c => $h) $widths[$c] = max($widths[$c], min(40, mb_strlen((string) $h) + 4));
                if (count($sections) === 1) { $freezeRow = $headerRow; }
            }
            $first = $r + 1;
            foreach ($section['rows'] ?? [] as $row) {
                $style = is_array($row) && array_key_exists('cells', $row) ? ($row['style'] ?? 'detail') : 'detail';
                $cells = [];
                foreach ($this->cellsOf($row) as $c => $value) {
                    [$kind, $num] = $this->classify($value, $style);
                    // Bare numbers: codes and years stay plain; amounts (outside the first column, 10,000+) get separators.
                    if ($kind === 'plain' && $c > 0 && abs((float) $num) >= 10000) $kind = is_float($num) && fmod($num, 1) != 0 ? 'dec' : 'int';
                    $cells[] = [$this->styleFor($style, $kind === 'empty' ? 'text' : $kind), $kind === 'empty' ? null : ($num ?? $value)];
                    if ($num !== null) $numericCells[$c] = ($numericCells[$c] ?? 0) + 1;
                    if ($kind === 'text' && trim((string) $value) !== '') $textCells[$c] = ($textCells[$c] ?? 0) + 1;
                    $shown = $num !== null ? strlen(number_format(abs((float) $num))) + 4 : mb_strlen((string) $value) + 2;
                    if (isset($widths[$c])) $widths[$c] = max($widths[$c], min(60, $shown));
                }
                if ($style === 'heading' || ($style === 'section')) {
                    $label = collect($this->cellsOf($row))->map(fn ($cell) => trim((string) $cell))->filter()->join('  ');
                    $cells = [[self::S_SECTION, $label]];
                    $merge($line($cells));
                    continue;
                }
                $line($cells);
            }
            // Right-align the header over columns that hold numbers.
            if (isset($headerIndex)) {
                foreach ($rows[$headerIndex][1] as $c => $cell) {
                    if (($numericCells[$c] ?? 0) > ($textCells[$c] ?? 0)) $rows[$headerIndex][1][$c][0] = self::S_HEADER_RIGHT;
                }
                unset($headerIndex);
            }
            if (count($sections) === 1 && ! empty($section['headers']) && $r >= $first) {
                $filterRange = 'A'.($first - 1).':'.$this->col(count($section['headers']) - 1).$r;
            }
            $r++;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0">';
        if ($freezeRow) $xml .= '<pane ySplit="'.$freezeRow.'" topLeftCell="A'.($freezeRow + 1).'" activePane="bottomLeft" state="frozen"/>';
        $xml .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="16"/><cols>';
        foreach ($widths as $c => $w) $xml .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.($c === 0 ? max($w, 14) : $w).'" customWidth="1"/>';
        $xml .= '</cols><sheetData>';
        foreach ($rows as [$number, $cells]) {
            $height = $number === 1 ? ' ht="22" customHeight="1"' : ($number === 2 ? ' ht="20" customHeight="1"' : '');
            $xml .= '<row r="'.$number.'"'.$height.'>';
            foreach ($cells as $c => [$style, $value]) {
                $ref = $this->col($c).$number;
                if ($value === null || $value === '') { $xml .= '<c r="'.$ref.'" s="'.$style.'"/>'; continue; }
                $xml .= is_int($value) || is_float($value)
                    ? '<c r="'.$ref.'" s="'.$style.'"><v>'.$this->num($value).'</v></c>'
                    : '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->x((string) $value).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ($filterRange) $xml .= '<autoFilter ref="'.$filterRange.'"/>';
        if ($merges) $xml .= '<mergeCells count="'.count($merges).'">'.implode('', array_map(fn ($m) => '<mergeCell ref="'.$m.'"/>', $merges)).'</mergeCells>';
        $xml .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>';
        $xml .= '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>';
        $xml .= '<headerFooter><oddFooter>&amp;L&amp;8Brooks Agro Farm ERP&amp;C&amp;8'.$this->x((string) ($sheet['title'] ?? '')).'&amp;R&amp;8Page &amp;P of &amp;N</oddFooter></headerFooter>';
        return $xml.'</worksheet>';
    }

    private function cellsOf($row): array
    {
        if (is_array($row) && array_key_exists('cells', $row)) return array_values((array) $row['cells']);
        return array_values((array) $row);
    }

    /** @return array{0:string,1:int|float|null} kind: text|int|money|dec|pct */
    private function classify($value, string $style): array
    {
        if (is_int($value)) return ['int', $value];
        if (is_float($value)) return [$style === 'percent' ? 'pct' : (floor($value) == $value ? 'money' : 'dec'), $style === 'percent' ? $value / 100 : $value];
        if (! is_string($value)) return ['text', null];
        $v = trim($value);
        if (in_array($v, ['—', '–', '-', 'N/A'], true)) return ['empty', null];
        // Money and counts written as text: "UGX 1,250", "UGX (200,000)", "-5,000", "1,250.50".
        $currency = (bool) preg_match('/^(UGX|USD|Shs?\.?)\s*/i', $v, $cm);
        $body = $currency ? trim(substr($v, strlen($cm[0]))) : $v;
        $negative = false;
        if (str_starts_with($body, '(') && str_ends_with($body, ')')) { $negative = true; $body = trim(substr($body, 1, -1)); }
        if (str_starts_with($body, '-')) { $negative = true; $body = ltrim(substr($body, 1)); }
        if (preg_match('/^[\d,]+(\.\d+)?$/', $body) && preg_match('/\d/', $body)) {
            if (preg_match('/^0\d/', $body) || (! $currency && strlen(str_replace([',', '.'], '', $body)) > 11)) return ['text', null]; // phone numbers, codes
            $n = (float) str_replace(',', '', $body);
            if ($negative) $n = -$n;
            if (! $currency && ! str_contains($body, ',')) return ['plain', str_contains($body, '.') ? $n : (int) $n];
            $kind = $currency ? (str_contains($body, '.') && fmod($n, 1) != 0 ? 'dec' : 'money') : (str_contains($body, '.') ? 'dec' : 'int');
            return [$kind, $kind === 'int' || $kind === 'money' && fmod($n, 1) == 0 ? (int) $n : $n];
        }
        if (preg_match('/^(-?[\d,]+(?:\.\d+)?)\s*%$/', $v, $m)) return ['pct', (float) str_replace(',', '', $m[1]) / 100];
        return ['text', null];
    }

    private function styleFor(string $rowStyle, string $kind): int
    {
        $sub = in_array($rowStyle, ['subtotal'], true);
        $total = in_array($rowStyle, ['total', 'grand-total'], true);
        if ($kind === 'text') return $total ? self::S_TOTAL_TEXT : ($sub ? self::S_SUB_TEXT : self::S_TEXT);
        if ($kind === 'pct') return self::S_PCT;
        if ($kind === 'plain') return $total || $sub ? ($total ? self::S_TOTAL_TEXT : self::S_SUB_TEXT) : self::S_PLAIN;
        if ($kind === 'dec') return $total ? self::S_TOTAL_DEC : ($sub ? self::S_SUB_DEC : self::S_DEC);
        return $total ? self::S_TOTAL_NUM : ($sub ? self::S_SUB_NUM : ($kind === 'money' ? self::S_MONEY : self::S_INT));
    }

    private function styles(): string
    {
        $fonts = '<fonts count="7">'
            .'<font><sz val="10"/><color rgb="FF'.self::INK.'"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF'.self::MAROON.'"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="12"/><color rgb="FF'.self::INK.'"/><name val="Calibri"/></font>'
            .'<font><sz val="9"/><color rgb="FF'.self::INK_SOFT.'"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FF'.self::INK.'"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF'.self::MAROON.'"/><name val="Calibri"/></font>'
            .'</fonts>';
        $fills = '<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.self::MAROON.'"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.self::GOLD_TINT.'"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF'.self::MAROON_TINT.'"/></patternFill></fill></fills>';
        $borders = '<borders count="3"><border/>'
            .'<border><bottom style="thin"><color rgb="FF'.self::LINE.'"/></bottom></border>'
            .'<border><top style="thin"><color rgb="FF'.self::MAROON.'"/></top><bottom style="double"><color rgb="FF'.self::MAROON.'"/></bottom></border></borders>';
        $numFmts = '<numFmts count="3"><numFmt numFmtId="164" formatCode="#,##0;[Red]-#,##0"/><numFmt numFmtId="165" formatCode="#,##0.00;[Red]-#,##0.00"/><numFmt numFmtId="166" formatCode="0.0%"/></numFmts>';
        $xf = fn ($num, $font, $fill, $border, $align = '') => '<xf numFmtId="'.$num.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"'.($align ? ' applyAlignment="1">'.$align.'</xf>' : '/>');
        $right = '<alignment horizontal="right" vertical="top"/>';
        $wrap = '<alignment vertical="top" wrapText="1"/>';
        $cellXfs = [
            $xf(0, 0, 0, 0),                                  // 0 default
            $xf(0, 1, 0, 0),                                  // 1 company
            $xf(0, 2, 0, 0),                                  // 2 title
            $xf(0, 3, 0, 0),                                  // 3 meta
            $xf(0, 4, 2, 0, '<alignment vertical="center" wrapText="1"/>'), // 4 header
            $xf(0, 0, 0, 1, $wrap),                           // 5 text
            $xf(164, 0, 0, 1, $right),                        // 6 int
            $xf(164, 0, 0, 1, $right),                        // 7 money
            $xf(165, 0, 0, 1, $right),                        // 8 decimal
            $xf(166, 0, 0, 1, $right),                        // 9 percent
            $xf(0, 5, 3, 1),                                  // 10 section row
            $xf(0, 5, 4, 1, $wrap),                           // 11 subtotal text
            $xf(164, 5, 4, 1, $right),                        // 12 subtotal number
            $xf(0, 5, 0, 2, $wrap),                           // 13 total text
            $xf(164, 5, 0, 2, $right),                        // 14 total number
            $xf(0, 6, 0, 0),                                  // 15 table heading
            $xf(165, 5, 4, 1, $right),                        // 16 subtotal decimal
            $xf(165, 5, 0, 2, $right),                        // 17 total decimal
            $xf(0, 0, 0, 1, $right),                          // 18 plain number (years, counts, IDs)
            $xf(0, 4, 2, 0, '<alignment horizontal="right" vertical="center" wrapText="1"/>'), // 19 header over numbers
        ];
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$numFmts.$fonts.$fills.$borders
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($cellXfs).'">'.implode('', $cellXfs).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function contentTypes(int $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        for ($i = 1; $i <= $sheets; $i++) $xml .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        return $xml.'</Types>';
    }

    private function workbookXml(array $names): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($names as $i => $name) $xml .= '<sheet name="'.$this->x($name).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        return $xml.'</sheets></workbook>';
    }

    private function workbookRels(int $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $sheets; $i++) $xml .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        return $xml.'<Relationship Id="rId'.($sheets + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function sheetName(string $name, array $taken): string
    {
        $name = mb_substr(trim(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', $name)) ?: 'Report', 0, 31);
        $base = $name;
        for ($i = 2; in_array(mb_strtolower($name), array_map('mb_strtolower', $taken), true); $i++) $name = mb_substr($base, 0, 27).' ('.$i.')';
        return $name;
    }

    private function col(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) $name = chr(65 + (($n - 1) % 26)).$name;
        return $name;
    }

    private function num($value): string
    {
        return is_float($value) ? rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') : (string) $value;
    }

    private function x(string $value): string
    {
        // Strip characters XML 1.0 does not allow, then escape.
        return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
