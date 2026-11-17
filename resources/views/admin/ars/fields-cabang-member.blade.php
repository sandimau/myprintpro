<div class="form-group mb-3">
    <label class="required" for="cabang_id">Cabang</label>
    <select class="form-select {{ $errors->has('cabang_id') ? 'is-invalid' : '' }}" name="cabang_id" id="cabang_id" required>
        <option value="">Pilih cabang</option>
        @foreach ($cabangs as $cabang)
            <option value="{{ $cabang->id }}" {{ (string) old('cabang_id', $selectedCabangId) === (string) $cabang->id ? 'selected' : '' }}>
                {{ $cabang->nama }}{{ $cabang->kode ? ' ('.$cabang->kode.')' : '' }}
            </option>
        @endforeach
    </select>
    @error('cabang_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="form-group mb-3">
    <label class="required" for="member_id">Member</label>
    <select class="form-select {{ $errors->has('member_id') ? 'is-invalid' : '' }}" name="member_id" id="member_id" data-selected="{{ old('member_id', $selectedMemberId) }}" required>
        <option value="">Pilih member</option>
        @foreach ($members as $member)
            <option value="{{ $member->id }}" data-cabang-id="{{ $member->cabang_id }}">{{ $member->nama_lengkap }}</option>
        @endforeach
    </select>
    @error('member_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@push('after-scripts')
    <script>
        const cabangSelect = document.getElementById('cabang_id');
        const memberSelect = document.getElementById('member_id');
        const selectedMember = memberSelect.dataset.selected;

        function filterMembers() {
            for (const option of memberSelect.options) {
                if (!option.value) continue;
                option.hidden = option.dataset.cabangId !== cabangSelect.value;
                option.disabled = option.hidden;
            }
            if (memberSelect.selectedOptions[0]?.disabled) memberSelect.value = '';
        }

        cabangSelect.addEventListener('change', filterMembers);
        memberSelect.value = selectedMember;
        filterMembers();
    </script>
@endpush
