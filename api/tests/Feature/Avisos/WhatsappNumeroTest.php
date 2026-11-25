<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Application\CadastroNumeroWhatsApp;
use App\Modules\Avisos\Application\CredenciaisNumeroWhatsApp;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->org = criarOrganizacao();
    $this->igreja = criarIgreja($this->org);
    $this->cadastro = new class implements CadastroNumeroWhatsApp
    {
        public function urlDeAutorizacao(string $estado): string
        {
            return 'https://meta.test/oauth?state='.urlencode($estado);
        }

        public function trocarCodigo(string $codigo): CredenciaisNumeroWhatsApp
        {
            expect($codigo)->toBe('codigo-meta');

            return new CredenciaisNumeroWhatsApp('waba-1', 'phone-1', 'Oikora', 'token-secreto');
        }

        public function garantirTemplates(CredenciaisNumeroWhatsApp $credenciais): array
        {
            return ['convite_acesso', 'exportacao_pronta'];
        }
    };
    app()->instance(CadastroNumeroWhatsApp::class, $this->cadastro);
});

it('liga e desliga o numero da organizacao sem expor o token na auditoria', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::AvisosWhatsappGerir]);

    $redirecionamento = $this->get('/api/v1/painel/avisos/whatsapp/oauth')->assertRedirect();
    parse_str((string) parse_url((string) $redirecionamento->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->getJson('/api/v1/painel/avisos/whatsapp/oauth/retorno?code=codigo-meta&state='.$query['state'])
        ->assertCreated()
        ->assertJsonPath('data.estado', 'ligado')
        ->assertJsonPath('data.phone_number_id', 'phone-1');

    $numero = DB::connection('pgsql_dono')->table('whatsapp_numeros')->where('organizacao_id', $this->org)->first();
    expect($numero)->not->toBeNull()
        ->and($numero->token_cifrado)->not->toBe('token-secreto')
        ->and(Crypt::decryptString($numero->token_cifrado))->toBe('token-secreto')
        ->and(json_decode($numero->templates, true))->toBe(['convite_acesso', 'exportacao_pronta']);

    $this->deleteJson('/api/v1/painel/avisos/whatsapp')->assertNoContent();
    $numero = DB::connection('pgsql_dono')->table('whatsapp_numeros')->where('organizacao_id', $this->org)->first();
    expect($numero->estado)->toBe('desligado')->and($numero->token_cifrado)->toBeNull();

    $auditoria = json_encode([
        ...auditoria('avisos.whatsapp_numero_ligado'),
        ...auditoria('avisos.whatsapp_numero_desligado'),
    ], JSON_THROW_ON_ERROR);
    expect($auditoria)->not->toContain('token-secreto');
});

it('exige a permissao de gerir whatsapp', function (): void {
    entrarNoPainel($this->org, $this->igreja, [Permissao::PessoasVer]);

    $this->getJson('/api/v1/painel/avisos/whatsapp/oauth')->assertForbidden()->assertJsonPath('codigo', 'authz.denied');
});
