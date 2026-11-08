<section class="card"><div class="section-heading"><div><p class="card-label">Worker accommodation</p><h3>Check in worker and record issued items</h3><p>Complete the room details, then add an item if anything is issued at check-in.</p></div></div>
<div class="accommodation-form-body"><form method="POST" action="{{ route('hr.accommodation.assignments.store') }}" class="form-grid">@csrf
 <div class="field"><label>Worker</label><select name="employee_id" required><option value="">Choose worker</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(old('employee_id', $filters['employee'] ?? '') == $employee->id)>{{ $employee->staff_no }} · {{ $employee->fullName() }}</option>@endforeach</select></div>
 <div class="field"><label>Room</label><select name="staff_hostel_room_id" required><option value="">Choose available room</option>@foreach($rooms->where('condition','!=','unavailable')->filter(fn($room)=>$room->occupied<$room->bed_spaces) as $room)<option value="{{ $room->id }}" @selected(old('staff_hostel_room_id') == $room->id)>{{ $room->hostel_name }} · Room {{ $room->room_number }} ({{ $room->bed_spaces-$room->occupied }} available)</option>@endforeach</select></div>
 <div class="field"><label>Check-in date</label><input type="date" name="checked_in_on" value="{{ old('checked_in_on', now()->toDateString()) }}" required></div>
 <div class="field"><label>Expected checkout</label><input type="date" name="expected_checkout_on" value="{{ old('expected_checkout_on') }}"></div>
 <div class="field field--wide"><strong>Optional: item issued at check-in</strong><small>Leave item blank if nothing is issued. Untick returnable for soap and other consumables.</small></div>
 <div class="field"><label>Item</label><input name="item_name" value="{{ old('item_name') }}" placeholder="Bed, mattress, bedsheet, soap"></div>
 <div class="field"><label>Quantity</label><input type="number" min="0.01" step="0.01" name="quantity" value="{{ old('quantity') }}"></div>
 <div class="field"><label>Issued on</label><input type="date" name="issued_on" value="{{ old('issued_on', now()->toDateString()) }}"></div>
 <div class="field"><label><input type="checkbox" name="is_returnable" value="1" @checked(old('is_returnable', true))> Must be returned</label></div>
 <div class="field field--wide"><label>Notes</label><textarea name="notes">{{ old('notes') }}</textarea></div>
 <div class="form-actions field--wide"><button class="btn">Check in worker and save</button></div>
</form></div></section>
