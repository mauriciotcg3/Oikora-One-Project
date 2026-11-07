<?php

namespace App\Modules\Identidade\Presentation;

use Laravel\Fortify\Http\Requests\LoginRequest;

/** Corpo do `POST /auth/login` no contrato do painel: `identificador` e `senha`. */
class EntrarRequest extends LoginRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'identificador' => 'required|string|max:255',
            'senha' => 'required|string|max:1024',
        ];
    }
}
