<?php

namespace App\TypeScript\Transformers;

use ReflectionClass;
use ReflectionProperty;
use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Transformers\Transformer;

class DtoTransformer implements Transformer
{
    public function canTransform(ReflectionClass $class): bool
    {
        // This transformer handles classes that end with 'Data' and are in the App\Data namespace
        return str_ends_with($class->getName(), 'Data') &&
               str_starts_with($class->getName(), 'App\\Data\\');
    }

    public function transform(ReflectionClass $class, string $name): TransformedType
    {
        $properties = [];

        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $propertyType = $this->getPropertyType($property);

            // Check if property is nullable
            $isNullable = $property->getType() && $property->getType()->allowsNull();

            if ($isNullable) {
                $propertyType .= ' | null';
            }

            $properties[] = "    {$propertyName}: {$propertyType};";
        }

        $propertiesString = implode("\n", $properties);

        $transformed = <<<TS
{
{$propertiesString}
}
TS;

        return new TransformedType($class, $name, $transformed);
    }

    private function getPropertyType(ReflectionProperty $property): string
    {
        $type = $property->getType();

        if (! $type) {
            return 'any';
        }

        if ($type instanceof \ReflectionNamedType) {
            return $this->convertPhpTypeToTypeScript($type->getName());
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = array_map(
                fn (\ReflectionNamedType $type) => $this->convertPhpTypeToTypeScript($type->getName()),
                $type->getTypes()
            );

            return implode(' | ', $types);
        }

        return 'any';
    }

    private function convertPhpTypeToTypeScript(string $phpType): string
    {
        return match ($phpType) {
            'int', 'integer', 'float', 'double' => 'number',
            'string' => 'string',
            'bool', 'boolean' => 'boolean',
            'array' => 'any[]',
            'object' => 'object',
            'null' => 'null',
            default => 'any',
        };
    }
}
