<?php

declare(strict_types=1);

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

class BuyStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_symbol' => 'required|exists:stocks,symbol',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
