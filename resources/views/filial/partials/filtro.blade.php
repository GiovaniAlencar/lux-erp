<div class="{{ $wrapperClass ?? 'col-md-3 col-12 mt-3' }}">
	<label class="form-label">{{ $lbl }}</label>
	<select id="locais" name="filial_id" class="form-select form-select-sm">
        <option value="todos">Todos</option>
		@foreach($locais as $key => $l)
		<option @if($filial_id == $key) selected @endif value="{{$key}}">{{$l}}</option>
		@endforeach
	</select>
</div>