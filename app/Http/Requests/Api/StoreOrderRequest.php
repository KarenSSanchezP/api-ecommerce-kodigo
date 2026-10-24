<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api') !== null;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            // Los importes y estados siempre los calcula/controla el servidor.
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'total_cents' => ['prohibited'],
            'currency' => ['prohibited'],
            'items.*.unit_price_cents' => ['prohibited'],
            'items.*.subtotal_cents' => ['prohibited'],
        ];
    }
}
