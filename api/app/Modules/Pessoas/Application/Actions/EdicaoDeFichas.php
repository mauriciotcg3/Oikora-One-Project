<?php

namespace App\Modules\Pessoas\Application\Actions;

/**
 * Persistência da edição da ficha por seção, na transação da requisição. Cada método devolve as chaves
 * de `CampoFicha` que mudaram de fato (comparando valores já decifrados), para a auditoria.
 *
 * @phpstan-type ContatoEditado array{tipo: string, valor: string, e_whatsapp: bool, principal: bool}
 */
interface EdicaoDeFichas
{
    /**
     * @param  array<string, string|bool|null>  $campos  colunas de `pessoas`
     * @return list<string>
     */
    public function atualizarCampos(int $id, array $campos): array;

    /**
     * Substitui o conjunto de contatos. Contato que continua (mesmo tipo e valor) mantém a marca de
     * descadastro e de falha de entrega.
     *
     * @param  list<ContatoEditado>  $contatos
     * @return list<string>
     */
    public function substituirContatos(int $organizacaoId, int $id, array $contatos): array;

    /**
     * @param  array<string, string|null>  $endereco
     * @return list<string>
     */
    public function atualizarEndereco(int $organizacaoId, int $id, array $endereco): array;
}
