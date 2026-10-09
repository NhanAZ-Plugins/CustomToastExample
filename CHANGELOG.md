# Changelog

## 1.0.1 - 2026-10-10

- Target Axolotl-PM 5.49.1 and use CustomToast virion 1.0.1 with its matching resource pack.
- Build with DevTools 1.0.1 and run PHPStan at level max in CI.
- Verify the PHAR manifest, resources, licenses, and asset notice before distribution.
- Keep existing commands, permissions, and configuration keys.

Stop the server before replacing version 1.0.0. Keep the previous PHAR for rollback and reconnect clients after the resource pack update. A live client visual test remains necessary to assess the rendered toast appearance and sound.
