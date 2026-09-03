<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trình đọc/ghi XLSX nhẹ, không phụ thuộc Composer.
 * Chỉ xử lý các kiểu dữ liệu mà file nhân sự Company Trip sử dụng.
 */
final class MAC_XLSX {
    public static function read_first_sheet(string $path) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('xlsx_unavailable', 'Máy chủ chưa bật ZipArchive nên không thể đọc XLSX.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return new WP_Error('invalid_xlsx', 'Không mở được file XLSX.');
        }
        // Bản đồ tên entry không phân biệt HOA/thường: Excel/WPS/Google Sheets có bản ghi
        // "xl/SharedStrings.xml" khác hoa thường, đọc đúng tên sẽ hụt sharedStrings khiến mọi ô chữ thành rỗng.
        $entries = array();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry_name = $zip->getNameIndex($i);
            if ($entry_name === false) continue;
            if (substr($entry_name, -1) === '/') continue;
            $entries[strtolower($entry_name)] = $entry_name;
        }
        $get = static function(string $want) use ($zip, $entries) {
            $actual = $entries[strtolower($want)] ?? null;
            if ($actual === null) return false;
            return $zip->getFromName($actual);
        };
        $shared = self::shared_strings($get);
        $result = null;
        foreach (self::sheet_paths($zip, $get, $entries) as $sheet_path) {
            $xml_text = $zip->getFromName($sheet_path);
            if ($xml_text === false) continue;
            $parsed = self::parse_sheet((string) $xml_text, $shared);
            if (is_wp_error($parsed)) continue;
            if (self::sheet_has_content($parsed)) {
                $result = $parsed;
                break;
            }
            if ($result === null) $result = $parsed;
        }
        $zip->close();
        if ($result === null) {
            return new WP_Error('invalid_xlsx', 'File XLSX không có worksheet hợp lệ.');
        }
        return $result;
    }

    public static function output(string $filename, array $sheets): void {
        if (!class_exists('ZipArchive')) {
            wp_die('Máy chủ chưa bật ZipArchive nên không thể tạo XLSX.');
        }
        $tmp = wp_tempnam($filename);
        if (!$tmp) wp_die('Không tạo được file XLSX tạm.');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            wp_die('Không tạo được file XLSX.');
        }
        $sheet_count = count($sheets);
        $zip->addFromString('[Content_Types].xml', self::content_types($sheet_count));
        $zip->addFromString('_rels/.rels', self::root_rels());
        $zip->addFromString('docProps/app.xml', self::app_xml($sheets));
        $zip->addFromString('docProps/core.xml', self::core_xml());
        $zip->addFromString('xl/workbook.xml', self::workbook_xml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbook_rels($sheet_count));
        $zip->addFromString('xl/styles.xml', self::styles_xml());
        foreach (array_values($sheets) as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($index + 1) . '.xml', self::sheet_xml($sheet));
        }
        $zip->close();
        nocache_headers();
        // Tên file tiếng Việt: filename* RFC 5987 cho browser hiện đại, filename ASCII làm dự phòng.
        $safe = str_replace(array('"', "\\", "\r", "\n"), '', $filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($safe) . '"; filename*=UTF-8\'\'' . rawurlencode($safe));
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private static function xml(string $text) {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($text, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $xml;
    }

    private static function shared_strings(callable $get): array {
        $text = $get('xl/sharedStrings.xml');
        if ($text === false) return array();
        $xml = self::xml((string) $text);
        if (!$xml) return array();
        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = array();
        foreach ($xml->xpath('//x:si') ?: array() as $item) {
            $value = '';
            foreach ($item->xpath('.//x:t') ?: array() as $part) $value .= (string) $part;
            $values[] = $value;
        }
        return $values;
    }

    /** Danh sách đường dẫn worksheet theo đúng thứ tự tab trong workbook. */
    private static function sheet_paths(ZipArchive $zip, callable $get, array $entries): array {
        $paths = array();
        $workbook_text = $get('xl/workbook.xml');
        $rels_text = $get('xl/_rels/workbook.xml.rels');
        if ($workbook_text !== false && $rels_text !== false) {
            $workbook = self::xml((string) $workbook_text);
            $rels = self::xml((string) $rels_text);
            if ($workbook && $rels) {
                $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rel_targets = array();
                foreach ($rels->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $relationship) {
                    $attr = $relationship->attributes();
                    $rel_targets[(string) ($attr['Id'] ?? '')] = (string) ($attr['Target'] ?? '');
                }
                foreach ($workbook->xpath('//x:sheets/x:sheet') ?: array() as $sheet) {
                    $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                    $target = $rel_targets[(string) ($attributes['id'] ?? '')] ?? '';
                    if ($target === '') continue;
                    $target = ltrim(str_replace('\\', '/', $target), '/');
                    $target = preg_replace('#^\.\./#', '', $target);
                    $target = strpos($target, 'xl/') === 0 ? $target : 'xl/' . ltrim($target, '/');
                    // Khớp lại theo bản đồ entry để chịu khác biệt hoa/thường.
                    $actual = $entries[strtolower($target)] ?? null;
                    if ($actual !== null) $paths[] = $actual;
                }
            }
        }
        if (!$paths) {
            $fallback = array();
            foreach ($entries as $lower => $actual) {
                if (preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $lower, $match)) $fallback[(int) $match[1]] = $actual;
            }
            ksort($fallback);
            $paths = array_values($fallback);
        }
        return $paths;
    }

    /** Parse một worksheet: chịu cell thiếu thuộc tính r (suy ra theo thứ tự) và mọi kiểu giá trị. */
    private static function parse_sheet(string $xml_text, array $shared) {
        $xml = self::xml($xml_text);
        if (!$xml) {
            return new WP_Error('invalid_xlsx', 'Worksheet trong XLSX không hợp lệ.');
        }
        $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = array();
        $max_row = 0;
        $max_col = 0;
        $row_cursor = 0;
        foreach ($xml->xpath('//x:sheetData/x:row') ?: array() as $row_node) {
            $row_attrs = $row_node->attributes();
            $row_ref = (int) ($row_attrs['r'] ?? 0);
            $row = $row_ref > 0 ? $row_ref : $row_cursor + 1;
            $row_cursor = max($row_cursor, $row);
            $col_cursor = 0;
            foreach ($row_node->xpath('x:c') ?: array() as $cell) {
                $attributes = $cell->attributes();
                $reference = (string) ($attributes['r'] ?? '');
                if (preg_match('/^([A-Z]+)(\d+)$/i', $reference, $match)) {
                    $col = self::column_number(strtoupper($match[1]));
                    $row = (int) $match[2];
                    $row_cursor = max($row_cursor, $row);
                } else {
                    $col = $col_cursor + 1;
                }
                $col_cursor = max($col_cursor, $col);
                $type = (string) ($attributes['t'] ?? '');
                $value = '';
                if ($type === 'inlineStr') {
                    $parts = $cell->xpath('.//x:is//x:t') ?: array();
                    foreach ($parts as $part) $value .= (string) $part;
                } else {
                    $raw = isset($cell->v) ? (string) $cell->v : '';
                    $value = $type === 's' ? (string) ($shared[(int) $raw] ?? '') : $raw;
                }
                $cells[$row][$col] = $value;
                $max_row = max($max_row, $row);
                $max_col = max($max_col, $col);
            }
        }
        $merges = array();
        foreach ($xml->xpath('//x:mergeCells/x:mergeCell') ?: array() as $merge) {
            $reference = (string) ($merge->attributes()['ref'] ?? '');
            if (!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/i', $reference, $match)) {
                continue;
            }
            $range = array(
                'startCol' => self::column_number(strtoupper($match[1])),
                'startRow' => (int) $match[2],
                'endCol' => self::column_number(strtoupper($match[3])),
                'endRow' => (int) $match[4],
                'ref' => strtoupper($reference),
            );
            $merges[] = $range;
            $value = (string) ($cells[$range['startRow']][$range['startCol']] ?? '');
            for ($row = $range['startRow']; $row <= $range['endRow']; $row++) {
                for ($col = $range['startCol']; $col <= $range['endCol']; $col++) {
                    if (!isset($cells[$row][$col]) || $cells[$row][$col] === '') {
                        $cells[$row][$col] = $value;
                    }
                }
            }
            $max_row = max($max_row, $range['endRow']);
            $max_col = max($max_col, $range['endCol']);
        }
        $rows = array();
        for ($row = 1; $row <= $max_row; $row++) {
            $values = array();
            for ($col = 1; $col <= $max_col; $col++) {
                $values[] = (string) ($cells[$row][$col] ?? '');
            }
            $rows[$row] = $values;
        }
        return array('rows' => $rows, 'merges' => $merges, 'maxCol' => $max_col, 'maxRow' => $max_row);
    }

    private static function sheet_has_content(array $parsed): bool {
        foreach (array_slice($parsed['rows'] ?? array(), 0, 40, true) as $row) {
            foreach ($row as $value) {
                if (trim((string) $value) !== '') return true;
            }
        }
        return false;
    }

    private static function column_number(string $letters): int {
        $number = 0;
        foreach (str_split($letters) as $letter) $number = ($number * 26) + (ord($letter) - 64);
        return $number;
    }

    private static function column_letters(int $number): string {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + ($number % 26)) . $letters;
            $number = (int) floor($number / 26);
        }
        return $letters;
    }

    private static function esc(string $value): string {
        return htmlspecialchars(self::valid_xml_text($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function valid_xml_text(string $value): string {
        return (string) preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);
    }

    private static function sheet_xml(array $sheet): string {
        $rows = array_values($sheet['rows'] ?? array());
        $row_styles = $sheet['rowStyles'] ?? array();
        $cell_styles = $sheet['cellStyles'] ?? array();
        $widths = $sheet['widths'] ?? array();
        $max_col = 1;
        $row_xml = '';
        foreach ($rows as $row_index => $row) {
            $excel_row = $row_index + 1;
            $max_col = max($max_col, count($row));
            $cells = '';
            foreach (array_values($row) as $col_index => $value) {
                $excel_col = $col_index + 1;
                $ref = self::column_letters($excel_col) . $excel_row;
                $style = (int) ($cell_styles[$excel_row . ':' . $excel_col] ?? $row_styles[$excel_row] ?? ($excel_row === 1 ? 1 : 0));
                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="' . $ref . '" s="' . $style . '" t="n"><v>' . $value . '</v></c>';
                } else {
                    $text = (string) ($value ?? '');
                    $cells .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc($text) . '</t></is></c>';
                }
            }
            $row_xml .= '<row r="' . $excel_row . '">' . $cells . '</row>';
        }
        $cols_xml = '';
        if ($widths) {
            $cols_xml = '<cols>';
            foreach (array_values($widths) as $index => $width) {
                $col = $index + 1;
                $cols_xml .= '<col min="' . $col . '" max="' . $col . '" width="' . max(6, min(60, (float) $width)) . '" customWidth="1"/>';
            }
            $cols_xml .= '</cols>';
        }
        $merge_xml = '';
        $merges = $sheet['merges'] ?? array();
        if ($merges) {
            $merge_xml = '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $merge) {
                $merge_xml .= '<mergeCell ref="' . self::column_letters((int) $merge[1]) . (int) $merge[0] . ':' . self::column_letters((int) $merge[3]) . (int) $merge[2] . '"/>';
            }
            $merge_xml .= '</mergeCells>';
        }
        $dimension = 'A1:' . self::column_letters($max_col) . max(1, count($rows));
        $auto_filter = !empty($sheet['autoFilter']) && count($rows) > 1 ? '<autoFilter ref="A1:' . self::column_letters($max_col) . count($rows) . '"/>' : '';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="' . $dimension . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="20"/>' . $cols_xml . '<sheetData>' . $row_xml . '</sheetData>' . $auto_filter . $merge_xml
            . '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '</worksheet>';
    }

    private static function styles_xml(): string {
        $fills = array('FFFFFF', 'E31E24', 'FDECEC', 'FFF1E8', 'FFF8CC', 'EAF8F0', 'EAF2FF', 'F3ECFF', 'FDEBF3');
        $fill_xml = '<fills count="' . (count($fills) + 2) . '"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
        foreach ($fills as $color) $fill_xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $color . '"/><bgColor indexed="64"/></patternFill></fill>';
        $fill_xml .= '</fills>';
        $xfs = '<cellXfs count="11"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>';
        $xfs .= '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>';
        for ($fill_id = 4; $fill_id <= 10; $fill_id++) {
            $xfs .= '<xf numFmtId="0" fontId="0" fillId="' . $fill_id . '" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>';
        }
        $xfs .= '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>';
        $xfs .= '<xf numFmtId="0" fontId="1" fillId="10" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3"><font><sz val="11"/><name val="Inter"/><family val="2"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Inter"/></font><font><b/><color rgb="FF111827"/><sz val="12"/><name val="Inter"/></font></fonts>'
            . $fill_xml
            . '<borders count="2"><border/><border><left style="thin"><color rgb="FFE4E7EC"/></left><right style="thin"><color rgb="FFE4E7EC"/></right><top style="thin"><color rgb="FFE4E7EC"/></top><bottom style="thin"><color rgb="FFE4E7EC"/></bottom></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' . $xfs
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function content_types(int $count): string {
        $overrides = '';
        for ($i = 1; $i <= $count; $i++) $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . $overrides . '</Types>';
    }

    private static function root_rels(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private static function workbook_xml(array $sheets): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach (array_values($sheets) as $index => $sheet) {
            $name = mb_substr((string) ($sheet['name'] ?? ('Sheet ' . ($index + 1))), 0, 31, 'UTF-8');
            $name = str_replace(array('[', ']', ':', '*', '?', '/', '\\'), '-', $name);
            $xml .= '<sheet name="' . self::esc($name) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }
        return $xml . '</sheets></workbook>';
    }

    private static function workbook_rels(int $count): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $count; $i++) $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        return $xml . '<Relationship Id="rId' . ($count + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private static function app_xml(array $sheets): string {
        $titles = '';
        foreach ($sheets as $sheet) $titles .= '<vt:lpstr>' . self::esc((string) ($sheet['name'] ?? 'Sheet')) . '</vt:lpstr>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>MAC Company Trip</Application><TitlesOfParts><vt:vector size="' . count($sheets) . '" baseType="lpstr">' . $titles . '</vt:vector></TitlesOfParts></Properties>';
    }

    private static function core_xml(): string {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>MAC Marketing</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created></cp:coreProperties>';
    }
}
