# contenir-extension/navigation

Managed site navigation for [Contenir](https://github.com/contenir) CMS. The add-on adds a Navigation section to Site
Pages, where editors build the site's menus, such as its header and footer, then publish them for the site to draw. It
works over [contenir/contenir-navigation](https://github.com/contenir/contenir-navigation), which holds the menus, and
the site draws them through an adapter such as `contenir/contenir-navigation-mezzio`.

- **Menus**: add, rename and delete them, each with the handle the site's templates ask for it by.
- **Items**: link each to a page or other record the site marks as linkable, to an address (a path, a full link, a
  fragment, an email address or a phone number, typed with no prefix), or to nothing, as a heading or a little text.
  Items nest, move up and down, and say how they are drawn: a label, an icon token or both, a new tab, extra classes,
  and a switch to hide them.
- **Publishing**: a new menu is a draft the site doesn't show until it is published once. After that, every change to it
  goes live as it is saved, each save recorded as a revision with who made it, and History puts any earlier revision
  back on the site.
- **Badges** mark items the site won't show as they stand: a record that is a draft, unpublished, archived or gone, or
  an item that is hidden.

## Requirements

- Contenir CMS with the extension API 0.7.x, lending add-ons the site's database, its records
  (`Record\RecordsInterface`) and the editor signed in (`Editor\CurrentEditorInterface`)
- contenir/contenir-navigation, whose migrations the CMS runs against the site's database

## Permissions

Every screen is guarded by the section it adds, `site.navigation.menus`. Viewing needs View; adding menus and items
needs Add; changing, moving and restoring need Update; deleting needs Delete; and publishing a menu for the first
time needs Approve. Once a menu is live, anyone who may change it changes the site's menu.

## Development

```bash
composer check   # format, lint, guard, static analysis, both suites and mutation testing
composer boundary
npm ci
npm run test:coverage
npm run build    # the menu tree's script, into public/js, which is committed
```
