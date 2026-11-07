import { useMutation, type UseMutationResult } from '@tanstack/react-query'
import {
  confirmarDoisFatores,
  entrar,
  enviarCodigo,
  pedirRecuperacaoSenha,
  type CanalDoisFatores,
  type ConfirmacaoDoisFatores,
  type Credenciais,
  type DesafioDoisFatores,
  type FalhaAutenticacao,
  type ResultadoLogin,
} from '../api/autenticacao'

// Estado de servidor do login (FE-STATE-001): só mutations; nada fica em cache.

export function useEntrar(): UseMutationResult<ResultadoLogin, FalhaAutenticacao, Credenciais> {
  return useMutation({ mutationFn: entrar })
}

export function useConfirmarDoisFatores(): UseMutationResult<void, FalhaAutenticacao, ConfirmacaoDoisFatores> {
  return useMutation({ mutationFn: confirmarDoisFatores })
}

export function useEnviarCodigo(): UseMutationResult<DesafioDoisFatores, FalhaAutenticacao, CanalDoisFatores> {
  return useMutation({ mutationFn: enviarCodigo })
}

export function usePedirRecuperacaoSenha(): UseMutationResult<void, FalhaAutenticacao, string> {
  return useMutation({ mutationFn: pedirRecuperacaoSenha })
}
