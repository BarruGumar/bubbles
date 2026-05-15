import pluginVue from 'eslint-plugin-vue'
import js from '@eslint/js'
import prettierConfig from 'eslint-config-prettier'

export default [
    {
        ignores: ['node_modules/**', 'vendor/**', 'public/**', 'bootstrap/**'],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    prettierConfig,
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            'no-undef': 'off',
            'no-unused-vars': [
                'warn',
                {
                    argsIgnorePattern: '^_',
                    varsIgnorePattern: '^_',
                    caughtErrorsIgnorePattern: '^_',
                },
            ],
        },
    },
]
