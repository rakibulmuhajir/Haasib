<?php

namespace App\Support\Palette\Commands;

/** The sentence commands core ships. Modules add theirs through ModuleManifest::paletteCommands(). */
final class CorePaletteCommands
{
    /** @return list<PaletteCommand> */
    public static function all(): array
    {
        return [];
    }
}
