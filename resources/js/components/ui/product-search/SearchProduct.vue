<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Package, Search } from 'lucide-vue-next';
import { buildVariantDisplayName, filterAndRankProductVariants, normalizeProductSearchText } from '@/composables/products/productSearch';
import type { VariantOption } from '@/composables/pos/models';

// ── Props / Emits ─────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<{
    variants: VariantOption[];
    modelValue: string;
    selectedWarehouseId?: number | null;
    placeholder?: string;
    disabled?: boolean;
}>(), {
    selectedWarehouseId: null,
    placeholder: 'Tìm hàng hóa',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    select: [variant: VariantOption];
    enterNoMatch: [query: string];
}>();

// ── Refs ──────────────────────────────────────────────────────────────────────

const inputRef = ref<HTMLInputElement | null>(null);
const listRef = ref<HTMLDivElement | null>(null);
const isOpen = ref(false);
const highlightedIndex = ref(-1);

// ── Search / filter ───────────────────────────────────────────────────────────

const normalize = normalizeProductSearchText;

const filteredVariants = computed(() => {
    return filterAndRankProductVariants(props.variants, props.modelValue, 30);
});

const hasQuery = computed(() => props.modelValue.trim().length > 0);
const showDropdown = computed(() => hasQuery.value && isOpen.value);

// ── Display helpers ───────────────────────────────────────────────────────────

const displayName = (v: VariantOption): string => {
    return buildVariantDisplayName(v.product_name, v.name);
};

const stockQty = (v: VariantOption): number | null => {
    if (props.selectedWarehouseId != null) {
        const entry = v.stock_by_warehouse?.find((s) => s.warehouse_id === props.selectedWarehouseId);
        return entry?.quantity ?? v.total_stock ?? null;
    }
    return v.total_stock ?? null;
};

const formatPrice = (price: number) =>
    new Intl.NumberFormat('vi-VN').format(price);

// ── Interactions ──────────────────────────────────────────────────────────────

const onInput = (e: Event) => {
    emit('update:modelValue', (e.target as HTMLInputElement).value);
    isOpen.value = true;
    highlightedIndex.value = -1;
};

const onFocus = () => {
    if (hasQuery.value) isOpen.value = true;
};

const selectVariant = (v: VariantOption) => {
    emit('select', v);
    emit('update:modelValue', '');
    isOpen.value = false;
    highlightedIndex.value = -1;
};

const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter') {
        const query = props.modelValue.trim();
        if (!query) return;

        e.preventDefault();
        const idx = highlightedIndex.value >= 0 ? highlightedIndex.value : 0;
        const variant = filteredVariants.value[idx];

        if (variant) {
            selectVariant(variant);
            return;
        }

        emit('enterNoMatch', query);
        isOpen.value = false;
        highlightedIndex.value = -1;
        return;
    }

    if (!showDropdown.value) {
        if (e.key === 'Escape') emit('update:modelValue', '');
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredVariants.value.length - 1);
        scrollToHighlighted();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1);
        scrollToHighlighted();
    } else if (e.key === 'Escape') {
        isOpen.value = false;
    }
};

const scrollToHighlighted = () => {
    nextTick(() => {
        const el = listRef.value?.querySelector(`[data-idx="${highlightedIndex.value}"]`) as HTMLElement | null;
        el?.scrollIntoView({ block: 'nearest' });
    });
};

const handleClickOutside = (e: MouseEvent) => {
    if (!(e.target as HTMLElement).closest('[data-product-search]')) {
        isOpen.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside));

// ── Expose ────────────────────────────────────────────────────────────────────

const focus = () => inputRef.value?.focus();
defineExpose({ focus });
</script>

<template>
    <div class="relative" data-product-search>
        <!-- Input -->
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <input
            ref="inputRef"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            type="text"
            autocomplete="off"
            class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm outline-none transition focus:ring-2 focus:ring-ring disabled:opacity-50"
            @input="onInput"
            @focus="onFocus"
            @keydown="onKeydown"
        />

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition-all duration-100 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-75 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="showDropdown"
                class="absolute left-0 top-full z-30 mt-1 w-full min-w-[320px] overflow-hidden rounded-md border border-border bg-popover shadow-lg"
            >
                <!-- Results list -->
                <div ref="listRef" class="max-h-72 overflow-y-auto p-1">
                    <template v-if="filteredVariants.length > 0">
                        <button
                            v-for="(v, idx) in filteredVariants"
                            :key="v.id"
                            :data-idx="idx"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition-colors"
                            :class="highlightedIndex === idx ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/60'"
                            @mouseenter="highlightedIndex = idx"
                            @click="selectVariant(v)"
                        >
                            <!-- Thumbnail -->
                            <div class="size-9 shrink-0 overflow-hidden rounded-md border border-border/60 bg-muted/40">
                                <img
                                    v-if="v.image_path"
                                    :src="v.image_path"
                                    :alt="displayName(v)"
                                    class="size-full object-cover"
                                />
                                <div v-else class="flex size-full items-center justify-center text-muted-foreground/50">
                                    <Package class="size-4" />
                                </div>
                            </div>

                            <!-- Info -->
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium leading-snug">{{ displayName(v) }}</p>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                                    <!-- SKU / barcode -->
                                    <span v-if="v.sku" class="font-mono">{{ v.sku }}</span>
                                    <span v-else-if="v.barcode" class="font-mono">{{ v.barcode }}</span>

                                    <!-- Stock -->
                                    <span
                                        v-if="stockQty(v) !== null"
                                        class="inline-flex items-center gap-0.5"
                                        :class="(stockQty(v) ?? 0) > 0 ? 'text-green-600 dark:text-green-400' : 'text-destructive'"
                                    >
                                        <span>Tồn:</span>
                                        <span class="font-medium">{{ stockQty(v) }}</span>
                                        <span v-if="v.base_unit">{{ v.base_unit }}</span>
                                    </span>

                                    <!-- Preorder qty -->
                                    <span
                                        v-if="v.preorder_quantity && v.preorder_quantity > 0"
                                        class="inline-flex items-center gap-0.5 text-amber-600 dark:text-amber-400"
                                    >
                                        <span>Đặt trước:</span>
                                        <span class="font-medium">{{ v.preorder_quantity }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Price -->
                            <span class="shrink-0 text-sm font-semibold tabular-nums">
                                {{ formatPrice(v.price) }}
                            </span>
                        </button>
                    </template>
                    <p v-else class="px-3 py-3 text-center text-xs text-muted-foreground">
                        Không tìm thấy hàng hóa phù hợp.
                    </p>
                </div>

                <!-- Optional slot for footer (e.g. "add product" button) -->
                <slot name="footer" />
            </div>
        </Transition>
    </div>
</template>
