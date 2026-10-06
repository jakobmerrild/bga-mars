# CLAUDE.md

## End of session: stage for BGA Studio

Changes are tested on the BGA Studio project `terraformingmarsjmerrild`, which needs differently named files and identifiers.
Always finish a session that changed game files by rebuilding and staging:

```bash
npm run build:ts && npm run build:scss
npm run stage -- terraformingmarsjmerrild
```

`stage` (`misc/other/stage.js`) recreates `dist/terraformingmarsjmerrild/` with `terraformingmars` renamed in file names and contents.
Never edit files under `dist/` directly; they are overwritten on every run. Upload uses the `mars-jmerrild` profile in `.vscode/sftp.json`.
