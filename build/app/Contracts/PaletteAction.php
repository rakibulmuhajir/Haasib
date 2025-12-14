<?php

namespace App\Contracts;

interface PaletteAction
{
    /**
     * Execute the action.
     *
     * @param array $params Validated parameters from palette
     * @return array Response with 'message', 'data', 'redirect', 'undo' keys
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(array $params): array;

    /**
     * Validation rules for this action.
     */
    public function rules(): array;

    /**
     * Permission required (null = no check).
     */
    public function permission(): ?string;
}
