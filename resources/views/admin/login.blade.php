@extends('layouts.app')
@section('title', 'Login Admin · Hadirku')
@section('content')
<form class="card login" method="POST" action="{{ route('admin.login') }}">
  @csrf
  <div class="logo">🛡️</div>
  <h2>Admin Hadirku</h2>
  @if($errors->any())<div class="info no">{{ $errors->first() }}</div>@endif
  <label for="email">Email</label>
  <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
  <label for="pw">Password</label>
  <input id="pw" name="password" type="password" required>
  <button class="btn full pulse" type="submit">Masuk</button>
  <p class="hint" style="margin-top:14px"><a href="{{ route('login') }}">Login karyawan</a></p>
</form>
@endsection
