import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'input',
        'dropdown',
        'optionsContainer',
        'chipsContainer',
        'selectedInput',
        'chip',
        'loading',
        'wrapper'
    ];

    static values = {
        provider: String,
        multiple: Boolean,
        minChars: { type: Number, default: 1 },
        debounce: { type: Number, default: 300 },
        limit: { type: Number, default: 10 },
        url: String,
        name: String
    };

    connect() {
        console.log('[autocomplete] Controller connected, multiple:', this.multipleValue);
        this.debounceTimer = null;
        this.selectedItems = new Map();
        this.currentFocusIndex = -1;
        this.abortController = null;
        this.isSelectingOption = false;

        // Initialize with existing chips (supports server-rendered chips if they embed meta)
        if (this.hasChipsContainerTarget) {
            this.chipTargets.forEach(chip => {
                const value = chip.dataset.autocompleteChipValue;
                const label = chip.querySelector('.autocomplete-chip-label')?.textContent?.trim();

                let meta = null;
                const metaJson = chip.dataset.autocompleteChipMeta;
                if (metaJson) {
                    try { meta = JSON.parse(metaJson); } catch (e) { /* ignore */ }
                }

                if (value && label) {
                    this.selectedItems.set(value, { id: value, label, meta });
                }
            });
        }

        // Close dropdown when clicking outside
        this.boundHandleClickOutside = this.handleClickOutside.bind(this);
        document.addEventListener('click', this.boundHandleClickOutside);
    }

    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        if (this.abortController) {
            this.abortController.abort();
        }
        document.removeEventListener('click', this.boundHandleClickOutside);
    }

    handleClickOutside(event) {
        if (!this.wrapperTarget.contains(event.target)) {
            this.closeDropdown();
        }
    }

    onInput(event) {
        if (this.isSelectingOption) return;

        const query = event.target.value.trim();

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        if (query.length < this.minCharsValue) {
            this.closeDropdown();
            return;
        }

        this.debounceTimer = setTimeout(() => {
            this.search(query);
        }, this.debounceValue);
    }

    onFocus(event) {
        if (this.isSelectingOption) return;

        // For single mode, select all text so user can easily replace it
        if (!this.multipleValue && event.target.value) {
            event.target.select();
        }

        const query = event.target.value.trim();
        if (query.length >= this.minCharsValue) {
            this.search(query);
        }
    }

    async search(query) {
        console.log('[autocomplete] search called with query:', query);

        if (this.abortController) {
            this.abortController.abort();
        }

        this.abortController = new AbortController();
        this.showLoading();

        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('query', query);
            url.searchParams.set('limit', this.limitValue);

            this.selectedItems.forEach((item, id) => {
                url.searchParams.append('selected[]', id);
            });

            const response = await fetch(url, {
                signal: this.abortController.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const html = await response.text();
            this.displayResults(html);
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Autocomplete search failed:', error);
                this.closeDropdown();
            }
        } finally {
            this.hideLoading();
        }
    }

    displayResults(html) {
        console.log('[autocomplete] displayResults called, HTML length:', html.length);
        this.optionsContainerTarget.innerHTML = html;
        this.openDropdown();
        this.currentFocusIndex = -1;

        const options = this.optionsContainerTarget.querySelectorAll('.autocomplete-option');
        console.log('[autocomplete] Found', options.length, 'options, attaching click listeners');
        options.forEach((option, index) => {
            option.addEventListener('click', () => {
                console.log('[autocomplete] Option clicked at index:', index);
                this.selectOption(index);
            });
            option.addEventListener('mouseenter', () => this.setFocusedOption(index));
        });
    }

    async selectOption(index) {
        console.log('[autocomplete] selectOption called, index:', index, 'multipleValue:', this.multipleValue);

        const options = this.optionsContainerTarget.querySelectorAll('.autocomplete-option');
        const option = options[index];
        if (!option) return;

        const value = option.dataset.autocompleteOptionValue;
        const label = option.dataset.autocompleteOptionLabel;

        let meta = null;
        const metaRaw = option.dataset.autocompleteOptionMeta;
        if (metaRaw) {
            try { meta = JSON.parse(metaRaw); } catch (e) { meta = null; }
        }

        const item = { id: value, label, meta };

        // Set flag to prevent input event from triggering search
        this.isSelectingOption = true;

        // Cancel pending stuff
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = null;
        }
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        this.closeDropdown();

        console.log('[autocomplete] About to check multipleValue:', this.multipleValue, 'type:', typeof this.multipleValue);

        if (this.multipleValue) {
            console.log('[autocomplete] Multiple mode - calling addChip');
            // Note: addChip now fetches chip HTML from backend
            await this.addChip(item);
            this.inputTarget.value = '';
            this.inputTarget.focus();
        } else {
            console.log('[autocomplete] Single mode - calling setSingleValue');
            this.setSingleValue(item);
            // For single mode, show the selected label in the input
            this.inputTarget.value = item.label;
            this.inputTarget.blur();
        }

        this.dispatch('select', { detail: { item } });

        // Reset flag after a short delay to ensure the blur/clear doesn't trigger search
        setTimeout(() => {
            this.isSelectingOption = false;
        }, 100);
    }

    /**
     * Adds a chip by fetching server-rendered HTML from:
     *   /_autocomplete/{provider}/chip?id=...&name=...&theme=...
     *
     * It also updates selectedItems map. If the request fails, it rolls back.
     */
    async addChip(item) {
        if (this.selectedItems.has(item.id)) return;

        // optimistic state update (used for excluding selected items in search)
        this.selectedItems.set(item.id, item);

        try {
            const html = await this.fetchChipHtml(item.id);

            const wrap = document.createElement('div');
            wrap.innerHTML = html.trim();

            const chipEl = wrap.firstElementChild;
            if (!chipEl) {
                throw new Error('Chip endpoint returned empty HTML.');
            }

            // Ensure dataset value exists (useful if backend template forgot)
            if (!chipEl.dataset.autocompleteChipValue) {
                chipEl.dataset.autocompleteChipValue = item.id;
            }

            this.chipsContainerTarget.appendChild(chipEl);

            // Dispatch change event on the new hidden input so frameworks
            // (e.g. Symfony Live Components) detect the added value
            const hiddenInput = chipEl.querySelector('input[type="hidden"]');
            if (hiddenInput) {
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Rehydrate selectedItems from server meta if present (server is source of truth)
            const serverMetaJson = chipEl.dataset.autocompleteChipMeta;
            let serverMeta = null;
            if (serverMetaJson) {
                try { serverMeta = JSON.parse(serverMetaJson); } catch (e) { /* ignore */ }
            }

            const serverLabel =
                chipEl.querySelector('.autocomplete-chip-label')?.textContent?.trim()
                ?? item.label;

            this.selectedItems.set(item.id, { id: item.id, label: serverLabel, meta: serverMeta });
        } catch (e) {
            // rollback
            this.selectedItems.delete(item.id);
            console.error('Failed to add chip:', e);
            throw e;
        }
    }

    /**
     * Builds the chip URL based on urlValue.
     * If urlValue is "/_autocomplete/users?theme=foo", chip URL becomes
     * "/_autocomplete/users/chip?theme=foo&id=...&name=..."
     */
    buildChipUrl(id) {
        const searchUrl = new URL(this.urlValue, window.location.origin);

        // /_autocomplete/{provider}/chip
        const chipPath = searchUrl.pathname.replace(/\/$/, '') + '/chip';
        const chipUrl = new URL(chipPath, window.location.origin);

        // carry over query params (e.g., theme=...)
        chipUrl.search = searchUrl.search;

        chipUrl.searchParams.set('id', id);
        chipUrl.searchParams.set('name', this.getInputName());

        return chipUrl.toString();
    }

    async fetchChipHtml(id) {
        const url = this.buildChipUrl(id);
        console.log('[autocomplete] chip url:', url);

        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error(`Chip HTTP error! status: ${response.status}`);
        }

        return await response.text();
    }


    removeChip(event) {
        const chip = event.target.closest('[data-kerrialnewham--autocomplete--ssr-autocomplete-target="chip"]');
        if (!chip) return;

        const value = chip.dataset.autocompleteChipValue;
        this.removeChipByValue(value);
    }

    removeChipByValue(value) {
        this.selectedItems.delete(value);

        const chip = this.chipTargets.find(c => c.dataset.autocompleteChipValue === value);
        if (chip) {
            // Dispatch change on the hidden input before removing,
            // so Live Components detect the removal
            const hiddenInput = chip.querySelector('input[type="hidden"]');
            if (hiddenInput) {
                hiddenInput.value = '';
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            chip.remove();
        }

        this.dispatch('remove', { detail: { value } });
    }

    setSingleValue(item) {
        this.selectedItems.clear();
        this.selectedItems.set(item.id, item);

        if (this.hasSelectedInputTarget) {
            this.selectedInputTarget.value = item.id;
            // Dispatch change event so frameworks (e.g. Symfony Live Components)
            // detect the programmatic value change
            this.selectedInputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    getInputName() {
        if (this.hasNameValue && this.nameValue) {
            return this.nameValue;
        }

        if (this.hasSelectedInputTarget && this.selectedInputTarget.name) {
            return this.selectedInputTarget.name.replace('[]', '');
        }

        // fallback: if the visible input has a name, use it
        if (this.hasInputTarget && this.inputTarget.name) {
            return this.inputTarget.name.replace('[]', '');
        }

        return 'autocomplete';
    }

    onKeydown(event) {
        if (!this.isDropdownOpen()) return;

        const options = this.optionsContainerTarget.querySelectorAll('.autocomplete-option');

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.currentFocusIndex = Math.min(this.currentFocusIndex + 1, options.length - 1);
                this.setFocusedOption(this.currentFocusIndex);
                break;

            case 'ArrowUp':
                event.preventDefault();
                this.currentFocusIndex = Math.max(this.currentFocusIndex - 1, 0);
                this.setFocusedOption(this.currentFocusIndex);
                break;

            case 'Enter':
                event.preventDefault();
                if (this.currentFocusIndex >= 0) {
                    // allow async selection (chip fetch)
                    this.selectOption(this.currentFocusIndex);
                }
                break;

            case 'Escape':
                event.preventDefault();
                this.closeDropdown();
                break;
        }
    }

    setFocusedOption(index) {
        const options = this.optionsContainerTarget.querySelectorAll('.autocomplete-option');

        options.forEach((option, i) => {
            if (i === index) {
                option.classList.add('autocomplete-option-focused');
                option.scrollIntoView({ block: 'nearest' });
            } else {
                option.classList.remove('autocomplete-option-focused');
            }
        });

        this.currentFocusIndex = index;
    }

    openDropdown() {
        this.dropdownTarget.style.display = 'block';
        this.dropdownTarget.classList.add('show');
    }

    closeDropdown() {
        this.dropdownTarget.style.display = 'none';
        this.dropdownTarget.classList.remove('show');
        this.currentFocusIndex = -1;
    }

    isDropdownOpen() {
        return this.dropdownTarget.style.display !== 'none';
    }

    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.style.display = 'inline-block';
        }
    }

    hideLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.style.display = 'none';
        }
    }
}
