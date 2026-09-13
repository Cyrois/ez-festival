import pluginVue from 'eslint-plugin-vue';
import eslintConfigPrettier from 'eslint-config-prettier';

export default [
    ...pluginVue.configs['flat/recommended'],
    eslintConfigPrettier,
    {
        files: ['resources/js/**/*.{js,vue}'],
        rules: {
            'vue/max-attributes-per-line': [
                'error',
                {
                    singleline: { max: 1 },
                    multiline: { max: 1 },
                },
            ],
            'vue/multi-word-component-names': 'off',
        },
    },
];
