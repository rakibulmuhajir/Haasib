<?php

namespace App\TypeScript\Collectors;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Spatie\TypeScriptTransformer\Collectors\Collector;
use Spatie\TypeScriptTransformer\Structures\CollectedClass;
use Spatie\TypeScriptTransformer\Structures\TransformedType;

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
        $modelPaths = File::isDirectory(app_path('Models'))
            ? [app_path('Models')]
            : [app_path()];

        return collect($modelPaths)
            ->filter(fn ($path) => File::isDirectory($path))
            ->flatMap(fn ($path) => File::allFiles($path))
            ->map(fn (\SplFileInfo $file) => $this->fullyQualifiedClassNameFromFile($file, base_path('app')))
            ->filter(fn (?string $fqcn) => $fqcn && class_exists($fqcn))
            ->map(fn (string $fqcn) => new ReflectionClass($fqcn))
            ->filter(fn (ReflectionClass $rc) => $rc->isSubclassOf(Model::class) && ! $rc->isAbstract())
            ->map(function (ReflectionClass $modelReflection) {
                // Convention: App\Models\User -> App\Data\UserData
                $dtoClassName = str_replace('App\\Models\\', 'App\\Data\\', $modelReflection->getName()).'Data';

                return class_exists($dtoClassName) ? new CollectedClass(new ReflectionClass($dtoClassName)) : null;
            })
            ->filter();
    }

    /**
     * This collector does not transform the types itself.
     * It returns null to let a registered Transformer handle it.
     */
    public function getTransformedType(ReflectionClass $class): ?TransformedType
    {
        return null;
    }

    private function fullyQualifiedClassNameFromFile(\SplFileInfo $file, string $basePath): string
    {
        // This approach is more robust than parsing the file with regex.
        return Str::of($file->getRealPath() ?: $file->getPathname())
            ->after(realpath($basePath).DIRECTORY_SEPARATOR)
            ->replace(['/', '.php'], ['\\', ''])
            ->prepend('App\\')
            ->toString();
    }
}
