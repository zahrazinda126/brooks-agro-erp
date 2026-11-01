@extends('layouts.app')
@section('title', 'Check In a Visitor')
@section('content')
<form class="card" method="POST" action="{{ route('visitors.store') }}">@csrf
<div class="form-grid">
 <div class="field"><label for="v-name">Visitor's name</label><input id="v-name" name="visitor_name" value="{{ old('visitor_name') }}" required maxlength="160"></div>
 <div class="field"><label for="v-phone">Phone number</label><input id="v-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="40"></div>
 <div class="field"><label for="v-org">Organisation</label><input id="v-org" name="organization" value="{{ old('organization') }}" maxlength="160"></div>
 <div class="field"><label for="v-host">Person being visited</label><input id="v-host" name="person_to_visit" value="{{ old('person_to_visit') }}" required maxlength="160"></div>
 <div class="field field--wide"><label for="v-purpose">Purpose of visit</label><input id="v-purpose" name="purpose" value="{{ old('purpose') }}" required maxlength="250"></div>
 <div class="field"><label for="v-date">Visit date</label><input id="v-date" type="date" name="visit_date" value="{{ old('visit_date', now()->toDateString()) }}" required></div>
 <div class="field"><label for="v-in">Check-in time</label><input id="v-in" type="datetime-local" name="checked_in_at" value="{{ old('checked_in_at', now()->format('Y-m-d\TH:i')) }}" required></div>
 <div class="field"><label for="v-pass">Visitor pass number</label><input id="v-pass" name="pass_number" value="{{ old('pass_number') }}" maxlength="60"></div>
 <div class="field"><label for="v-car">Vehicle registration</label><input id="v-car" name="vehicle_registration" value="{{ old('vehicle_registration') }}" maxlength="40"></div>
 <div class="field"><label for="v-items">Items carried in</label><input id="v-items" name="items_carried" value="{{ old('items_carried') }}" maxlength="500"></div>
 <div class="field"><label for="v-notes">Notes</label><input id="v-notes" name="notes" value="{{ old('notes') }}" maxlength="2000"></div>
</div>
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
<div class="form-actions"><button class="btn">Check in visitor</button></div>
</form>
@endsection
