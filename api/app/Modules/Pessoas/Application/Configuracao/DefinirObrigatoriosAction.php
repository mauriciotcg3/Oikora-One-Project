<?php

namespace App\Modules\Pessoas\Application\Configuracao;

use App\Modules\Auditoria\Application\AuditaCom;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\RequerPermissao;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Domain\Vinculo;

/**
 * Substitui os obrigatórios de um vínculo (RN-13; U-06). Não bloqueia o cadastro rápido: só define a
 * completude da ficha (CA-14).
 */
#[RequerPermissao(Permissao::PessoasConfigurar)]
#[AuditaCom(EventoAuditoria::ObrigatoriosAtualizados)]
final readonly class DefinirObrigatoriosAction
{
    public function __construct(
        private Configuracao $configuracao,
        private CamposObrigatorios $obrigatorios,
    ) {}

    /** @param  list<string>  $campos  chaves de `CampoFicha`, já validadas */
    public function executar(Vinculo $vinculo, array $campos): Resultado
    {
        if ($negada = $this->configuracao->negada()) {
            return $negada;
        }

        $campos = array_values(array_unique($campos));
        sort($campos);

        $antes = $this->obrigatorios->doVinculo($vinculo);
        if ($antes !== $campos) {
            $this->obrigatorios->definir($this->configuracao->organizacaoId(), $vinculo, $campos);
            $this->configuracao->auditar(EventoAuditoria::ObrigatoriosAtualizados, 'campos_obrigatorios', $vinculo->value, [
                'vinculo' => $vinculo->value,
                'campos_antes' => $antes,
                'campos_depois' => $campos,
            ]);
        }

        return Resultado::ok(['obrigatorios' => $this->obrigatorios->todos()]);
    }
}
