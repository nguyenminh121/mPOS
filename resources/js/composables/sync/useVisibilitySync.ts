type UseVisibilitySyncParams = {
	onVisible: () => void;
	onHidden: () => void;
	onFocus: () => void;
};

export const useVisibilitySync = ({ onVisible, onHidden, onFocus }: UseVisibilitySyncParams) => {
	const handleVisibilityChange = () => {
		if (document.visibilityState === 'visible') {
			onVisible();
			return;
		}

		onHidden();
	};

	const handleWindowFocus = () => {
		onFocus();
	};

	const bindVisibilitySync = () => {
		document.addEventListener('visibilitychange', handleVisibilityChange);
		window.addEventListener('focus', handleWindowFocus);
	};

	const unbindVisibilitySync = () => {
		document.removeEventListener('visibilitychange', handleVisibilityChange);
		window.removeEventListener('focus', handleWindowFocus);
	};

	return {
		bindVisibilitySync,
		unbindVisibilitySync,
	};
};
