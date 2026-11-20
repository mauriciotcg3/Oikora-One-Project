<?php

namespace App\Modules\Organizacao\Application;

use App\Modules\Autorizacao\Domain\Permissao;
use App\Shared\Arquivos\ArquivosPrivados;
use Illuminate\Support\Str;

/**
 * Emite a URL assinada para o navegador enviar o logo direto ao armazenamento privado (OIK-ADR-0011 §7).
 * Não muda a organização: o logo só passa a valer quando o caminho volta no `PUT /painel/organizacao`,
 * que confere o prefixo e a existência do arquivo (e então audita).
 */
final readonly class PedirEnvioDoLogo
{
    public const TIPOS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public const TAMANHO_MAXIMO = 1_048_576;

    public function __construct(
        private Governanca $governanca,
        private CadastroDaOrganizacao $cadastro,
        private ArquivosPrivados $arquivos,
    ) {}

    public static function prefixo(string $organizacaoUuid): string
    {
        return "organizacoes/{$organizacaoUuid}/logo/";
    }

    public function executar(string $tipoConteudo): Resultado
    {
        if ($negada = $this->governanca->negada(Permissao::OrganizacaoConfigurar)) {
            return $negada;
        }

        $uuid = $this->cadastro->dados($this->governanca->organizacaoId())['uuid'];
        $caminho = self::prefixo($uuid).Str::uuid().'.'.self::TIPOS[$tipoConteudo];

        return Resultado::ok(['envio' => $this->arquivos->urlDeEnvio($caminho, $tipoConteudo)], 201);
    }
}
