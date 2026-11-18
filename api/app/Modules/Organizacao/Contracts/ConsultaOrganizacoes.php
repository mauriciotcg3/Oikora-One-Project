<?php

namespace App\Modules\Organizacao\Contracts;

interface ConsultaOrganizacoes
{
    /** @return list<int> */
    public function idsDisponiveis(): array;

    /** Organização em migração: o módulo Avisos não cria envios (OIK-ADR-0009 §10). */
    public function emMigracao(int $organizacaoId): bool;

    /** Nome, fuso e contato de resposta, para remetente e janela diurna dos avisos. */
    public function perfilDeEnvio(int $organizacaoId): PerfilDeEnvio;

    /** Id da organização disponível pelo identificador público (links assinados, sem sessão). */
    public function idPorUuid(string $uuid): ?int;

    /** Identificador público, para prefixos de arquivo por organização (OIK-ADR-0011 §7). */
    public function uuidDe(int $organizacaoId): string;

    /** Modo de matrícula configurado (`manual` ou `automatica`; RN-05, U-18). */
    public function modoMatricula(int $organizacaoId): string;
}
