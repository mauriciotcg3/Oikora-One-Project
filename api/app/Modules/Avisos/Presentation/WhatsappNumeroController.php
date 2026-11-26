<?php

namespace App\Modules\Avisos\Presentation;

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Application\CadastroNumeroWhatsApp;
use App\Modules\Avisos\Application\ConfiguracoesNumeroWhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class WhatsappNumeroController
{
    public function autorizar(Request $request, AlcanceAtual $alcance, CadastroNumeroWhatsApp $cadastro): RedirectResponse|JsonResponse
    {
        if (! $alcance->temNaOrganizacao(Permissao::AvisosWhatsappGerir)) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }
        $estado = Str::random(48);
        $request->session()->put('whatsapp_oauth_estado', $estado);

        return redirect()->away($cadastro->urlDeAutorizacao($estado));
    }

    public function retorno(Request $request, AlcanceAtual $alcance, CadastroNumeroWhatsApp $cadastro, ConfiguracoesNumeroWhatsApp $numeros, Auditoria $auditoria): JsonResponse
    {
        if (! $alcance->temNaOrganizacao(Permissao::AvisosWhatsappGerir)) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }
        $dados = $request->validate(['code' => ['required', 'string', 'max:2048'], 'state' => ['required', 'string', 'max:128']]);
        if (! hash_equals((string) $request->session()->pull('whatsapp_oauth_estado'), $dados['state'])) {
            return new JsonResponse(['codigo' => 'whatsapp.oauth_estado_invalido'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $ator = $alcance->ator();
        $credenciais = $cadastro->trocarCodigo($dados['code']);
        $templates = $cadastro->garantirTemplates($credenciais);
        DB::transaction(function () use ($ator, $credenciais, $templates, $numeros, $auditoria): void {
            $numeros->ligar($ator->organizacaoId, $credenciais, $templates);
            $auditoria->registrar(EventoAuditoria::WhatsappNumeroLigado, $ator->organizacaoId, 'whatsapp_numero', $credenciais->phoneNumberId, $ator->acessoId, $ator->contaId, campos: ['estado']);
        });

        return new JsonResponse(['data' => ['waba_id' => $credenciais->wabaId, 'phone_number_id' => $credenciais->phoneNumberId, 'nome_exibicao' => $credenciais->nomeExibicao, 'estado' => 'ligado', 'templates' => $templates]], Response::HTTP_CREATED);
    }

    public function desligar(AlcanceAtual $alcance, ConfiguracoesNumeroWhatsApp $numeros, Auditoria $auditoria): JsonResponse
    {
        if (! $alcance->temNaOrganizacao(Permissao::AvisosWhatsappGerir)) {
            return new JsonResponse(['codigo' => 'authz.denied'], Response::HTTP_FORBIDDEN);
        }
        $ator = $alcance->ator();
        DB::transaction(function () use ($ator, $numeros, $auditoria): void {
            $numeros->desligar($ator->organizacaoId);
            $auditoria->registrar(EventoAuditoria::WhatsappNumeroDesligado, $ator->organizacaoId, 'whatsapp_numero', $ator->organizacaoId, $ator->acessoId, $ator->contaId, campos: ['estado']);
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
