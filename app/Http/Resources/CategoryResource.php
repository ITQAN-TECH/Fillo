<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ar_title' => $this->ar_title,
            'en_title' => $this->en_title,
            'image' => $this->image,
            'type' => $this->type,
            'status' => $this->status,
            'sub_categories' => SubCategoryResource::collection($this->whenLoaded('subCategories')),
        ];
    }

    public function toResponse($request)
    {
        return $this->collection;
    }
}
