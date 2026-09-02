<?php

namespace KIC\Importer\Package;

use KIC\Importer\Contract\KicContract;
use RuntimeException;
use ZipArchive;

final class SecureZipExtractor
{
    public function extract(string $zipPath, string $destination): void
    {
        if (!is_file($zipPath) || filesize($zipPath) > KicContract::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Archive is missing or exceeds the 100 MB limit.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The ZIP archive could not be opened.');
        }
        if ($zip->numFiles > KicContract::MAX_FILES) {
            $zip->close();
            throw new RuntimeException('Archive contains too many files.');
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            $total += (int) ($stat['size'] ?? 0);
            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('/^[A-Za-z]:/', $name)) {
                $zip->close();
                throw new RuntimeException('Archive contains an unsafe path: ' . $name);
            }
            if ($total > KicContract::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();
                throw new RuntimeException('Archive exceeds the uncompressed size limit.');
            }
            if (preg_match('#(^|/)(node_modules|\.git|vendor|__MACOSX)(/|$)|\.(map|phar|php|phtml)$#i', $name)) {
                $zip->close();
                throw new RuntimeException('Archive contains a prohibited file: ' . $name);
            }
        }
        if (!wp_mkdir_p($destination) || !$zip->extractTo($destination)) {
            $zip->close();
            throw new RuntimeException('Archive could not be extracted.');
        }
        $zip->close();
    }
}
