import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'
import { createSortable } from '../utils/Sortable.js'

/**
 * MenuTree — drag-and-drop ordering for a menu's tree on the Navigation
 * screen.
 *
 * Registered against `[data-navtree]`, one list per level of the tree: the
 * menu's top level carries an empty `data-parent`, and every item holds a
 * list for the items under it (empty for an item with none) whose
 * `data-parent` is that item's id. Each `[data-navtree-item]` is dragged by
 * its `[data-navtree-handle]` grip, and the lists of one menu share a
 * SortableJS group named by their `data-menu`, so an item can move to
 * another place at its level, under another item, or out to a shallower
 * level, but never into another menu.
 *
 * Dropping an item somewhere new posts its new parent and position (its
 * index among its new siblings) to the item's `data-move-url` as an
 * ordinary form, with the host's handler token read from the page's
 * `[data-navtree-token]` wrapper, so the screen reloads with the menu's
 * draft as the add-on saved it. Dropping it back where it was posts
 * nothing.
 *
 * Turning an item's `[data-navtree-visibility]` switch posts the form it
 * sits in, which shows or hides the item. Each list answers only for its
 * own items' switches, as the lists nested in it have their own.
 */
const DRAGGING_CLASS = 'navtree--dragging'

export default class MenuTree extends BaseComponent {
    _getSelector() {
        return 'data-navtree'
    }

    _init(element) {
        const tree = element.closest('[data-navtree-root]') || element

        const sortable = createSortable(element, {
            draggable: '[data-navtree-item]',
            handle: '[data-navtree-handle]',
            group: `navigation-menu-${element.dataset.menu || ''}`,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onStart: () => tree.classList.add(DRAGGING_CLASS),
            onEnd: (event) => {
                tree.classList.remove(DRAGGING_CLASS)
                this._moved(event)
            },
        })

        const onChange = (event) => {
            const toggle = event.target.closest('[data-navtree-visibility]')
            if (!toggle || toggle.closest('[data-navtree]') !== element) return
            toggle.form?.requestSubmit()
        }
        element.addEventListener('change', onChange)

        return {
            cleanup: () => {
                sortable.destroy()
                element.removeEventListener('change', onChange)
            },
        }
    }

    _moved({ item, from, to, oldIndex, newIndex }) {
        if (from === to && oldIndex === newIndex) return

        const form = document.createElement('form')
        form.method = 'post'
        form.action = item.dataset.moveUrl || ''
        form.hidden = true

        const fields = { parent: to.dataset.parent || '', position: String(newIndex) }
        const token = document.querySelector('[data-navtree-token] input[type="hidden"]')
        if (token && token.name) {
            fields[token.name] = token.value
        }

        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = name
            input.value = value
            form.appendChild(input)
        }

        document.body.appendChild(form)
        form.submit()
    }
}
