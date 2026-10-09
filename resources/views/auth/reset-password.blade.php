@extends('layouts.guest')

@section('content')
    <h1 class="text-2xl font-bold tracking-tight mb-1">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4 mt-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" value="{{ $email ?? old('email') }}" required class="w-full rounded-lg border-slate-300">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">New password</label>
            <input type="password" name="password" required class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Confirm</label>
            <input type="password" name="password_confirmation" required class="w-full rounded-lg border-slate-300">
        </div>
        <button class="w-full rounded-lg bg-brand text-white font-semibold py-2.5 hover:bg-brand-dark">Reset password</button>
    </form>
@endsection
