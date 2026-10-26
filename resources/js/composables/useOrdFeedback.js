import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

/**
 * Surfaces Inertia flash success/error messages for ORD pages. The shared
 * middleware now exposes flash.success / flash.error in addition to the
 * legacy flash.message, so controllers can give real feedback.
 */
export function useOrdFeedback() {
    const page = usePage();

    watch(
        () => page.props.flash,
        (flash) => {
            if (flash?.success) toast.success(flash.success);
            if (flash?.error) toast.error(flash.error);
            if (!flash?.success && !flash?.error && flash?.message) toast.info(flash.message);
        },
        { immediate: true, deep: true },
    );

    return {
        showError: (message) => toast.error(message || 'Something went wrong.'),
        showSuccess: (message) => toast.success(message),
    };
}
