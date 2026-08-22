<div class="ask">
    <p class="ask-q">Which house is this record for?</p>
    <p class="ask-hint">Only houses assigned to you are shown.</p>
    <select name="poultry_house_id" required onchange="if(this.selectedOptions[0].dataset.url){window.location.href=this.selectedOptions[0].dataset.url}">
        <option value="">Choose house</option>
        @foreach($houseChoices as $house)
            <option value="{{ $house->id }}" data-url="{{ route($entryRoute, ['batch'=>$house->animal_group_id, 'house'=>$house->id]) }}" @selected((int) old('poultry_house_id', $selectedHouse->id) === $house->id)>
                {{ $house->house_number }} · {{ number_format($house->bird_quantity) }} birds
            </option>
        @endforeach
    </select>
</div>
