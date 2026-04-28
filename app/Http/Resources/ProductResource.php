<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
 use App\Http\Resources\CategoryResource;
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
     

return [
    'id' => $this->id,
    'name' => $this->name,
    'sku' => $this->sku,
    'description' => $this->description,
    'price' => (float) $this->price,
    'formatted_price' => $this->formatted_price,

    'category' => new CategoryResource($this->whenLoaded('category')),
    'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
    'is_active' => $this->is_active,
    'track_stock' => $this->track_stock,
    'stock_count' => $this->stock_count ?? 0,
    'created_at' => $this->created_at->format('d/m/Y H:i'),
];
    }
}