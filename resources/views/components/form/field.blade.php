@props(['label', 'name', 'type' => 'text'])

<div class="space-y-2">
    <label for="{{ $name }}" class="label">{{ $label }}</label>
    <input type="{{ $type }}" class="input" id="{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" {{ $attributes }}>

    @error($name)
        <p class="error">{{ $message }}</p>
    @enderror
</div>
