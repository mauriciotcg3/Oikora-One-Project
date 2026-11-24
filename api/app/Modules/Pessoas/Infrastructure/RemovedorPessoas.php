<?php

namespace App\Modules\Pessoas\Infrastructure;

use App\Modules\Auditoria\Contracts\OperacoesDesfaziveis;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Avisos\Contracts\AvisosDaPessoa;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use App\Modules\Pessoas\Application\Lixeira\ExclusaoBloqueada;
use App\Modules\Pessoas\Application\Lixeira\RemovedorDePessoas;
use App\Shared\Arquivos\ArquivosPrivados;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Removedor de Pessoas (OIK-ADR-0005 §8; RN-19):
 * - `anonimizar`: apaga contatos, documentos, saúde, endereço, foto e textos livres; o nome vira
 *   "Pessoa removida"; vínculo, situação e datas ficam, para as contagens;
 * - `apagar`: remove a Pessoa e o que só existe por ela;
 * - nos dois: apaga snapshots de desfazer, tira o acesso desta organização e grava `exclusoes_executadas`.
 * Pessoa que teve acesso não some no `apagar` (a auditoria e o histórico do acesso apontam para ela):
 * é anonimizada, e o registro guarda o modo efetivo.
 */
final readonly class RemovedorPessoas implements RemovedorDePessoas
{
    public const NOME_REMOVIDA = 'Pessoa removida';

    /** Colunas de `pessoas` que o anonimizar limpa: documentos, saúde, foto e textos livres. */
    private const LIMPAR = [
        'cpf', 'cpf_hash', 'rg', 'rg_pais', 'necessidades_especiais', 'tipo_sanguineo', 'foto_path',
        'batizado_por', 'igreja_origem', 'conversao_canal', 'profissao', 'naturalidade', 'nacionalidade',
        'escolaridade', 'exclusao_motivo', 'matricula',
    ];

    public function __construct(
        private AlcanceAtual $alcance,
        private OperacoesDesfaziveis $operacoes,
        private AcessosDaOrganizacao $acessos,
        private AvisosDaPessoa $avisos,
        private ArquivosPrivados $arquivos,
    ) {}

    public function modo(): string
    {
        $modo = config('lgpd.modo_exclusao');
        if (in_array($modo, ['anonimizar', 'apagar'], true)) {
            return $modo;
        }

        if (app()->environment('production')) {
            Log::error(ExclusaoBloqueada::CODIGO);

            throw new ExclusaoBloqueada;
        }

        // Fora de produção não há dado real (OIK-ADR-0011 §4): anonimiza, e avisa no log.
        Log::warning('lgpd.erasure.modo_padrao_fora_de_producao', ['modo' => 'anonimizar']);

        return 'anonimizar';
    }

    public function remover(int $pessoaId, string $origem = 'purga'): string
    {
        $modo = $this->modo();
        $organizacaoId = (int) DB::scalar('select app_organizacao_atual()');

        // Rotina de sistema: lê e grava pelo model, com o alcance da organização inteira.
        return $this->alcance->comoSistema($organizacaoId, fn (): string => $this->removerComo($modo, $pessoaId, $origem));
    }

    private function removerComo(string $modo, int $pessoaId, string $origem): string
    {
        $pessoa = Pessoa::query()->whereKey($pessoaId)->toBase()->first(['id', 'uuid', 'organizacao_id', 'foto_path', 'excluida_em']);
        if ($pessoa === null) {
            return $modo;
        }

        $this->operacoes->expirarDaPessoa($pessoaId);
        $teveAcesso = $this->acessos->pessoaTeveAcesso($pessoaId);
        $this->acessos->revogarDaPessoa($pessoaId);
        $efetivo = $modo === 'apagar' && ! $teveAcesso ? 'apagar' : 'anonimizar';

        $this->avisos->remover($pessoaId, $efetivo);
        $this->apagarFoto($pessoa->foto_path);

        if ($efetivo === 'apagar') {
            foreach (['pessoa_contatos', 'pessoa_enderecos', 'eventos_linha_do_tempo'] as $tabela) {
                DB::table($tabela)->where('pessoa_id', $pessoaId)->delete();
            }
            // Convites, pares, funções, relacionamentos e transferências saem em cascata.
            Pessoa::query()->whereKey($pessoaId)->delete();
        } else {
            DB::table('pessoa_contatos')->where('pessoa_id', $pessoaId)->delete();
            DB::table('pessoa_enderecos')->where('pessoa_id', $pessoaId)->delete();
            DB::table('eventos_linha_do_tempo')->where('pessoa_id', $pessoaId)->update(['motivo_texto' => null, 'dados' => null, 'updated_at' => now()]);
            DB::table('relacionamentos')->where(fn ($q) => $q->where('pessoa_id', $pessoaId)->orWhere('relacionada_id', $pessoaId))
                ->whereNotNull('descricao')->update(['descricao' => 'removido', 'updated_at' => now()]);
            Pessoa::query()->whereKey($pessoaId)->update([
                ...array_fill_keys(self::LIMPAR, null),
                'nome' => self::NOME_REMOVIDA,
                'nome_busca' => 'pessoa removida',
                'updated_at' => now(),
            ]);
        }

        DB::table('exclusoes_executadas')->insertOrIgnore([
            'organizacao_id' => (int) $pessoa->organizacao_id,
            'pessoa_id' => $pessoaId,
            'pessoa_uuid' => (string) $pessoa->uuid,
            'modo' => $efetivo,
            'origem' => $origem,
            'pedida_em' => $pessoa->excluida_em,
            'executada_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Log::info('lgpd.erasure.executada', ['modo' => $efetivo, 'origem' => $origem]);

        return $efetivo;
    }

    private function apagarFoto(mixed $caminho): void
    {
        if (! is_string($caminho) || $caminho === '' || ! $this->arquivos->existe($caminho)) {
            return;
        }

        Storage::disk((string) config('oikora.arquivos.disco'))->delete($caminho);
    }
}
