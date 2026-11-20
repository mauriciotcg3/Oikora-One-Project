<?php

namespace App\Shared\Arquivos;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Sobre o disco `oikora.arquivos.disco`: S3 em produção (bucket privado do ambiente) e, em dev e testes,
 * o disco local servido pelo próprio Laravel com URL assinada (`serve`), sem acesso público.
 */
final class ArquivosNoDisco implements ArquivosPrivados
{
    public function urlDeEnvio(string $caminho, string $tipoConteudo): UrlDeEnvio
    {
        $expiraEm = now()->addMinutes($this->validade())->toImmutable();
        $envio = $this->disco()->temporaryUploadUrl($caminho, $expiraEm, ['ContentType' => $tipoConteudo]);

        return new UrlDeEnvio(
            url: (string) $envio['url'],
            cabecalhos: ['Content-Type' => $tipoConteudo, ...array_map(strval(...), $envio['headers'] ?? [])],
            caminho: $caminho,
            expiraEm: $expiraEm,
        );
    }

    public function urlDeLeitura(string $caminho): ?string
    {
        if (! $this->existe($caminho)) {
            return null;
        }

        return $this->disco()->temporaryUrl($caminho, now()->addMinutes($this->validade()));
    }

    public function existe(string $caminho): bool
    {
        return $this->disco()->exists($caminho);
    }

    private function validade(): int
    {
        return max(1, (int) config('oikora.arquivos.validade_minutos', 5));
    }

    private function disco(): FilesystemAdapter
    {
        /** @var Filesystem&FilesystemAdapter $disco */
        $disco = Storage::disk((string) config('oikora.arquivos.disco', 'arquivos'));

        return $disco;
    }
}
