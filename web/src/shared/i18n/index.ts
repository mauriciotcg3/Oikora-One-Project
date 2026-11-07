import i18next, { type i18n, type ResourceLanguage } from 'i18next'
import { initReactI18next } from 'react-i18next'

/** Textos de uma língua, por namespace (um namespace por feature — OIK-ADR-0006 §13). */
export type RecursosI18n = ResourceLanguage

export type InstanciaI18n = i18n

export const IDIOMA_PADRAO = 'pt-BR'

/** Cria uma instância isolada do i18next, já pronta para uso síncrono. */
export function criarI18n(recursos: RecursosI18n): InstanciaI18n {
  const instancia = i18next.createInstance()
  void instancia.use(initReactI18next).init({
    lng: IDIOMA_PADRAO,
    fallbackLng: IDIOMA_PADRAO,
    resources: { [IDIOMA_PADRAO]: recursos },
    ns: Object.keys(recursos),
    interpolation: { escapeValue: false },
    initAsync: false,
  })
  return instancia
}
