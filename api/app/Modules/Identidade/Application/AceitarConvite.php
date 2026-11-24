<?php

namespace App\Modules\Identidade\Application;

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Contracts\ConcessaoDeAcesso;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Identidade\Application\Actions\Resultado;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use App\Modules\Pessoas\Contracts\ContatosDaPessoa;
use Illuminate\Support\Facades\Hash;

/**
 * Leitura e aceite do convite pelo link (OIK-ADR-0003 §5; RN-02, RN-12; CA-02). Chega sem organização no
 * contexto: a via estreita acha a organização pelo hash do token e o resto roda sob a RLS dela.
 *
 * - Com sessão: liga a conta logada à Pessoa do convite.
 * - Sem sessão: cria a conta com a senha que a própria pessoa define e os contatos da ficha. Se um desses
 *   contatos já é de outra conta, recusa com `entre_para_aceitar`: quem já tem conta faz login para
 *   aceitar, e nenhum vínculo é deduzido por e-mail igual.
 *
 * Token usado, revogado ou vencido → `convite_invalido`. Aceitar cria o acesso e a atribuição do convite.
 */
final readonly class AceitarConvite
{
    public function __construct(
        private Convites $convites,
        private ContatosDaPessoa $pessoas,
        private ConcessaoDeAcesso $concessao,
        private ConsultaOrganizacoes $organizacoes,
        private Auditoria $auditoria,
    ) {}

    public function dados(string $token): Resultado
    {
        $organizacaoId = $this->convites->organizacaoDoToken(hash('sha256', $token));
        if ($organizacaoId === null) {
            return self::invalido(404);
        }

        return $this->convites->naOrganizacao($organizacaoId, null, function () use ($token, $organizacaoId): Resultado {
            $convite = $this->convites->doToken(hash('sha256', $token));
            if ($convite === null || ! self::valido($convite)) {
                return self::invalido(404);
            }

            return Resultado::ok([
                'organizacao' => ['nome' => $this->organizacoes->perfilDeEnvio($organizacaoId)->nome],
                'pessoa' => ['nome' => $this->pessoas->nome($convite['pessoa_id'])],
                'papel' => ['nome' => $this->concessao->nomeDoPapel($convite['papel_id'])],
                'expira_em' => $convite['expira_em'],
            ]);
        });
    }

    public function aceitar(string $token, ?int $contaLogada, ?string $senha): Resultado
    {
        $organizacaoId = $this->convites->organizacaoDoToken(hash('sha256', $token));
        if ($organizacaoId === null) {
            return self::invalido(422);
        }

        return $this->convites->naOrganizacao($organizacaoId, $contaLogada, function () use ($token, $organizacaoId, $contaLogada, $senha): Resultado {
            $convite = $this->convites->doToken(hash('sha256', $token));
            if ($convite === null || ! self::valido($convite)) {
                return self::invalido(422);
            }
            if ($this->convites->pessoaTemAcesso($convite['pessoa_id'])) {
                return Resultado::falha(409, 'pessoa_ja_tem_acesso');
            }

            $contaNova = $contaLogada === null;
            if ($contaLogada !== null) {
                if ($this->convites->contaTemAcesso($contaLogada)) {
                    return Resultado::falha(409, 'conta_ja_tem_acesso', ['mensagem' => 'Sua conta já tem acesso a esta organização.']);
                }
                $contaId = $contaLogada;
            } else {
                if ($senha === null) {
                    return Resultado::invalido(['senha' => 'senha_obrigatoria']);
                }
                $uuid = $this->pessoas->uuid($convite['pessoa_id']);
                $contatos = $uuid === null ? null : $this->pessoas->paraConvite($uuid);
                if ($contatos === null || ($contatos['email'] === null && $contatos['celular'] === null)) {
                    return self::invalido(422);
                }
                foreach ([$contatos['email'], $contatos['celular']] as $identificador) {
                    if ($identificador !== null && $this->convites->identificadorEmUso($identificador)) {
                        return Resultado::falha(409, 'entre_para_aceitar', [
                            'mensagem' => 'Já existe uma conta com este contato. Entre nela para aceitar o convite.',
                        ]);
                    }
                }
                $contaId = $this->convites->criarConta($contatos['email'], $contatos['celular'], Hash::make($senha));
                $this->convites->usarConta($contaId);
            }

            $acesso = $this->convites->criarAcesso($organizacaoId, $contaId, $convite['pessoa_id']);
            $tipo = EscopoTipo::from($convite['escopo_tipo']);
            $this->concessao->atribuir($organizacaoId, $acesso['id'], $convite['papel_id'], $tipo, $convite['escopo_id']);
            $this->convites->aceitar($convite['id'], $acesso['id']);
            $this->auditoria->registrar(
                evento: EventoAuditoria::AcessoConcedido,
                organizacaoId: $organizacaoId,
                alvoTipo: 'acesso',
                alvoId: $acesso['uuid'],
                acessoId: $acesso['id'],
                contaId: $contaId,
                metadados: [
                    'convite_id' => $convite['uuid'],
                    'papel_id' => $convite['papel_id'],
                    'escopo_tipo' => $convite['escopo_tipo'],
                    'escopo_id' => $convite['escopo_id'],
                    'conta_nova' => $contaNova,
                ],
                canal: 'convite',
            );

            return Resultado::ok([
                'acesso' => $acesso['uuid'],
                'conta_nova' => $contaNova,
                'organizacao' => $this->organizacoes->uuidDe($organizacaoId),
            ], 201);
        });
    }

    /** @param  array{estado: string, expira_em: string}  $convite */
    private static function valido(array $convite): bool
    {
        return $convite['estado'] === 'enviado' && strtotime($convite['expira_em']) > time();
    }

    private static function invalido(int $status): Resultado
    {
        return Resultado::falha($status, 'convite_invalido', ['mensagem' => 'Este convite não vale mais. Peça um novo a quem convidou.']);
    }
}
