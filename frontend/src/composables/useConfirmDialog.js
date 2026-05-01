import { ref } from "vue";

// Shared reactive state for the global confirm dialog
const isOpen = ref(false);
const title = ref("Confirm");
const message = ref("");
const confirmText = ref("Confirm");
const cancelText = ref("Cancel");
const confirmColor = ref("primary");

let resolvePromise = null;

/**
 * Global confirmation dialog composable.
 * Replaces browser `confirm()` with a Vuetify dialog.
 *
 * Usage in any component:
 *   import { useConfirmDialog } from "@/composables/useConfirmDialog";
 *   const { confirm } = useConfirmDialog();
 *   const ok = await confirm("Are you sure?");
 *   if (!ok) return;
 *
 * Options:
 *   confirm("Message", { title, confirmText, cancelText, confirmColor })
 */
export function useConfirmDialog() {
  /**
   * Show confirmation dialog and return a Promise<boolean>.
   * @param {string} msg - The message to display
   * @param {Object} [options] - Optional overrides
   * @param {string} [options.title="Confirm"] - Dialog title
   * @param {string} [options.confirmText="Confirm"] - Confirm button text
   * @param {string} [options.cancelText="Cancel"] - Cancel button text
   * @param {string} [options.confirmColor="primary"] - Confirm button color
   * @returns {Promise<boolean>}
   */
  function confirm(msg, options = {}) {
    message.value = msg;
    title.value = options.title || "Confirm";
    confirmText.value = options.confirmText || "Confirm";
    cancelText.value = options.cancelText || "Cancel";
    confirmColor.value = options.confirmColor || "primary";
    isOpen.value = true;

    return new Promise((resolve) => {
      resolvePromise = resolve;
    });
  }

  function onConfirm() {
    isOpen.value = false;
    if (resolvePromise) resolvePromise(true);
    resolvePromise = null;
  }

  function onCancel() {
    isOpen.value = false;
    if (resolvePromise) resolvePromise(false);
    resolvePromise = null;
  }

  return {
    // State (for the ConfirmDialog component)
    isOpen,
    title,
    message,
    confirmText,
    cancelText,
    confirmColor,
    // Methods
    confirm,
    onConfirm,
    onCancel,
  };
}
