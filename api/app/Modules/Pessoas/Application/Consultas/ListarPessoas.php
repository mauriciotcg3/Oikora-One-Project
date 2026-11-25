<?php

namespace App\Modules\Pessoas\Application\Consultas;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Domain\Situacao;
use App\Modules\Pessoas\Domain\Vinculo;

/**
 * Lista de pessoas (P FR-014; U-17; RN-14; CA-19): 25/50/100 por página, ordenação e os filtros
 * igreja, vínculo, situação, tipo de membro, função, ficha incompleta e aniversariantes do mês. O
 * alcance vem do filtro global; a lixeira fica de fora. Contatos (categoria sensível) só nas linhas cuja
 * igreja quem lê pode ver nessa categoria. Todo total informa o critério (`meta.criterio`); "pessoas
 * ativas por vínculo e igreja" é a contagem oficial (RN-14).
 */
final readonly class ListarPessoas
{
    public function __construct(
        private AlcanceAtual $alcance,
        private ListaDePessoas $lista,
        private ResolverFiltroPessoas $resolverFiltro,
        private CatalogoIgrejas $igrejas,
        private ListasDeValores $listas,
    ) {}

    public function permitido(): bool
    {
        return $this->alcance->igrejasCom(Permissao::PessoasVer) !== [];
    }

    /**
     * @param  array{igreja?: string, vinculo?: string, situacao?: string, tipo_membro?: string, funcao?: string, ficha_incompleta?: bool, aniversariantes_mes?: int}  $filtros
     * @return array{itens: list<array<string, mixed>>, total: int, criterio: array{descricao: string, oficial: bool, filtros: array<string, mixed>}}
     */
    public function executar(array $filtros, int $pagina, int $porPagina, string $ordem): array
    {
        $filtro = $this->resolverFiltro->resolver($filtros, $pagina, $porPagina, $ordem);

        $comContatos = $this->alcance->igrejasCom(Permissao::ContatosVer);
        $resultado = $this->lista->buscar($filtro, $comContatos !== []);
        $linhas = $resultado['itens'];

        $resumoIgrejas = $this->igrejas->resumo(array_values(array_unique(array_column($linhas, 'igreja_id'))));
        $tipos = array_values(array_unique(array_filter(array_column($linhas, 'tipo_membro_id'), 'is_int')));
        $nomesTipos = $this->listas->nomes($tipos);
        $uuidsTipos = $this->listas->uuids($tipos);

        $itens = array_map(function (array $linha) use ($comContatos, $resumoIgrejas, $nomesTipos, $uuidsTipos): array {
            $tipo = $linha['tipo_membro_id'];
            $item = [
                'uuid' => $linha['uuid'],
                'nome' => $linha['nome'],
                'igreja' => $resumoIgrejas[$linha['igreja_id']] ?? null,
                'vinculo' => $linha['vinculo'],
                'situacao' => $linha['situacao'],
                'tipo_membro' => $tipo === null ? null : ['uuid' => $uuidsTipos[$tipo] ?? null, 'nome' => $nomesTipos[$tipo] ?? null],
                'nascimento' => $linha['nascimento'],
                'matricula' => $linha['matricula'],
                'entrada_em' => $linha['entrada_em'],
            ];
            // Sensível (OIK-ADR-0004 §7): sem `contatos.ver` na igreja da linha, a chave nem aparece.
            if (in_array($linha['igreja_id'], $comContatos, true)) {
                $item['contatos'] = ['celular' => $linha['celular'] ?? null, 'email' => $linha['email'] ?? null];
            }
            $item['meta'] = ['acoes' => $this->acoes($linha['igreja_id'])];

            return $item;
        }, $linhas);

        return ['itens' => $itens, 'total' => $resultado['total'], 'criterio' => $this->criterio($filtros)];
    }

    /** @return list<string> */
    private function acoes(int $igrejaId): array
    {
        $acoes = ['abrir'];
        foreach (['editar' => Permissao::PessoasEditar, 'excluir' => Permissao::PessoasExcluir, 'exportar' => Permissao::PessoasExportar] as $acao => $permissao) {
            if ($this->alcance->pode($permissao, $igrejaId)) {
                $acoes[] = $acao;
            }
        }

        return $acoes;
    }

    /**
     * RN-14: o número exibido diz o que conta. Só situação `ativa`, com ou sem vínculo e igreja, é a
     * contagem oficial; o resto é um recorte.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{descricao: string, oficial: bool, filtros: array<string, mixed>}
     */
    private function criterio(array $filtros): array
    {
        $partes = ['Pessoas fora da lixeira, nas igrejas que você pode ver'];
        $rotulos = [
            'igreja' => 'igreja escolhida',
            'vinculo' => 'vínculo '.(isset($filtros['vinculo']) ? Vinculo::from($filtros['vinculo'])->rotulo() : ''),
            'situacao' => 'situação '.($filtros['situacao'] ?? ''),
            'tipo_membro' => 'tipo de membro escolhido',
            'funcao' => 'função ativa escolhida',
            'ficha_incompleta' => 'ficha incompleta (obrigatórios do vínculo)',
            'aniversariantes_mes' => 'aniversariantes do mês '.($filtros['aniversariantes_mes'] ?? ''),
        ];
        foreach ($rotulos as $chave => $rotulo) {
            if (isset($filtros[$chave]) && $filtros[$chave] !== false) {
                $partes[] = $rotulo;
            }
        }

        $restantes = array_diff(array_keys(array_filter($filtros, static fn (mixed $v): bool => $v !== null && $v !== false)), ['igreja', 'vinculo', 'situacao']);
        $oficial = ($filtros['situacao'] ?? null) === Situacao::Ativa->value && $restantes === [];

        return [
            'descricao' => $oficial ? 'Pessoas ativas por vínculo e igreja (contagem oficial, RN-14): '.implode('; ', $partes) : implode('; ', $partes),
            'oficial' => $oficial,
            'filtros' => $filtros,
        ];
    }
}
