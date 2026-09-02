<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>@include('factures.pdf._styles')</style>
</head>
<body>
    @include('factures.pdf._header')
    @include('factures.pdf._client')
    @include('factures.pdf._lignes')
    @include('factures.pdf._totaux')
    @include('factures.pdf._pied')
</body>
</html>
