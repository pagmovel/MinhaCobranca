<?php

/*
 * O pacote mescla a configuração de forma rasa (array_merge no primeiro nível), então a chave
 * `pages` precisa vir inteira. Os valores são os do padrão do pacote, só com a pasta trocada:
 * as páginas ficam em resources/js/Pages (P maiúsculo), e o padrão procura em js/pages.
 */
return [

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [
            resource_path('js/Pages'),
        ],

        'extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],

    ],

];
