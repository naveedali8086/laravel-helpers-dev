<?php

use Illuminate\Support\Facades\File;

if (!function_exists('copy_migrations')) {
    function copy_migrations(string $destinationPath, array $filesToCopy): void
    {
        $timestamp = null;
        foreach ($filesToCopy as $file) {
            // Extract the filename without extension
            $fileName = pathinfo($file, PATHINFO_FILENAME);

            // Get current date and time to append to the filename
            // Adding 1 second to previous $timestamp, that will publish the files in
            // the same order as they are required. Otherwise, child tables may be
            // published before parent table that will raise DB exceptions when migrations are run
            $timestamp = $timestamp ?
                date('Y-m-d H:i:s', strtotime($timestamp) + 1) :
                date('Y-m-d H:i:s');

            $newFilename = preg_replace(
                '/\d{4}_\d{2}_\d{2}_\d+/',
                str_replace(['-', ' ', ':'], ['_', '', ''], $timestamp),
                $fileName
            );

            // Publish the migration file with the new filename
            File::copy($file, "$destinationPath/$newFilename.php");
        }
    }

}
