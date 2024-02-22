<?php

namespace Naveedali8086\LaravelHelpersDev\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AddTraitNameCommand extends Command
{
    protected $signature = 'add-traits {file : fully qualified file path in which traits needs to be added} {traits : comma separated fully qualified list of traits that needs to be added}';

    protected $description = 'It adds traits list into the file';

    public function handle()
    {
        $traitsToAdd = $this->argument('traits');

        $traitsToAdd = preg_replace('/\s+/', '', $traitsToAdd);
        $traitsToAdd = explode(',', $traitsToAdd);
        $traitsToAdd = array_map(function ($trait) {
            // first remove "\", "/" from the start of the string then replace all "\" from "/"
            return str_replace('/', '\\', ltrim($trait, '\\/'));
        }, $traitsToAdd);

        $filePath = $this->argument('file');
        // first remove "\", "/" from the start of the string then replace all "\" from "/"
        $filePath = str_replace('/', '\\', ltrim($filePath, '\\/'));
        $filePath = $this->getFilePath($filePath);

        if (!File::exists($filePath)) {
            $this->error("$filePath not found.");
            return false;
        }

        // Read the contents of the model file
        $content = File::get($filePath);

        // Find the position of class opening bracket
        $classOpeningPosition = strpos($content, '{');

        //$traitsStart = $classOpeningPosition + 1;
        $useTraitPos = strpos($content, 'use ', $classOpeningPosition);

        $traitNames = [];
        foreach ($traitsToAdd as $trait) {
            $traitParts = explode('\\', $trait);
            $traitNames[] = end($traitParts);
        }

        if ($useTraitPos === false) { // file do not have any trait at all
            // add your traits here
            sort($traitNames);
            $updatedTraits = "\nuse " . implode(', ', $traitNames) . ';';

            $content = substr_replace($content, $updatedTraits, $classOpeningPosition + 1, 0);

        } else { // some traits already exist in file
            $traitEndingPos = strpos($content, ';', $useTraitPos);
            $existingTraits = substr($content, $useTraitPos, $traitEndingPos - $useTraitPos + 1);

            // removing "use", "space after use" and "semicolon"
            $existingTraits = trim(str_replace(["use", " ", ";"], "", $existingTraits));

            $existingTraitsArr = explode(',', $existingTraits);

            foreach ($traitNames as $traitsName) {
                if (!in_array($traitsName, $existingTraitsArr)) {
                    $existingTraitsArr[] = $traitsName;
                }
            }

            sort($existingTraitsArr);

            $updatedTraits = "use " . implode(', ', $existingTraitsArr) . ';';

            $content = substr_replace($content, $updatedTraits, $useTraitPos, $traitEndingPos - $useTraitPos + 1);
        }

        insertImports($content, $traitsToAdd);

        // Write the modified contents back to the file
        File::put($filePath, $content);

        if (windows_os()) {
            $filePath = str_replace('/', '\\', $filePath);
        }

        $this->info(sprintf("\n Trait (%s) added to the %s", implode(', ', $traitNames), $filePath));
    }

    protected function getFilePath($name): string
    {
        $name = Str::replaceFirst($this->laravel->getNamespace(), '', $name);

        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . '.php';
    }
}