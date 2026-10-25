<?php
declare(strict_types=1);

function write_sample_file(string $outputFile, string $content): void
{
    $dir = dirname($outputFile);
    if (!is_dir($dir)) {
        throw new RuntimeException(
            "Export directory {$dir} does not exist. "
            . 'For Docker, mount ./exports to /exports and recreate the web container.'
        );
    }

    if (file_put_contents($outputFile, $content) === false) {
        throw new RuntimeException(
            "Failed to write {$outputFile}. "
            . 'Ensure the host exports/ directory is writable by the web server '
            . '(e.g. chmod 775 exports or chown to the container www-data UID).'
        );
    }
}

function default_export_dir(): string
{
    if (file_exists('/.dockerenv')) {
        if (!is_dir('/exports')) {
            throw new RuntimeException(
                'Missing /exports in the container. Add "./exports:/exports" to the web service in docker-compose.yml and run docker compose up -d --force-recreate web.'
            );
        }
        return '/exports';
    }

    $projectExports = dirname(__DIR__) . '/exports';
    if (!is_dir($projectExports)) {
        if (!mkdir($projectExports, 0755, true) && !is_dir($projectExports)) {
            throw new RuntimeException("Failed to create directory {$projectExports}");
        }
    }

    return $projectExports;
}
