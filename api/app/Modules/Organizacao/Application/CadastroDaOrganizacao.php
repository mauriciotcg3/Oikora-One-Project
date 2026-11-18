<?php

namespace App\Modules\Organizacao\Application;

/**
 * Persistência da configuração da organização e das igrejas, sob a RLS da organização em contexto.
 *
 * @phpstan-type DadosOrganizacao array{uuid: string, nome: string, fuso: string, cor_primaria: string|null, modo_matricula: string, limite_whatsapp_mensal: int|null, email_contato: string|null, logo_path: string|null}
 * @phpstan-type DadosIgreja array{id: int, uuid: string, nome: string, regional_id: int|null}
 */
interface CadastroDaOrganizacao
{
    /** @return DadosOrganizacao */
    public function dados(int $organizacaoId): array;

    /** @param  array<string, mixed>  $mudancas */
    public function atualizar(int $organizacaoId, array $mudancas): void;

    /** @return list<DadosIgreja> */
    public function igrejas(): array;

    /** @return DadosIgreja|null */
    public function igreja(string $uuid): ?array;

    /** @return DadosIgreja */
    public function criarIgreja(int $organizacaoId, string $nome, ?int $regionalId): array;

    /** @param  array{nome?: string, regional_id?: int|null}  $mudancas */
    public function atualizarIgreja(int $igrejaId, array $mudancas): void;
}
