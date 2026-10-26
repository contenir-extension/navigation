import { BaseComponent } from '@parallelogram-js/core/core/BaseComponent.js'

/**
 * MenuTree — saves what is done to a menu's tree on the Navigation screen.
 *
 * Registered against `[data-navtree]`, the menu's top list, which is the
 * dashboard's sortable action bar tree (`data-actionbar-sortable="tree"`):
 * the dashboard drags an item by its grip, or moves it with the keyboard, to
 * another place at its level, under another item, or out a level, and says
 * so with an `actionbar:reorder` event. MenuTree then posts the item's new
 * parent (the id on the item it now sits under, or none at the top) and
 * position (its index among its new siblings) to the item's
 * `data-move-url` as an ordinary form, with the host's handler token read
 * from the page's `[data-navtree-token]` wrapper, so the screen reloads with
 * the menu as the add-on saved it.
 *
 * Turning an item's `[data-navtree-visibility]` switch posts the form it
 * sits in, which shows or hides the item.
 */
export default class MenuTree extends BaseComponent {
    _getSelector() {
        return 'data-navtree'
    }

    _init(element) {
        const onReorder = (event) => this._moved(event.detail)
        const onChange = (event) => {
            const toggle = event.target.closest('[data-navtree-visibility]')
            if (toggle) toggle.form?.requestSubmit()
        }
        element.addEventListener('actionbar:reorder', onReorder)
        element.addEventListener('change', onChange)

        return {
            cleanup: () => {
                element.removeEventListener('actionbar:reorder', onReorder)
                element.removeEventListener('change', onChange)
            },
        }
    }

    _moved({ item, to, toParent }) {
        const form = document.createElement('form')
        form.method = 'post'
        form.action = item?.dataset.moveUrl || ''
        form.hidden = true

        const fields = { parent: toParent?.dataset.item || '', position: String(to) }
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
