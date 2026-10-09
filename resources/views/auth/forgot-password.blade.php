@extends('layouts.guest')

@section('content')
    <h1 class="text-2xl font-bold tracking-tight mb-1">Reset your password</h1>
    <p class="text-slate-500 text-sm mb-6">We will email you a reset link.</p>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border-slate-300">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <button class="w-full rounded-lg bg-brand text-white font-semibold py-2.5 hover:bg-brand-dark">Email reset link</button>
    </form>
@endsection
