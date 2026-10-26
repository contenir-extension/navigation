import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import MenuTree from '../../../assets/js/components/MenuTree.js'

const TOKEN_NAME  = 'host_handler_token'
const TOKEN_VALUE = 'test-token-value'

let submitted
let requested

beforeEach(() => {
    submitted = []
    requested = []
    vi.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(function () {
        submitted.push(this)
    })
    vi.spyOn(HTMLFormElement.prototype, 'requestSubmit').mockImplementation(function () {
        requested.push(this.id)
    })
})

afterEach(() => {
    document.body.innerHTML = ''
    vi.restoreAllMocks()
})

/**
 * A menu's tree, as the Navigation screen draws it: Home and About at the
 * top, About holding Team, each with its Show switch.
 */
function drawTree({ token = true } = {}) {
    document.body.innerHTML = `
        <div data-navtree-root>
            ${token ? `<div data-navtree-token hidden><input type="hidden" name="${TOKEN_NAME}" value="${TOKEN_VALUE}"></div>` : ''}
            <ul data-navtree data-actionbar-sortable="tree" id="top">
                <li id="home" data-item="11" data-move-url="/admin/extension/navigation/item-move?item=11">
                    <form id="home-visibility"><input type="checkbox" name="active" value="1" data-navtree-visibility id="home-switch"></form>
                </li>
                <li id="about" data-item="12" data-move-url="/admin/extension/navigation/item-move?item=12">
                    <ul id="under-about">
                        <li id="team" data-item="13" data-move-url="/admin/extension/navigation/item-move?item=13">
                            <form id="team-visibility"><input type="checkbox" name="active" value="1" data-navtree-visibility id="team-switch"></form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>`

    return document.getElementById('top')
}

function mount(element) {
    const component = new MenuTree({ eventBus: null, logger: null, router: null })
    component.mount(element)

    return component
}

/** Says, as the dashboard's tree does, that $item now sits at $to under $toParent. */
function reorder(tree, item, to, toParent = null) {
    tree.dispatchEvent(new CustomEvent('actionbar:reorder', {
        bubbles: true,
        detail: { item: document.getElementById(item), from: 0, to, fromParent: null, toParent: toParent && document.getElementById(toParent) },
    }))
}

function flip(id) {
    const toggle = document.getElementById(id)
    toggle.checked = !toggle.checked
    toggle.dispatchEvent(new Event('change', { bubbles: true }))
}

function posted(form) {
    return Object.fromEntries(new FormData(form).entries())
}

describe('MenuTree', () => {
    it('keeps its state under the attribute the host mounts it on', () => {
        const element = document.createElement('ul')

        new MenuTree({ eventBus: null, logger: null, router: null }).setState(element, 'ready')

        expect(element.getAttribute('data-navtree-state')).toBe('ready')
    })

    it('posts an item moved under another with its new parent, position and the token', () => {
        const tree = drawTree()
        mount(tree)

        reorder(tree, 'home', 1, 'about')

        expect([submitted[0].action, submitted[0].method, posted(submitted[0])]).toEqual([
            'http://localhost:3000/admin/extension/navigation/item-move?item=11',
            'post',
            { parent: '12', position: '1', [TOKEN_NAME]: TOKEN_VALUE },
        ])
    })

    it('posts an item moved to the top with no parent', () => {
        const tree = drawTree()
        mount(tree)

        reorder(tree, 'team', 0)

        expect(posted(submitted[0])).toEqual({ parent: '', position: '0', [TOKEN_NAME]: TOKEN_VALUE })
    })

    it('posts a move without a token when the page has none', () => {
        const tree = drawTree({ token: false })
        mount(tree)

        reorder(tree, 'home', 1)

        expect(posted(submitted[0])).toEqual({ parent: '', position: '1' })
    })

    it('posts a move of an item with no move address to the page it is on', () => {
        const tree = drawTree()
        mount(tree)
        document.getElementById('home').removeAttribute('data-move-url')

        reorder(tree, 'home', 1)

        expect(submitted[0].action).toBe(window.location.href)
    })

    it('posts the move as a hidden form it adds to the page', () => {
        const tree = drawTree()
        mount(tree)

        reorder(tree, 'home', 1)

        expect([submitted[0].hidden, submitted[0].isConnected]).toEqual([true, true])
    })

    it('posts an item\'s visibility switch when it is turned, at any depth', () => {
        const tree = drawTree()
        mount(tree)

        flip('home-switch')
        flip('team-switch')

        expect(requested).toEqual(['home-visibility', 'team-visibility'])
    })

    it('ignores a change that is not a visibility switch', () => {
        const tree = drawTree()
        mount(tree)

        document.getElementById('home').dispatchEvent(new Event('change', { bubbles: true }))

        expect(requested).toEqual([])
    })

    it('stops posting once unmounted', () => {
        const tree = drawTree()
        const component = mount(tree)
        component.unmount(tree)

        reorder(tree, 'home', 1)
        flip('home-switch')

        expect([submitted, requested]).toEqual([[], []])
    })
})
