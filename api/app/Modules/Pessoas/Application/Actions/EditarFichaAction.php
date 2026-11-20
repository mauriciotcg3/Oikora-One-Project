<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Consultas\BuscaDenominacao;
use App\Modules\Pessoas\Application\Consultas\Fichas;
use Illuminate\Support\Facades\Log;

/**
 * Edição da ficha por seção (CA-14; RN-10; OIK-ADR-0004 §5, §7; OIK-ADR-0005 §2): `pessoas.editar` na
 * igreja da pessoa; seção de categoria sensível exige o `…editar` da categoria, senão 422 por campo. A
 * auditoria leva só os nomes dos campos que mudaram, nunca os valores.
 *
 * @phpstan-import-type ContatoEditado from EdicaoDeFichas
 */
#[RequerPermissao(Permissao::PessoasEditar)]
#[AuditaCom(EventoAuditoria::PessoaAtualizada)]
final readonly class EditarFichaAction
{
    /** Seção → categoria (CampoFicha). */
    public const CATEGORIAS = [
        'pessoais' => 'geral',
        'documentos' => 'documentos',
        'contatos' => 'contatos',
        'endereco' => 'contatos',
        'saude' => 'saude',
        'eclesiasticos' => 'fe',
        'adicionais' => 'geral',
    ];

    /** Categoria sensível → permissão de escrita. */
    public const EDICAO = [
        'saude' => Permissao::SaudeEditar,
        'documentos' => Permissao::DocumentosEditar,
        'contatos' => Permissao::ContatosEditar,
        'fe' => Permissao::FeEditar,
    ];

    public function __construct(
        private AlcanceAtual $alcance,
        private Fichas $fichas,
        private BuscaDenominacao $busca,
        private EdicaoDeFichas $edicao,
        private Auditoria $auditoria,
    ) {}

    /**
     * @param  string  $secao  chave de `CATEGORIAS`
     * @param  array<string, mixed>  $dados  campos da seção já validados e normalizados pela borda
     */
    public function executar(string $uuid, string $secao, array $dados): Resultado
    {
        $pessoa = $this->fichas->localizar($uuid);
        if ($pessoa === null) {
            return $this->busca->existe($uuid) ? $this->negar(Permissao::PessoasVer) : Resultado::falha(404, 'pessoa_inexistente');
        }

        $igrejaId = $pessoa['igreja_id'];
        if (! $this->alcance->pode(Permissao::PessoasEditar, $igrejaId)) {
            return $this->negar(Permissao::PessoasEditar);
        }

        $permissao = self::EDICAO[self::CATEGORIAS[$secao]] ?? null;
        if ($permissao !== null && ! $this->alcance->pode($permissao, $igrejaId)) {
            Log::info('authz.denied', ['permissao' => $permissao->value]);

            return Resultado::invalido(array_fill_keys(array_keys($dados), 'sem_permissao_categoria'));
        }

        if (isset($dados['cpf']) && is_string($dados['cpf']) && $this->busca->cpfEmUso($dados['cpf'], $pessoa['id'])) {
            return Resultado::invalido(['cpf' => 'cpf_em_uso']);
        }

        $ator = $this->alcance->ator();
        $alterados = match ($secao) {
            'contatos' => $this->edicao->substituirContatos($ator->organizacaoId, $pessoa['id'], $this->contatos($dados)),
            'endereco' => $this->edicao->atualizarEndereco($ator->organizacaoId, $pessoa['id'], $this->textos($dados)),
            default => $this->edicao->atualizarCampos($pessoa['id'], $this->escalares($dados)),
        };

        if ($alterados !== []) {
            $this->auditoria->registrar(
                evento: EventoAuditoria::PessoaAtualizada,
                organizacaoId: $ator->organizacaoId,
                alvoTipo: 'pessoa',
                alvoId: $pessoa['uuid'],
                acessoId: $ator->acessoId,
                contaId: $ator->contaId,
                igrejaId: $igrejaId,
                campos: $alterados,
            );
        }

        return Resultado::ok(['alterados' => $alterados]);
    }

    private function negar(Permissao $permissao): Resultado
    {
        Log::info('authz.denied', ['permissao' => $permissao->value]);

        return Resultado::falha(403, 'authz.denied');
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return list<ContatoEditado>
     */
    private function contatos(array $dados): array
    {
        /** @var list<ContatoEditado> $contatos */
        $contatos = $dados['contatos'] ?? [];

        return $contatos;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, string|null>
     */
    private function textos(array $dados): array
    {
        return array_map(static fn (mixed $valor): ?string => is_string($valor) ? $valor : null, $dados);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, string|bool|null>
     */
    private function escalares(array $dados): array
    {
        return array_map(static fn (mixed $valor): string|bool|null => is_string($valor) || is_bool($valor) ? $valor : null, $dados);
    }
}
