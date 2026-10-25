@if (isset($page))
    <x-themes.shared.layout :page="$page">
        <x-pwa.offline-content :name="$page->name" :whatsapp="$page->whatsapp_number" />
    </x-themes.shared.layout>
@else
    @extends('layouts.platform')

    @section('title', 'You are offline — mypage.ng')
    @section('content')
        <x-pwa.offline-content name="mypage.ng" />
    @endsection
@endif
