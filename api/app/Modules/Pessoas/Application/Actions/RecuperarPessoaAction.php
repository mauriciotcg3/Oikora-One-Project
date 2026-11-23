<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Modules\Pessoas\Application\Lixeira\Lixeira;
use DateTimeImmutable;

/**
 * Recuperar da lixeira em até 30 dias (RN-19): a ficha volta com todos os dados. A absorvida por
 * mesclagem não se recupera por aqui (o caminho é desfazer a mesclagem, U-33).
 */
#[RequerPermissao(Permissao::PessoasExcluir)]
#[AuditaCom(EventoAuditoria::PessoaRecuperada)]
final readonly class RecuperarPessoaAction
{
    public function __construct(
        private AlcanceAtual $alcance,
        private Lixeira $lixeira,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $uuid): Resultado
    {
        $pessoa = $this->lixeira->localizar($uuid, true);
        if ($pessoa === null || $pessoa['excluida_em'] === null || $pessoa['exclusao_motivo'] === 'mesclagem') {
            return Resultado::falha(404, 'pessoa_fora_da_lixeira');
        }
        if (! $this->alcance->pode(Permissao::PessoasExcluir, $pessoa['igreja_id'])) {
            return FichaParaEditar::negar(Permissao::PessoasExcluir);
        }

        $dias = (int) config('lgpd.dias_lixeira', 30);
        if (new DateTimeImmutable($pessoa['excluida_em']) < new DateTimeImmutable("-{$dias} days")) {
            return Resultado::falha(422, 'prazo_de_recuperacao_vencido');
        }

        $ator = $this->alcance->ator();
        $this->lixeira->recuperar($pessoa['id']);
        $this->lixeira->evento($ator->organizacaoId, $pessoa['id'], 'recuperada', $ator->acessoId);
        $this->auditoria->registrar(
            evento: EventoAuditoria::PessoaRecuperada,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            igrejaId: $pessoa['igreja_id'],
        );

        return Resultado::ok([], 204);
    }
}
