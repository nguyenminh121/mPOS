<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    expanded: boolean;
    colSpan: number;
    rowClass?: string;
    expandedRowClass?: string;
    expandedCellClass?: string;
}>(), {
    rowClass: '',
    expandedRowClass: '',
    expandedCellClass: '',
});

const emit = defineEmits<{
    (event: 'toggle'): void;
}>();

const summaryRowClass = computed(() => [
    'border-t border-border/70 transition hover:bg-muted/30',
    'cursor-pointer',
    props.expanded ? 'border-x border-t border-primary/40 bg-primary/5 hover:bg-primary/10' : '',
    props.rowClass,
].join(' '));
const detailRowClass = computed(() => [
    'bg-muted/20',
    props.expanded ? 'border-x border-b border-primary/40 bg-primary/5' : 'border-t border-border/50',
    props.expandedRowClass,
].join(' '));
const detailCellClass = computed(() => ['px-4 py-4', props.expandedCellClass].join(' '));

const toggle = () => {
    emit('toggle');
};

const shouldIgnoreToggle = (target: EventTarget | null): boolean => {
    if (!(target instanceof HTMLElement)) return false;

    return Boolean(target.closest('a, button, input, select, textarea, [role="button"], [role="menuitem"], [data-no-row-toggle]'));
};

const onSummaryClick = (event: MouseEvent) => {
    if (shouldIgnoreToggle(event.target)) return;
    toggle();
};

const onSummaryKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    if (shouldIgnoreToggle(event.target)) return;

    event.preventDefault();
    toggle();
};

const onEnter = (element: Element) => {
    const node = element as HTMLElement;
    node.style.height = '0px';
    node.style.opacity = '0';
    node.style.overflow = 'hidden';

    requestAnimationFrame(() => {
        node.style.height = `${node.scrollHeight}px`;
        node.style.opacity = '1';
    });
};

const onAfterEnter = (element: Element) => {
    const node = element as HTMLElement;
    node.style.height = 'auto';
    node.style.overflow = '';
};

const onLeave = (element: Element) => {
    const node = element as HTMLElement;
    node.style.height = `${node.scrollHeight}px`;
    node.style.opacity = '1';
    node.style.overflow = 'hidden';

    requestAnimationFrame(() => {
        node.style.height = '0px';
        node.style.opacity = '0';
    });
};
</script>

<template>
    <tr :class="summaryRowClass" tabindex="0" @click="onSummaryClick" @keydown="onSummaryKeydown">
        <slot name="summary" :expanded="expanded" :toggle="toggle" />
    </tr>

    <tr v-if="expanded" :class="detailRowClass">
        <td :colspan="colSpan" :class="detailCellClass">
            <Transition
                @enter="onEnter"
                @after-enter="onAfterEnter"
                @leave="onLeave"
            >
                <div v-if="expanded" class="expandable-accordion">
                    <slot name="content" :expanded="expanded" :toggle="toggle" />
                </div>
            </Transition>
        </td>
    </tr>
</template>

<style scoped>
.expandable-accordion {
    transition: height 200ms ease, opacity 180ms ease;
    will-change: height, opacity;
}
</style>
