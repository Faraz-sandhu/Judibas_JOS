<div class="branding-asset">
    <div class="branding-asset-preview {{ $previewClass }}"><img id="preview-{{ $field }}" src="{{ isset($fallbackField) ? $branding->variantUrl($field, $fallbackField, $fallback) : $branding->assetUrl($field, $fallback) }}" alt="{{ $title }} preview"></div>
    <div class="branding-asset-info">
        <h5>{{ $title }}</h5><p>{{ $description }}</p>
        <div class="branding-upload">
            <label for="{{ $field }}" class="branding-upload-label"><i class="ti ti-upload"></i>Choose image</label>
            <span class="branding-file-name" data-file-name="{{ $field }}">No new file selected</span>
            <input type="file" class="branding-file" id="{{ $field }}" name="{{ $field }}" data-preview="preview-{{ $field }}" accept="{{ ($favicon ?? false) ? '.png,.ico,.jpg,.jpeg,.webp' : '.png,.jpg,.jpeg,.webp' }}">
        </div>
        <small class="branding-hint">{{ $hint }}</small>
        @error($field)<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</div>
