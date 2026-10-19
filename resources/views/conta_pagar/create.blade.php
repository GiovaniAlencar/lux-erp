@extends('default.layout',['title' => 'Nova conta a pagar'])
@section('content')
<div class="page-content">
    <div class="card" style="max-width: 820px; margin: 0 auto;">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Nova conta a pagar</h5>
            <a href="{{ route('conta-pagar.index') }}" class="btn btn-light btn-sm"><i class="bx bx-arrow-back"></i> Voltar</a>
        </div>
        @if($errors->any())<div class="alert alert-danger m-3 mb-0">{{ $errors->first() }}</div>@endif
        @include('conta_pagar._nova')
    </div>
</div>
@endsection
