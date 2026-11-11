@extends('default.layout', ['title' => 'Início'])
@section('content')
{{-- Painel da equipe (não-administradores): só quantidades e atalhos, sem valores de vendas ou contas. --}}
<div class="page-content">
    <div class="card card-custom gutter-b">
        <div class="card-body">
            <h4 class="mb-1">Olá{{ $nome ? ', ' . \Illuminate\Support\Str::of($nome)->before(' ') : '' }}!</h4>
            <p class="text-muted mb-4">Resumo do dia — {{ date('d/m/Y') }}</p>

            <div class="row g-3">
                @foreach($cards as $c)
                <div class="col-12 col-md-6 col-xl">
                    <a href="{{ $c[4] }}" class="text-decoration-none">
                        <div class="card radius-10 overflow-hidden {{ $c[3] }} mb-0 h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center m-1">
                                    <div>
                                        <p class="mb-0 text-white">{{ $c[0] }}</p>
                                        <h4 class="mb-0 text-white">{{ $c[1] }}</h4>
                                    </div>
                                    <div class="ms-auto text-white"><i class="{{ $c[2] }} font-30"></i></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>

            <h6 class="mt-4 mb-3">Atalhos</h6>
            <div class="row g-3">
                @foreach($atalhos as $a)
                <div class="col-6 col-md-4 col-xl-3">
                    <a href="{{ $a[2] }}" class="btn btn-outline-dark w-100 py-3">
                        <i class="{{ $a[1] }} d-block mb-1" style="font-size: 26px"></i>{{ $a[0] }}
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
