<?php

namespace App\Modules\Organizacao\Application;

use App\Modules\Autorizacao\Contracts\PapeisProntos;
use App\Modules\Identidade\Contracts\ConviteDoProvisionamento;
use App\Modules\Organizacao\Domain\EstadoOrganizacao;
use App\Modules\Organizacao\Domain\Fuso;
use App\Modules\Pessoas\Contracts\PessoasDoProvisionamento;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Provisionamento da organização (plan.md §3.3, §4; OIK-ADR-0002 §5, OIK-ADR-0005 §4; RN-17). Roda na
 * conexão `pgsql_plataforma` (a única com `BYPASSRLS`, só para comandos administrativos), numa
 * transação: cria a organização, os papéis prontos, a primeira igreja, a Pessoa do primeiro
 * administrador e o convite dele (Administrador, escopo `organizacao`), e grava `auditoria_plataforma`.
 * Os outros módulos entram só pelos Contracts, com a organização nova no contexto. Repetir com o mesmo
 * identificador não cria nada.
 */
final readonly class ProvisionarOrganizacao
{
    public const CONEXAO = 'pgsql_plataforma';

    public const COMANDO = 'oikora:organizacao:provisionar';

    public function __construct(
        private PapeisProntos $papeis,
        private PessoasDoProvisionamento $pessoas,
        private ConviteDoProvisionamento $convites,
    ) {}

    /** @return array{criada: bool, organizacao: string, estado: string, convite: string|null} */
    public function executar(DadosProvisionamento $dados): array
    {
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $dados->identificador) || strlen($dados->identificador) > 60) {
            throw new InvalidArgumentException('identificador_invalido');
        }
        if (! Fuso::valido($dados->fuso)) {
            throw new InvalidArgumentException('fuso_invalido');
        }
        if ($dados->adminEmail === null && $dados->adminCelular === null) {
            throw new InvalidArgumentException('administrador_sem_contato');
        }

        $anterior = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONEXAO);

        try {
            return DB::transaction(fn (): array => $this->provisionar($dados));
        } finally {
            DB::setDefaultConnection($anterior);
        }
    }

    /** @return array{criada: bool, organizacao: string, estado: string, convite: string|null} */
    private function provisionar(DadosProvisionamento $dados): array
    {
        // Trava o identificador: duas execuções simultâneas não criam duas organizações.
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['provisionar:'.$dados->identificador]);

        $existente = DB::table('organizacoes')->where('identificador', $dados->identificador)->first(['uuid', 'estado']);
        if ($existente !== null) {
            Log::info('organizacao.provisionamento_repetido', ['identificador' => $dados->identificador]);

            return ['criada' => false, 'organizacao' => (string) $existente->uuid, 'estado' => (string) $existente->estado, 'convite' => null];
        }

        $estado = $dados->emMigracao ? EstadoOrganizacao::EmMigracao : EstadoOrganizacao::Ativa;
        $uuid = (string) Str::uuid();
        $organizacaoId = (int) DB::table('organizacoes')->insertGetId([
            'uuid' => $uuid,
            'identificador' => $dados->identificador,
            'nome' => $dados->nome,
            'estado' => $estado->value,
            'fuso' => $dados->fuso,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);

        $papeis = $this->papeis->criar($organizacaoId);
        $igrejaId = (int) DB::table('igrejas')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'nome' => $dados->igreja,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $pessoa = $this->pessoas->criarAdministrador($organizacaoId, $igrejaId, $dados->adminNome, $dados->adminEmail, $dados->adminCelular);
        $convite = $this->convites->convidarAdministrador($organizacaoId, $pessoa['id'], $papeis['administrador']);

        DB::table('auditoria_plataforma')->insert([
            'comando' => self::COMANDO,
            'operador' => $dados->operador,
            'motivo_codigo' => $dados->motivo,
            'organizacoes' => json_encode([$uuid], JSON_THROW_ON_ERROR),
            'request_id' => Context::get('request_id'),
        ]);
        Log::info('organizacao.provisionada', ['organizacao' => $uuid, 'estado' => $estado->value]);

        return ['criada' => true, 'organizacao' => $uuid, 'estado' => $estado->value, 'convite' => $convite['uuid']];
    }
}
