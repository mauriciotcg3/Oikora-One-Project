<?php

namespace App\Modules\Pessoas\Application\Consultas;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Pessoas\Application\Configuracao\Resultado;
use App\Shared\Arquivos\ArquivosPrivados;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Emite a URL assinada para o navegador enviar a foto direto ao armazenamento privado (OIK-ADR-0011 §7),
 * para quem tem `pessoas.editar` na igreja da pessoa. Não muda a ficha: a foto passa a valer quando o
 * caminho volta no `PATCH` da seção `pessoais`, que confere prefixo e existência e audita.
 */
final readonly class PedirEnvioDaFoto
{
    public const TIPOS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public const TAMANHO_MAXIMO = 5_242_880;

    public function __construct(
        private AlcanceAtual $alcance,
        private Fichas $fichas,
        private BuscaDenominacao $busca,
        private ConsultaOrganizacoes $organizacoes,
        private ArquivosPrivados $arquivos,
    ) {}

    public static function prefixo(string $organizacaoUuid, string $pessoaUuid): string
    {
        return "organizacoes/{$organizacaoUuid}/pessoas/{$pessoaUuid}/foto/";
    }

    public function executar(string $uuid, string $tipoConteudo): Resultado
    {
        $pessoa = $this->fichas->localizar($uuid);
        if ($pessoa === null) {
            return $this->busca->existe($uuid) ? $this->negar() : Resultado::falha(404, 'pessoa_inexistente');
        }

        if (! $this->alcance->pode(Permissao::PessoasEditar, $pessoa['igreja_id'])) {
            return $this->negar();
        }

        $organizacao = $this->organizacoes->uuidDe($this->alcance->ator()->organizacaoId);
        $caminho = self::prefixo($organizacao, $uuid).Str::uuid().'.'.self::TIPOS[$tipoConteudo];

        return Resultado::ok(['envio' => $this->arquivos->urlDeEnvio($caminho, $tipoConteudo)], 201);
    }

    private function negar(): Resultado
    {
        Log::info('authz.denied', ['permissao' => Permissao::PessoasEditar->value]);

        return Resultado::falha(403, 'authz.denied');
    }
}
