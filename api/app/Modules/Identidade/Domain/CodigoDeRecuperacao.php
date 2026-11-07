<?php

namespace App\Modules\Identidade\Domain;

/** Código de uso único para entrar sem o segundo fator, no formato ABCD-1234. */
final class CodigoDeRecuperacao
{
    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private const QUANTIDADE = 10;

    /** @return list<string> */
    public static function gerarLote(): array
    {
        $codigos = [];

        while (count($codigos) < self::QUANTIDADE) {
            $codigos[self::gerar()] = true;
        }

        return array_map('strval', array_keys($codigos));
    }

    /** A pessoa pode digitar sem hífen e em minúsculas. */
    public static function normalizar(string $codigo): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($codigo));
    }

    private static function gerar(): string
    {
        return self::bloco().'-'.self::bloco();
    }

    private static function bloco(): string
    {
        $bloco = '';

        for ($posicao = 0; $posicao < 4; $posicao++) {
            $bloco .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        return $bloco;
    }
}
