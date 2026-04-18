<x-filament-panels::page>
    <div
        x-data="navigationSettings(@js($this->structure))"
        class="space-y-6"
    >
        {{-- Save button --}}
        <div class="flex justify-end">
            <x-filament::button
                wire:click="save"
                icon="heroicon-o-check"
            >
                Save Navigation
            </x-filament::button>
        </div>

        {{-- Cluster list --}}
        <template x-for="(cluster, ci) in structure.clusters" :key="ci">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">

                {{-- Cluster header --}}
                <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <x-filament::icon icon="heroicon-o-rectangle-stack" class="w-5 h-5 text-primary-500" />

                    <input
                        type="text"
                        x-model="cluster.label"
                        @change="renameCluster(ci, cluster.label)"
                        class="flex-1 bg-transparent font-semibold text-gray-800 dark:text-gray-100 border-none focus:ring-0 focus:outline-none text-sm"
                    />

                    <div class="flex gap-1">
                        <button @click="moveClusters(ci, -1)" :disabled="ci === 0" class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30">
                            <x-filament::icon icon="heroicon-o-chevron-up" class="w-4 h-4" />
                        </button>
                        <button @click="moveClusters(ci, 1)" :disabled="ci === structure.clusters.length - 1" class="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 disabled:opacity-30">
                            <x-filament::icon icon="heroicon-o-chevron-down" class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {{-- Groups --}}
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="(group, gi) in cluster.groups" :key="gi">
                        <div class="px-4 py-3">

                            {{-- Group header --}}
                            <div class="flex items-center gap-2 mb-2">
                                <x-filament::icon icon="heroicon-o-folder" class="w-4 h-4 text-gray-400" />

                                <input
                                    type="text"
                                    x-model="group.label"
                                    @change="renameGroup(ci, gi, group.label)"
                                    class="flex-1 bg-transparent text-sm font-medium text-gray-600 dark:text-gray-300 border-none focus:ring-0 focus:outline-none"
                                />

                                <div class="flex gap-1">
                                    <button @click="moveGroup(ci, gi, -1)" :disabled="gi === 0" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30">
                                        <x-filament::icon icon="heroicon-o-chevron-up" class="w-3 h-3" />
                                    </button>
                                    <button @click="moveGroup(ci, gi, 1)" :disabled="gi === cluster.groups.length - 1" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-30">
                                        <x-filament::icon icon="heroicon-o-chevron-down" class="w-3 h-3" />
                                    </button>
                                    <button @click="removeGroup(ci, gi)" class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-red-400 hover:text-red-600">
                                        <x-filament::icon icon="heroicon-o-trash" class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>

                            {{-- Resources (drag and drop zone) --}}
                            <div
                                class="ml-6 space-y-1 min-h-[2rem] rounded-lg transition-colors"
                                :class="dragTarget === `${ci}:${gi}` ? 'bg-primary-50 dark:bg-primary-900/20 ring-2 ring-primary-400' : ''"
                                @dragover.prevent="dragTarget = `${ci}:${gi}`"
                                @dragleave="dragTarget = null"
                                @drop.prevent="onDrop(ci, gi)"
                            >
                                <template x-for="(resource, ri) in group.resources" :key="ri">
                                    <div
                                        class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm text-gray-700 dark:text-gray-300 cursor-grab active:cursor-grabbing"
                                        :class="dragging && dragging.ci === ci && dragging.gi === gi && dragging.ri === ri ? 'opacity-40 ring-2 ring-primary-400' : ''"
                                        draggable="true"
                                        @dragstart="onDragStart(ci, gi, ri, $event)"
                                        @dragend="onDragEnd()"
                                    >
                                        {{-- Drag handle --}}
                                        <x-filament::icon icon="heroicon-o-bars-2" class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 shrink-0" />

                                        {{-- Icon: different for page vs resource --}}
                                        <template x-if="resource.type === 'page'">
                                            <x-filament::icon icon="heroicon-o-document-text" class="w-3.5 h-3.5 text-blue-400 shrink-0" />
                                        </template>
                                        <template x-if="resource.type !== 'page'">
                                            <x-filament::icon icon="heroicon-o-table-cells" class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                        </template>

                                        <span x-text="resource.label" class="flex-1 truncate"></span>

                                        {{-- Type badge --}}
                                        <span
                                            x-text="resource.type === 'page' ? 'page' : 'resource'"
                                            :class="resource.type === 'page'
                                                ? 'text-[10px] px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-300 shrink-0'
                                                : 'text-[10px] px-1.5 py-0.5 rounded-full bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400 shrink-0'"
                                        ></span>

                                        {{-- Move to select — pre-selected to current position --}}
                                        <select
                                            :value="`${ci}:${gi}`"
                                            @change="moveResource(ci, gi, ri, $event.target.value); $event.target.value = `${ci}:${gi}`"
                                            class="text-xs bg-transparent border-none focus:ring-0 text-gray-400 cursor-pointer max-w-[120px]"
                                        >
                                            <template x-for="(tc, tci) in structure.clusters" :key="tci">
                                                <template x-for="(tg, tgi) in tc.groups" :key="tgi">
                                                    <option
                                                        :value="`${tci}:${tgi}`"
                                                        :selected="`${tci}:${tgi}` === `${ci}:${gi}`"
                                                        x-text="`${tc.label} › ${tg.label}`"
                                                    ></option>
                                                </template>
                                            </template>
                                        </select>
                                    </div>
                                </template>

                                <div x-show="group.resources.length === 0" class="text-xs text-gray-400 italic px-2 py-1">
                                    Drop items here
                                </div>
                            </div>

                        </div>
                    </template>
                </div>

                {{-- Add Group button --}}
                <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-800">
                    <button
                        @click="addGroup(ci)"
                        class="flex items-center gap-2 text-sm text-primary-600 dark:text-primary-400 hover:underline"
                    >
                        <x-filament::icon icon="heroicon-o-plus-circle" class="w-4 h-4" />
                        Add Group
                    </button>
                </div>

            </div>
        </template>
    </div>

    <script>
    function navigationSettings(initialStructure) {
        return {
            structure: initialStructure,
            dragging: null,
            dragTarget: null,

            renameCluster(ci, label) {
                @this.renameCluster(ci, label);
            },

            renameGroup(ci, gi, label) {
                @this.renameGroup(ci, gi, label);
            },

            addGroup(ci) {
                const label = prompt('Group name:', 'New Group');
                if (! label) return;
                this.structure.clusters[ci].groups.push({ label, resources: [] });
                @this.addGroup(ci, label);
            },

            removeGroup(ci, gi) {
                const group = this.structure.clusters[ci].groups[gi];
                if (group.resources.length > 0) {
                    if (! confirm(`"${group.label}" still has ${group.resources.length} item(s). Remove anyway? Items will be lost.`)) return;
                }
                this.structure.clusters[ci].groups.splice(gi, 1);
                @this.removeGroup(ci, gi);
            },

            moveClusters(ci, dir) {
                const clusters = this.structure.clusters;
                const target = ci + dir;
                if (target < 0 || target >= clusters.length) return;
                [clusters[ci], clusters[target]] = [clusters[target], clusters[ci]];
                @this.reorderClusters(clusters.map((_, i) => i));
            },

            moveGroup(ci, gi, dir) {
                const groups = this.structure.clusters[ci].groups;
                const target = gi + dir;
                if (target < 0 || target >= groups.length) return;
                [groups[gi], groups[target]] = [groups[target], groups[gi]];
                @this.reorderGroups(ci, groups.map((_, i) => i));
            },

            moveResource(fromCi, fromGi, ri, destination) {
                if (! destination) return;
                const [tci, tgi] = destination.split(':').map(Number);
                if (tci === fromCi && tgi === fromGi) return;
                const resource = this.structure.clusters[fromCi].groups[fromGi].resources[ri];
                this.structure.clusters[fromCi].groups[fromGi].resources.splice(ri, 1);
                this.structure.clusters[tci].groups[tgi].resources.push(resource);
                @this.moveResource(fromCi, fromGi, ri, tci, tgi);
            },

            onDragStart(ci, gi, ri, event) {
                this.dragging = { ci, gi, ri };
                event.dataTransfer.effectAllowed = 'move';
            },

            onDragEnd() {
                this.dragging = null;
                this.dragTarget = null;
            },

            onDrop(tci, tgi) {
                if (! this.dragging) return;
                const { ci, gi, ri } = this.dragging;
                this.dragTarget = null;
                if (tci === ci && tgi === gi) return;
                const resource = this.structure.clusters[ci].groups[gi].resources[ri];
                this.structure.clusters[ci].groups[gi].resources.splice(ri, 1);
                this.structure.clusters[tci].groups[tgi].resources.push(resource);
                @this.moveResource(ci, gi, ri, tci, tgi);
                this.dragging = null;
            },
        }
    }
    </script>
</x-filament-panels::page>