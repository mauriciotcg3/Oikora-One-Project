<?php

namespace App\Modules\Pessoas\Application\Consultas;

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use App\Modules\Pessoas\Application\Actions\FichaParaEditar;
use App\Modules\Pessoas\Application\Actions\RegistrosDaFicha;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Application\Configuracao\Resultado;

/**
 * Leitura da linha do tempo, dos relacionamentos e das funções da ficha (T-29), com `pessoas.ver` na
 * igreja da pessoa. Motivos e funções saem pelo nome do item da lista; o autor, pelo acesso e pelo nome
 * da Pessoa ligada a ele (CA-17).
 */
final readonly class LerRegistros
{
    public function __construct(
        private FichaParaEditar $ficha,
        private RegistrosDaFicha $registros,
        private ListasDeValores $listas,
        private AcessosDaOrganizacao $acessos,
        private BuscaDenominacao $busca,
    ) {}

    public function linhaDoTempo(string $uuid): Resultado
    {
        $pessoa = $this->ficha->localizar($uuid, Permissao::PessoasVer);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }

        $eventos = $this->registros->linhaDoTempo($pessoa['id']);
        $motivos = $this->listas->nomes(array_values(array_filter(array_column($eventos, 'motivo_id'))));
        $acessoIds = array_values(array_unique(array_filter(array_column($eventos, 'autor_acesso_id'))));
        $uuidsDosAcessos = $this->acessos->uuids($acessoIds);
        $pessoasDosAcessos = $this->acessos->pessoas($acessoIds);
        $autores = $this->busca->resumos(array_values(array_unique($pessoasDosAcessos)));

        return Resultado::ok(['data' => array_map(fn (array $evento): array => [
            'uuid' => $evento['uuid'],
            'tipo' => $evento['tipo'],
            'ocorrido_em' => $evento['ocorrido_em'],
            'origem' => $evento['origem'],
            'motivo' => $evento['motivo_id'] === null ? null : ($motivos[$evento['motivo_id']] ?? null),
            'descricao' => $evento['motivo_texto'],
            'dados' => $evento['dados'],
            'autor' => $evento['autor_acesso_id'] === null ? null : [
                'acesso' => $uuidsDosAcessos[$evento['autor_acesso_id']] ?? null,
                'nome' => $autores[$pessoasDosAcessos[$evento['autor_acesso_id']] ?? 0]['nome'] ?? null,
            ],
        ], $eventos)]);
    }

    public function relacionamentos(string $uuid): Resultado
    {
        $pessoa = $this->ficha->localizar($uuid, Permissao::PessoasVer);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }

        // A relacionada pode ser de outra igreja: só a projeção restrita da busca na denominação (T-14).
        $relacionamentos = $this->registros->relacionamentos($pessoa['id']);
        $resumos = $this->busca->resumos(array_values(array_unique(array_column($relacionamentos, 'relacionada_id'))));

        return Resultado::ok(['data' => array_map(static fn (array $r): array => [
            'uuid' => $r['uuid'],
            'tipo' => $r['tipo'],
            'descricao' => $r['descricao'],
            'relacionada' => [
                'uuid' => $resumos[$r['relacionada_id']]['uuid'] ?? null,
                'nome' => $resumos[$r['relacionada_id']]['nome'] ?? null,
            ],
        ], $relacionamentos)]);
    }

    public function funcoes(string $uuid): Resultado
    {
        $pessoa = $this->ficha->localizar($uuid, Permissao::PessoasVer);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }

        $funcoes = $this->registros->funcoes($pessoa['id']);
        $ids = array_values(array_unique(array_column($funcoes, 'funcao_id')));
        $nomes = $this->listas->nomes($ids);
        $uuids = $this->listas->uuids($ids);

        return Resultado::ok(['data' => array_map(static fn (array $funcao): array => [
            'uuid' => $funcao['uuid'],
            'funcao' => ['uuid' => $uuids[$funcao['funcao_id']] ?? null, 'nome' => $nomes[$funcao['funcao_id']] ?? null],
            'desde' => $funcao['desde'],
            'ate' => $funcao['ate'],
        ], $funcoes)]);
    }
}
