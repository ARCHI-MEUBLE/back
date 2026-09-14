<?php

declare(strict_types=1);

namespace App\Domain\Facade;

final class FacadeDxfGenerator
{
    public function __construct(private readonly string $templatesDir) {}

    public function generate(float $width, float $height, float $depth, array $drillings, string $facadeId, string $materialName): string
    {
        $handle = 0x100;
        $header = str_replace(
            ['{{WIDTH}}', '{{HEIGHT}}'],
            [(string) $width, (string) $height],
            (string) file_get_contents($this->templatesDir . '/facade-header.dxf'),
        );
        $entities = $this->contour($width, $height, $handle)
            . $this->drillingCircles($drillings, $width, $height, $handle)
            . $this->labels($width, $height, $depth, $facadeId, $materialName, $handle);
        $footer = (string) file_get_contents($this->templatesDir . '/facade-footer.dxf');
        return $header . $entities . $footer;
    }

    private function contour(float $width, float $height, int &$handle): string
    {
        return "  0\nLWPOLYLINE\n  5\n" . dechex($handle++) . "\n330\n17\n100\nAcDbEntity\n  8\ncontour\n100\nAcDbPolyline\n"
            . " 90\n4\n 70\n1\n"
            . " 10\n0.0\n 20\n0.0\n"
            . " 10\n" . self::number($width) . "\n 20\n0.0\n"
            . " 10\n" . self::number($width) . "\n 20\n" . self::number($height) . "\n"
            . " 10\n0.0\n 20\n" . self::number($height) . "\n";
    }

    private function drillingCircles(array $drillings, float $width, float $height, int &$handle): string
    {
        $dxf = '';
        foreach ($drillings as $drilling) {
            $cx = ((float) ($drilling['x'] ?? 0) / 100) * $width;
            $cy = ((float) ($drilling['y'] ?? 0) / 100) * $height;
            $radius = ((float) ($drilling['diameter'] ?? 26) / 1000) / 2;
            $dxf .= "  0\nCIRCLE\n  5\n" . dechex($handle++) . "\n330\n17\n100\nAcDbEntity\n  8\npercages\n100\nAcDbCircle\n"
                . " 10\n" . self::number($cx) . "\n 20\n" . self::number($cy) . "\n 30\n0.0\n 40\n" . self::number($radius) . "\n";
        }
        return $dxf;
    }

    private function labels(float $width, float $height, float $depth, string $facadeId, string $materialName, int &$handle): string
    {
        $textY = $height + 0.02;
        $info = sprintf('Facade #%s - %dx%dmm - Ep.%dmm', $facadeId, round($width * 1000), round($height * 1000), round($depth * 1000));
        return $this->textEntity($textY, 0.02, $info, $handle) . $this->textEntity($textY + 0.025, 0.02, $materialName, $handle);
    }

    private function textEntity(float $y, float $textHeight, string $value, int &$handle): string
    {
        return "  0\nTEXT\n  5\n" . dechex($handle++) . "\n330\n17\n100\nAcDbEntity\n  8\ntexte\n100\nAcDbText\n"
            . " 10\n0.0\n 20\n" . self::number($y) . "\n 30\n0.0\n 40\n" . self::number($textHeight) . "\n"
            . "  1\n" . $value . "\n100\nAcDbText\n";
    }

    private static function number(float $value): string
    {
        return sprintf('%.6f', $value);
    }
}
