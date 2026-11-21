<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\ListasDeValores;
use App\Modules\Pessoas\Application\Configuracao\Resultado;

/**
 * Define (ou limpa) o tipo de membro, item ativo da lista `tipo_membro` conferido pela borda
 * (P FR-013; CA-18). A mudança entra na linha do tempo como `tipo_membro_alterado`, com de/para pelos
 * uuids dos itens da lista, nunca pelo nome (texto livre).
 */
#[RequerPermissao(Permissao::PessoasEditar)]
#[AuditaCom(EventoAuditoria::PessoaAtualizada)]
final readonly class DefinirTipoMembroAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private FichaParaEditar $ficha,
        private RegistrosDaFicha $registros,
        private VinculosDePessoas $vinculos,
        private ListasDeValores $listas,
        private Auditoria $auditoria,
    ) {}

    /** @param  int|null  $tipoMembroId  item ativo da lista `tipo_membro`, ou null para limpar */
    public function executar(string $uuid, ?int $tipoMembroId): Resultado
    {
        $pessoa = $this->ficha->localizar($uuid);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }

        $atual = $this->registros->tipoMembro($pessoa['id']);
        if ($atual === $tipoMembroId) {
            return Resultado::ok();
        }

        $ator = $this->alcance->ator();
        $this->registros->definirTipoMembro($pessoa['id'], $tipoMembroId);
        $this->vinculos->registrarEvento($ator->organizacaoId, $pessoa['id'], 'tipo_membro_alterado', null, [
            'de' => $this->uuid($atual),
            'para' => $this->uuid($tipoMembroId),
        ], $ator->acessoId);
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaAtualizada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
            campos: ['tipo_membro'],
        );

        return Resultado::ok();
    }

    private function uuid(?int $id): ?string
    {
        return $id === null ? null : $this->listas->uuids([$id])[$id] ?? null;
    }
}
