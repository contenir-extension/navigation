# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

Requires contenir/contenir-extension-api 0.7.3 and contenir/contenir-navigation 0.2.

### Removed

- Discard changes, and its `discard` handler: a live menu holds no unpublished changes to discard.

### Changed

- A menu is published once. Until then it is a draft the site doesn't show; after that, every change to it (adding,
  editing, moving or deleting an item, or restoring an earlier version) goes live as it is saved, each save recorded
  as a revision with who made it. Publishing needs Approve; once a menu is live, anyone who may change it changes the
  site's menu.
- Publishing asks for no note, and History puts an earlier version back on the site.
- A menu's items draw as action bars, as Site Pages draws its pages: a grip to drag by, the icon of what it links to
  beside its label and address, a Show switch that shows or hides it straight from the tree (the new `item-visibility`
  handler, needing Update), Edit, and a menu with Add item below and Delete. The up and down buttons are gone: the tree
  is the dashboard's sortable action bar tree, so an item is dragged by its grip, or moved with the keyboard (Space,
  then the arrow keys), and SortableJS is no longer a dependency.
- An item's "Spot on the page" is part of its link: typed after the chosen page in the link field, or into an address
  as its fragment. "Under" is labelled Parent, its first choice "None (top level)".
- Times on the Navigation and history screens show in the site's timezone, the zone of the clock the host lends,
  rather than in the UTC the tables hold.
- A menu's handle is labelled Identifier. It fills from the Title as you type, until you change it, and keeps to the
  handle rule (lowercase letters, digits and dashes, at most 64 characters).
- Where it appears sits in an Info card in the sidebar, beside the menu's Title and Identifier.
- The Navigation and history screens' headers pin as the page scrolls, their descriptions below them.
- An identifier another menu has is saved rather than refused. The form warns beside it while it is shared, naming the
  other menus and which one the site shows (the first published in menu order), and the save says so too.

## [0.1.0] - 2026-10-25

### Added

- The Navigation section of Site Pages: a switcher of the site's menus and the chosen menu's tree, with where it stands
  (not live, published with when and by whom, or its unpublished changes) and badges for items the site won't show.
- Menus added, edited and deleted; items added, edited, moved up and down or under another, and deleted, each change
  going into the menu's draft.
- Publish, with an optional note, discard changes, and History, which restores an earlier revision into the draft.
- Every screen guarded by `site.navigation.menus`, with publishing needing Approve.
- Dragging an item by its grip to another place in its menu, under another item or out to a shallower level, saving
  the move into the draft as the move buttons do.
