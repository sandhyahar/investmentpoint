@extends('admin.layouts.app')
@section('page-title', 'Edit Premium Content')
@section('content')
<div class="mb-3"><a href="{{ route('admin.premium.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Premium</a></div>
<div class="form-card" style="max-width:700px"><form action="{{ route('admin.premium.update', $premium) }}" method="POST">@csrf @method('PUT')
@include('admin.premium.form')
<button class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> Update Content</button></form></div>
@endsection
