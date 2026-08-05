{{-- Shared by add and edit. Save as _form.blade.php --}}

<div class="card">
    <div class="form-grid">
        <div class="field @error('name') field--error @enderror">
            <label for="name">Name <span class="req">required</span></label>
            <input id="name" name="name" type="text"
                   value="{{ old('name', $feed->name ?? '') }}"
                   placeholder="e.g. Maize bran" required>
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">As your staff call it.</p>
        </div>

        <div class="field">
            <label for="category">What is it for? <span class="req">required</span></label>
            <select id="category" name="category" required>
                @foreach ([
                    'complete'   => 'Main feed — fed on its own',
                    'energy'     => 'Energy — mixed in',
                    'protein'    => 'Protein — mixed in',
                    'mineral'    => 'Minerals and shells',
                    'supplement' => 'Supplement',
                ] as $v => $label)
                    <option value="{{ $v }}" @selected(old('category', $feed->category ?? 'complete') === $v)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <p class="field-hint">Decides where it appears on the worker's screen.</p>
        </div>

        <div class="field @error('default_kg_per_bag') field--error @enderror">
            <label for="default_kg_per_bag">Usual bag size (kg) <span class="req">required</span></label>
            <input id="default_kg_per_bag" name="default_kg_per_bag" type="number"
                   inputmode="numeric" min="1" max="200"
                   value="{{ old('default_kg_per_bag', $feed->default_kg_per_bag ?? 50) }}" required>
            @error('default_kg_per_bag')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field @error('cost_per_kg') field--error @enderror">
            <label for="cost_per_kg">Cost per kg (UGX)</label>
            <input id="cost_per_kg" name="cost_per_kg" type="number"
                   inputmode="numeric" min="0" step="1"
                   value="{{ old('cost_per_kg', isset($feed->cost_per_kg) ? (int) $feed->cost_per_kg : '') }}">
            @error('cost_per_kg')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">Used later to cost a batch. Leave blank if it changes often.</p>
        </div>

        <div class="field @error('from_week') field--error @enderror">
            <label for="from_week">Suits birds from week</label>
            <input id="from_week" name="from_week" type="number"
                   inputmode="numeric" min="0" max="100"
                   value="{{ old('from_week', $feed->from_week ?? '') }}">
            @error('from_week')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">Leave blank if it suits any age.</p>
        </div>

        <div class="field @error('to_week') field--error @enderror">
            <label for="to_week">Until week</label>
            <input id="to_week" name="to_week" type="number"
                   inputmode="numeric" min="0" max="150"
                   value="{{ old('to_week', $feed->to_week ?? '') }}">
            @error('to_week')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field field--wide">
            <label for="note">Note</label>
            <input id="note" name="note" type="text"
                   value="{{ old('note', $feed->note ?? '') }}"
                   placeholder="e.g. Too much dilutes protein">
            <p class="field-hint">Shown to workers under the feed name.</p>
        </div>
    </div>
</div>
