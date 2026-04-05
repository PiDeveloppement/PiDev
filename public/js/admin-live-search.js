document.addEventListener('DOMContentLoaded', () => {
    const normalize = (value) =>
        (value || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();

    document.querySelectorAll('[data-live-search-root]').forEach((root) => {
        const input = root.querySelector('[data-live-search-input]');
        const hiddenInput = root.querySelector('[data-live-search-hidden]');
        const items = Array.from(root.querySelectorAll('[data-live-search-item]'));
        const emptyState = root.querySelector('[data-live-search-empty]');

        if (!input || items.length === 0) {
            return;
        }

        const parentForm = input.closest('form');
        const wrapper = document.createElement('div');
        wrapper.className = 'live-search-shell';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const panel = document.createElement('div');
        panel.className = 'live-search-panel';
        panel.hidden = true;
        wrapper.appendChild(panel);

        const syncPanelSpace = () => {
            if (panel.hidden || panel.childElementCount === 0) {
                wrapper.classList.remove('is-open');
                wrapper.style.marginBottom = '0px';
                return;
            }

            wrapper.classList.add('is-open');
            wrapper.style.marginBottom = `${panel.offsetHeight + 12}px`;
        };

        const suggestions = Array.from(new Set(
            items.flatMap((item) =>
                (item.dataset.liveSuggest || item.dataset.search || '')
                    .split('|')
                    .map((part) => part.trim())
                    .filter(Boolean)
            )
        ));

        const closeSuggestions = () => {
            panel.hidden = true;
            panel.innerHTML = '';
            syncPanelSpace();
        };

        const applyFilter = () => {
            const query = normalize(input.value);
            if (hiddenInput) {
                hiddenInput.value = input.value;
            }
            let visibleCount = 0;

            items.forEach((item) => {
                const haystack = normalize(item.dataset.search || item.textContent);
                const match = query === '' || haystack.includes(query);
                item.hidden = !match;
                if (match) {
                    visibleCount += 1;
                }
            });

            if (emptyState) {
                emptyState.hidden = visibleCount !== 0;
            }
        };

        const renderSuggestions = () => {
            const query = normalize(input.value);
            panel.innerHTML = '';

            if (query.length === 0) {
                closeSuggestions();
                return;
            }

            const matches = suggestions
                .filter((value) => normalize(value).includes(query))
                .slice(0, 6);

            if (matches.length === 0) {
                closeSuggestions();
                return;
            }

            matches.forEach((value) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'live-search-option';
                button.textContent = value;
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    input.value = value;
                    if (hiddenInput) {
                        hiddenInput.value = value;
                    }
                    applyFilter();
                    closeSuggestions();
                });
                panel.appendChild(button);
            });

            panel.hidden = false;
            requestAnimationFrame(syncPanelSpace);
        };

        input.addEventListener('input', () => {
            applyFilter();
            renderSuggestions();
        });

        input.addEventListener('focus', renderSuggestions);
        input.addEventListener('change', () => {
            applyFilter();
            renderSuggestions();
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSuggestions();
            }
        });

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) {
                closeSuggestions();
            }
        });

        if (parentForm) {
            parentForm.querySelectorAll('select').forEach((select) => {
                select.addEventListener('change', () => parentForm.submit());
            });
        }

        applyFilter();
        syncPanelSpace();
    });
});
