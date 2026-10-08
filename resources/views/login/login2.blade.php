{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LUX Perfumes - Login</title>

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --foreground: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --bg: #f1f5f9;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 28px;
            padding: 40px 32px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
        }

        .login-logo {
            width: 128px;
            height: 128px;
            border-radius: 18px;
            object-fit: cover;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .login-title {
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--foreground);
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            font-size: 0.9rem;
            color: var(--muted);
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 16px;
        }

        .input-group-custom .bi {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 1.1rem;
        }

        .input-group-custom .bi-left {
            left: 16px;
        }

        .form-control-custom {
            height: 56px;
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0 48px;
            font-size: 1rem;
            color: var(--foreground);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .form-control-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 1.1rem;
        }

        .btn-primary-custom {
            height: 56px;
            width: 100%;
            border: none;
            border-radius: 12px;
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background .15s;
        }

        .btn-primary-custom:hover {
            background: var(--primary-hover);
        }

        .btn-secondary-custom {
            height: 56px;
            width: 100%;
            border: none;
            border-radius: 12px;
            background: #f1f5f9;
            color: var(--foreground);
            font-weight: 600;
            font-size: 1rem;
            margin-top: 12px;
        }

        .forgot-link {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--primary);
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
        }

        .divider span.line {
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span.text {
            font-size: 0.875rem;
            color: var(--muted);
        }

        .secure-box {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            background: #f1f5f9;
            border-radius: 12px;
            padding: 16px;
        }

        .secure-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .form-check-label {
            color: var(--foreground);
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="login-card">
        {{-- Logo --}}
        <div class="d-flex justify-content-center mb-4">
            <img src="{{ 'data:image/png;base64,' . base64_encode(file_get_contents(@public_path('uploads/configEmitente/') . '9SZsBLjqCPFDbRKM7RIOgnKDw.png')) }}" alt="LUX Perfumes Distribuidora" class="login-logo">
        </div>

        {{-- ===== Tela de Login ===== --}}
        <div id="login-view">
            <div class="text-center mb-4">
                <h1 class="login-title mb-2">Bem-vindo</h1>
                <p class="login-subtitle mb-0">Entre com suas credenciais para acessar o sistema</p>
            </div>

            {{-- ALERTS --}}
            @if(session()->has('flash_sucesso'))
            <div class="alert alert-success py-2">
                {{ session()->get('flash_sucesso') }}
            </div>
            @endif

            @if(session()->has('flash_erro'))
            <div class="alert alert-danger py-2">
                {{ session()->get('flash_erro') }}
            </div>
            @endif


            <form method="post" action="{{ route('login.request') }}" id="form-login">
                @csrf

                <div class="input-group-custom">
                    <i class="bi bi-person bi-left"></i>
                    <input
                        type="text"
                        name="login" v
                        alue="{{ old('login') }}"
                        class="form-control-custom"
                        placeholder="Usuário"
                        id="login"

                        @if(session('login') !=null)
                        value="{{ session('login') }}"
                        @elseif(isset($loginCookie))
                        value="{{ $loginCookie }}"
                        @endif
                        autocomplete="off">
                </div>

                <div class="input-group-custom">
                    <i class="bi bi-lock bi-left"></i>
                    <input type="password"
                        name="senha"
                        id="senha"
                        class="form-control-custom"
                        placeholder="Senha"
                        @if(isset($senhaCookie))
                        value="{{ $senhaCookie }}"
                        @endif autocomplete="off">
                    <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Mostrar senha">
                        <i class="bi bi-eye" id="toggle-icon"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="lembrar" name="lembrar"
                            @isset($lembrarCookie)
                            @if($lembrarCookie==true) checked @endif
                            @endisset>
                        <label class="form-check-label" for="lembrar">Lembrar-me</label>
                    </div>
                    <button type="button" class="forgot-link" onclick="showRecover()">Esqueci minha senha</button>
                </div>

                <button type="submit" class="btn-primary-custom">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Entrar
                </button>
            </form>

            <div class="divider">
                <span class="line"></span>
                <span class="text">ou</span>
                <span class="line"></span>
            </div>

            <div class="secure-box">
                <div class="secure-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <p class="fw-semibold mb-1" style="color: var(--foreground)">Acesso seguro</p>
                    <p class="mb-0" style="font-size: 0.875rem; color: var(--muted); line-height: 1.5;">
                        Seus dados estão protegidos com criptografia e tecnologia de ponta.
                    </p>
                </div>
            </div>
        </div>

        {{-- ===== Tela de Recuperar Senha ===== --}}
        <div id="recover-view" style="display: none;">
            <div class="text-center mb-4">
                <h1 class="login-title mb-2">Recuperar senha</h1>
                <p class="login-subtitle mb-0">Informe seu e-mail para receber uma nova senha.</p>
            </div>

            <form method="post" action="{{ route('recuperarSenha') }}">
                @csrf

                <div class="input-group-custom">
                    <i class="bi bi-envelope bi-left"></i>
                    <input type="email" name="email" class="form-control-custom" placeholder="E-mail">
                </div>

                <button type="submit" class="btn-primary-custom">Recuperar senha</button>
                <button type="button" class="btn-secondary-custom" onclick="showLogin()">Voltar</button>
            </form>
        </div>
    </div>
    <script src="/assets/js/jquery.min.js"></script>
    <script>
        function togglePassword() {
            const input = document.getElementById('senha');
            const icon = document.getElementById('toggle-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        function showRecover() {
            document.getElementById('login-view').style.display = 'none';
            document.getElementById('recover-view').style.display = 'block';
        }

        function showLogin() {
            document.getElementById('recover-view').style.display = 'none';
            document.getElementById('login-view').style.display = 'block';
        }
    </script>
</body>

</html>