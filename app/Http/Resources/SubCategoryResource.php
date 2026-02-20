<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CategoryResource;

class SubCategoryResource extends JsonResource
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
            'status' => $this->status,
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
