<?php

namespace App\Modules\Avisos\Application;

interface CadastroNumeroWhatsApp
{
    public function urlDeAutorizacao(string $estado): string;

    public function trocarCodigo(string $codigo): CredenciaisNumeroWhatsApp;

    /** @return list<string> nomes dos templates garantidos no provedor */
    public function garantirTemplates(CredenciaisNumeroWhatsApp $credenciais): array;
}
