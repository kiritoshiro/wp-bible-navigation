# Bible navigation

WordPress plugin for adventistai.lt. It lists Bible study videos, audio and articles under the 66 books of the Bible, and shows how many there are in each book.

In wp-admin it appears as **Bible navigation**.

## What it does

- **Links** (videos, audio, articles) are their own entries. You can put one link under several books.
  - YouTube links become videos.
  - Audio files (`.mp3`, `.m4a`…) and Anchor/Spotify play links become audio.
  - Anything else is an article, for example a dievozodis.lt lesson.
- **Regular posts** on the site can be put under a book from the **Biblijos knygos** panel in the post editor. They are listed and counted with the links.
- **Counts** are worked out automatically: the contents show every book with its number of entries, and books without entries are greyed out.
- **Book order and groups** (Penkiaknygė, Laiškai…) are built into the plugin. The book anchors are the same as the old page (`#pradzios-knyga`, `#penkiaknyge-turinys`…), so existing links keep working.

## Admin

- **Bible navigation → Greitas pridėjimas** (Quick add): pick a book and paste links, one per line, or `Title | URL`. Titles are fetched automatically.
  - A link to a post on this site puts that post under the book.
  - A link that already exists is added to the book instead of being duplicated.
- **Pridėti nuorodą** (Add link): a single link, with an optional type override and an optional source page (for an audio episode).
- **Biblijos knygos**: the books with their counts. The links list can be filtered by book.
- **Importas iš puslapio** (Import from page): a one-time move of a hand-built page.
  - It reads the page's book headings and the embeds, audio, `[embedyt]`/`[audio]` shortcodes and links under each one.
  - It shows a preview first and never changes the page.
  - Running it again adds nothing twice, and fills in titles it could not fetch the first time.

Book slugs must not be changed: the plugin finds each book by its slug. Renaming a book only changes its display name in wp-admin; the list uses the built-in names.

## Showing the list

Add the **Bible navigation** block to a page, or use the shortcode `[bible_navigation]` (`show_empty="0"` hides books without entries).

## Weight

The page loads one stylesheet and one deferred script (about 1 KB each gzipped, 3 KB and 2.6 KB raw), and only on pages that show the list. Videos and audio load only when a visitor clicks them; until then the page makes no YouTube or audio requests. Each book is a collapsed `<details>` section. The HTML is cached and rebuilt after a link, a book or a tagged post changes.

## Development

- `php tests/run.php`: behaviour tests (link detection, escaping, importer, translations, updater).
- `python3 .github/playground/smoke.py --php 8.4`: a throwaway WordPress in WordPress Playground (on Windows, set `PYTHONUTF8=1`).
- Security and CI: see [docs/ci-checks.md](docs/ci-checks.md).

## Releases and updates

Tag a commit on `main` as `vX.Y.Z`, matching the `Version:` header and `BNAV_VERSION`. The release workflow runs the security gate and tests, builds `wp-bible-navigation-X.Y.Z.zip` and publishes a GitHub release. Installed sites see the update under Dashboard → Updates; the updater only installs the release asset whose SHA-256 matches GitHub's digest.

License: GPL-3.0-or-later.
