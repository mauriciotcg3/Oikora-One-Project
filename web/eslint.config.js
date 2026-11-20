import js from '@eslint/js'
import jsxA11y from 'eslint-plugin-jsx-a11y'
import reactHooks from 'eslint-plugin-react-hooks'
import globals from 'globals'
import tseslint from 'typescript-eslint'

// Fronteiras do Frontend Standard (FE-QUAL-002), sem plugin extra: `no-restricted-imports` por camada.
// shared → features → pages → app (FE-DEP-001); fora da feature, só o `index.ts` dela (FE-MOD-003).
const fundoDeFeature = {
  regex: '(^|/)features/[^/]+/.+',
  message: 'Importe a feature só pelo index.ts dela (FE-MOD-003).',
}

export default tseslint.config(
  { ignores: ['dist', 'coverage', 'public/mockServiceWorker.js'] },
  js.configs.recommended,
  ...tseslint.configs.strict,
  jsxA11y.flatConfigs.recommended,
  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: { globals: globals.browser },
    plugins: { 'react-hooks': reactHooks },
    rules: {
      ...reactHooks.configs.recommended.rules,
      '@typescript-eslint/no-explicit-any': 'error',
    },
  },
  {
    files: ['src/shared/**/*.{ts,tsx}'],
    ignores: ['src/shared/**/__tests__/**'],
    rules: {
      'no-restricted-imports': ['error', {
        patterns: [{ regex: '(^|/)(features|pages|app)(/|$)', message: 'shared/ não conhece features, pages nem app (FE-DEP-001).' }],
      }],
    },
  },
  {
    files: ['src/features/**/*.{ts,tsx}'],
    rules: {
      'no-restricted-imports': ['error', {
        patterns: [
          { regex: '(^|/)(pages|app)(/|$)', message: 'features/ não conhece pages nem app (FE-DEP-001).' },
          // De dentro de `features/<A>/<segmento>/`, `../../<B>/` é outra feature (nomes em PascalCase).
          { regex: '^\\.\\./\\.\\./[A-Z][^/]*/', message: 'Outra feature só pelo index.ts dela (FE-MOD-003).' },
          fundoDeFeature,
        ],
      }],
    },
  },
  {
    files: ['src/app/**/*.{ts,tsx}', 'src/pages/**/*.{ts,tsx}'],
    rules: {
      'no-restricted-imports': ['error', { patterns: [fundoDeFeature] }],
    },
  },
)
