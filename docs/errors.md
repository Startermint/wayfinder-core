# Error Rendering

Wayfinder keeps production exception responses generic, but debug mode renders a native HTML exception page for browser requests.

When `app.debug` is true and the request does not expect JSON, uncaught exceptions render with:

- exception class and message
- source excerpt around the failing line
- request method, path, and request ID
- full stack trace

JSON requests keep the structured debug JSON response so API clients and tests do not receive HTML unexpectedly.

Never enable `app.debug` in production. The debug renderer intentionally exposes file paths, source snippets, and stack frames.
