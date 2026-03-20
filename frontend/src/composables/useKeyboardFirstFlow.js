export function useKeyboardFirstFlow({ onEscape, onSubmitLast } = {}) {
  const focusableSelector = [
    "input:not([type='hidden'])",
    "select",
    "textarea",
    "button",
    "[tabindex]:not([tabindex='-1'])",
  ].join(",");

  const isVisible = (element) => {
    if (!element) return false;
    const style = window.getComputedStyle(element);
    return (
      style.display !== "none" &&
      style.visibility !== "hidden" &&
      element.offsetParent !== null
    );
  };

  const getFocusableElements = (root) => {
    if (!root) return [];

    return Array.from(root.querySelectorAll(focusableSelector)).filter(
      (element) => {
        if (!isVisible(element)) return false;
        if (element.disabled || element.readOnly) return false;
        if (element.getAttribute("aria-hidden") === "true") return false;

        const tabindexAttr = element.getAttribute("tabindex");
        if (tabindexAttr !== null && Number(tabindexAttr) < 0) return false;

        return true;
      },
    );
  };

  const shouldSkipEnterHandling = (target) => {
    if (!target) return true;

    const tag = target.tagName?.toLowerCase();
    if (tag === "textarea") return true;

    if (
      target.closest(
        ".v-select, .v-autocomplete, .v-combobox, [role='listbox'], [role='menu']",
      )
    ) {
      return true;
    }

    if (target.closest("button")) return true;

    return false;
  };

  const handleKeydown = (event) => {
    const root = event.currentTarget;

    if (event.key === "Escape") {
      if (typeof onEscape === "function") {
        event.preventDefault();
        onEscape();
      }
      return;
    }

    if (event.key !== "Enter") return;
    if (event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) return;
    if (shouldSkipEnterHandling(event.target)) return;

    const focusable = getFocusableElements(root);
    if (!focusable.length) return;

    const activeElement = document.activeElement;
    const currentIndex = focusable.indexOf(activeElement);

    if (currentIndex === -1) return;

    event.preventDefault();

    if (currentIndex < focusable.length - 1) {
      focusable[currentIndex + 1]?.focus();
      return;
    }

    if (typeof onSubmitLast === "function") {
      onSubmitLast();
    }
  };

  return { handleKeydown };
}
