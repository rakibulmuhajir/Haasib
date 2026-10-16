<?php

namespace App\Contracts;

/**
 * Optional on a PaletteAction: improves how the palette catalog describes it.
 * Everything is optional; omitted keys fall back to what rules() implies.
 */
interface DescribesPalette
{
    /**
     * @return array{description?: string, hidden?: bool, form_url?: string, fields?: array<string, array{hint?: string, inputType?: string, searchEntity?: string}>}
     */
    public function palette(): array;
}
