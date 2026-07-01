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

    /**
     * @param array<string,string|int|float> $replacements  ej. ['{cliente.nombre}' => 'ACME SA']
     * @return string contenido binario del xlsx ya generado (listo para DataDownloadResponse)
     */
    public function fill(array $replacements): string
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

        // Ordenamos por longitud descendente para evitar que un placeholder
        // corto (ej. {fecha}) reemplace parte de uno más largo por accidente.
        uksort($replacements, static fn ($a, $b) => strlen((string)$b) <=> strlen((string)$a));

        $search = [];
        $replace = [];
        foreach ($replacements as $placeholder => $valor) {
            $search[]  = $placeholder;
            $replace[] = htmlspecialchars(
                (string)$valor,
                ENT_XML1 | ENT_QUOTES,
                'UTF-8'
            );
        }

        $sharedStringsXml = str_replace($search, $replace, $sharedStringsXml);

        $zip->deleteName('xl/sharedStrings.xml');
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->close();

        $contenido = file_get_contents($tmpFile);
        unlink($tmpFile);

        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el xlsx generado.');
        }

        return $contenido;
    }
}
