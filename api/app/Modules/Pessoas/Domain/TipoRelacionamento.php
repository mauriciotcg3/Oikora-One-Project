<?php

namespace App\Modules\Pessoas\Domain;

/**
 * Tipos de relacionamento, lista fixa no código (U-24). Cada relação grava a inversa: "pai/mãe" de A
 * para B é "filho(a)" de B para A. "Responsável legal ↔ dependente" liga a criança ao adulto que
 * responde por ela (ASSUMPTION-005). "Outro" exige descrição.
 */
enum TipoRelacionamento: string
{
    case Conjuge = 'conjuge';
    case PaiMae = 'pai_mae';
    case Filho = 'filho';
    case Irmao = 'irmao';
    case Avo = 'avo';
    case Neto = 'neto';
    case ResponsavelLegal = 'responsavel_legal';
    case Dependente = 'dependente';
    case Outro = 'outro';

    public function inverso(): self
    {
        return match ($this) {
            self::PaiMae => self::Filho,
            self::Filho => self::PaiMae,
            self::Avo => self::Neto,
            self::Neto => self::Avo,
            self::ResponsavelLegal => self::Dependente,
            self::Dependente => self::ResponsavelLegal,
            self::Conjuge, self::Irmao, self::Outro => $this,
        };
    }

    public function exigeDescricao(): bool
    {
        return $this === self::Outro;
    }
}
