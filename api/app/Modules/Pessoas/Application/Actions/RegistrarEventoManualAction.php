<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use DateTimeImmutable;

/**
 * Registro manual na linha do tempo, com data retroativa e autor (P FR-011; CA-17; OIK-ADR-0005 §1).
 * A descrição é dado de negócio da ficha; a auditoria leva só o nome do campo.
 */
#[RequerPermissao(Permissao::PessoasEditar)]
#[AuditaCom(EventoAuditoria::PessoaAtualizada)]
final readonly class RegistrarEventoManualAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private FichaParaEditar $ficha,
        private RegistrosDaFicha $registros,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid, DateTimeImmutable $ocorridoEm, string $descricao): Resultado
    {
        $pessoa = $this->ficha->localizar($uuid);
        if ($pessoa instanceof Resultado) {
            return $pessoa;
        }

        $ator = $this->alcance->ator();
        $evento = $this->registros->registrarManual($ator->organizacaoId, $pessoa['id'], $ocorridoEm, $descricao, $ator->acessoId);
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaAtualizada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
            campos: ['linha_do_tempo'],
        );

        return Resultado::ok(['uuid' => $evento['uuid']], 201);
    }
}
