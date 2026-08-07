/**
 * Performance helpers (production-safe: no demo forms, no external placeholder assets)
 */
class PerformanceOptimizer {
	init() {
		this.applyImageLoadingHints();
	}

	applyImageLoadingHints() {
		const images = document.querySelectorAll('img');
		images.forEach((img) => {
			if (!img.hasAttribute('loading')) {
				img.setAttribute('loading', 'lazy');
			}
			if (!img.hasAttribute('decoding')) {
				img.setAttribute('decoding', 'async');
			}
		});
	}
}

export default PerformanceOptimizer;
