<?php

namespace App\Modules\Pessoas\Application\Actions;

/** Persistência do cadastro: ficha, contatos, evento na linha do tempo e pares "é outra pessoa". */
interface CadastroDePessoas
{
    /** @return array{id: int, uuid: string} */
    public function cadastrar(int $organizacaoId, DadosCadastroRapido $dados, int $autorAcessoId): array;

    /** Grava o par (menor id, maior id); repetir não duplica. */
    public function marcarNaoDuplicadas(int $organizacaoId, int $pessoaId, int $outraId, int $autorAcessoId): void;
}
