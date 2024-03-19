<?php

use Illuminate\Support\Facades\File;

if (!function_exists('copy_migrations')) {
    function copy_migrations(string $destinationPath, array|string $filePaths): void
    {
        $timestamp = null;

        $filePaths = is_string($filePaths) ? [$filePaths] : $filePaths;

        foreach ($filePaths as $file) {
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

if (!function_exists('insertImports')) {
    function insertImports(string &$content, array|string $classesNamespace): void
    {
        $classesNamespace = is_string($classesNamespace) ? [$classesNamespace] : $classesNamespace;

        // append "use " in every element of $classNamespace
        $classesNamespace = array_map(function ($classNamespace) {
            return "use " . $classNamespace . ";";
        }, $classesNamespace);

        if (preg_match('/(?P<imports>(?:^use [^;{]+;$\n?)+)/m', $content, $match)) {
            $imports = explode("\n", trim($match['imports']));

            $imports = array_unique(array_merge($imports, $classesNamespace));

            sort($imports);

            $content = str_replace(trim($match['imports']), implode("\n", $imports), $content);
        } else {
            // if no imports exist
            // adding imports at the top of file
            $namespacePos = strpos($content, 'namespace');
            $namespaceSemicolonPos = strpos($content, ';', $namespacePos);
            $content = substr_replace(
                $content,
                "\n\n" . implode("\n", $classesNamespace),
                $namespaceSemicolonPos+1,
                0
            );
        }

    }
}

if (!function_exists('create_table_migration_exists')) {
    function create_table_migration_exists(array|string $tables): string
    {
        $tables = is_string($tables) ? [$tables] : $tables;

        $migrationFileFoundFor = '';

        $existingMigrationFiles = File::glob(database_path('migrations') . '/*.php');

        foreach ($existingMigrationFiles as $migrationFile) {

            $content = File::get($migrationFile);

            foreach ($tables as $table) {

                $pattern = '/Schema::create\s*\(\s*[\'"]' . preg_quote($table, '/') . '[\'"]\s*,\s*/i';
                if (preg_match($pattern, $content)) {
                    $migrationFileFoundFor = "------- IMPORTANT -------\n";
                    $migrationFileFoundFor .= "Migration file '$migrationFile' for table '$table' already exists.\n";
                    $migrationFileFoundFor .= "To install this package you need to delete above migration file, its entry from migrations table as well as drop '$table' table from DB.\n";
                    $migrationFileFoundFor .= "Make sure to backup both (the existing migration file and '$table' table data).";
                    break;
                }
            }

            if ($migrationFileFoundFor) {
                break;
            }
        }

        return $migrationFileFoundFor;
    }

    if (!function_exists('getLaravelMajorVersion')) {
        function getLaravelMajorVersion(): string
        {
            $laravelVersion = exec('php artisan -V');
            // because laravel has semantic versioning
            preg_match('/\d+\.\d+\.\d+/', $laravelVersion, $matches);
            return explode('.', $matches[0])[0];
        }
    }

    if (!function_exists('getLaravelSemanticVersionSum')) {
        function getLaravelSemanticVersionSum(): float|int
        {
            $laravelVersion = exec('php artisan -V');
            // because laravel has semantic versioning
            preg_match('/\d+\.\d+\.\d+/', $laravelVersion, $matches);
            $versionParts = explode('.', $matches[0]);
            return array_sum($versionParts);
        }
    }
}