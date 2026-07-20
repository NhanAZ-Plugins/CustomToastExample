# CustomToastExample

CustomToastExample is a ready-to-run PocketMine-MP plugin that demonstrates every feature of the [CustomToast](https://github.com/NhanAZ-Libraries/CustomToast) virion.

The library repository is the canonical documentation. This repository stays focused on runnable examples that support those docs.

You do not need to install the virion separately. Release builds already contain both the PHP library and its resource pack.

It is safe for other plugins to inject and use the same CustomToast release. The first consumer registers the pack, all later consumers share it, and the pack stays active until the final consumer closes.

## What this plugin demonstrates

- Info, success, warning, and error toast styles.
- Rounded and square background options.
- Automatic colors and every current Minecraft Bedrock formatting-code color.
- Image icons, compact iconless layouts, and fixed-width three-byte Unicode glyph icons.
- Bold title-only, message-only, or title-and-message notifications.
- A title with a message containing one or more lines.
- UTF-8 text, including Vietnamese.
- Minecraft's optional built-in vanilla toast sound.
- Sending to one player or everyone online.
- A guided visual debug suite for layout, text parsing, colors, and stacked notifications.

## Installation

1. Download `CustomToastExample.phar` from the latest release.
2. Put it in your PocketMine-MP `plugins` folder.
3. Start the server.
4. Join the server and accept the resource pack.
5. Use the `toast` command from the server console.

If a player refuses the resource pack, the default configuration prevents that player from joining. This is intentional: the custom packet marker should never appear as a normal chat message.

## Copy-and-paste console commands

Replace `NhanAZ` with the name of an online player if needed. Console commands do not start with `/`.

```text
toast NhanAZ info round auto You have a new friend request.
toast NhanAZ success round green Success\nThe operation completed successfully.
toast NhanAZ warning square gold Warning\nThe server will restart in 5 minutes.
toast NhanAZ error round red Error\nSomething needs your attention.
toast NhanAZ info square dark_blue Dark blue uses Minecraft code 1.
toast NhanAZ success round material_emerald Emerald reward
toast NhanAZ warning square material_resin Resin warning
toast NhanAZ info round light_blue Party notification
toast NhanAZ info round blue 1 reward is ready.
toast NhanAZ plain round dark_gray This message has no icon.
toast NhanAZ plain square dark_aqua Title only\n
toast NhanAZ glyph: round dark_gray Glyph title\nMinecraft default glyph U+E100.
toast NhanAZ success round green Daily rewards\nFirst reward\nSecond reward\n\nCome back tomorrow.
toast NhanAZ info round dark_gray §cRed title\n§aGreen §bAqua §eYellow §rDefault message
toast all info round auto The event is now open!
toastdebug NhanAZ
```

Every command follows the same order:

```text
toast <player|all> <plain|glyph:character|type> <corner> <color> <message>
```

Use an online player name for one recipient, or use `all` for every online player.

## Visual debug suite

Run the complete visual test from the console:

```text
toastdebug NhanAZ
```

The suite sends 36 focused cases, 30 private-use glyphs in the icon slot, six glyph-in-text cases, and an eight-toast stack burst. The glyph coverage includes all 16 characters from `U+E000` through `U+E00F` and all 14 characters from `U+E100` through `U+E10D`. A Tip stays visible throughout the run, identifies the active group, and counts down both seconds and ticks. Chat announces each group once with its wait time, so it remains useful without being spammed. Every group waits until the previous group's final toast has completely disappeared before it starts:

1. Appearance: toast types, corners, colors, Unicode, and sound.
2. Number width A/B: equal-length title and message text beginning with a number or a letter.
3. Text and formatting: iconless layouts, long multi-paragraph messages, repeated and blank lines, ultra-long text, colored title/message text, `§k`, `§i`, and `§r`.
4. Unicode glyphs: `U+E000` through `U+E00F` and `U+E100` through `U+E10D` in the icon slot, title, message, title-only, and message-only layouts.
5. Stack stability: spacing and protection against textures swapping between queued items.

The full run takes about 138 seconds. Avoid starting it a second time before the first run finishes.

## Message-only and multi-line toasts

Choose `round` or `square`, followed by a color name. Everything after the color is treated as toast text:

```text
toast NhanAZ success round green Success\nThis toast has rounded corners.
toast NhanAZ warning square gold Warning\nThis toast has square corners.
toast NhanAZ info round light_blue Party\nYour party is ready.
toast NhanAZ success square material_emerald Reward\nYou found an emerald.
```

For a message-only toast, write the message normally:

```text
toast NhanAZ info round auto You have a new friend request.
```

Use `plain` instead of a type when the toast should not reserve or display an icon:

```text
toast NhanAZ plain round dark_gray This message has no icon.
toast NhanAZ plain square dark_aqua Title only\n
toast NhanAZ plain round blue Plain title\nPlain message.
```

The trailing `\n` in the second command intentionally creates a title with an empty message.

Prefix one three-byte Unicode code point with `glyph:` to replace the PNG icon while retaining icon spacing:

```text
toast NhanAZ glyph: round dark_gray Glyph U+E100\nFirst Minecraft default glyph.
toast NhanAZ glyph: square blue Glyph U+E10D\nFourteenth Minecraft default glyph.
```

The debug suite tests all of these defaults: ``. Custom glyphs should use a three-byte Basic Multilingual Plane code point supplied by the active Minecraft font or a resource-pack `font/glyph_<page>.png` spritesheet. ASCII characters, four-byte emoji, and multi-code-point emoji sequences are rejected to keep the JSON UI payload width fixed and client-safe.

For a title and a message, place the literal characters `\n` between them:

```text
toast NhanAZ warning round yellow Warning\nThe server will restart in 5 minutes.
```

Additional `\n` sequences stay inside the message, including repeated sequences that create an empty line:

```text
toast NhanAZ success round green Daily rewards\nFirst reward\nSecond reward\n\nCome back tomorrow.
```

Start the text with `\n` when you want a multi-line message without a title:

```text
toast NhanAZ info square aqua \nLine one\n\nLine three after an empty line.
```

The example plugin converts the first `\n` into the title/message boundary and preserves every later line break in the message. The pipe character `|` is not a separator; it remains visible in the message. This means a message such as `Rank A | Rank B` works exactly as written.

Titles are automatically bold. Minecraft formatting codes inside either field remain active, and the library resets formatting between the title and message:

```text
toast NhanAZ info round dark_gray §cRed title\n§aGreen §bAqua §eYellow §rDefault message
toast NhanAZ info square dark_gray Formatting\n§kObfuscated §rReset §iMaterial iron
```

## Configuration

The default `plugin_data/CustomToastExample/config.yml` is:

```yaml
force-resource-pack: true
play-sound: true
max-message-bytes: 256
```

- `force-resource-pack`: require players to accept the UI pack before joining.
- `play-sound`: play the built-in vanilla `random.toast` sound with every toast by default.
- `max-message-bytes`: limit the message without cutting a UTF-8 character in half.

Restart the server after changing these settings.

## Permissions

Both commands are operator-only by default.

| Permission | Command |
|---|---|
| `customtoastexample.command.toast` | `/toast` |
| `customtoastexample.command.toastdebug` | `/toastdebug` |

## Building from source

### Recommended release build

This project uses [Pockgin CLI](https://github.com/pockgin/cli) because the library must be injected into the plugin PHAR. `pockgin.libs.yml` maps two folders from the CustomToast repository:

```text
src/NhanAZ/CustomToast  -> PHP library
resources/CustomToast  -> UI and images
```

Install Pockgin CLI and build:

```bash
git clone https://github.com/pockgin/cli.git
cd cli
npm install
node bin/pockgin.js build /path/to/CustomToastExample
```

The finished file is `CustomToastExample/dist/CustomToastExample.phar`. The build verifier checks that both halves of the virion are present.

Do not change the Pockgin target namespace. The fixed `NhanAZ/CustomToast` path is what allows several plugins to share one runtime instead of registering duplicate resource packs.

### Local development build

When the two repositories are adjacent on disk, no published tag is needed:

```text
Downloads/
├── CustomToast/
└── CustomToastExample/
```

Run:

```bash
php -d phar.readonly=0 tools/build-local.php
```

The local builder uses a temporary staging directory, injects the sibling library, creates the PHAR, verifies it, and removes the staging directory. It never writes vendored library files into the example's source tree.

## Using the virion in your own plugin

The example's essential setup is deliberately small:

```php
use NhanAZ\CustomToast\CustomToast;
use NhanAZ\CustomToast\ToastColor;
use NhanAZ\CustomToast\ToastType;

protected function onEnable() : void{
    $this->customToast = CustomToast::create($this);
}

protected function onDisable() : void{
    $this->customToast?->close();
}
```

Then send a toast:

```php
$this->customToast->send(
    $player,
    ToastType::WARNING,
    "The server will restart in 5 minutes.",
    "Warning",
    null,
    null,
    ToastColor::GOLD
);
```

Use the named `showIcon` argument for an iconless toast without changing its semantic type or automatic color:

```php
$this->customToast->send(
    player: $player,
    type: ToastType::SUCCESS,
    message: "Daily reward claimed.",
    showIcon: false
);
```

Use the named `glyph` argument to replace the image icon:

```php
$this->customToast->send(
    player: $player,
    type: ToastType::INFO,
    message: "Minecraft default glyph U+E100.",
    title: "Glyph notification",
    glyph: ""
);
```

Read the [CustomToast documentation](https://github.com/NhanAZ-Libraries/CustomToast#readme) for the complete API, build mappings, lifecycle details, customization notes, and troubleshooting.

## Troubleshooting

### The command says the player is offline

Use the player's exact current name. The demo intentionally avoids partial-name matching.

### The toast appears in normal chat

The resource pack did not load, or another UI resource pack overrode it. Reconnect, accept the pack, and temporarily test without other HUD/chat packs.

### `\n` appears literally

Make sure it is one backslash followed by a lowercase `n`. Do not add spaces around it unless you want those spaces in the title or message.

### The build cannot resolve CustomToast

The public build resolves `NhanAZ-Libraries/CustomToast` at version `1.0.0`. If no matching tag exists yet, Pockgin falls back to the repository's default branch. For a completely local build, place both repositories next to each other and use `tools/build-local.php`.

## Version policy

The plugin, virion, and bundled resource pack are all version `1.0.0`. Do not change these versions unless the project owner explicitly requests it.

## Asset notice

The bundled presentation assets were created for CustomToast by NhanAZ. See the virion's `ASSETS.md` for reuse terms.

## License

The PHP source is licensed under LGPL-3.0-or-later.
