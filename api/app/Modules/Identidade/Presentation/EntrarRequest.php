<?php

namespace App\Modules\Identidade\Presentation;

use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * Corpo do `POST /auth/login` no contrato do painel: `identificador`, `senha` e, opcional, a `entrada`
 * de onde a pessoa vem (`painel` abre a elevação junto, U-32).
 */
class EntrarRequest extends LoginRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'identificador' => 'required|string|max:255',
            'senha' => 'required|string|max:1024',
            'entrada' => 'nullable|string|in:painel,membro',
        ];
    }

    public function entrada(): ?string
    {
        $entrada = $this->input('entrada');

        return is_string($entrada) ? $entrada : null;
    }
}
