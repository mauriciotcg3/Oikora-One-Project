<?php

namespace App\Modules\Pessoas\Application\Configuracao;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Domain\ListaValores;

/**
 * Renomeia, reordena, desativa ou reativa um item (CA-30). O item não é excluído: quem já o usa continua
 * mostrando o nome; desativado, ele só deixa de ser aceito em cadastro novo.
 */
#[RequerPermissao(Permissao::PessoasConfigurar)]
#[AuditaCom(EventoAuditoria::ListaValorAtualizado)]
final readonly class EditarItemListaAction
{
    public function __construct(
        private Configuracao $configuracao,
        private ListasDeValores $listas,
    ) {}

    /** @param  array{nome?: string, ordem?: int, ativo?: bool}  $mudancas */
    public function executar(ListaValores $lista, string $uuid, array $mudancas): Resultado
    {
        if ($negada = $this->configuracao->negada()) {
            return $negada;
        }

        $antes = $this->listas->porUuid($lista, $uuid);
        if ($antes === null) {
            return Resultado::falha(404, 'item_inexistente');
        }

        if (isset($mudancas['nome']) && $this->listas->nomeEmUso($lista, $mudancas['nome'], $antes['id'])) {
            return Resultado::falha(422, 'nome_em_uso');
        }

        $depois = $this->listas->atualizar($antes['id'], $mudancas);

        $metadados = ['lista' => $lista->value];
        if ($antes['nome'] !== $depois['nome']) {
            $metadados['nome_alterado'] = true;
        }
        foreach (['ordem', 'ativo'] as $campo) {
            if ($antes[$campo] !== $depois[$campo]) {
                $metadados["{$campo}_antes"] = $antes[$campo];
                $metadados["{$campo}_depois"] = $depois[$campo];
            }
        }

        if (count($metadados) > 1) {
            $this->configuracao->auditar(EventoAuditoria::ListaValorAtualizado, 'lista_valor', $uuid, $metadados);
        }

        return Resultado::ok(['item' => $depois]);
    }
}
