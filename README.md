# CustomToastExample

CustomToastExample is a ready-to-run PocketMine-MP plugin that demonstrates every feature of the [CustomToast](https://github.com/NhanAZ-Libraries/CustomToast) virion.

The library repository is the canonical documentation. This repository stays focused on runnable examples that support those docs.

You do not need to install the virion separately. Release builds already contain both the PHP library and its resource pack.

It is safe for other plugins to inject and use the same CustomToast release. The first consumer registers the pack, all later consumers share it, and the pack stays active until the final consumer closes.

## What this plugin demonstrates

- Info, success, warning, and error toast styles.
- Rounded and square background options.
- Automatic colors and every current Minecraft Bedrock formatting-code color.
- Message-only notifications.
- A title and message on separate lines.
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
toast all info round auto The event is now open!
toastdebug NhanAZ
```

Every command follows the same order:

```text
toast <player|all> <type> <corner> <color> <message>
```

Use an online player name for one recipient, or use `all` for every online player.

## Visual debug suite

Run the complete visual test from the console:

```text
toastdebug NhanAZ
```

The suite sends 18 focused cases followed by an eight-toast stack burst. A Tip stays visible throughout the run and identifies the active group:

1. Appearance: toast types, corners, colors, Unicode, and sound.
2. Number width A/B: equal-length text beginning with a number or a letter.
3. Text edge cases: long spaced text, long unbroken text, and literal marker characters.
4. Stack stability: spacing and protection against textures swapping between queued items.

The full run takes about 38 seconds. Avoid starting it a second time before the first run finishes.

## Message-only and two-line toasts

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

For a title and a message, place the literal characters `\n` between them:

```text
toast NhanAZ warning round yellow Warning\nThe server will restart in 5 minutes.
```

The example plugin converts the first `\n` into the toast's two fields. The pipe character `|` is not a separator; it remains visible in the message. This means a message such as `Rank A | Rank B` works exactly as written.

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
