<?php

namespace Naveedali8086\LaravelHelpersDev\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AddTraitNameCommand extends Command
{
    protected $signature = 'add-traits {file : fully qualified file path in which traits needs to be added} {traits : comma separated fully qualified list of traits that needs to be added}';

    protected $description = 'It adds traits list into the file';

    public function handle()
    {
        $traitsToAdd = $this->argument('traits');
        $traitsToAdd = preg_replace('/\s+/', '', $traitsToAdd);
        $traitsToAdd = explode(',', $traitsToAdd);

        $modelName = $this->argument('file');
        $modelPath = $modelName;

        $modelNameArr = explode('\\', $modelName);
        $modelName = end($modelNameArr);

        // Prepend App\Models namespace if not provided
        if (!str_contains($modelPath, '\\')) {
            $modelPath = 'App\Models\\' . $modelName;
        }
        $modelFile = app_path('Models/' . $modelName . '.php');

        if (!class_exists($modelPath)) {
            $this->error("Model class $modelPath not found.");
            return false;
        }

        // Read the contents of the model file
        $contents = File::get($modelFile);

        // Find the position of class opening bracket
        $classOpeningPosition = strpos($contents, '{');


        $traitsStart = $classOpeningPosition + 1;
        $useTraitPos = strpos($contents, 'use ', $traitsStart);

        if ($useTraitPos === false) { // file do not have any trait at all
            // add your traits here
            $traitNames = [];
            foreach ($traitsToAdd as $trait) {
                $traitParts = explode('\\', $trait);
                $traitNames[] = end($traitParts);
            }
            sort($traitNames);
            $updatedTraits = "\nuse " . implode(', ', $traitNames) . ';';
            // put in the file content]
            $contents = substr_replace($contents, $updatedTraits, $traitsStart, 0);

        } else { // some traits already exist in file
            $traitEndingPos = strpos($contents, ';', $useTraitPos);
            $existingTraits = substr($contents, $useTraitPos, ($traitEndingPos - $useTraitPos) + 2);
            // removing "use", "space after use" and "semicolon" at the end
            $existingTraits = trim($existingTraits, 'use ; ');
            $existingTraits = preg_replace('/\s+/', '', $existingTraits);
            $existingTraits = trim(str_replace(["use", " ", ";"], "", $existingTraits));

            $existingTraitsArr = explode(',', $existingTraits);
            foreach ($traitsToAdd as $trait) {
                $traitParts = explode('\\', $trait);
                $traitsName = end($traitParts);
                if (!in_array($traitsName, $existingTraitsArr)) {
                    $existingTraitsArr[] = $traitsName;
                }
            }
            sort($existingTraitsArr);
            $updatedTraits = 'use ' . implode(', ', $existingTraitsArr) . ';';

            $contents = substr_replace($contents, $updatedTraits, $useTraitPos - 1, ($traitEndingPos - $useTraitPos) + 2);
        }

        // adding traits imports at the top of file after sorting
        // namespace App\Models;
        $namespacePos = strpos($contents, 'namespace');
        $namespaceSemicolonPos = strpos($contents, ';', $namespacePos);
        $classKeywordPos = strpos($contents, 'class', $namespaceSemicolonPos);
        $imports = substr($contents, $namespaceSemicolonPos + 1, ($classKeywordPos - $namespaceSemicolonPos) - 1);

        $imports = explode("\n", trim($imports));

        $traitsToAdd = array_map(function ($item) {
            return "use " . $item . ";";
        }, $traitsToAdd);

        foreach ($traitsToAdd as $trait) {
            if (!in_array($trait, $imports)) {
                $imports[] = $trait;
            }
        }

        sort($imports);

        $imports = "\n\n" . implode("\n", $imports) . "\n";

        $contents = substr_replace($contents, $imports, $namespaceSemicolonPos + 1, ($classKeywordPos - $namespaceSemicolonPos) - 1);

        // Write the modified contents back to the model file
        File::put($modelFile, $contents);

        $this->info("trait(s) has been added to the $modelName.");
    }

}