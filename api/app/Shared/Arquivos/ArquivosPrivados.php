<?php

namespace App\Shared\Arquivos;

/**
 * Arquivos privados da organização (OIK-ADR-0011 §7): bucket sem acesso público, leitura e envio só por
 * URL assinada de curta duração, emitida pela API depois da autorização. O navegador envia direto ao
 * armazenamento; a API nunca recebe o arquivo.
 */
interface ArquivosPrivados
{
    /** URL para o navegador enviar o arquivo com `PUT`, junto dos cabeçalhos devolvidos. */
    public function urlDeEnvio(string $caminho, string $tipoConteudo): UrlDeEnvio;

    /** URL de leitura de curta duração; null quando o arquivo não existe. */
    public function urlDeLeitura(string $caminho): ?string;

    public function existe(string $caminho): bool;
}
