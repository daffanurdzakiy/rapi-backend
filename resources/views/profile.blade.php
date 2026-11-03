@extends('layouts.app')
@section('content')
<div class="heading"><div><h1>Profil {{ auth()->user()->role?->role_name }}</h1><p>Informasi akun dan peran Anda di RAPI-Sekolah.</p></div></div>
<div class="card"><div class="form-grid"><div><label>Nama lengkap</label><p>{{ auth()->user()->name }}</p></div><div><label>Email</label><p>{{ auth()->user()->email }}</p></div><div><label>WhatsApp</label><p>{{ auth()->user()->whatsapp ?: 'Belum diatur' }}</p></div></div></div>
@endsection