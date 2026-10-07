@extends('errors.mise-en-page')

@section('code', '419')
@section('titre', 'Votre session a expiré')
@section('message', 'Pour votre sécurité, la page est restée ouverte trop longtemps sans activité. Revenez en arrière, rechargez la page, puis recommencez.')

@section('actions')
    <a class="bouton secondaire" href="{{ url()->previous() }}">Revenir à la page précédente</a>
@endsection
