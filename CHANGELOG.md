# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

Requires contenir/contenir-extension-api 0.7.3 and contenir/contenir-navigation 0.2.

### Changed

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
