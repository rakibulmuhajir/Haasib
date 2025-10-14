<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyCollection extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        
        return [
            'data' => CompanyResource::collection($this->collection),
            'meta' => [
                'total' => $this->collection->count(),
                'current_page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 15),
                'has_more' => $this->collection->hasMorePages(),
                'filters' => [
                    'industry' => $request->get('industry'),
                    'country' => $request->get('country'),
                    'is_active' => $request->get('is_active'),
                    'search' => $request->get('search'),
                ],
                'sort' => [
                    'field' => $request->get('sort', 'name'),
                    'direction' => $request->get('direction', 'asc'),
                ],
                'company_context' => $request->attributes->get('company')?->id,
            ],
            'links' => [
                'first' => $request->url(),
                'last' => null,
                'prev' => null,
                'next' => null,
                'self' => $request->fullUrl(),
            ],
        ];
    }
}