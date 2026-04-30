/**
 * 旧POS {@see App\Livewire\Pos\TableStatusGrid::tileSurfaceClasses} と同値の内側タイル面クラス（Tailwind）。
 * @param {string} uiStatus
 * @returns {string}
 */
export function tileSurfaceInnerClasses(uiStatus) {
    const s = String(uiStatus ?? 'free');
    switch (s) {
        case 'alert':
            return 'bg-red-600 text-white border-2 border-red-800 animate-pulse dark:bg-red-500 dark:border-red-300';
        case 'pending':
            return 'bg-red-600 text-white border-2 border-red-800 dark:bg-red-500 dark:text-white dark:border-red-300';
        case 'active':
            return 'bg-sky-400 text-sky-950 border-2 border-sky-600 shadow-sm dark:bg-sky-500 dark:text-sky-950 dark:border-sky-300';
        case 'billed':
            return 'bg-yellow-300 text-yellow-950 border-2 border-yellow-700 dark:bg-yellow-400 dark:text-yellow-950 dark:border-yellow-200';
        default:
            return 'bg-white text-gray-950 border border-gray-300 dark:bg-gray-800 dark:text-white dark:border-gray-600';
    }
}
