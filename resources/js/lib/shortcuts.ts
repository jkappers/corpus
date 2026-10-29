export const isMac =
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.platform);

export const modKey = isMac ? '⌘' : 'Ctrl+';

export const shiftKey = isMac ? '⇧' : 'Shift+';

/** True when a keystroke belongs to a text field, so single-key shortcuts must not fire. */
export function isTyping(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLElement &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    );
}

export const addNoteShortcut = isMac ? '⌘⌥M' : 'Ctrl+Alt+M';
