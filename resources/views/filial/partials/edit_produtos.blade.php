<div class="col-md-4">
	<label class="form-label">{{ $lbl }}</label>
	<select id="locais" name="local[]" required class="multiple-select" multiple>
		@foreach($locais as $key => $l)
		<option @if(in_array($key, $locais_ativos)) selected @endif value="{{$key}}">{{$l}}</option>
		@endforeach
	</select>

</div>