<?php

namespace App\Modules\Avisos\Application;

interface ConfiguracoesNumeroWhatsApp
{
    /** @param list<string> $templates */
    public function ligar(int $organizacaoId, CredenciaisNumeroWhatsApp $credenciais, array $templates): void;

    public function desligar(int $organizacaoId): void;
}
