import type { Ref } from 'vue';
import type { CartItem, PosDraftTab } from '@/composables/pos/models';

type UsePosTabsParams = {
    tabs: Ref<PosDraftTab[]>;
    activeTabId: Ref<string>;
    storageKey: string;
    createDefaultTab: (index: number) => PosDraftTab;
    normalizeCartItem: (item: CartItem) => CartItem;
    startNewOrder: () => void;
    toDatetimeLocal: (value?: string | Date | null) => string;
};

export const usePosTabs = ({
    tabs,
    activeTabId,
    storageKey,
    createDefaultTab,
    normalizeCartItem,
    startNewOrder,
    toDatetimeLocal,
}: UsePosTabsParams) => {
    const persistTabs = () => {
        if (typeof window === 'undefined') return;
        window.localStorage.setItem(storageKey, JSON.stringify({ tabs: tabs.value, activeTabId: activeTabId.value }));
    };

    const ensureActiveTab = () => {
        if (!tabs.value.length) {
            const tab = createDefaultTab(1);
            tabs.value = [tab];
            activeTabId.value = tab.id;
            return;
        }

        const exists = tabs.value.some((tab) => tab.id === activeTabId.value);
        if (!exists) {
            activeTabId.value = tabs.value[0].id;
        }
    };

    const restoreTabs = () => {
        if (typeof window === 'undefined') return;

        const raw = window.localStorage.getItem(storageKey);
        if (!raw) {
            ensureActiveTab();
            return;
        }

        try {
            const parsed = JSON.parse(raw) as { tabs?: PosDraftTab[]; activeTabId?: string };
            if (Array.isArray(parsed.tabs) && parsed.tabs.length > 0) {
                tabs.value = parsed.tabs.map((tab, index) => ({
                    ...createDefaultTab(index + 1),
                    ...tab,
                    order_date: tab.order_date || toDatetimeLocal(),
                    items: Array.isArray(tab.items) ? tab.items.map((item) => normalizeCartItem(item as CartItem)) : [],
                }));
                activeTabId.value = parsed.activeTabId ?? parsed.tabs[0].id;
            }
        } catch {
            tabs.value = [];
        }

        ensureActiveTab();
    };

    const getNextTabLabel = () => {
        const numbers = tabs.value
            .map((tab) => tab.label.match(/\d+/)?.[0])
            .filter((value): value is string => Boolean(value))
            .map((value) => Number(value));

        const next = numbers.length ? Math.max(...numbers) + 1 : 1;

        return `Hóa đơn ${next}`;
    };

    const addOrderTab = () => {
        const tab = createDefaultTab(tabs.value.length + 1);
        tab.label = getNextTabLabel();
        tabs.value.push(tab);
        activeTabId.value = tab.id;
    };

    const closeOrderTab = (tabId: string) => {
        if (tabs.value.length === 1) {
            startNewOrder();
            return;
        }

        const currentIndex = tabs.value.findIndex((tab) => tab.id === tabId);
        tabs.value = tabs.value.filter((tab) => tab.id !== tabId);

        if (activeTabId.value === tabId) {
            const nextIndex = Math.max(0, currentIndex - 1);
            activeTabId.value = tabs.value[nextIndex]?.id ?? tabs.value[0].id;
        }
    };

    return {
        persistTabs,
        ensureActiveTab,
        restoreTabs,
        addOrderTab,
        closeOrderTab,
    };
};
