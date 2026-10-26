import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createSortable } from '../../../assets/js/utils/Sortable.js'
import MenuTree from '../../../assets/js/components/MenuTree.js'

vi.mock('../../../assets/js/utils/Sortable.js', () => ({
    createSortable: vi.fn(() => ({ destroy: vi.fn() })),
}))

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
    vi.clearAllMocks()
})

/**
 * Menu 3's tree, as the Navigation screen draws it: Home and About at the
 * top, About holding Team, and each item's list for the items under it.
 */
function drawTree({ token = true } = {}) {
    document.body.innerHTML = `
        <div data-navtree-root>
            ${token ? `<div data-navtree-token hidden><input type="hidden" name="${TOKEN_NAME}" value="${TOKEN_VALUE}"></div>` : ''}
            <ul data-navtree data-menu="3" data-parent="" id="top">
                <li data-navtree-item data-move-url="/admin/extension/navigation/item-move?item=11" id="home">
                    <form id="home-visibility"><input type="checkbox" name="active" value="1" data-navtree-visibility id="home-switch"></form>
                    <ul data-navtree data-menu="3" data-parent="11" id="under-home"></ul>
                </li>
                <li data-navtree-item data-move-url="/admin/extension/navigation/item-move?item=12" id="about">
                    <ul data-navtree data-menu="3" data-parent="12" id="under-about">
                        <li data-navtree-item data-move-url="/admin/extension/navigation/item-move?item=13" id="team">
                            <form id="team-visibility"><input type="checkbox" name="active" value="1" data-navtree-visibility id="team-switch"></form>
                            <ul data-navtree data-menu="3" data-parent="13" id="under-team"></ul>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>`
}

function list(id) {
    return document.getElementById(id)
}

function mount(element) {
    const component = new MenuTree({ eventBus: null, logger: null, router: null })
    component.mount(element)

    return component
}

function options(element) {
    return createSortable.mock.calls.find(([el]) => el === element)[1]
}

function drop(element, { item, from, to, oldIndex, newIndex }) {
    options(element).onEnd({ item: list(item), from: list(from), to: list(to), oldIndex, newIndex })
}

function posted(form) {
    return Object.fromEntries(new FormData(form).entries())
}

function flip(id) {
    const toggle = list(id)
    toggle.checked = !toggle.checked
    toggle.dispatchEvent(new Event('change', { bubbles: true }))
}

describe('MenuTree', () => {
    it('posts an item\'s visibility switch when it is turned', () => {
        drawTree()
        mount(list('top'))

        flip('home-switch')

        expect(requested).toEqual(['home-visibility'])
    })

    it('leaves the switches of the items under its own to their list', () => {
        drawTree()
        mount(list('top'))
        mount(list('under-about'))

        flip('team-switch')

        expect(requested).toEqual(['team-visibility'])
    })

    it('ignores a change that is not a visibility switch', () => {
        drawTree()
        mount(list('top'))

        list('home').dispatchEvent(new Event('change', { bubbles: true }))

        expect(requested).toEqual([])
    })

    it('stops posting switches once unmounted', () => {
        drawTree()
        const component = mount(list('top'))
        component.unmount(list('top'))

        flip('home-switch')

        expect(requested).toEqual([])
    })

    it('keeps its state under the attribute the host mounts it on', () => {
        const element = document.createElement('ul')

        new MenuTree({ eventBus: null, logger: null, router: null }).setState(element, 'ready')

        expect(element.getAttribute('data-navtree-state')).toBe('ready')
    })

    it('makes each item of a list draggable by its grip', () => {
        drawTree()
        mount(list('top'))

        expect(options(list('top'))).toMatchObject({
            draggable: '[data-navtree-item]',
            handle: '[data-navtree-handle]',
        })
    })

    it('lets every list of one menu take items from the others', () => {
        drawTree()
        mount(list('top'))
        mount(list('under-about'))

        expect([options(list('top')).group, options(list('under-about')).group])
            .toEqual(['navigation-menu-3', 'navigation-menu-3'])
    })

    it('names a group of its own for a list that names no menu', () => {
        drawTree()
        list('top').removeAttribute('data-menu')
        mount(list('top'))

        expect(options(list('top')).group).toBe('navigation-menu-')
    })

    it('opens the empty lists as drop zones while an item is dragged', () => {
        drawTree()
        mount(list('under-about'))

        options(list('under-about')).onStart()

        expect(document.querySelector('[data-navtree-root]').classList.contains('navtree--dragging')).toBe(true)
    })

    it('closes the drop zones when the item is dropped', () => {
        drawTree()
        mount(list('top'))
        options(list('top')).onStart()

        drop(list('top'), { item: 'home', from: 'top', to: 'top', oldIndex: 0, newIndex: 0 })

        expect(document.querySelector('[data-navtree-root]').classList.contains('navtree--dragging')).toBe(false)
    })

    it('marks the list itself while dragging when it sits outside a tree root', () => {
        document.body.innerHTML = '<ul data-navtree data-menu="3" data-parent="" id="loose"></ul>'
        mount(list('loose'))

        options(list('loose')).onStart()

        expect(list('loose').classList.contains('navtree--dragging')).toBe(true)
    })

    it('posts nothing when an item is dropped back where it was', () => {
        drawTree()
        mount(list('top'))

        drop(list('top'), { item: 'about', from: 'top', to: 'top', oldIndex: 1, newIndex: 1 })

        expect(submitted).toEqual([])
    })

    it('posts the new position of an item moved along its level', () => {
        drawTree()
        mount(list('top'))

        drop(list('top'), { item: 'about', from: 'top', to: 'top', oldIndex: 1, newIndex: 0 })

        expect(posted(submitted[0])).toEqual({ parent: '', position: '0', [TOKEN_NAME]: TOKEN_VALUE })
    })

    it('posts the parent of the list an item is dropped into', () => {
        drawTree()
        mount(list('top'))

        drop(list('top'), { item: 'home', from: 'top', to: 'under-about', oldIndex: 0, newIndex: 1 })

        expect(posted(submitted[0])).toMatchObject({ parent: '12', position: '1' })
    })

    it('posts to the address of the item moved', () => {
        drawTree()
        mount(list('under-about'))

        drop(list('under-about'), { item: 'team', from: 'under-about', to: 'top', oldIndex: 0, newIndex: 2 })

        expect([submitted[0].method, new URL(submitted[0].action).pathname + new URL(submitted[0].action).search])
            .toEqual(['post', '/admin/extension/navigation/item-move?item=13'])
    })

    it('posts the move as a form of hidden fields added to the page', () => {
        drawTree()
        mount(list('top'))

        drop(list('top'), { item: 'about', from: 'top', to: 'top', oldIndex: 1, newIndex: 0 })

        expect([submitted[0].hidden, submitted[0].isConnected, [...submitted[0].elements].map((input) => input.type)])
            .toEqual([true, true, ['hidden', 'hidden', 'hidden']])
    })

    it('posts without a token when the page carries none', () => {
        drawTree({ token: false })
        mount(list('top'))

        drop(list('top'), { item: 'about', from: 'top', to: 'top', oldIndex: 1, newIndex: 0 })

        expect(posted(submitted[0])).toEqual({ parent: '', position: '0' })
    })

    it('posts without a token whose input has no name', () => {
        drawTree()
        document.querySelector('[data-navtree-token] input').removeAttribute('name')
        mount(list('top'))

        drop(list('top'), { item: 'about', from: 'top', to: 'top', oldIndex: 1, newIndex: 0 })

        expect(posted(submitted[0])).toEqual({ parent: '', position: '0' })
    })

    it('posts to the page itself for an item that names no address', () => {
        drawTree()
        list('about').removeAttribute('data-move-url')
        mount(list('top'))

        drop(list('top'), { item: 'about', from: 'top', to: 'top', oldIndex: 1, newIndex: 0 })

        expect(submitted[0].getAttribute('action')).toBe('')
    })

    it('posts an empty parent for a list that names none', () => {
        drawTree()
        list('under-about').removeAttribute('data-parent')
        mount(list('top'))

        drop(list('top'), { item: 'home', from: 'top', to: 'under-about', oldIndex: 0, newIndex: 0 })

        expect(posted(submitted[0]).parent).toBe('')
    })

    it('posts a move between lists even at the same index', () => {
        drawTree()
        mount(list('top'))

        drop(list('top'), { item: 'home', from: 'top', to: 'under-about', oldIndex: 0, newIndex: 0 })

        expect(submitted).toHaveLength(1)
    })

    it('stops dragging its list when it is unmounted', () => {
        drawTree()
        const component = mount(list('top'))
        const sortable = createSortable.mock.results[0].value

        component.unmount(list('top'))

        expect(sortable.destroy).toHaveBeenCalledOnce()
    })
})
