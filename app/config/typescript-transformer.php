<?php

return [
    'auto_discover_types' => [
        app_path(),
    ],

    'collectors' => [
        App\TypeScript\Collectors\ModelDtoCollector::class,
        Spatie\TypeScriptTransformer\Collectors\EnumCollector::class,
    ],

    'transformers' => [
        Spatie\TypeScriptTransformer\Transformers\EnumTransformer::class,
        Spatie\TypeScriptTransformer\Transformers\SpatieEnumTransformer::class,
        Spatie\LaravelData\Support\TypeScriptTransformer\DataTypeScriptTransformer::class,
    ],

    'default_type_replacements' => [
        DateTime::class => 'string',
        DateTimeImmutable::class => 'string',
        Carbon\CarbonInterface::class => 'string',
        Carbon\CarbonImmutable::class => 'string',
        Carbon\Carbon::class => 'string',
    ],

    'output_file' => resource_path('js/types/generated.d.ts'),
    'writer' => Spatie\TypeScriptTransformer\Writers\TypeDefinitionWriter::class,
    'formatter' => null,
    'transform_to_native_enums' => false,
    'transform_null_to_optional' => false,
];
