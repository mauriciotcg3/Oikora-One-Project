<?php

namespace App\Shared\Cifra;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use LogicException;

class CampoSensivelEncrypter
{
    private Encrypter $atual;

    /** @var list<Encrypter> */
    private array $anteriores;

    /** @param list<string> $chavesAnteriores */
    public function __construct(string $chaveAtual, array $chavesAnteriores = [])
    {
        $this->atual = $this->criarEncrypter($chaveAtual);
        $this->anteriores = array_map($this->criarEncrypter(...), $chavesAnteriores);
    }

    public function encryptString(string $valor): string
    {
        return $this->atual->encryptString($valor);
    }

    public function decryptString(string $valor): string
    {
        foreach ([$this->atual, ...$this->anteriores] as $encrypter) {
            try {
                return $encrypter->decryptString($valor);
            } catch (DecryptException) {
                // Tenta a próxima chave da janela de rotação.
            }
        }

        throw new DecryptException('O campo sensível não pôde ser decifrado com as chaves configuradas.');
    }

    private function criarEncrypter(string $chave): Encrypter
    {
        if ($chave === '') {
            throw new LogicException('OIKORA_CHAVE_CAMPOS não foi configurada.');
        }

        $chaveBruta = str_starts_with($chave, 'base64:')
            ? base64_decode(substr($chave, 7), true)
            : $chave;

        if (! is_string($chaveBruta) || strlen($chaveBruta) !== 32) {
            throw new LogicException('A chave de campos deve ter 32 bytes (AES-256-CBC).');
        }

        return new Encrypter($chaveBruta, 'AES-256-CBC');
    }
}
