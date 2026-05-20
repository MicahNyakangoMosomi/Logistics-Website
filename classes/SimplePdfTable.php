<?php

class SimplePdfTable
{
    public static function download(string $filename, string $title, array $headers, array $rows, array $widths): void
    {
        $pdf = self::build($title, $headers, $rows, $widths);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private static function build(string $title, array $headers, array $rows, array $widths): string
    {
        $pageWidth = 842;
        $pageHeight = 595;
        $margin = 24;
        $rowHeight = 18;
        $headerHeight = 20;
        $fontSize = 7;
        $titleSize = 14;
        $yStart = $pageHeight - 58;
        $yMin = $margin + 22;
        $pages = [];
        $content = self::pageHeader($title, $margin, $pageHeight, $titleSize);
        $y = $yStart;

        self::drawTableHeader($content, $headers, $widths, $margin, $y, $headerHeight, $fontSize);
        $y -= $headerHeight;

        foreach ($rows as $row) {
            if ($y - $rowHeight < $yMin) {
                $pages[] = $content;
                $content = self::pageHeader($title, $margin, $pageHeight, $titleSize);
                $y = $yStart;
                self::drawTableHeader($content, $headers, $widths, $margin, $y, $headerHeight, $fontSize);
                $y -= $headerHeight;
            }

            self::drawRow($content, $row, $widths, $margin, $y, $rowHeight, $fontSize);
            $y -= $rowHeight;
        }

        if (!$rows) {
            self::drawRow($content, ['No records found'], [array_sum($widths)], $margin, $y, $rowHeight, $fontSize);
        }

        $pages[] = $content;

        return self::assemblePdf($pages, $pageWidth, $pageHeight);
    }

    private static function pageHeader(string $title, int $margin, int $pageHeight, int $titleSize): string
    {
        $date = date('Y-m-d H:i');
        return "0.4 G 0.5 w 0 g\n"
            . self::text($margin, $pageHeight - 28, $titleSize, $title)
            . self::text($margin, $pageHeight - 44, 8, 'Generated: ' . $date);
    }

    private static function drawTableHeader(string &$content, array $headers, array $widths, int $x, int $y, int $height, int $fontSize): void
    {
        $content .= "0.90 0.94 0.98 rg\n";
        self::drawCells($content, $headers, $widths, $x, $y, $height, $fontSize, true);
        $content .= "0 g\n";
    }

    private static function drawRow(string &$content, array $row, array $widths, int $x, int $y, int $height, int $fontSize): void
    {
        self::drawCells($content, $row, $widths, $x, $y, $height, $fontSize, false);
    }

    private static function drawCells(string &$content, array $cells, array $widths, int $x, int $y, int $height, int $fontSize, bool $fill): void
    {
        $cursor = $x;
        foreach ($widths as $index => $width) {
            $content .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $cursor, $y - $height, $width, $height, $fill ? 'B' : 'S');
            $text = self::truncate((string)($cells[$index] ?? ''), max(4, (int)floor($width / ($fontSize * 0.55))));
            $content .= self::text($cursor + 3, $y - 12, $fontSize, $text);
            $cursor += $width;
        }
    }

    private static function text(float $x, float $y, int $size, string $text): string
    {
        return sprintf("BT /F1 %d Tf %.2F %.2F Td (%s) Tj ET\n", $size, $x, $y, self::escape($text));
    }

    private static function truncate(string $text, int $length): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, max(0, $length - 3)) . '...';
    }

    private static function escape(string $text): string
    {
        $text = str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", ' ', ' '], $text);
        return preg_replace('/[^\x20-\x7E]/', '?', $text);
    }

    private static function assemblePdf(array $pages, int $pageWidth, int $pageHeight): string
    {
        $objects = [];
        $pageObjectNumbers = [];
        $fontObject = 3;
        $nextObject = 4;

        foreach ($pages as $content) {
            $contentObject = $nextObject++;
            $pageObject = $nextObject++;
            $objects[$contentObject] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 {$fontObject} 0 R >> >> /Contents {$contentObject} 0 R >>";
            $pageObjectNumbers[] = $pageObject;
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(function ($number) { return $number . ' 0 R'; }, $pageObjectNumbers)) . '] /Count ' . count($pageObjectNumbers) . ' >>';
        $objects[$fontObject] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_keys($objects) as $number) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
