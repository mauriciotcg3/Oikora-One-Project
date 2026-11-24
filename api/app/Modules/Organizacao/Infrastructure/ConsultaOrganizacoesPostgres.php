<?php

namespace App\Modules\Organizacao\Infrastructure;

use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Organizacao\Contracts\PerfilDeEnvio;
use App\Modules\Organizacao\Domain\Fuso;
use App\Shared\Tenancy\Contracts\OrganizacaoDisponivel;

final class ConsultaOrganizacoesPostgres implements ConsultaOrganizacoes, OrganizacaoDisponivel
{
    public function verificar(int $organizacaoId): bool
    {
        return Organizacao::query()
            ->whereKey($organizacaoId)
            ->whereIn('estado', ['ativa', 'em_migracao'])
            ->exists();
    }

    public function idsDisponiveis(): array
    {
        /** @var list<int> $ids */
        $ids = Organizacao::query()
            ->whereIn('estado', ['ativa', 'em_migracao'])
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    public function emMigracao(int $organizacaoId): bool
    {
        return Organizacao::query()->whereKey($organizacaoId)->where('estado', 'em_migracao')->exists();
    }

    public function perfilDeEnvio(int $organizacaoId): PerfilDeEnvio
    {
        $organizacao = Organizacao::query()->whereKey($organizacaoId)->firstOrFail(['uuid', 'nome', 'fuso', 'email_contato']);
        $email = $organizacao->getAttribute('email_contato');

        return new PerfilDeEnvio(
            uuid: (string) $organizacao->getAttribute('uuid'),
            nome: (string) $organizacao->getAttribute('nome'),
            fuso: (string) ($organizacao->getAttribute('fuso') ?: Fuso::PADRAO),
            responderPara: is_string($email) && $email !== '' ? $email : null,
        );
    }

    public function uuidDe(int $organizacaoId): string
    {
        return (string) Organizacao::query()->whereKey($organizacaoId)->value('uuid');
    }

    public function modoMatricula(int $organizacaoId): string
    {
        $modo = Organizacao::query()->whereKey($organizacaoId)->toBase()->value('modo_matricula');

        return is_string($modo) ? $modo : 'manual';
    }

    public function limiteWhatsappMensal(int $organizacaoId): ?int
    {
        $limite = Organizacao::query()->whereKey($organizacaoId)->toBase()->value('limite_whatsapp_mensal');

        return $limite === null ? null : (int) $limite;
    }

    public function idPorUuid(string $uuid): ?int
    {
        $id = Organizacao::query()->where('uuid', $uuid)->whereIn('estado', ['ativa', 'em_migracao'])->value('id');

        return $id === null ? null : (int) $id;
    }
}
