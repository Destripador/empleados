<?php

declare(strict_types=1);

namespace OCA\Empleados\Service;

class XlsxTemplateFiller
{
    private string $templatePath;

    public function __construct(string $templatePath)
    {
        if (!is_file($templatePath)) {
            throw new \RuntimeException("No existe la plantilla: {$templatePath}");
        }
        $this->templatePath = $templatePath;
    }

    public function fill(array $replacements, ?array $logo = null): string
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('La extensión ZipArchive de PHP no está disponible.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_fill_');
        if ($tmpFile === false || !copy($this->templatePath, $tmpFile)) {
            throw new \RuntimeException('No se pudo copiar la plantilla a un archivo temporal.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            @unlink($tmpFile);
            throw new \RuntimeException('No se pudo abrir la plantilla xlsx como zip.');
        }

        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml === false) {
            $zip->close();
            @unlink($tmpFile);
            throw new \RuntimeException('La plantilla no contiene xl/sharedStrings.xml.');
        }

        uksort($replacements, static fn ($a, $b) => strlen((string)$b) <=> strlen((string)$a));

        $search = [];
        $replace = [];
        foreach ($replacements as $placeholder => $valor) {
            $search[]  = $placeholder;
            $replace[] = htmlspecialchars((string)$valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }

        $sharedStringsXml = str_replace($search, $replace, $sharedStringsXml);

        $zip->deleteName('xl/sharedStrings.xml');
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);

        if ($logo !== null) {
            $this->replaceLogo($zip, $logo);
        }

        $zip->close();

        $contenido = file_get_contents($tmpFile);
        unlink($tmpFile);

        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el xlsx generado.');
        }

        return $contenido;
    }

    private function replaceLogo(\ZipArchive $zip, array $logo): void
    {
        $mediaFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && str_starts_with($name, 'xl/media/')) {
                $mediaFiles[] = $name;
            }
        }

        if (empty($mediaFiles)) {
            return;
        }

        sort($mediaFiles);
        $targetName = $mediaFiles[0];
        $targetExt = strtolower(pathinfo($targetName, PATHINFO_EXTENSION));
        $targetBaseName = basename($targetName);

        $content = $this->normalizeImage($logo['content'], $logo['mime'], $targetExt);

        $zip->deleteName($targetName);
        $zip->addFromString($targetName, $content);

        // NUEVO: ajustamos el tamaño/posición del cuadro del logo
        // para mantener el aspect ratio de la imagen nueva.
        $this->fixDrawingSize($zip, $targetBaseName, $content);
    }

    /**
     * Recalcula el tamaño del <xdr:pic> que usa la imagen reemplazada,
     * haciendo un "contain fit" dentro de la caja original y centrando.
     */
    private function fixDrawingSize(\ZipArchive $zip, string $mediaBaseName, string $imageContent): void
    {
        $imgInfo = @getimagesizefromstring($imageContent);
        if ($imgInfo === false) {
            return;
        }

        [$imgW, $imgH] = $imgInfo;
        if ($imgW <= 0 || $imgH <= 0) {
            return;
        }

        // Recolectamos los nombres de drawing ANTES de tocar el zip,
        // para no iterar sobre un índice que se mueve mientras mutamos.
        $drawingNames = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && preg_match('#^xl/drawings/drawing\d+\.xml$#', $name)) {
                $drawingNames[] = $name;
            }
        }

        foreach ($drawingNames as $name) {
            $relsName = 'xl/drawings/_rels/' . basename($name) . '.rels';
            $relsXml = $zip->getFromName($relsName);
            if ($relsXml === false) {
                continue;
            }

            $relsDoc = new \DOMDocument();
            $relsDoc->loadXML($relsXml);

            $rId = null;
            foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
                if (basename($rel->getAttribute('Target')) === $mediaBaseName) {
                    $rId = $rel->getAttribute('Id');
                    break;
                }
            }

            if ($rId === null) {
                continue;
            }

            $drawingXml = $zip->getFromName($name);
            if ($drawingXml === false) {
                continue;
            }

            $doc = new \DOMDocument();
            $doc->loadXML($drawingXml);

            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
            $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

            $blips = $xpath->query("//a:blip[@r:embed='{$rId}']");
            if ($blips === false || $blips->length === 0) {
                continue;
            }

            $anchor = $blips->item(0);
            while ($anchor !== null && !in_array($anchor->localName, ['twoCellAnchor', 'oneCellAnchor'], true)) {
                $anchor = $anchor->parentNode;
            }

            if ($anchor === null) {
                continue;
            }

            $xfrmExt = $xpath->query('.//xdr:spPr/a:xfrm/a:ext', $anchor)->item(0);
            $xfrmOff = $xpath->query('.//xdr:spPr/a:xfrm/a:off', $anchor)->item(0);

            $boxCx = $xfrmExt ? (int)$xfrmExt->getAttribute('cx') : null;
            $boxCy = $xfrmExt ? (int)$xfrmExt->getAttribute('cy') : null;

            if ((!$boxCx || !$boxCy) && $anchor->localName === 'oneCellAnchor') {
                $extNode = $xpath->query('./xdr:ext', $anchor)->item(0);
                if ($extNode) {
                    $boxCx = (int)$extNode->getAttribute('cx');
                    $boxCy = (int)$extNode->getAttribute('cy');
                }
            }

            if (!$boxCx || !$boxCy) {
                continue;
            }

            $emuPerPx = 9525;
            $imgCxEmu = $imgW * $emuPerPx;
            $imgCyEmu = $imgH * $emuPerPx;

            $scale = min($boxCx / $imgCxEmu, $boxCy / $imgCyEmu);
            $newCx = (int)round($imgCxEmu * $scale);
            $newCy = (int)round($imgCyEmu * $scale);

            if ($xfrmExt) {
                $xfrmExt->setAttribute('cx', (string)$newCx);
                $xfrmExt->setAttribute('cy', (string)$newCy);
            }

            if ($xfrmOff) {
                $offX = (int)$xfrmOff->getAttribute('x');
                $offY = (int)$xfrmOff->getAttribute('y');
                $xfrmOff->setAttribute('x', (string)($offX + intdiv($boxCx - $newCx, 2)));
                $xfrmOff->setAttribute('y', (string)($offY + intdiv($boxCy - $newCy, 2)));
            }

            if ($anchor->localName === 'oneCellAnchor') {
                $extNode = $xpath->query('./xdr:ext', $anchor)->item(0);
                if ($extNode) {
                    $extNode->setAttribute('cx', (string)$newCx);
                    $extNode->setAttribute('cy', (string)$newCy);
                }
            } else {
                $this->convertTwoCellToOneCell($doc, $anchor, $newCx, $newCy, $boxCx, $boxCy);
            }

            $zip->deleteName($name);
            $zip->addFromString($name, $doc->saveXML());
        }
    }

    /**
     * Convierte un twoCellAnchor a oneCellAnchor para que el tamaño
     * quede fijo (cx/cy explícitos) y Excel no lo vuelva a estirar
     * según las celdas al abrir el archivo.
     */
    private function convertTwoCellToOneCell(
        \DOMDocument $doc,
        \DOMElement $twoCellAnchor,
        int $newCx,
        int $newCy,
        int $boxCx,
        int $boxCy
    ): void {
        $xdrNs = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';

        $from = null;
        foreach ($twoCellAnchor->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'from') {
                $from = $child;
                break;
            }
        }

        if ($from === null) {
            return;
        }

        $oneCellAnchor = $doc->createElementNS($xdrNs, 'xdr:oneCellAnchor');
        $oneCellAnchor->appendChild($from->cloneNode(true));

        $fromClone = $oneCellAnchor->lastChild;
        foreach ($fromClone->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'colOff') {
                $child->nodeValue = (string)((int)$child->nodeValue + intdiv($boxCx - $newCx, 2));
            }
            if ($child->localName === 'rowOff') {
                $child->nodeValue = (string)((int)$child->nodeValue + intdiv($boxCy - $newCy, 2));
            }
        }

        $ext = $doc->createElementNS($xdrNs, 'xdr:ext');
        $ext->setAttribute('cx', (string)$newCx);
        $ext->setAttribute('cy', (string)$newCy);
        $oneCellAnchor->appendChild($ext);

        foreach ($twoCellAnchor->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if (in_array($child->localName, ['pic', 'sp', 'graphicFrame', 'grpSp', 'cxnSp', 'clientData'], true)) {
                $oneCellAnchor->appendChild($child->cloneNode(true));
            }
        }

        $twoCellAnchor->parentNode->replaceChild($oneCellAnchor, $twoCellAnchor);
    }

    private function normalizeImage(string $content, string $mime, string $targetExt): string
    {
        $sourceExt = $mime === 'image/png' ? 'png' : 'jpg';

        if ($sourceExt === $targetExt || ($targetExt === 'jpeg' && $sourceExt === 'jpg')) {
            return $content;
        }

        if (!function_exists('imagecreatefromstring')) {
            return $content;
        }

        $img = @imagecreatefromstring($content);
        if ($img === false) {
            return $content;
        }

        ob_start();
        if ($targetExt === 'png') {
            imagesavealpha($img, true);
            imagepng($img);
        } else {
            imagejpeg($img, null, 90);
        }
        $converted = ob_get_clean();
        imagedestroy($img);

        return $converted !== false ? $converted : $content;
    }
}