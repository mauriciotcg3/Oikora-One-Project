<?php

use App\Modules\Autorizacao\Presentation\PapeisController;
use App\Modules\Autorizacao\Presentation\SolicitacoesController;
use Illuminate\Support\Facades\Route;

Route::get('permissoes', [PapeisController::class, 'permissoes']);
Route::get('papeis', [PapeisController::class, 'listar']);
Route::post('papeis', [PapeisController::class, 'criar']);
Route::put('papeis/{papel}', [PapeisController::class, 'editar'])->whereUuid('papel');
Route::post('acessos/{acesso}/atribuicoes', [PapeisController::class, 'atribuir'])->whereUuid('acesso');
Route::delete('acessos/{acesso}/atribuicoes/{atribuicao}', [PapeisController::class, 'removerAtribuicao'])
    ->whereUuid(['acesso', 'atribuicao']);

// Solicitações e concessões temporárias (OIK-ADR-0012; T-47).
Route::get('solicitacoes-permissao', [SolicitacoesController::class, 'listar']);
Route::post('solicitacoes-permissao', [SolicitacoesController::class, 'criar']);
Route::post('solicitacoes-permissao/{solicitacao}/cancelar', [SolicitacoesController::class, 'cancelar'])->whereUuid('solicitacao');
Route::post('solicitacoes-permissao/{solicitacao}/aprovar', [SolicitacoesController::class, 'aprovar'])->whereUuid('solicitacao');
Route::post('solicitacoes-permissao/{solicitacao}/recusar', [SolicitacoesController::class, 'recusar'])->whereUuid('solicitacao');
Route::post('concessoes-permissao/{concessao}/revogar', [SolicitacoesController::class, 'revogar'])->whereUuid('concessao');
