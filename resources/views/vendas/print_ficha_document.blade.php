<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="utf-8">
	<title>Ficha de separação #{{ $item->id }}</title>
	<style type="text/css">
		body {
			font-family: Arial, sans-serif;
			background-color: #f9f9f9;
			padding: 20px;
		}
@include('vendas._print_ficha_css')
	</style>
</head>
<body>
@include('vendas.print_ficha_separacao')
</body>
</html>
