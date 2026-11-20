<?php

namespace App\Modules\Organizacao\Application\Actions;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Organizacao\Application\CadastroDaOrganizacao;
use App\Modules\Organizacao\Application\Governanca;
use App\Modules\Organizacao\Application\Resultado;

/**
 * Configuração da organização (E-04; OIK-ADR-0006 §7, OIK-ADR-0008 §14): nome, fuso, cor, contato de
 * resposta, limite de WhatsApp e logo pedem `organizacao.configurar`; o modo de matrícula pede
 * `pessoas.configurar` (U-18). As duas valem só com escopo organização. O modo `automatica` é aceito
 * aqui, mas o cadastro de membro continua só manual até o formato existir (U-07, T-28).
 *
 * @phpstan-import-type DadosOrganizacao from CadastroDaOrganizacao
 */
#[RequerPermissao(Permissao::OrganizacaoConfigurar)]
#[AuditaCom(EventoAuditoria::OrganizacaoConfigurada)]
final readonly class ConfigurarOrganizacaoAction
{
    /** Campo da API → coluna. */
    private const COLUNAS = [
        'nome' => 'nome',
        'fuso' => 'fuso',
        'cor_primaria' => 'cor_primaria',
        'modo_matricula' => 'modo_matricula',
        'limite_whatsapp_mensal' => 'limite_whatsapp_mensal',
        'email_contato' => 'email_contato',
        'logo' => 'logo_path',
    ];

    public function __construct(
        private Governanca $governanca,
        private CadastroDaOrganizacao $cadastro,
    ) {}

    /** @param  array<string, string|int|null>  $dados  campos já validados pela borda */
    public function executar(array $dados): Resultado
    {
        $organizacaoId = $this->governanca->organizacaoId();
        $antes = $this->cadastro->dados($organizacaoId);

        $mudancas = [];
        foreach (array_intersect_key($dados, self::COLUNAS) as $campo => $valor) {
            if ($antes[self::COLUNAS[$campo]] !== $valor) {
                $mudancas[$campo] = $valor;
            }
        }

        if ($negada = $this->negada(array_keys($mudancas))) {
            return $negada;
        }

        if ($mudancas !== []) {
            $colunas = [];
            foreach ($mudancas as $campo => $valor) {
                $colunas[self::COLUNAS[$campo]] = $valor;
            }
            $this->cadastro->atualizar($organizacaoId, $colunas);

            $metadados = array_key_exists('modo_matricula', $mudancas)
                ? ['modo_matricula_antes' => $antes['modo_matricula'], 'modo_matricula_depois' => (string) $mudancas['modo_matricula']]
                : [];
            $this->governanca->auditar(EventoAuditoria::OrganizacaoConfigurada, 'organizacao', $antes['uuid'], array_keys($mudancas), $metadados);
        }

        return Resultado::ok(['organizacao' => $this->cadastro->dados($organizacaoId)]);
    }

    /** @param  list<string>  $campos */
    private function negada(array $campos): ?Resultado
    {
        if ($campos === []) {
            return $this->governanca->pode(Permissao::OrganizacaoConfigurar) || $this->governanca->pode(Permissao::PessoasConfigurar)
                ? null
                : $this->governanca->negada(Permissao::OrganizacaoConfigurar);
        }

        if (in_array('modo_matricula', $campos, true) && ($negada = $this->governanca->negada(Permissao::PessoasConfigurar))) {
            return $negada;
        }

        return array_diff($campos, ['modo_matricula']) === [] ? null : $this->governanca->negada(Permissao::OrganizacaoConfigurar);
    }
}
