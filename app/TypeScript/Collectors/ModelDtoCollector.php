<?php

namespace App\TypeScript\Collectors;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Spatie\TypeScriptTransformer\Collectors\Collector;
use Spatie\TypeScriptTransformer\Structures\CollectedClass;

/**
 * This collector finds all Eloquent models in the `app/Models` directory
 * and then looks for a corresponding Data Transfer Object (DTO) in `app/Data`.
 *
 * It follows a convention:
 * App\Models\User -> App\Data\UserData
 * App\Models\Billing\Invoice -> App\Data\Billing\InvoiceData
 *
 * Only the found DTO classes are passed to the TypeScript transformer.
 */
class ModelDtoCollector extends Collector
{
    public function get(): Collection
    {
        // Define potential model paths to support different project structures.
        $modelPaths = [
            app_path('app/Models'), // As per your User model
            app_path('Models'),     // A common Laravel structure
        ];

        $allFiles = collect($modelPaths)
            ->filter(fn ($path) => is_dir($path))
            ->flatMap(fn ($path) => File::allFiles($path));

        return $allFiles
            ->map(fn (\SplFileInfo $file) => $this->fullyQualifiedClassNameFromFile($file, app_path()))
            ->filter(fn (?string $fqcn) => $fqcn && class_exists($fqcn))
            ->map(fn (string $fqcn) => new ReflectionClass($fqcn))
            ->filter(fn (ReflectionClass $rc) => $rc->isSubclassOf(Model::class) && !$rc->isAbstract())
            ->map(function (ReflectionClass $modelReflection) {
                // Convention: App\Models\User -> App\Data\UserData
                // This handles nested models correctly, e.g., App\Models\Billing\Invoice -> App\Data\Billing\InvoiceData
                $dtoClassName = str_replace('App\\Models\\', 'App\\Data\\', $modelReflection->getName()) . 'Data';
                return class_exists($dtoClassName) ? new CollectedClass(new ReflectionClass($dtoClassName), $dtoClassName) : null;
            })
            ->filter();
    }

    private function fullyQualifiedClassNameFromFile(\SplFileInfo $file, string $basePath): string
    {
        $class = str_replace(
            ['/', '.php'],
            ['\\', ''],
            Str::after($file->getRealPath(), realpath($basePath) . DIRECTORY_SEPARATOR)
        );

        return "App\\{$class}";
    }
}
