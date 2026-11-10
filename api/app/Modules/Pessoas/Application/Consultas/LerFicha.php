<?php

namespace App\Modules\Pessoas\Application\Consultas;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Pessoas\Domain\CampoFicha;

/**
 * Ficha com projeção por alcance e por categoria sensível (OIK-ADR-0004 §5, §7; RN-09, RN-10; CA-03).
 * Primeiro localiza pelo filtro de alcance; depois lê só as colunas das categorias que quem consulta vê
 * na igreja da pessoa. Fora do alcance: 403 se a pessoa existe na organização, 404 se não existe.
 */
final readonly class LerFicha
{
    /** Categoria sensível → permissão de leitura. */
    public const LEITURA = [
        'saude' => Permissao::SaudeVer,
        'documentos' => Permissao::DocumentosVer,
        'contatos' => Permissao::ContatosVer,
        'fe' => Permissao::FeVer,
    ];

    /** Ações sobre o registro (`meta.acoes`, OIK-ADR-0006 §9). */
    private const ACOES = [
        'editar' => Permissao::PessoasEditar,
        'excluir' => Permissao::PessoasExcluir,
        'mesclar' => Permissao::PessoasMesclar,
        'transferir' => Permissao::TransferenciasSolicitar,
    ];

    public function __construct(
        private AlcanceAtual $alcance,
        private Fichas $fichas,
        private BuscaDenominacao $busca,
        private CatalogoIgrejas $igrejas,
    ) {}

    /** @throws FichaNegada|FichaInexistente */
    public function executar(string $uuid): Ficha
    {
        $pessoa = $this->fichas->localizar($uuid);

        if ($pessoa === null) {
            throw $this->busca->existe($uuid) ? new FichaNegada : new FichaInexistente;
        }

        $igrejaId = $pessoa['igreja_id'];
        $categorias = ['geral'];
        foreach (self::LEITURA as $categoria => $permissao) {
            if ($this->alcance->pode($permissao, $igrejaId)) {
                $categorias[] = $categoria;
            }
        }

        $colunas = ['uuid', 'igreja_id', 'vinculo', 'situacao'];
        foreach (CampoFicha::cases() as $campo) {
            if ($campo->tabela() === 'pessoas' && in_array($campo->categoria(), $categorias, true)) {
                $colunas[] = $campo->value;
            }
        }

        $campos = $this->fichas->ler($pessoa['id'], $colunas);
        $contatosVisiveis = in_array('contatos', $categorias, true);

        $acoes = [];
        foreach (self::ACOES as $acao => $permissao) {
            if ($this->alcance->pode($permissao, $igrejaId)) {
                $acoes[] = $acao;
            }
        }

        return new Ficha(
            uuid: $pessoa['uuid'],
            igreja: $this->igrejas->resumo([$igrejaId])[$igrejaId] ?? null,
            vinculo: is_string($campos['vinculo']) ? $campos['vinculo'] : null,
            situacao: is_string($campos['situacao']) ? $campos['situacao'] : null,
            campos: array_diff_key($campos, array_flip(['uuid', 'igreja_id', 'vinculo', 'situacao'])),
            contatos: $contatosVisiveis ? $this->fichas->contatos($pessoa['id']) : null,
            enderecos: $contatosVisiveis ? $this->fichas->enderecos($pessoa['id']) : null,
            categorias: array_values(array_diff($categorias, ['geral'])),
            acoes: $acoes,
        );
    }
}
