<?php

namespace App\Modules\Pessoas\Application\Consultas;

/**
 * Leitura paginada de Pessoa pelo filtro global de alcance (OIK-ADR-0004 §5), sem a lixeira. Só traz os
 * contatos principais quando pedidos: a consulta não seleciona categoria que ninguém pode ver.
 *
 * @phpstan-type LinhaLista array{id: int, uuid: string, nome: string, igreja_id: int, vinculo: string|null, situacao: string|null, tipo_membro_id: int|null, nascimento: string|null, matricula: string|null, entrada_em: string|null, celular?: string|null, email?: string|null}
 */
interface ListaDePessoas
{
    /** @return array{itens: list<LinhaLista>, total: int} */
    public function buscar(FiltroPessoas $filtro, bool $comContatos): array;
}
