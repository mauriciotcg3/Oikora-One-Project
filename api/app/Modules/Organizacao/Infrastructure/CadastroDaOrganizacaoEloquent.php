<?php

namespace App\Modules\Organizacao\Infrastructure;

use App\Modules\Organizacao\Application\CadastroDaOrganizacao;
use Illuminate\Support\Str;

/**
 * Sob a RLS da organização em contexto (igrejas) e pelo id da organização ativa (organizacoes).
 *
 * @phpstan-import-type DadosOrganizacao from CadastroDaOrganizacao
 * @phpstan-import-type DadosIgreja from CadastroDaOrganizacao
 */
final class CadastroDaOrganizacaoEloquent implements CadastroDaOrganizacao
{
    public function dados(int $organizacaoId): array
    {
        $organizacao = Organizacao::query()->whereKey($organizacaoId)->firstOrFail();
        $limite = $organizacao->getAttribute('limite_whatsapp_mensal');

        return [
            'uuid' => (string) $organizacao->getAttribute('uuid'),
            'nome' => (string) $organizacao->getAttribute('nome'),
            'fuso' => (string) $organizacao->getAttribute('fuso'),
            'cor_primaria' => self::texto($organizacao->getAttribute('cor_primaria')),
            'modo_matricula' => $organizacao->getRawOriginal('modo_matricula'),
            'limite_whatsapp_mensal' => $limite === null ? null : (int) $limite,
            'email_contato' => self::texto($organizacao->getAttribute('email_contato')),
            'logo_path' => self::texto($organizacao->getAttribute('logo_path')),
        ];
    }

    public function atualizar(int $organizacaoId, array $mudancas): void
    {
        Organizacao::query()->whereKey($organizacaoId)->update([...$mudancas, 'updated_at' => now()]);
    }

    public function igrejas(): array
    {
        return array_values(Igreja::query()->orderBy('nome')->orderBy('id')->get()
            ->map(fn (Igreja $igreja): array => $this->igrejaComoArray($igreja))
            ->all());
    }

    public function igreja(string $uuid): ?array
    {
        $igreja = Igreja::query()->where('uuid', $uuid)->first();

        return $igreja === null ? null : $this->igrejaComoArray($igreja);
    }

    public function criarIgreja(int $organizacaoId, string $nome, ?int $regionalId): array
    {
        $igreja = new Igreja;
        $igreja->forceFill([
            'uuid' => (string) Str::uuid(),
            'organizacao_id' => $organizacaoId,
            'nome' => $nome,
            'regional_id' => $regionalId,
        ])->save();

        return $this->igrejaComoArray($igreja);
    }

    public function atualizarIgreja(int $igrejaId, array $mudancas): void
    {
        Igreja::query()->whereKey($igrejaId)->update([...$mudancas, 'updated_at' => now()]);
    }

    /** @return DadosIgreja */
    private function igrejaComoArray(Igreja $igreja): array
    {
        $regional = $igreja->getAttribute('regional_id');

        return [
            'id' => (int) $igreja->getKey(),
            'uuid' => (string) $igreja->getAttribute('uuid'),
            'nome' => (string) $igreja->getAttribute('nome'),
            'regional_id' => $regional === null ? null : (int) $regional,
        ];
    }

    private static function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
