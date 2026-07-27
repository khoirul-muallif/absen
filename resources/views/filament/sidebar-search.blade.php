<div x-data="{ query: '' }" class="px-3 pb-2">
    <input
        type="text"
        x-model="query"
        @input="
            document.querySelectorAll('.fi-sidebar-nav .fi-sidebar-item').forEach(item => {
                const label = item.querySelector('.fi-sidebar-item-label')?.textContent?.toLowerCase() ?? '';
                item.style.display = label.includes(query.toLowerCase()) ? '' : 'none';
            });
            document.querySelectorAll('.fi-sidebar-group').forEach(group => {
                const hasVisible = [...group.querySelectorAll('.fi-sidebar-item')].some(i => i.style.display !== 'none');
                group.style.display = hasVisible ? '' : 'none';
            });
        "
        placeholder="Cari menu..."
        class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm px-3 py-1.5"
    />
</div>
