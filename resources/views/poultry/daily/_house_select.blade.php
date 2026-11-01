<div class="ask">
    <p class="ask-q">Which house is this record for?</p>
    @if($houseChoices->count() === 1)
        {{-- Only one house: nothing to choose, it is shown and used. --}}
        @php($onlyHouse = $houseChoices->first())
        <input type="hidden" name="poultry_house_id" value="{{ $onlyHouse->id }}">
        <p class="house-fixed"><strong>{{ $onlyHouse->house_number }}</strong> · {{ number_format($onlyHouse->bird_quantity) }} birds</p>
    @else
        <p class="ask-hint">Only houses assigned to you are shown.</p>
        <select name="poultry_house_id" required onchange="if(this.selectedOptions[0].dataset.url){window.location.href=this.selectedOptions[0].dataset.url}">
            <option value="">Choose house</option>
            @foreach($houseChoices as $house)
                <option value="{{ $house->id }}" data-url="{{ route($entryRoute, ['batch'=>$house->animal_group_id, 'house'=>$house->id]) }}" @selected((int) old('poultry_house_id', $selectedHouse->id) === $house->id)>
                    {{ $house->house_number }} · {{ number_format($house->bird_quantity) }} birds
                </option>
            @endforeach
        </select>
    @endif
</div>
