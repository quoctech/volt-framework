/* ============================================================
   Volt Claro Theme — Alpine.js Components
   Inspired by Drupal Claro admin theme
   ============================================================ */

document.addEventListener('alpine:init', () => {
  /* -------------------------------------------------------
   * Dropdown
   * Usage: x-data="claroDropdown"
   *        @click.outside="close()"
   *        @keydown.escape.window="close()"
   * ------------------------------------------------------- */
  Alpine.data('claroDropdown', () => ({
    open: false,
    toggle() {
      this.open = !this.open;
    },
    close() {
      this.open = false;
    },
  }));

  /* -------------------------------------------------------
   * Dialog / Modal
   * Usage: x-data="claroDialog"
   *        x-show="open"
   *        @keydown.escape.window="close()"
   * ------------------------------------------------------- */
  Alpine.data('claroDialog', () => ({
    open: false,
    init() {
      this.$watch('open', (val) => {
        if (val) {
          document.body.style.overflow = 'hidden';
          this.$nextTick(() => {
            const focusable = this.$el.querySelector(
              'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (focusable) focusable.focus();
          });
        } else {
          document.body.style.overflow = '';
        }
      });
    },
    openDialog() {
      this.open = true;
    },
    close() {
      this.open = false;
    },
    destroy() {
      document.body.style.overflow = '';
    },
  }));

  /* -------------------------------------------------------
   * Tabs
   * Usage: x-data="claroTabs('tab1')"
   *        :class="{ 'claro-tabs__tab--active': active === 'tab1' }"
   * ------------------------------------------------------- */
  Alpine.data('claroTabs', (initialTab = '') => ({
    active: initialTab,
    setTab(tab) {
      this.active = tab;
    },
  }));

  /* -------------------------------------------------------
   * Confirm Dialog (for delete actions)
   * Usage: x-data="claroConfirm(title, message)"
   *        @confirm.window="handleConfirm()"
   * ------------------------------------------------------- */
  Alpine.data('claroConfirm', (title = 'Confirm', message = 'Are you sure?') => ({
    open: false,
    title: title,
    message: message,
    onConfirm: null,
    confirm(value) {
      if (this.onConfirm) this.onConfirm(value);
      this.open = false;
    },
    cancel() {
      this.open = false;
    },
    ask(msg, onConfirmCallback) {
      this.message = msg;
      this.onConfirm = onConfirmCallback;
      this.open = true;
    },
  }));
});
