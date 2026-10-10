<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-inertia::head>
            <title>{{ config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="bg-papel font-sans text-grafite antialiased">
        <x-inertia::app />
    </body>
</html>
