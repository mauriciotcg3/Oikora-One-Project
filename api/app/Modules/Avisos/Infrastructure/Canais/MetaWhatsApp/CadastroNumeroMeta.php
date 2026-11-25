<?php

namespace App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp;

use App\Modules\Avisos\Application\CadastroNumeroWhatsApp;
use App\Modules\Avisos\Application\CredenciaisNumeroWhatsApp;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class CadastroNumeroMeta implements CadastroNumeroWhatsApp
{
    public function urlDeAutorizacao(string $estado): string
    {
        return 'https://www.facebook.com/'.rawurlencode((string) config('services.meta_whatsapp.versao', 'v21.0')).'/dialog/oauth?'.http_build_query([
            'client_id' => $this->config('app_id'),
            'redirect_uri' => $this->config('oauth_redirect'),
            'state' => $estado,
            'response_type' => 'code',
            'scope' => 'business_management,whatsapp_business_management,whatsapp_business_messaging',
        ]);
    }

    public function trocarCodigo(string $codigo): CredenciaisNumeroWhatsApp
    {
        $token = Http::timeout(10)->acceptJson()->get('https://graph.facebook.com/'.config('services.meta_whatsapp.versao', 'v21.0').'/oauth/access_token', [
            'client_id' => $this->config('app_id'), 'client_secret' => $this->config('app_secret'),
            'redirect_uri' => $this->config('oauth_redirect'), 'code' => $codigo,
        ])->throw()->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('whatsapp.oauth_invalido');
        }
        $dados = Http::timeout(10)->withToken($token)->get('https://graph.facebook.com/'.config('services.meta_whatsapp.versao', 'v21.0').'/debug_token', ['input_token' => $token])->throw()->json('data.granular_scopes.0.target_ids');
        $waba = is_array($dados) ? ($dados[0] ?? null) : null;
        if (! is_string($waba)) {
            throw new RuntimeException('whatsapp.waba_ausente');
        }
        $numero = Http::timeout(10)->withToken($token)->get('https://graph.facebook.com/'.config('services.meta_whatsapp.versao', 'v21.0').'/'.$waba.'/phone_numbers')->throw()->json('data.0');
        if (! is_array($numero) || ! is_string($numero['id'] ?? null)) {
            throw new RuntimeException('whatsapp.numero_ausente');
        }

        return new CredenciaisNumeroWhatsApp($waba, $numero['id'], (string) ($numero['verified_name'] ?? ''), $token);
    }

    public function garantirTemplates(CredenciaisNumeroWhatsApp $credenciais): array
    {
        $nomes = ['convite_painel', 'servico_exportacao_pronta', 'servico_transferencia_solicitada'];
        foreach ($nomes as $nome) {
            Http::timeout(10)->withToken($credenciais->token)->post('https://graph.facebook.com/'.config('services.meta_whatsapp.versao', 'v21.0').'/'.$credenciais->wabaId.'/message_templates', [
                'name' => $nome, 'language' => (string) config('services.meta_whatsapp.idioma', 'pt_BR'), 'category' => 'UTILITY',
                'components' => [['type' => 'BODY', 'text' => '{{1}}'], ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'Abrir', 'url' => config('app.url').'/{{1}}']]]],
            ])->throw();
        }

        return $nomes;
    }

    private function config(string $chave): string
    {
        $valor = config('services.meta_whatsapp.'.$chave);
        if (! is_string($valor) || $valor === '') {
            throw new RuntimeException('whatsapp.provedor_nao_configurado');
        }

        return $valor;
    }
}
