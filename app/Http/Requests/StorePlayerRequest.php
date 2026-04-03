<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Player identity
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'jersey_number'  => ['required', 'integer', 'min:0', 'max:99'],
            'role'           => ['nullable', 'string', 'max:50'],
            'height_feet'    => ['nullable', 'numeric', 'min:0', 'max:9.99'],
            'weight_kg'      => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'is_active'      => ['boolean'],

            // Stats (all optional for manual entry)
            'pc'                  => ['nullable', 'string', 'max:50'],
            'sd'                  => ['nullable', 'string', 'max:50'],
            'three_p_pct'         => ['nullable', 'numeric'],
            'three_pt'            => ['nullable', 'string', 'max:20'],
            'ast'                 => ['nullable', 'numeric'],
            'ast_to'              => ['nullable', 'numeric'],
            'blk'                 => ['nullable', 'numeric'],
            'dd2'                 => ['nullable', 'integer'],
            'dq'                  => ['nullable', 'integer'],
            'dr'                  => ['nullable', 'numeric'],
            'eject'               => ['nullable', 'integer'],
            'fg'                  => ['nullable', 'string', 'max:20'],
            'fg_pct'              => ['nullable', 'numeric'],
            'flag'                => ['nullable', 'integer'],
            'ft'                  => ['nullable', 'string', 'max:20'],
            'ft_pct'              => ['nullable', 'numeric'],
            'gp'                  => ['nullable', 'integer'],
            'gs'                  => ['nullable', 'integer'],
            'min'                 => ['nullable', 'numeric'],
            'offensive_rebounds'  => ['nullable', 'numeric'],
            'pf'                  => ['nullable', 'numeric'],
            'pts'                 => ['nullable', 'numeric'],
            'reb'                 => ['nullable', 'numeric'],
            'sc_eff'              => ['nullable', 'numeric'],
            'sh_eff'              => ['nullable', 'numeric'],
            'stl'                 => ['nullable', 'numeric'],
            'stl_to'              => ['nullable', 'numeric'],
            'td3'                 => ['nullable', 'integer'],
            'tech'                => ['nullable', 'integer'],
            'to_per_game'         => ['nullable', 'numeric'],
        ];
    }
}
