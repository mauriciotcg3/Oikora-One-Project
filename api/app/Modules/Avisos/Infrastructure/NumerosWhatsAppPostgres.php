<?php

namespace App\Modules\Avisos\Infrastructure;

use App\Modules\Avisos\Application\ConfiguracoesNumeroWhatsApp;
use App\Modules\Avisos\Application\CredenciaisNumeroWhatsApp;
use App\Modules\Avisos\Application\NumerosWhatsApp;
use App\Modules\Avisos\Application\NumeroWhatsApp;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class NumerosWhatsAppPostgres implements ConfiguracoesNumeroWhatsApp, NumerosWhatsApp
{
    public function daOrganizacao(int $organizacaoId): ?NumeroWhatsApp
    {
        $numero = DB::table('whatsapp_numeros')->where('organizacao_id', $organizacaoId)->where('estado', 'ligado')->first();

        return $numero === null ? null : new NumeroWhatsApp((string) $numero->phone_number_id, Crypt::decryptString((string) $numero->token_cifrado));
    }

    public function ligar(int $organizacaoId, CredenciaisNumeroWhatsApp $credenciais, array $templates): void
    {
        DB::table('whatsapp_numeros')->upsert([[
            'organizacao_id' => $organizacaoId,
            'waba_id' => $credenciais->wabaId,
            'phone_number_id' => $credenciais->phoneNumberId,
            'nome_exibicao' => $credenciais->nomeExibicao,
            'token_cifrado' => Crypt::encryptString($credenciais->token),
            'templates' => json_encode($templates, JSON_THROW_ON_ERROR),
            'estado' => 'ligado',
            'ligado_em' => now(),
            'desligado_em' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['organizacao_id'], ['waba_id', 'phone_number_id', 'nome_exibicao', 'token_cifrado', 'templates', 'estado', 'ligado_em', 'desligado_em', 'updated_at']);
    }

    public function desligar(int $organizacaoId): void
    {
        DB::table('whatsapp_numeros')->where('organizacao_id', $organizacaoId)->update([
            'estado' => 'desligado', 'token_cifrado' => null, 'desligado_em' => now(), 'updated_at' => now(),
        ]);
    }
}
