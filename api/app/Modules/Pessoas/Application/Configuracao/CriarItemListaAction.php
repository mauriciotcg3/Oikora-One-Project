<?php

namespace App\Modules\Pessoas\Application\Configuracao;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Domain\ListaValores;

/** Inclui um item numa lista da organização (CA-30). Sem `ordem`, entra no fim da lista. */
#[RequerPermissao(Permissao::PessoasConfigurar)]
#[AuditaCom(EventoAuditoria::ListaValorCriado)]
final readonly class CriarItemListaAction
{
    public function __construct(
        private Configuracao $configuracao,
        private ListasDeValores $listas,
    ) {}

    public function executar(ListaValores $lista, string $nome, ?int $ordem): Resultado
    {
        if ($negada = $this->configuracao->negada()) {
            return $negada;
        }

        if ($this->listas->nomeEmUso($lista, $nome)) {
            return Resultado::falha(422, 'nome_em_uso');
        }

        $item = $this->listas->criar($this->configuracao->organizacaoId(), $lista, $nome, $ordem);
        $this->configuracao->auditar(EventoAuditoria::ListaValorCriado, 'lista_valor', $item['uuid'], [
            'lista' => $lista->value,
            'ordem_depois' => $item['ordem'],
        ]);

        return Resultado::ok(['item' => $item], 201);
    }
}
