<?php

namespace App\Modules\Pessoas\Application\Mesclagem;

/**
 * Persistência da mesclagem e da lista de possíveis duplicados (RN-11, RN-23; plan.md §6). Roda na
 * transação da requisição, pelo filtro de alcance de Pessoa.
 *
 * @phpstan-type Filhos array{contatos: list<int>, enderecos: list<int>, relacionamentos: list<int>, funcoes: list<int>, eventos: list<int>}
 * @phpstan-type Par array{a: int, b: int, criterios: list<string>}
 */
interface Mesclagens
{
    /**
     * Fichas cruas (colunas de `pessoas`, sensíveis ainda cifradas), bloqueadas na ordem do id. Só as
     * que o filtro de alcance deixa ver e que não estão na lixeira.
     *
     * @param  list<string>  $uuids
     * @return array<string, array<string, mixed>> uuid → linha
     */
    public function bloquear(array $uuids): array;

    /** @return Filhos */
    public function filhos(int $pessoaId): array;

    /**
     * Copia da absorvida para a mantida os campos escolhidos, depois de limpar na absorvida `cpf`,
     * `cpf_hash` e `matricula` (U-30): as unicidades não quebram.
     *
     * @param  list<string>  $campos  só colunas de `pessoas` do catálogo
     */
    public function aplicar(int $mantidaId, int $absorvidaId, array $campos): void;

    /**
     * Move contatos, endereços, relacionamentos, funções e eventos da absorvida para a mantida.
     * Relacionamentos entre as duas são removidos (não há relacionamento consigo); repetidos de tipo fixo
     * ficam só na mantida.
     *
     * @return Filhos o que foi movido (os removidos não entram)
     */
    public function moverFilhos(int $absorvidaId, int $mantidaId): array;

    public function marcarAbsorvida(int $absorvidaId, ?int $porAcessoId): void;

    /** @param  array<string, mixed>  $dados */
    public function evento(int $organizacaoId, int $pessoaId, string $tipo, ?int $autorAcessoId, array $dados): void;

    /**
     * Pares pela RN-23 (nome + nascimento, CPF ou e-mail iguais), sem a lixeira e sem os marcados como
     * "não duplicados", entre pessoas que quem consulta vê.
     *
     * @return array{itens: list<Par>, total: int}
     */
    public function pares(int $pagina, int $porPagina): array;

    /**
     * @param  list<int>  $ids
     * @return array<int, array{uuid: string, nome: string, igreja_id: int, nascimento: string|null, vinculo: string|null}>
     */
    public function resumos(array $ids): array;
}
