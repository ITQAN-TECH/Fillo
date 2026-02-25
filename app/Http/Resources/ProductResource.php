<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'ar_name' => $this->ar_name,
            'en_name' => $this->en_name,
            'ar_description' => $this->ar_description,
            'en_description' => $this->en_description,
            'sku' => $this->sku,
            'converted_sale_price' => $this->converted_sale_price,
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image' => url('storage/media/'.$image->image),
                    ];
                });
            }),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'total_quantity' => $this->total_quantity,
            'available_colors' => ColorResource::collection($this->available_colors ?? []),
            'available_sizes' => SizeResource::collection($this->available_sizes ?? []),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
