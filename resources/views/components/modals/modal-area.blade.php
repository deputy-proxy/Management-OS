<div id="grve-modal-area">
    <div class="fixed inset-0 bg-black/50"></div>
</div>

<div id="grve-search-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded shadow-lg relative w-full max-w-lg">
        <button class="absolute top-2 right-3 text-2xl" aria-label="Close search modal">&times;</button>
        <form method="get" action="{{ route('search') }}" class="flex gap-2">
            <input type="search" 
                name="q" 
                placeholder="Caută cursuri, examene, articole..." 
                class="border p-2 rounded flex-1"
                required />
            <button type="submit" class="bg-primary-1 text-white py-2 px-4 rounded">Caută</button>
        </form>
    </div>
</div>
