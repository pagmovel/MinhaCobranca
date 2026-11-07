@php($tema = auth()->user()?->tema ?? \App\Enums\TemaVisual::Talao)
<!DOCTYPE html>
<html lang="pt-BR" data-tema="{{ $tema->value }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Só as fontes do tema ativo: sem os aliases, a diretiva carregaria (e pré-carregaria) as de todos os temas. --}}
        @fonts($tema->aliasesDasFontes())
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-inertia::head>
            <title>{{ config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="bg-papel font-sans text-grafite antialiased">
        <x-inertia::app />
    </body>
</html>
