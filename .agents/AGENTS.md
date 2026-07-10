### GitHub Release Creation
When the user asks to create a new GitHub release:
1. Do not attempt to use the `gh` CLI for release creation as it lacks authentication.
2. Always generate and provide a pre-filled GitHub Release URL so the user can create it with one click.
3. The URL format is: `https://github.com/<owner>/<repo>/releases/new?tag=<tag>&title=<title>&body=<body>`
4. URL-encode the values for `tag`, `title`, and `body`.
