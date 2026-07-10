### Git Workflow
1. **Never run `git push` automatically.** Only commit changes locally (`git commit`).
2. Wait for the user to explicitly request a push (e.g., "push", "อัปขึ้น git") before executing `git push`.

### GitHub Release Creation
When the user asks to create a new GitHub release:
1. **First**, ensure that the `version` value in `manifest.json` strictly matches the new release version and the latest entry in `CHANGELOG.md`. Update these files and commit them if they do not match.
2. Do not attempt to use the `gh` CLI for release creation as it lacks authentication.
3. Always generate and provide a pre-filled GitHub Release URL so the user can create it with one click.
4. The URL format is: `https://github.com/<owner>/<repo>/releases/new?tag=<tag>&title=<title>&body=<body>`
5. URL-encode the values for `tag`, `title`, and `body`.
