# Forms

How form validation, custom selects, dark-theme pickers, and the character
counter work in SevenCMS — and how to add new forms without re-implementing
any of it.

## Validation engine

`src/admin/forms.js` (loaded via the admin Vite bundle) auto-applies to
every `<form>` in the admin panel. You don't import it, you don't initialize
it per form — it scans the DOM on `DOMContentLoaded` and again whenever
`initForms(root)` is called.

What it does:

1. Adds `novalidate` to disable browser tooltips
2. On `submit`, checks every field's `validity` state
3. If anything's bad: shakes the form, scrolls to the first invalid field,
   focuses it, shows a toast with the error count, and prevents the submit
4. Renders inline `.form-error--inline` messages with an icon
5. Re-validates on blur (after the first error) and clears errors on input
   when the field becomes valid
6. Listens for `reset` and clears all errors

### Constraints

Standard HTML attributes are picked up automatically:

```html
<input required />
<input type="email" required />
<input type="url" />
<input type="number" min="1" max="100" step="1" />
<input pattern="[A-Z]{3}" />
<input minlength="3" maxlength="20" />
<textarea maxlength="200"></textarea>
```

### Custom messages

Override the default English message per-field with `data-msg-*`:

```html
<input type="email" required
       data-msg-required="Email is required."
       data-msg-email="That doesn't look like a real address." />

<input type="number" min="1" max="100"
       data-msg-min="Must be at least 1."
       data-msg-max="Must be at most 100." />

<input pattern="[a-z0-9-]+"
       data-msg-pattern="Lowercase letters, digits, and dashes only." />
```

Supported keys: `msgRequired`, `msgEmail`, `msgUrl`, `msgPattern`,
`msgMinlength`, `msgMaxlength`, `msgMin`, `msgMax`, `msgStep`, `msgMismatch`.

### Opting out

```html
<form data-no-validate>...</form>     <!-- whole form -->
<input data-no-validate />            <!-- single field -->
```

## Custom select

`src/admin/select.js` wraps every `<select>` (single, non-`size>1`) in a
`.seven-select` element. The native `<select>` stays in the DOM so form
submission and the validation engine work unchanged — it's just hidden
behind a styled trigger and menu.

Features:
- Keyboard nav: `↑/↓` move active item, `Enter` selects, `Escape` closes
- Theme-aware (uses `--bg-primary`, `--border-color`, `--primary` tokens)
- `disabled` and `<option disabled>` respected
- `option value=""` rendered as italic placeholder
- Plays well with `[required]` — invalid state propagates a red border to
  the wrapper via `:has()`

### Opting out

```html
<select data-native-select>      <!-- single element -->
<div data-native-select>
  <select>...</select>           <!-- any descendant -->
</div>
```

## Character counter

Any `<textarea>` or `<input maxlength="N">` gets a live counter injected
below it (`X / N`). It turns amber at 90% and red when full.

```html
<input type="text" maxlength="50" />
<textarea maxlength="200" rows="3"></textarea>
```

## Dark-theme pickers

`src/components/_forms.scss` does three things you'd otherwise have to wire
up per-input:

1. **Date / time / month / week pickers** — Chromium's calendar indicator
   is a black SVG by default. In dark mode we apply
   `filter: invert(1) brightness(1.2)` so it's visible.
2. **Color input** — restyled into a 44×32 pill swatch with a 1px border
   instead of the OS-native 25-pixel rectangle.
3. **File input** — the `::file-selector-button` becomes a styled
   secondary button that goes primary on hover.

All effects are CSS-only; no JS required.

## Character set support

Inputs with non-Latin keyboards (Cyrillic, Georgian, etc.) work fine —
`pattern` attributes that use Latin character classes will reject them, so
either avoid `pattern` for free-text fields or use Unicode classes:

```html
<!-- Bad: rejects "Алёша" -->
<input pattern="[A-Za-z ]+" />

<!-- Good: accepts any letter in any script -->
<input pattern="[\p{L} ]+" />
```

(Browser support: all modern browsers since 2020.)

## Adding a new form

The minimum:

```html
<form action="/en/admin/foo/save" method="POST">
  <!-- CSRF token: auto-injected by Master.html, or hardcode for paranoia -->
  <?= Csrf::field() ?>

  <div class="form-group">
    <label>Name</label>
    <input type="text" name="name" required
           data-msg-required="Name is required." />
  </div>

  <button type="submit" class="btn btn-primary">Save</button>
</form>
```

That's it. No JS to write. The validation engine, CSRF guard, custom
select, dark-theme pickers, and character counter all hook themselves up
on page load.

## See also

- [Security](#mdlink#security.md#) — CSRF, rate limiting, file upload
  sanitization
- UI Kit at `/admin/ui` — interactive demos of every form widget
