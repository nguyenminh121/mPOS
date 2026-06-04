import type { Router } from '@inertiajs/core';
import type { ComputedRef, Ref } from 'vue';
import type { PosDraftTab } from '@/composables/pos/models';

type SyncReason = 'poll' | 'focus' | 'visibility' | 'manual';

type UseAdaptiveSyncParams = {
	activeTab: ComputedRef<PosDraftTab | null>;
	searchQuery: ComputedRef<string>;
	lastPosActivityAt: Ref<number>;
	router: Router;
};

export const useAdaptiveSync = ({ activeTab, searchQuery, lastPosActivityAt, router }: UseAdaptiveSyncParams) => {
	let syncPollTimer: number | null = null;
	let syncRequestInFlight = false;

	const refreshPosData = (reason: SyncReason = 'poll') => {
		if (syncRequestInFlight || !document.hasFocus()) {
			return;
		}

		if (reason === 'poll' && activeTab.value && activeTab.value.items.length === 0 && searchQuery.value.trim() === '') {
			const idleDurationMs = Date.now() - lastPosActivityAt.value;
			if (idleDurationMs < 60_000) {
				return;
			}
		}

		syncRequestInFlight = true;

		router.visit(window.location.pathname, {
			method: 'get',
			only: ['variants', 'sales', 'priceLists'],
			preserveState: true,
			preserveScroll: true,
			replace: true,
			onFinish: () => {
				syncRequestInFlight = false;
			},
		});
	};

	const stopSyncPolling = () => {
		if (syncPollTimer !== null) {
			window.clearInterval(syncPollTimer);
			syncPollTimer = null;
		}
	};

	const startSyncPolling = () => {
		stopSyncPolling();

		if (document.visibilityState !== 'visible') {
			return;
		}

		const isActive = (activeTab.value?.items.length ?? 0) > 0 || searchQuery.value.trim() !== '';
		const intervalMs = isActive ? 15000 : 45000;
		syncPollTimer = window.setInterval(() => {
			refreshPosData('poll');
		}, intervalMs);
	};

	const markPosActivity = () => {
		lastPosActivityAt.value = Date.now();
	};

	return {
		refreshPosData,
		stopSyncPolling,
		startSyncPolling,
		markPosActivity,
	};
};
