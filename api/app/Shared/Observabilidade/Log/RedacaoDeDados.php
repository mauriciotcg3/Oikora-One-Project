<?php

namespace App\Shared\Observabilidade\Log;

/**
 * Remove dado pessoal e segredo do que vai para o log (OIK-ADR-0010 §1 e §9; OBS-002; CA-28).
 * Redige por chave (campos proibidos) e por padrão (CPF, e-mail, telefone, código de 6 dígitos, token).
 * Redigir demais é aceitável; deixar passar não é.
 */
final class RedacaoDeDados
{
    public const MARCA = '[redigido]';

    /** Chaves cujo valor nunca vai para o log, em qualquer nível do contexto. */
    private const CHAVES_PROIBIDAS = [
        'senha', 'password', 'password_confirmation', 'senha_atual', 'nova_senha',
        'token', 'access_token', 'refresh_token', 'remember_token', 'api_key', 'secret', 'segredo',
        'authorization', 'cookie', 'set-cookie', 'x-xsrf-token', 'x-csrf-token',
        'codigo', 'code', 'codigo_recuperacao', 'codigos_recuperacao', 'two_factor_secret',
        'cpf', 'rg', 'rg_pais', 'email', 'celular', 'telefone', 'destino', 'valor',
        'necessidades_especiais', 'tipo_sanguineo', 'logradouro', 'numero', 'complemento', 'cep',
    ];

    /** @var array<string, string> padrão → substituto, na ordem de aplicação */
    private const PADROES = [
        // Token: cabeçalho Bearer e sequências longas de hex/base64 (≥ 32), antes dos números.
        '/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i' => 'Bearer '.self::MARCA,
        // UUID não é segredo (identificador público, request_id): fica de fora.
        '/(?<![A-Za-z0-9_-])(?![0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}(?![A-Za-z0-9_-]))[A-Za-z0-9_-]{32,}={0,2}(?![A-Za-z0-9_-])/' => self::MARCA,
        // E-mail.
        '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/' => self::MARCA,
        // CPF com ou sem pontuação.
        '/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/' => self::MARCA,
        // Telefone: 10 a 13 dígitos, com DDI, DDD, espaços, parênteses e hífen opcionais.
        '/(?<![\w-])\+?\(?(?:\d[\s().-]{0,2}){9,12}\d(?![\w-])/' => self::MARCA,
        // Código de verificação de 6 dígitos.
        '/(?<![\d-])\d{6}(?![\d-])/' => self::MARCA,
    ];

    public function texto(string $texto): string
    {
        return (string) preg_replace(array_keys(self::PADROES), array_values(self::PADROES), $texto);
    }

    /**
     * @param  array<array-key, mixed>  $dados
     * @return array<array-key, mixed>
     */
    public function dados(array $dados): array
    {
        $limpo = [];

        foreach ($dados as $chave => $valor) {
            if (is_string($chave) && in_array(strtolower($chave), self::CHAVES_PROIBIDAS, true)) {
                $limpo[$chave] = self::MARCA;

                continue;
            }

            $limpo[$chave] = $this->valor($valor);
        }

        return $limpo;
    }

    private function valor(mixed $valor): mixed
    {
        return match (true) {
            is_string($valor) => $this->texto($valor),
            is_array($valor) => $this->dados($valor),
            $valor instanceof \Throwable => [
                'classe' => $valor::class,
                'mensagem' => $this->texto($valor->getMessage()),
                'arquivo' => $valor->getFile().':'.$valor->getLine(),
            ],
            $valor instanceof \Stringable => $this->texto((string) $valor),
            is_object($valor) => $valor::class,
            default => $valor,
        };
    }
}
