<?php

namespace App\Modules\Identidade\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Contracts\ConcessaoDeAcesso;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Destinatario;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Identidade\Application\Convites;
use App\Modules\Pessoas\Contracts\ContatosDaPessoa;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Convite ao painel (OIK-ADR-0003 §5; RN-02, RN-12; CA-02): Pessoa + papel + escopo, com a anti-escalada
 * da atribuição (OIK-ADR-0004 §3). Link de uso único, token aleatório guardado só em hash, válido por 72h.
 * Sai pelo aviso `convite.painel` nos canais que a Pessoa tiver (OIK-ADR-0008 §12).
 */
#[RequerPermissao(Permissao::AcessosGerir)]
#[AuditaCom(EventoAuditoria::ConviteEmitido)]
final readonly class ConvidarAction
{
    public const VALIDADE_HORAS = 72;

    public function __construct(
        private AlcanceAtual $alcance,
        private Convites $convites,
        private ContatosDaPessoa $pessoas,
        private ConcessaoDeAcesso $concessao,
        private Avisos $avisos,
        private Auditoria $auditoria,
    ) {}

    public function executar(string $pessoaUuid, string $papelUuid, EscopoTipo $tipo, ?string $escopoUuid): Resultado
    {
        // Permissão de governança: vale só com escopo organização (como acessos e papéis, T-15).
        if (! $this->alcance->temNaOrganizacao(Permissao::AcessosGerir)) {
            Log::info('authz.denied', ['permissao' => Permissao::AcessosGerir->value]);

            return Resultado::falha(403, 'authz.denied');
        }

        $pessoa = $this->pessoas->paraConvite($pessoaUuid);
        if ($pessoa === null) {
            return Resultado::invalido(['pessoa' => 'pessoa_inexistente']);
        }
        if ($pessoa['email'] === null && $pessoa['celular'] === null) {
            return Resultado::invalido(['pessoa' => 'pessoa_sem_contato']);
        }
        if ($this->convites->pessoaTemAcesso($pessoa['id'])) {
            return Resultado::falha(422, 'pessoa_ja_tem_acesso', ['mensagem' => 'Esta pessoa já tem acesso ao painel.']);
        }
        if ($this->convites->pendente($pessoa['id'])) {
            return Resultado::falha(422, 'convite_pendente', ['mensagem' => 'Já existe um convite válido para esta pessoa. Revogue-o para enviar outro.']);
        }

        $concessao = $this->concessao->conferir($papelUuid, $tipo, $escopoUuid);
        if (is_string($concessao)) {
            return $concessao === 'escalada_bloqueada'
                ? Resultado::falha(403, 'escalada_bloqueada')
                : Resultado::invalido([$concessao === 'papel_inexistente' ? 'papel' : 'escopo' => $concessao]);
        }

        $ator = $this->alcance->ator();
        $token = Str::random(48);
        $expiraEm = new DateTimeImmutable('+'.self::VALIDADE_HORAS.' hours');
        $convite = $this->convites->emitir(
            $ator->organizacaoId,
            $pessoa['id'],
            $concessao['papel_id'],
            $concessao['escopo_tipo'],
            $concessao['escopo_id'],
            hash('sha256', $token),
            $expiraEm,
            $ator->acessoId,
        );

        $this->avisos->registrar(
            TipoAviso::ConvitePainel,
            [Destinatario::pessoa($pessoa['id'], "/convites/{$token}")],
            new Referencia('convite', $convite['uuid']),
        );
        $this->auditoria->registrar(
            evento: EventoAuditoria::ConviteEmitido,
            organizacaoId: $ator->organizacaoId,
            alvoTipo: 'pessoa',
            alvoId: $pessoa['uuid'],
            acessoId: $ator->acessoId,
            contaId: $ator->contaId,
            metadados: [
                'convite_id' => $convite['uuid'],
                'papel_id' => $concessao['papel_id'],
                'escopo_tipo' => $concessao['escopo_tipo'],
                'escopo_id' => $concessao['escopo_id'],
            ],
        );

        return Resultado::ok([
            'uuid' => $convite['uuid'],
            'estado' => 'enviado',
            'expira_em' => $expiraEm->format(DATE_ATOM),
        ], 201);
    }
}
