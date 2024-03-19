<?php

namespace Naveedali8086\LaravelHelpersDev\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AddTraitNameCommand extends Command
{
    protected $signature = 'add-traits {file : fully qualified file path in which traits needs to be added} {traits : comma separated fully qualified list of traits that needs to be added}';

    protected $description = 'It adds trait(s) into the file';

    public function handle()
    {
        $traitsToAdd = $this->argument('traits');

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

        insertImports($content, $traitsToAdd);

        // Find the position of class opening bracket
        $classOpeningPosition = strpos($content, '{');

        // this pattern searches all 3 scenarios of defining the traits in a php class. scenarios are given below
        $allTraitsRegex = '/use\s+[a-zA-Z0-9_]+(?:,\s*[a-zA-Z0-9_]+)*;/';

        $traitsFound = preg_match($allTraitsRegex, $content, $matches, 0, $classOpeningPosition);

        $traitNames = [];
        foreach ($traitsToAdd as $trait) {
            $traitParts = explode('\\', $trait);
            $traitNames[] = end($traitParts);
        }

        if ($traitsFound === 0) { // file do not have any trait at all
            // add your traits here
            sort($traitNames);
            $updatedTraits = "\nuse " . implode(', ', $traitNames) . ';';

            $content = substr_replace($content, $updatedTraits, $classOpeningPosition + 1, 0);

        } else { // some traits already exist in file

            // following are 3 ways to define a trait in a file
            /*
            scenario 1:
            ===========
                use Trait1, Trait2;

            scenario 2:
            ===========
                use Trait1, Trait2;
                use Trait3, Trait4;

            scenario 3:
            ===========
                use Trait1;
                use Trait2;
           */

            // the output of above regex for all the 3 scenarios above is given below:
            /*
            scenario 1: (matches found 1)
            ===========
            use Trait1, Trait2;
            regex output: [ 0 => use Trait1, Trait2;]

            scenario 2: (matches found 2)
            ===========
                use Trait1, Trait2;
                use Trait3, Trait4;
                regex output: [
                       0 => use Trait1, Trait2;
                       1 => use Trait3, Trait4;
                      ]

            scenario 3: (matches found 2)
            ===========
                use Trait1;
                use Trait2;
                regex output: [
                       0 => use Trait1;
                       1 => use Trait2;
                      ]
            */

            if (preg_match_all($allTraitsRegex, $content, $matches, 0, $classOpeningPosition)) {
                // if regex output ($matches[0]) count is 1 then add the trait using any one of the
                // above scenarios otherwise insert new line having "use " keyword (at the start of traitName)
                // to add a new trait at the end traits list

                $existingTraits = $matches[0][0];
                if (count($matches[0]) === 1) { // handling scenario 1
                    $newTraits = '';
                    foreach ($traitNames as $traitsName) {
                        // do not add a trait into the file, if it already exists
                        $singleTraitsRegex = "/\b" . preg_quote($traitsName, '/') . "\b/";
                        if (preg_match($singleTraitsRegex, $existingTraits) === 0) {
                            $newTraits .= "$traitsName, ";
                        }
                    }
                    $newTraits = rtrim($newTraits, ', ');

                    $newTraits = $newTraits ?
                        str_replace(';', '', $existingTraits) . ", $newTraits;" :
                        $existingTraits;

                    $content = str_replace($existingTraits, $newTraits, $content);
                } else {  // handling scenario 2 & 3
                    $traitsAsString = implode(', ', $matches[0]);
                    $newTraits = '';
                    foreach ($traitNames as $traitsName) {
                        // do not add a trait into the file, if it already exists
                        $singleTraitsRegex = "/\b" . preg_quote($traitsName, '/') . "\b/";
                        if (preg_match($singleTraitsRegex, $traitsAsString) === 0) {
                            $newTraits .= "use $traitsName;\n";
                        }
                    }
                    $lastTrait = end($matches[0]);
                    $offset = strpos($content, $lastTrait) + strlen($lastTrait);
                    $content = substr_replace($content, "\n$newTraits", $offset, 0);
                }
            }
        }
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