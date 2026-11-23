<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Destinatario;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Identidade\Application\Actions\ConvidarAction;
use App\Modules\Identidade\Contracts\ConviteDoProvisionamento;
use DateTimeImmutable;
use Illuminate\Support\Str;

/** Mesmo convite do painel (T-22), sem ator: o provisionamento é a autoridade (OIK-ADR-0004 §3). */
final readonly class ConvidarAdministradorDoProvisionamento implements ConviteDoProvisionamento
{
    public function __construct(
        private Convites $convites,
        private Avisos $avisos,
    ) {}

    public function convidarAdministrador(int $organizacaoId, int $pessoaId, int $papelAdministradorId): array
    {
        $token = Str::random(48);
        $convite = $this->convites->emitir(
            $organizacaoId,
            $pessoaId,
            $papelAdministradorId,
            'organizacao',
            null,
            hash('sha256', $token),
            new DateTimeImmutable('+'.ConvidarAction::VALIDADE_HORAS.' hours'),
            null,
        );

        $this->avisos->registrar(
            TipoAviso::ConvitePainel,
            [Destinatario::pessoa($pessoaId, "/convites/{$token}")],
            new Referencia('convite', $convite['uuid']),
        );

        return ['uuid' => $convite['uuid'], 'token' => $token];
    }

    public function jaConvidada(int $pessoaId): bool
    {
        return $this->convites->pendente($pessoaId) || $this->convites->pessoaTemAcesso($pessoaId);
    }
}
